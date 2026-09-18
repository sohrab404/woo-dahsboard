<?php
/**
 * Custom front-end login/register surface for WordPress + WooCommerce.
 *
 * Architecture (v2):
 *  - ONE login surface, ONE URL: the WooCommerce account page (/my-account/).
 *    Guests get the redesigned auth card there; after login the same URL is
 *    taken over by Salesbin_Account_Panel (custom dashboard). No separate
 *    login page exists anymore.
 *  - The [salesbin_login] shortcode and the legacy /my-account/woodesh-login
 *    endpoint keep rendering the same card so old links never break.
 *
 * Auth methods:
 *  - password (default, works without any SMS provider)
 *  - SMS OTP via the Salesbin_SMS provider factory (default: Melipayamak):
 *    REST: POST salesbin/v1/login/sms/send + /login/sms/verify (unified flow)
 *    Any valid number receives a code — existence is never revealed. On
 *    verify, an unknown number is auto-registered when registration is open
 *    (login_sms_autoregister + users_can_register).
 *  - Codes are HMAC-hashed in transients; users are real WP users (meta `salesbin_phone`).
 *
 * Extension points:
 * - salesbin_login_methods       filter: register auth method tabs
 * - salesbin_login_countrycodes  filter: allowed country codes
 * - salesbin_login_redirect_url  filter: post-login redirect target
 * - salesbin_sms_provider        filter: swap the SMS gateway
 * - salesbin_login_otp_send / salesbin_login_otp_verify actions (masked number only)
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Salesbin_Login_Module
 */
class Salesbin_Login_Module {

	const SHORTCODE       = 'salesbin_login';
	const PAGE_SLUG       = 'woodesh-login';
	const ACTION_LOGIN    = 'salesbin_login';
	const ACTION_REGISTER = 'salesbin_register';
	const OTP_NONCE       = 'salesbin_login_otp';
	const TEST_TRANSIENT  = 'salesbin_sms_test';

	/**
	 * Instance.
	 *
	 * @var Salesbin_Login_Module|null
	 */
	private static $instance = null;

	/**
	 * @return Salesbin_Login_Module
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		add_shortcode( self::SHORTCODE, array( $this, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'init', array( $this, 'register_endpoint' ) );

		// Login surface takeover: guests opening /my-account/ get the
		// redesigned auth card instead of the default WooCommerce form.
		add_action( 'wp', array( $this, 'maybe_boot_account_login' ) );

		// Legacy endpoint route: /my-account/woodesh-login still renders the form.
		add_action( 'woocommerce_account_' . self::PAGE_SLUG . '_endpoint', array( $this, 'render_endpoint' ) );

		add_action( 'admin_post_nopriv_' . self::ACTION_LOGIN, array( $this, 'handle_login' ) );
		add_action( 'admin_post_' . self::ACTION_LOGIN, array( $this, 'redirect_if_logged_in' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION_REGISTER, array( $this, 'handle_register' ) );
		add_action( 'admin_post_' . self::ACTION_REGISTER, array( $this, 'redirect_if_logged_in' ) );

		if ( Salesbin_Settings::get( 'login_replace_default', 0 ) ) {
			add_filter( 'login_url', array( $this, 'filter_login_url' ), 20, 3 );
			add_filter( 'woocommerce_login_url', array( $this, 'wc_login_redirect' ), 20 );
		}

		add_filter( 'salesbin_login_methods', array( $this, 'default_methods' ) );
	}

	/**
	 * Admin-only hooks (registered regardless of the enabled flag).
	 *
	 * @return void
	 */
	public function admin_hooks() {
		add_action( 'admin_post_salesbin_rebuild_login_page', array( $this, 'handle_rebuild_login_page' ) );
		add_action( 'admin_post_salesbin_test_melipayamak', array( $this, 'handle_test_melipayamak' ) );

		// Self-healing: the endpoint rewrite must exist; flush once per site.
		add_action( 'admin_init', array( $this, 'maybe_flush_rewrites' ) );
	}

	/**
	 * Flush rewrite rules once so /my-account/woodesh-login resolves.
	 *
	 * @return void
	 */
	public function maybe_flush_rewrites() {
		if ( ! $this->is_enabled() ) {
			return;
		}
		if ( ! get_option( 'salesbin_login_flushed' ) ) {
			flush_rewrite_rules( false );
			update_option( 'salesbin_login_flushed', 1, false );
		}
	}

	/**
	 * Take over the account page for logged-out visitors.
	 *
	 * @return void
	 */
	public function maybe_boot_account_login() {
		if ( ! $this->is_enabled() || is_user_logged_in() ) {
			return;
		}
		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
			return;
		}
		add_filter( 'body_class', array( $this, 'body_class' ) );
		add_filter( 'woocommerce_locate_template', array( $this, 'override_account_login_template' ), 100, 3 );
	}

	/**
	 * Serve the bundled login template in place of myaccount/my-account.php.
	 *
	 * @param string $template      Resolved template path.
	 * @param string $template_name Template name.
	 * @param string $template_path Theme template path.
	 * @return string
	 */
	public function override_account_login_template( $template, $template_name, $template_path ) {
		unset( $template_path );
		if ( 'myaccount/my-account.php' === $template_name ) {
			return SALESBIN_PATH . 'templates/myaccount/login.php';
		}
		return $template;
	}

	/**
	 * Endpoint renderer — delegates to the shortcode markup.
	 *
	 * @return void
	 */
	public function render_endpoint() {
		echo $this->render_shortcode(); // phpcs:ignore WordPress.Security.EscapeOutput -- fully escaped in the view
	}

	/**
	 * Enabled flag from settings.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return (bool) Salesbin_Settings::get( 'login_enabled', 0 );
	}

	/**
	 * SMS OTP method enabled flag (via the provider factory).
	 *
	 * @return bool
	 */
	public function is_sms_enabled() {
		if ( ! $this->is_enabled() || ! Salesbin_Settings::get( 'login_sms_enabled', 0 ) ) {
			return false;
		}
		return Salesbin_SMS::provider()->is_configured();
	}

	/**
	 * Whether the site allows creating new accounts at all
	 * (WordPress "Anyone can register"). The OTP auto-register switch and
	 * the register tab both respect this.
	 *
	 * @return bool
	 */
	public function registration_open() {
		return (bool) get_option( 'users_can_register' );
	}

	/**
	 * OTP auto-register switch from settings.
	 *
	 * @return bool
	 */
	public function is_sms_auto_register() {
		return (bool) Salesbin_Settings::get( 'login_sms_autoregister', 1 );
	}

	/**
	 * THE login URL: always the WooCommerce account page. Guests see the
	 * auth card there; members see their custom panel on the same address.
	 *
	 * @return string
	 */
	public function main_url() {
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$base = wc_get_page_permalink( 'myaccount' );
			if ( $base ) {
				return user_trailingslashit( $base );
			}
		}
		return '';
	}

	/**
	 * Backward-compatible alias of main_url(). The dedicated custom-link
	 * setting was removed in v2 — everything lives on /my-account/.
	 *
	 * @return string
	 */
	public function page_url() {
		$url = $this->main_url();
		if ( $url ) {
			return $url;
		}
		// Legacy standalone page kept working if it was created before.
		$page_id = (int) Salesbin_Settings::get( 'login_page_id', 0 );
		if ( $page_id && get_post_status( $page_id ) === 'publish' ) {
			$legacy = get_permalink( $page_id );
			return $legacy ? $legacy : '';
		}
		return '';
	}

	/**
	 * The legacy WooCommerce endpoint URL (/my-account/woodesh-login).
	 * Kept for backward compatibility with links created by older versions.
	 *
	 * @return string
	 */
	public function default_endpoint_url() {
		$base = $this->main_url();
		if ( ! $base ) {
			return '';
		}
		return user_trailingslashit( trailingslashit( $base ) . self::PAGE_SLUG );
	}

	/**
	 * Rebuild the login surface from the settings button: ensures the
	 * WooCommerce account page (/my-account/) exists and is bound to
	 * WooCommerce, then flushes rewrite rules.
	 *
	 * @return void
	 */
	public function handle_rebuild_login_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'دسترسی غیرمجاز.', 'salesbin' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'salesbin_rebuild_login_page' );

		$this->ensure_account_page();

		// Revive the legacy shortcode page when one exists (old links keep working).
		$legacy = get_page_by_path( self::PAGE_SLUG );
		if ( $legacy && 'trash' === $legacy->post_status ) {
			wp_untrash_post( $legacy->ID );
			wp_publish_post( $legacy->ID );
		}
		if ( $legacy && 'trash' !== get_post_status( $legacy->ID ) ) {
			$settings                  = get_option( Salesbin_Settings::OPTION_KEY, array() );
			$settings                  = is_array( $settings ) ? $settings : array();
			$settings['login_page_id'] = (int) $legacy->ID;
			update_option( Salesbin_Settings::OPTION_KEY, $settings );
			Salesbin_Settings::forget();
		}

		flush_rewrite_rules( false );
		update_option( 'salesbin_login_flushed', 1, false );

		wp_safe_redirect( add_query_arg( array( 'page' => 'salesbin-settings', 'sb_rebuilt' => 'login' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Make sure the WooCommerce account page exists and is bound; it is the
	 * single login URL of this module.
	 *
	 * @return string created|bound|ok
	 */
	private function ensure_account_page() {
		$page_id = (int) get_option( 'woocommerce_myaccount_page_id' );
		$page    = $page_id ? get_post( $page_id ) : null;
		if ( $page && 'page' === $page->post_type && 'publish' === $page->post_status ) {
			return 'ok';
		}

		$existing = get_page_by_path( 'my-account' );
		if ( $existing && 'page' === $existing->post_type && 'trash' !== $existing->post_status ) {
			update_option( 'woocommerce_myaccount_page_id', (int) $existing->ID );
			return 'bound';
		}
		if ( $existing && 'trash' === $existing->post_status ) {
			wp_untrash_post( $existing->ID );
			wp_publish_post( $existing->ID );
			update_option( 'woocommerce_myaccount_page_id', (int) $existing->ID );
			return 'bound';
		}

		$created = wp_insert_post(
			array(
				'post_title'     => __( 'حساب کاربری من', 'salesbin' ),
				'post_name'      => 'my-account',
				'post_content'   => '[woocommerce_my_account]',
				'post_status'    => 'publish',
				'post_type'      => 'page',
				'comment_status' => 'closed',
			)
		);
		if ( $created && ! is_wp_error( $created ) ) {
			update_option( 'woocommerce_myaccount_page_id', (int) $created );
			return 'created';
		}
		return 'missing';
	}

	/**
	 * Connection test for the active SMS provider from the settings screen.
	 * Stores the result in a transient and redirects back.
	 *
	 * @return void
	 */
	public function handle_test_melipayamak() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'دسترسی غیرمجاز.', 'salesbin' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'salesbin_test_melipayamak' );

		$result = Salesbin_SMS::provider()->test_connection();
		set_transient( self::TEST_TRANSIENT, $result, 120 );

		wp_safe_redirect( add_query_arg( array( 'page' => 'salesbin-settings' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Front assets + server-side palette (theme + both modes) so the design
	 * system works even before/without JS.
	 *
	 * @return void
	 */
	public function enqueue() {
		if ( ! $this->is_login_context() ) {
			return;
		}
		add_filter( 'body_class', array( $this, 'body_class' ) );
		wp_enqueue_style( 'salesbin-fonts', SALESBIN_URL . 'assets/css/fonts.css', array(), SALESBIN_VERSION );
		wp_enqueue_style( 'salesbin-login', SALESBIN_URL . 'assets/css/login.css', array( 'salesbin-fonts' ), SALESBIN_VERSION );

		$accent = sanitize_key( (string) Salesbin_Settings::get( 'theme', 'woodesh' ) );
		wp_add_inline_style( 'salesbin-login', $this->palette_css( $accent ) );
		wp_add_inline_style(
			'salesbin-login',
			'.salesbin-login-page{--sb-font:' . Salesbin_Settings::font_stack( Salesbin_Settings::get( 'account_font', 'yekanbakh' ) ) . ';}'
		);

		wp_enqueue_script( 'salesbin-fx', SALESBIN_URL . 'assets/js/effects.js', array(), SALESBIN_VERSION, true );
		wp_enqueue_script( 'salesbin-login', SALESBIN_URL . 'assets/js/login.js', array( 'salesbin-fx' ), SALESBIN_VERSION, true );
		wp_localize_script(
			'salesbin-login',
			'salesbinLoginCfg',
			array(
				'restUrl'        => esc_url_raw( rest_url( Salesbin_Rest_API::NAMESPACE . '/' ) ),
				'restNonce'      => wp_create_nonce( 'wp_rest' ),
				'otpNonce'       => wp_create_nonce( self::OTP_NONCE ),
				'smsEnabled'     => $this->is_sms_enabled(),
				'otpSize'        => Salesbin_Login_Otp::CODE_SIZE,
				'resendAfter'    => Salesbin_Login_Otp::cooldown(),
				'expiresIn'      => Salesbin_Login_Otp::expiry(),
				'defaultCountry' => Salesbin_Phone::default_country_code(),
				'defaultMode'    => Salesbin_Settings::get( 'mode', 'dark' ),
			)
		);
	}

	/**
	 * Palette CSS for the login surface: the selected accent + full
	 * dark/light neutrals, scoped to the page so mode toggling always works.
	 *
	 * @param string $accent Accent slug.
	 * @return string CSS.
	 */
	private function palette_css( $accent ) {
		$accents = array(
			'woodesh'  => array( '#8b5cf6', '139, 92, 246', '#a78bfa' ),
			'ocean'    => array( '#0ea5e9', '14, 165, 233', '#38bdf8' ),
			'emerald'  => array( '#10b981', '16, 185, 129', '#34d399' ),
			'rose'     => array( '#f43f5e', '244, 63, 94', '#fb7185' ),
			'sunset'   => array( '#f59e0b', '245, 158, 11', '#fbbf24' ),
			'graphite' => array( '#9ca3af', '156, 163, 175', '#d1d5db' ),
		);
		$a = isset( $accents[ $accent ] ) ? $accents[ $accent ] : $accents['woodesh'];

		$css  = '.salesbin-login-page{--sb-accent:' . $a[0] . ';--sb-accent-rgb:' . $a[1] . ';--sb-accent-2:' . $a[2] . ';}';
		$css .= '.salesbin-login-page[data-sb-mode="dark"]{'
			. '--sb-bg:#07080c;--sb-bg-2:#0e1018;--sb-card:rgba(20,23,34,.78);--sb-card-2:#1a1e2b;'
			. '--sb-border:rgba(255,255,255,.09);--sb-text:#e8eaf2;--sb-muted:#8b91a7;'
			. '--sb-track:rgba(255,255,255,.07);--sb-sheen:rgba(255,255,255,.04);'
			. '--sb-success:#34d399;--sb-success-rgb:52,211,153;--sb-danger:#f87171;--sb-danger-rgb:248,113,113;'
			. '--sb-shadow:0 24px 80px rgba(0,0,0,.45);--sb-glow:0 8px 32px rgba(var(--sb-accent-rgb),.4);'
			. 'color-scheme:dark;}';
		$css .= '.salesbin-login-page[data-sb-mode="light"]{'
			. '--sb-bg:#eef0f7;--sb-bg-2:#ffffff;--sb-card:rgba(255,255,255,.82);--sb-card-2:#eef0f7;'
			. '--sb-border:rgba(15,18,35,.12);--sb-text:#171a26;--sb-muted:#5b6172;'
			. '--sb-track:rgba(15,18,35,.07);--sb-sheen:rgba(15,18,35,.03);'
			. '--sb-success:#059669;--sb-success-rgb:5,150,105;--sb-danger:#dc2626;--sb-danger-rgb:220,38,38;'
			. '--sb-shadow:0 24px 70px rgba(23,26,38,.16);--sb-glow:0 8px 28px rgba(var(--sb-accent-rgb),.28);'
			. 'color-scheme:light;}';
		return $css;
	}

	/**
	 * Marker class for the login body surface (added once).
	 *
	 * @param array $classes Classes.
	 * @return array
	 */
	public function body_class( $classes ) {
		if ( ! in_array( 'sb-login-surface', (array) $classes, true ) ) {
			$classes[] = 'sb-login-surface';
		}
		return $classes;
	}

	/**
	 * Whether the current request renders the login surface.
	 *
	 * @return bool
	 */
	private function is_login_context() {
		global $post;
		if ( $post && has_shortcode( (string) $post->post_content, self::SHORTCODE ) ) {
			return true;
		}

		// Account page while logged out (the main login surface).
		if ( ! is_user_logged_in() && function_exists( 'is_account_page' ) && is_account_page() ) {
			return true;
		}

		// Legacy endpoint route: /my-account/woodesh-login.
		$slug = get_query_var( self::PAGE_SLUG );
		if ( is_string( $slug ) && '' !== $slug ) {
			return true;
		}
		return false;
	}

	/**
	 * REST endpoints for the SMS OTP flow (logged-out capable).
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		if ( ! $this->is_sms_enabled() ) {
			return;
		}

		register_rest_route(
			Salesbin_Rest_API::NAMESPACE,
			'/login/sms/send',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_sms_send' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			Salesbin_Rest_API::NAMESPACE,
			'/login/sms/verify',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_sms_verify' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Shared guard for the public SMS endpoints: nonce + JSON body.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	private function sms_guard( WP_REST_Request $request ) {
		$nonce = $request->get_param( 'otp_nonce' );
		if ( ! $nonce || ! wp_verify_nonce( (string) $nonce, self::OTP_NONCE ) ) {
			return new WP_Error(
				'salesbin_otp_nonce',
				__( 'نشست فرم منقضی شده است؛ صفحه را رفرش کنید.', 'salesbin' ),
				array( 'status' => 403 )
			);
		}
		if ( is_user_logged_in() ) {
			return new WP_Error(
				'salesbin_otp_logged_in',
				__( 'شما هم‌اکنون وارد شده‌اید.', 'salesbin' ),
				array( 'status' => 400 )
			);
		}
		return true;
	}

	/**
	 * Normalize phone params or build a REST error.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array{0:string,1:string}|WP_Error
	 */
	private function sms_phone( WP_REST_Request $request ) {
		try {
			return Salesbin_Phone::normalize(
				(string) $request->get_param( 'countrycode' ),
				(string) $request->get_param( 'mobile' )
			);
		} catch ( InvalidArgumentException $e ) {
			return new WP_Error( 'salesbin_otp_phone', $e->getMessage(), array( 'status' => 400 ) );
		}
	}

	/**
	 * Find a WP user by phone (meta salesbin_phone, fallback billing_phone).
	 *
	 * @param string $e164 Full international number.
	 * @return WP_User|null
	 */
	public static function find_user_by_phone( $e164 ) {
		$meta_keys = apply_filters( 'salesbin_login_phone_meta_keys', array( 'salesbin_phone', 'billing_phone' ) );
		foreach ( $meta_keys as $key ) {
			$query = new WP_User_Query(
				array(
					'meta_key'   => $key,
					'meta_value' => $e164,
					'number'     => 1,
					'fields'     => 'all',
				)
			);
			$users = $query->get_results();
			if ( ! empty( $users ) ) {
				return $users[0];
			}
		}
		return null;
	}

	/**
	 * Create a WP user from a verified phone (username = national number).
	 *
	 * @param string $cc       Country code.
	 * @param string $national National number.
	 * @return WP_User|WP_Error
	 */
	private function create_phone_user( $cc, $national ) {
		$username = $national;
		$suffix   = 2;
		while ( username_exists( $username ) ) {
			$username = $national . $suffix;
			$suffix++;
		}

		$email = $national . '@phone.local';
		while ( email_exists( $email ) ) {
			$email = $username . '@phone.local';
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => $username,
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 20, true, true ),
				'display_name' => $national,
				'role'         => class_exists( 'WooCommerce' ) ? 'customer' : get_option( 'default_role', 'subscriber' ),
			)
		);
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		update_user_meta( $user_id, 'salesbin_phone', Salesbin_Phone::e164( $cc, $national ) );
		if ( class_exists( 'WooCommerce' ) ) {
			update_user_meta( $user_id, 'billing_phone', Salesbin_Phone::e164( $cc, $national ) );
		}
		wp_new_user_notification( $user_id, null, 'admin' );

		return get_user_by( 'id', $user_id );
	}

	/**
	 * Send an OTP to any valid number. User existence is deliberately NOT
	 * checked here — no enumeration. Unknown numbers are handled at verify
	 * time (auto-register when registration is open).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_sms_send( WP_REST_Request $request ) {
		$guard = $this->sms_guard( $request );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$phone = $this->sms_phone( $request );
		if ( is_wp_error( $phone ) ) {
			return $phone;
		}
		list( $cc, $national ) = $phone;

		if ( ! $this->registration_open() && ! self::find_user_by_phone( Salesbin_Phone::e164( $cc, $national ) ) ) {
			return new WP_Error(
				'salesbin_otp_registration_closed',
				__( 'ثبت‌نام در حال حاضر بسته است؛ اگر قبلاً ثبت‌نام کرده‌اید با پشتیبانی تماس بگیرید.', 'salesbin' ),
				array( 'status' => 403 )
			);
		}

		$result = Salesbin_Login_Otp::create( $cc, $national, Salesbin_Helpers::client_ip() );
		if ( empty( $result['ok'] ) ) {
			return new WP_Error(
				'salesbin_otp_send',
				isset( $result['message'] ) ? (string) $result['message'] : __( 'ارسال کد تایید با خطا مواجه شد.', 'salesbin' ),
				array( 'status' => isset( $result['status'] ) ? (int) $result['status'] : 429 )
			);
		}

		$out = array(
			'resend_after' => (int) $result['resend_after'],
			'expires_in'   => (int) $result['expires_in'],
			'message'      => $result['message'],
		);
		if ( isset( $result['debug_code'] ) ) {
			$out['debug_code'] = $result['debug_code'];
		}

		return new WP_REST_Response( array( 'success' => true, 'data' => $out ), 200 );
	}

	/**
	 * Verify an OTP; logs in existing users or auto-registers new numbers
	 * when registration is open (Digikala-style unified flow).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_sms_verify( WP_REST_Request $request ) {
		$guard = $this->sms_guard( $request );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$phone = $this->sms_phone( $request );
		if ( is_wp_error( $phone ) ) {
			return $phone;
		}
		list( $cc, $national ) = $phone;

		$check = Salesbin_Login_Otp::verify( $cc, $national, (string) $request->get_param( 'otp' ), true );
		if ( true !== $check ) {
			return new WP_Error( 'salesbin_otp_invalid', $check, array( 'status' => 400 ) );
		}

		$e164 = Salesbin_Phone::e164( $cc, $national );
		$user = self::find_user_by_phone( $e164 );

		if ( ! $user ) {
			if ( ! $this->is_sms_auto_register() ) {
				return new WP_Error(
					'salesbin_otp_registration_closed',
					__( 'ثبت‌نام خودکار غیرفعال است؛ لطفاً از تب ثبت‌نام استفاده کنید یا با پشتیبانی تماس بگیرید.', 'salesbin' ),
					array( 'status' => 403 )
				);
			}
			if ( ! $this->registration_open() ) {
				return new WP_Error(
					'salesbin_otp_registration_closed',
					__( 'امکان ورود با این شماره وجود ندارد؛ ثبت‌نام بسته است.', 'salesbin' ),
					array( 'status' => 403 )
				);
			}
			$user = $this->create_phone_user( $cc, $national );
			if ( is_wp_error( $user ) ) {
				return new WP_Error( 'salesbin_otp_register', $user->get_error_message(), array( 'status' => 500 ) );
			}
			$welcome = true;
		} else {
			$welcome = false;
		}

		$remember = '1' === (string) $request->get_param( 'remember' );
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, $remember, is_ssl() );
		do_action( 'wp_login', $user->user_login, $user );

		$redirect = $this->resolve_redirect( $request->get_param( 'redirect_to' ) );

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'message'  => $welcome ? __( 'ثبت‌نام انجام شد؛ خوش آمدید!', 'salesbin' ) : __( 'ورود موفق؛ در حال انتقال...', 'salesbin' ),
					'redirect' => $redirect,
				),
			),
			200
		);
	}

	/**
	 * Resolve a redirect target: validated explicit value, else the account
	 * page (which becomes the custom panel after login), else home.
	 *
	 * @param mixed $raw Raw redirect value.
	 * @return string
	 */
	private function resolve_redirect( $raw ) {
		$target = '';
		if ( ! empty( $raw ) ) {
			$target = wp_validate_redirect( wp_unslash( (string) $raw ), '' );
		}
		if ( ! $target ) {
			$target = $this->main_url();
		}
		if ( ! $target ) {
			$target = home_url( '/' );
		}

		/**
		 * Post-login redirect target.
		 *
		 * @param string $target Redirect URL.
		 */
		return (string) apply_filters( 'salesbin_login_redirect_url', $target );
	}

	/**
	 * Default auth method tabs.
	 *
	 * @param array $methods Methods.
	 * @return array
	 */
	public function default_methods( $methods ) {
		$methods = is_array( $methods ) ? $methods : array();
		$methods['password'] = array(
			'label'  => __( 'ورود با رمز عبور', 'salesbin' ),
			'active' => true,
		);
		$methods['sms'] = array(
			'label'  => __( 'ورود با موبایل', 'salesbin' ),
			'active' => $this->is_sms_enabled(),
		);
		return $methods;
	}

	/**
	 * Shortcode renderer.
	 *
	 * @return string
	 */
	public function render_shortcode() {
		if ( is_user_logged_in() ) {
			return $this->render_logged_in();
		}

		$error  = '';
		$notice = '';
		if ( isset( $_GET['salesbin_error'] ) ) {
			$error = $this->error_message( sanitize_key( wp_unslash( $_GET['salesbin_error'] ) ) );
		}
		if ( isset( $_GET['salesbin_notice'] ) ) {
			$notice = $this->notice_message( sanitize_key( wp_unslash( $_GET['salesbin_notice'] ) ) );
		}

		$methods       = apply_filters( 'salesbin_login_methods', array() );
		$show_register = (bool) Salesbin_Settings::get( 'login_show_register', 1 );
		$action_url    = admin_url( 'admin-post.php' );
		$redirect_to   = '';
		if ( isset( $_GET['redirect_to'] ) ) {
			$redirect_to = wp_validate_redirect( wp_unslash( $_GET['redirect_to'] ), '' );
		}

		ob_start();
		include SALESBIN_PATH . 'templates/login/form.php';
		return ob_get_clean();
	}

	/**
	 * Logged-in card.
	 *
	 * @return string
	 */
	private function render_logged_in() {
		$user = wp_get_current_user();
		$name = $user->exists() ? $user->display_name : '';
		$acc  = $this->main_url() ? $this->main_url() : admin_url( 'profile.php' );
		$out  = '<div class="salesbin-login-page" dir="rtl" data-sb-mode="dark"><div class="sb-login-wrap"><div class="sb-login-card sb-magic">';
		$out .= '<h2 class="sb-login-title">' . esc_html( sprintf( __( 'سلام %s', 'salesbin' ), $name ) ) . '</h2>';
		$out .= '<p class="sb-login-lead">' . esc_html__( 'شما وارد حساب کاربری خود شده‌اید.', 'salesbin' ) . '</p>';
		$out .= '<p><a class="sb-btn sb-btn--primary" href="' . esc_url( $acc ) . '">' . esc_html__( 'حساب کاربری من', 'salesbin' ) . '</a> ';
		$out .= '<a class="sb-link" href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">' . esc_html__( 'خروج', 'salesbin' ) . '</a></p>';
		$out .= '</div></div></div>';
		return $out;
	}

	/**
	 * Process password login.
	 *
	 * @return void
	 */
	public function handle_login() {
		$back = $this->fallback_url();

		if ( ! isset( $_POST['salesbin_login_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['salesbin_login_nonce'] ) ), 'salesbin_login_form' ) ) {
			wp_safe_redirect( add_query_arg( 'salesbin_error', 'nonce', $back ) );
			exit;
		}

		$log = isset( $_POST['log'] ) ? sanitize_user( wp_unslash( $_POST['log'] ) ) : '';
		$pw  = isset( $_POST['pwd'] ) ? (string) wp_unslash( $_POST['pwd'] ) : '';

		if ( ! $log || ! $pw ) {
			wp_safe_redirect( add_query_arg( 'salesbin_error', 'empty', $back ) );
			exit;
		}

		$creds = array(
			'user_login'    => $log,
			'user_password' => $pw,
			'remember'      => ! empty( $_POST['rememberme'] ),
		);
		$user  = wp_signon( $creds, is_ssl() );

		if ( is_wp_error( $user ) ) {
			wp_safe_redirect( add_query_arg( 'salesbin_error', 'invalid', $back ) );
			exit;
		}

		$redirect = $this->after_login_redirect( $user );
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Process registration.
	 *
	 * @return void
	 */
	public function handle_register() {
		$back = $this->fallback_url();

		if ( ! isset( $_POST['salesbin_login_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['salesbin_login_nonce'] ) ), 'salesbin_login_form' ) ) {
			wp_safe_redirect( add_query_arg( 'salesbin_error', 'nonce', $back ) );
			exit;
		}

		if ( ! $this->registration_open() ) {
			wp_safe_redirect( add_query_arg( 'salesbin_error', 'registration_closed', $back ) );
			exit;
		}

		$username = isset( $_POST['user_login'] ) ? sanitize_user( wp_unslash( $_POST['user_login'] ) ) : '';
		$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$password = isset( $_POST['user_pass'] ) ? (string) wp_unslash( $_POST['user_pass'] ) : '';

		if ( ! $username || ! $email || ! $password ) {
			wp_safe_redirect( add_query_arg( 'salesbin_error', 'empty', $back ) );
			exit;
		}
		if ( ! is_email( $email ) ) {
			wp_safe_redirect( add_query_arg( 'salesbin_error', 'email', $back ) );
			exit;
		}
		if ( username_exists( $username ) || email_exists( $email ) ) {
			wp_safe_redirect( add_query_arg( 'salesbin_error', 'exists', $back ) );
			exit;
		}

		$user_id = wp_create_user( $username, $password, $email );
		if ( is_wp_error( $user_id ) ) {
			wp_safe_redirect( add_query_arg( 'salesbin_error', 'invalid', $back ) );
			exit;
		}

		if ( class_exists( 'WooCommerce' ) ) {
			$user = new WP_User( $user_id );
			$user->set_role( 'customer' );
		}
		wp_new_user_notification( $user_id, null, 'admin' );

		$user = wp_signon(
			array(
				'user_login'    => $username,
				'user_password' => $password,
			),
			is_ssl()
		);

		$redirect = is_wp_error( $user ) ? add_query_arg( 'salesbin_notice', 'registered', $back ) : $this->after_login_redirect( $user );
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Logged-in users hitting the form actions get bounced home.
	 *
	 * @return void
	 */
	public function redirect_if_logged_in() {
		wp_safe_redirect( home_url( '/' ) );
		exit;
	}

	/**
	 * Redirect target after successful password auth: validated explicit
	 * redirect_to POST field, else the account page (custom panel).
	 *
	 * @param WP_User|null $user User.
	 * @return string
	 */
	private function after_login_redirect( $user = null ) {
		unset( $user );
		$target = '';
		if ( ! empty( $_POST['redirect_to'] ) ) {
			$target = wp_validate_redirect( wp_unslash( $_POST['redirect_to'] ), '' );
		}
		return $this->resolve_redirect( $target );
	}

	/**
	 * Fallback redirect target (the login surface when available).
	 *
	 * @return string
	 */
	private function fallback_url() {
		$url = $this->page_url();
		return $url ? $url : home_url( '/' );
	}

	/**
	 * Replace wp-login.php URL with our page.
	 *
	 * @param string $login_url    URL.
	 * @param string $redirect     Redirect arg.
	 * @param bool   $force_reauth Reauth.
	 * @return string
	 */
	public function filter_login_url( $login_url, $redirect, $force_reauth ) {
		$page = $this->page_url();
		if ( ! $page ) {
			return $login_url;
		}
		if ( ! empty( $redirect ) ) {
			$page = add_query_arg( 'redirect_to', rawurlencode( $redirect ), $page );
		}
		if ( $force_reauth ) {
			$page = add_query_arg( 'reauth', '1', $page );
		}
		return $page;
	}

	/**
	 * Send WC login links (my-account, checkout) to our page.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public function wc_login_redirect( $url ) {
		$page = $this->page_url();
		return $page ? $page : $url;
	}

	/**
	 * Register the legacy login slug as a WooCommerce account endpoint so old
	 * /my-account/woodesh-login links keep resolving.
	 *
	 * @return void
	 */
	public function register_endpoint() {
		add_rewrite_endpoint( self::PAGE_SLUG, EP_PAGES );
		add_filter( 'woocommerce_get_query_vars', array( $this, 'register_query_var' ) );
	}

	/**
	 * Query var for the endpoint.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function register_query_var( $vars ) {
		$vars[ self::PAGE_SLUG ] = self::PAGE_SLUG;
		return $vars;
	}

	/**
	 * Error copy.
	 *
	 * @param string $code Error code.
	 * @return string
	 */
	private function error_message( $code ) {
		$map = array(
			'nonce'               => __( 'نشست شما منقضی شده است؛ دوباره تلاش کنید.', 'salesbin' ),
			'empty'               => __( 'همه فیلدهای الزامی را تکمیل کنید.', 'salesbin' ),
			'invalid'             => __( 'نام کاربری یا رمز عبور اشتباه است.', 'salesbin' ),
			'exists'              => __( 'این نام کاربری یا ایمیل قبلاً ثبت شده است.', 'salesbin' ),
			'email'               => __( 'ایمیل واردشده معتبر نیست.', 'salesbin' ),
			'registration_closed' => __( 'ثبت‌نام در این سایت غیرفعال است.', 'salesbin' ),
		);
		return isset( $map[ $code ] ) ? $map[ $code ] : $map['invalid'];
	}

	/**
	 * Notice copy.
	 *
	 * @param string $code Notice code.
	 * @return string
	 */
	private function notice_message( $code ) {
		$map = array(
			'registered' => __( 'ثبت‌نام انجام شد. اکنون می‌توانید وارد شوید.', 'salesbin' ),
		);
		return isset( $map[ $code ] ) ? $map[ $code ] : '';
	}
}
