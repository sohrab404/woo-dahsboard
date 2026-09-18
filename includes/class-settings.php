<?php
/**
 * Settings API.
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Salesbin_Settings
 */
class Salesbin_Settings {

	const OPTION_KEY = 'salesbin_settings';

	/**
	 * Instance.
	 *
	 * @var Salesbin_Settings|null
	 */
	private static $instance = null;

	/**
	 * Per-request memo of the merged settings array.
	 *
	 * @var array|null
	 */
	private static $memo = null;

	/**
	 * @return Salesbin_Settings
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Default settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'low_stock_threshold'            => 5,
			'new_order_notification_window'  => 60,
			'notify_new_order'               => 1,
			'notify_low_stock'               => 1,
			'notify_pending_review'          => 1,
			'notify_needs_attention'         => 1,
			'notify_high_value'              => 1,
			'notify_refund'                  => 1,
			'high_value_order_threshold'     => 10000000,
			'daily_sales_goal'               => 50000000,
			'orders_per_page'                => 10,
			'default_date_range'             => '30d',
			'default_chart_type'             => 'line',
			'cache_duration'                 => 300,
			'admin_bar_enabled'              => 1,
			'admin_bar_show_low_stock'       => 1,
			'auto_refresh_interval'          => 0,
			'theme'                          => 'woodesh',
			'mode'                           => 'dark',
			'notifications_retention_days'   => 90,
		'login_enabled'                  => 1,
		'login_replace_default'          => 1,
		'login_show_register'            => 1,
		'login_page_id'                  => 0,
		'login_sms_enabled'              => 0,
		'login_sms_debug'                => 0,
		'login_sms_autoregister'         => 1,
		'login_otp_expiry'               => 180,
		'login_otp_cooldown'             => 60,
			'melipayamak_username'           => '',
			'melipayamak_password'           => '',
			'melipayamak_from'               => '',
			'melipayamak_khadamati'          => 0,
			'melipayamak_body_id'            => '',
			'melipayamak_template'           => 'کد ورود شما: {OTP}',
			'account_panel_enabled'          => 0,
			'account_panel_mode'             => 'dark',
			'account_panel_accent'           => 'woodesh',
			'account_panel_welcome'          => 1,
			'account_panel_stats'            => 1,
			'account_panel_breakdown'        => 1,
			'account_panel_recent'           => 1,
			'account_panel_quick'            => 1,
			'account_panel_messages'         => 1,
			'account_menu'                   => array(),
			'account_custom_links'           => '',
			'account_tickets_enabled'        => 0,
			'admin_font'                     => 'yekanbakh',
			'account_font'                   => 'yekanbakh',
			'admin_chrome_theme'             => 1,
			'debug_logging'                  => 0,
			'delete_data_on_uninstall'       => 0,
		);
	}

	/**
	 * Whitelisted theme slugs.
	 *
	 * @return string[]
	 */
	public static function themes() {
		return array( 'woodesh', 'ocean', 'emerald', 'rose', 'sunset', 'graphite' );
	}

	/**
	 * Bundled Persian fonts (locally served). Slug => label + CSS family.
	 *
	 * @return array<string,array{label:string,family:string}>
	 */
	public static function fonts() {
		return array(
			'yekanbakh' => array(
				'label'  => __( 'یکان بخ', 'salesbin' ),
				'family' => "'SB Yekan Bakh'",
			),
			'iranyekan' => array(
				'label'  => __( 'ایران یکان', 'salesbin' ),
				'family' => "'SB Iran Yekan'",
			),
			'iransans'  => array(
				'label'  => __( 'ایران سنس', 'salesbin' ),
				'family' => "'SB IranSans'",
			),
			'peyda'     => array(
				'label'  => __( 'پیدا', 'salesbin' ),
				'family' => "'SB Peyda'",
			),
		);
	}

	/**
	 * Font-family stack for a bundled font slug.
	 *
	 * @param string $slug Font slug.
	 * @return string
	 */
	public static function font_stack( $slug ) {
		$fonts = self::fonts();
		$family = isset( $fonts[ $slug ] ) ? $fonts[ $slug ]['family'] : $fonts['yekanbakh']['family'];
		return $family . ', Tahoma, "Segoe UI", system-ui, sans-serif';
	}

	/**
	 * Server-side theme palette (CSS custom properties) for a theme + mode.
	 * Used where JS theming can't be relied on (settings page, admin chrome).
	 *
	 * @param string $theme Theme slug.
	 * @param string $mode  dark|light.
	 * @return string
	 */
	public static function palette_css( $theme, $mode ) {
		$accents = array(
			'woodesh'  => array( '#8b5cf6', '139, 92, 246', '#a78bfa' ),
			'ocean'    => array( '#0ea5e9', '14, 165, 233', '#38bdf8' ),
			'emerald'  => array( '#10b981', '16, 185, 129', '#34d399' ),
			'rose'     => array( '#f43f5e', '244, 63, 94', '#fb7185' ),
			'sunset'   => array( '#f59e0b', '245, 158, 11', '#fbbf24' ),
			'graphite' => array( '#9ca3af', '156, 163, 175', '#d1d5db' ),
		);
		$a = isset( $accents[ $theme ] ) ? $accents[ $theme ] : $accents['woodesh'];

		if ( 'light' === $mode ) {
			$css = '--sb-bg:#f6f7fb;--sb-bg-2:#ffffff;--sb-card:#ffffff;--sb-card-2:#eef0f7;'
				. '--sb-border:rgba(15,18,35,.10);--sb-text:#171a26;--sb-muted:#5b6172;'
				. '--sb-track:rgba(15,18,35,.08);--sb-sheen:rgba(15,18,35,.03);'
				. '--sb-success:#059669;--sb-success-rgb:5,150,105;--sb-warn:#b45309;--sb-warn-rgb:180,83,9;'
				. '--sb-danger:#dc2626;--sb-danger-rgb:220,38,38;--sb-info:#2563eb;--sb-info-rgb:37,99,235;'
				. '--sb-shadow:0 10px 40px rgba(23,26,38,.10);--sb-glow:0 8px 24px rgba(' . $a[1] . ',.22);'
				. 'color-scheme:light;';
		} else {
			$css = '--sb-bg:#07080c;--sb-bg-2:#0e1018;--sb-card:#141722;--sb-card-2:#1a1e2b;'
				. '--sb-border:rgba(255,255,255,.07);--sb-text:#e8eaf2;--sb-muted:#8b91a7;'
				. '--sb-track:rgba(255,255,255,.06);--sb-sheen:rgba(255,255,255,.03);'
				. '--sb-success:#34d399;--sb-success-rgb:52,211,153;--sb-warn:#fbbf24;--sb-warn-rgb:251,191,36;'
				. '--sb-danger:#f87171;--sb-danger-rgb:248,113,113;--sb-info:#60a5fa;--sb-info-rgb:96,165,250;'
				. '--sb-shadow:0 10px 40px rgba(0,0,0,.35);--sb-glow:0 8px 24px rgba(' . $a[1] . ',.35);'
				. 'color-scheme:dark;';
		}
		return $css . '--sb-accent:' . $a[0] . ';--sb-accent-rgb:' . $a[1] . ';--sb-accent-2:' . $a[2] . ';';
	}

	/**
	 * Seed defaults if missing.
	 *
	 * @return void
	 */
	public static function maybe_seed_defaults() {
		$existing = get_option( self::OPTION_KEY, null );
		if ( null === $existing || false === $existing ) {
			add_option( self::OPTION_KEY, self::defaults(), '', false );
			self::forget();
			return;
		}
		if ( is_array( $existing ) ) {
			update_option( self::OPTION_KEY, wp_parse_args( $existing, self::defaults() ) );
			self::forget();
		}
	}

	/**
	 * Get all settings. Memoized per request; bumped via forget().
	 *
	 * @return array<string,mixed>
	 */
	public static function all() {
		if ( null !== self::$memo ) {
			return self::$memo;
		}
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		self::$memo = wp_parse_args( $stored, self::defaults() );
		return self::$memo;
	}

	/**
	 * Drop the per-request settings memo.
	 *
	 * @return void
	 */
	public static function forget() {
		self::$memo = null;
	}

	/**
	 * Get a single setting.
	 *
	 * @param string $key     Key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();
		if ( array_key_exists( $key, $all ) ) {
			return $all[ $key ];
		}
		return $default;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'update_option_' . self::OPTION_KEY, array( $this, 'on_settings_saved' ), 10, 2 );
	}

	/**
	 * Enqueue assets on the settings page.
	 *
	 * @param string $hook Hook.
	 * @return void
	 */
	public function enqueue( $hook ) {
		// Always enqueue plugin admin CSS so color vars are available site-wide
		wp_enqueue_style( 'salesbin-admin', SALESBIN_URL . 'assets/css/admin.css', array( 'salesbin-dashboard' ), SALESBIN_VERSION );

		// Apply plugin color vars to WC settings main form (works from first load, even before any option is saved)
		// This uses CSS custom properties defined in admin.css via :root or body scope
		if ( false !== strpos( $hook, 'woocommerce_page_wc-settings' ) ) {
			wp_add_inline_style( 'salesbin-admin', '
				body.woocommerce_page_wc-settings #mainform {
					background: var( --sb-bg, #07080c ) !important;
				}
				body.woocommerce_page_wc-settings #mainform .inside {
					background: var( --sb-bg-2, #0e1018 ) !important;
				}
				body.woocommerce_page_wc-settings #mainform .submit {
					background: var( --sb-card, rgba(20, 23, 34, 0.78) ) !important;
					color: var( --sb-text, #e8eaf2 ) !important;
				}
			' );
		}

		// Deterministic theming for the whole settings page (no JS dependency):
		// palette + themed backdrop printed server-side on the page scope.
		if ( false === strpos( $hook, 'salesbin-settings' ) ) {
			return;
		}
		wp_enqueue_style( 'salesbin-fonts', SALESBIN_URL . 'assets/css/fonts.css', array(), SALESBIN_VERSION );
		wp_enqueue_style( 'salesbin-dashboard', SALESBIN_URL . 'assets/css/dashboard.css', array( 'salesbin-fonts' ), SALESBIN_VERSION );
		wp_enqueue_style( 'salesbin-admin', SALESBIN_URL . 'assets/css/admin.css', array( 'salesbin-dashboard' ), SALESBIN_VERSION );

		// palette + themed backdrop printed server-side on the page scope.
		$theme = Salesbin_Settings::get( 'theme', 'woodesh' );
		$mode  = Salesbin_Settings::get( 'mode', 'dark' );
		$page  = 'body.salesbin_page_salesbin-settings,'
			. 'body.salesbin_page_salesbin-settings #wpwrap,'
			. 'body.salesbin_page_salesbin-settings #wpcontent,'
			. 'body.salesbin_page_salesbin-settings #wpbody,'
			. 'body.salesbin_page_salesbin-settings #wpbody-content{'
			. self::palette_css( $theme, $mode )
			. 'background:radial-gradient(1100px 460px at 100% -8%, rgba(var(--sb-accent-rgb), .14), transparent 55%),var(--sb-bg) !important;}';
		$page .= 'body.salesbin_page_salesbin-settings{--sb-font:' . self::font_stack( self::get( 'admin_font', 'yekanbakh' ) ) . ';}';
		wp_add_inline_style( 'salesbin-admin', $page );
		wp_add_inline_style(
			'salesbin-admin',
			'.salesbin-settings{--sb-font:' . self::font_stack( self::get( 'admin_font', 'yekanbakh' ) ) . ';}'
		);
		wp_enqueue_script( 'salesbin-theme', SALESBIN_URL . 'assets/js/theme.js', array(), SALESBIN_VERSION, true );
		wp_enqueue_script( 'salesbin-settings', SALESBIN_URL . 'assets/js/settings.js', array( 'salesbin-theme' ), SALESBIN_VERSION, true );
		wp_localize_script(
			'salesbin-settings',
			'salesbinSettings',
			array(
				'theme'        => Salesbin_Settings::get( 'theme', 'woodesh' ),
				'mode'         => Salesbin_Settings::get( 'mode', 'dark' ),
				'loginPageUrl' => Salesbin_Login_Module::instance()->page_url(),
				'i18n'         => array(
					'preview' => __( 'پیش‌نمایش زنده — با ذخیره تنظیمات برای همه کاربران اعمال می‌شود.', 'salesbin' ),
				),
			)
		);
	}

	/**
	 * After settings are saved, keep derived state in sync (login page creation).
	 *
	 * @param array $old_value Old settings.
	 * @param array $new_value New settings.
	 * @return void
	 */
	public function on_settings_saved( $old_value, $new_value ) {
		$old = is_array( $old_value ) ? $old_value : array();
		$new = is_array( $new_value ) ? $new_value : array();

		// Login module: no page to create anymore (the surface is /my-account/),
		// but the legacy endpoint rewrite needs a flush when toggled.
		$old_login = (int) ( $old['login_enabled'] ?? 0 );
		$new_login = (int) ( $new['login_enabled'] ?? 0 );
		if ( $old_login !== $new_login ) {
			flush_rewrite_rules( false );
		}

		// Tickets endpoint registers rewrite rules — flush only when toggled.
		$old_tickets = (int) ( $old['account_tickets_enabled'] ?? 0 );
		$new_tickets = (int) ( $new['account_tickets_enabled'] ?? 0 );
		if ( $old_tickets !== $new_tickets ) {
			flush_rewrite_rules( false );
		}
	}

	/**
	 * Register Settings API.
	 *
	 * @return void
	 */
	public function register() {
		register_setting(
			'salesbin',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Sanitize settings payload.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string,mixed>
	 */
	public function sanitize( $input ) {
		$defaults = self::defaults();
		$input    = is_array( $input ) ? $input : array();
		self::forget();
		$out      = array();

		$out['low_stock_threshold']           = max( 0, absint( $input['low_stock_threshold'] ?? $defaults['low_stock_threshold'] ) );
		$out['new_order_notification_window'] = max( 1, min( 10080, absint( $input['new_order_notification_window'] ?? $defaults['new_order_notification_window'] ) ) );
		$out['notify_new_order']              = empty( $input['notify_new_order'] ) ? 0 : 1;
		$out['notify_low_stock']              = empty( $input['notify_low_stock'] ) ? 0 : 1;
		$out['notify_pending_review']         = empty( $input['notify_pending_review'] ) ? 0 : 1;
		$out['notify_needs_attention']        = empty( $input['notify_needs_attention'] ) ? 0 : 1;
		$out['notify_high_value']             = empty( $input['notify_high_value'] ) ? 0 : 1;
		$out['notify_refund']                 = empty( $input['notify_refund'] ) ? 0 : 1;
		$out['high_value_order_threshold']    = max( 0, (float) ( $input['high_value_order_threshold'] ?? $defaults['high_value_order_threshold'] ) );
		$out['daily_sales_goal']              = max( 0, (float) ( $input['daily_sales_goal'] ?? $defaults['daily_sales_goal'] ) );

		$per_page = absint( $input['orders_per_page'] ?? 10 );
		$out['orders_per_page'] = in_array( $per_page, array( 10, 20, 50, 100 ), true ) ? $per_page : 10;

		$range = sanitize_key( $input['default_date_range'] ?? '30d' );
		$out['default_date_range'] = in_array( $range, array( '7d', '30d', '90d', '1y' ), true ) ? $range : '30d';

		$chart = sanitize_key( $input['default_chart_type'] ?? 'line' );
		$out['default_chart_type'] = in_array( $chart, array( 'line', 'bar' ), true ) ? $chart : 'line';

		$out['cache_duration']           = max( 0, min( 86400, absint( $input['cache_duration'] ?? 300 ) ) );
		$out['admin_bar_enabled']        = empty( $input['admin_bar_enabled'] ) ? 0 : 1;
		$out['admin_bar_show_low_stock'] = empty( $input['admin_bar_show_low_stock'] ) ? 0 : 1;

		$interval = absint( $input['auto_refresh_interval'] ?? 0 );
		$out['auto_refresh_interval'] = in_array( $interval, array( 0, 30, 60, 120, 300 ), true ) ? $interval : 0;

		$theme = sanitize_key( $input['theme'] ?? 'woodesh' );
		$out['theme'] = in_array( $theme, self::themes(), true ) ? $theme : 'woodesh';

		$mode = sanitize_key( $input['mode'] ?? 'dark' );
		$out['mode'] = in_array( $mode, array( 'dark', 'light' ), true ) ? $mode : 'dark';

		$out['notifications_retention_days'] = max( 7, min( 365, absint( $input['notifications_retention_days'] ?? 90 ) ) );

		$out['login_enabled']         = empty( $input['login_enabled'] ) ? 0 : 1;
		$out['login_replace_default'] = empty( $input['login_replace_default'] ) ? 0 : 1;
		$out['login_show_register']   = empty( $input['login_show_register'] ) ? 0 : 1;
		// The login page id is managed programmatically; preserve it when not part of the submitted payload.
		$out['login_page_id']         = absint( $input['login_page_id'] ?? Salesbin_Settings::get( 'login_page_id', 0 ) );

		$out['login_sms_enabled']      = empty( $input['login_sms_enabled'] ) ? 0 : 1;
		$out['login_sms_debug']        = empty( $input['login_sms_debug'] ) ? 0 : 1;
		$out['login_sms_autoregister'] = empty( $input['login_sms_autoregister'] ) ? 0 : 1;
		$out['login_otp_expiry']       = max( 60, min( 900, absint( $input['login_otp_expiry'] ?? 180 ) ) );
		$out['login_otp_cooldown']     = max( 15, min( 300, absint( $input['login_otp_cooldown'] ?? 60 ) ) );
		$out['melipayamak_username'] = sanitize_text_field( $input['melipayamak_username'] ?? '' );
		$out['melipayamak_password'] = sanitize_text_field( $input['melipayamak_password'] ?? '' );
		$out['melipayamak_from']     = preg_replace( '/[^0-9+]/', '', (string) ( $input['melipayamak_from'] ?? '' ) );
		$out['melipayamak_khadamati'] = empty( $input['melipayamak_khadamati'] ) ? 0 : 1;
		$out['melipayamak_body_id']  = preg_replace( '/[^0-9]/', '', (string) ( $input['melipayamak_body_id'] ?? '' ) );
		$out['melipayamak_template'] = sanitize_text_field( $input['melipayamak_template'] ?? __( 'کد ورود شما: {OTP}', 'salesbin' ) );

		// ---- Account panel ----
		$out['account_panel_enabled'] = empty( $input['account_panel_enabled'] ) ? 0 : 1;
		$acc_mode = sanitize_key( $input['account_panel_mode'] ?? 'dark' );
		$out['account_panel_mode'] = in_array( $acc_mode, array( 'dark', 'light' ), true ) ? $acc_mode : 'dark';
		$acc_accent = sanitize_key( $input['account_panel_accent'] ?? 'woodesh' );
		$out['account_panel_accent'] = in_array( $acc_accent, self::themes(), true ) ? $acc_accent : 'woodesh';

		foreach ( array( 'account_panel_welcome', 'account_panel_stats', 'account_panel_breakdown', 'account_panel_recent', 'account_panel_quick', 'account_panel_messages' ) as $acc_flag ) {
			$out[ $acc_flag ] = empty( $input[ $acc_flag ] ) ? 0 : 1;
		}

		// Per-endpoint menu config: label / icon / visible / order. Keys and
		// values are sanitized; unknown fields are dropped.
		if ( isset( $input['account_menu'] ) && is_array( $input['account_menu'] ) ) {
			$icons     = Salesbin_Account_Panel::icons();
			$acc_menu  = array();
			foreach ( $input['account_menu'] as $acc_endpoint => $acc_conf ) {
				$acc_endpoint = sanitize_key( (string) $acc_endpoint );
				if ( '' === $acc_endpoint || ! is_array( $acc_conf ) ) {
					continue;
				}
				$acc_icon = sanitize_key( (string) ( $acc_conf['icon'] ?? '' ) );
				$acc_menu[ $acc_endpoint ] = array(
					'label'   => sanitize_text_field( (string) ( $acc_conf['label'] ?? '' ) ),
					'icon'    => ( '' === $acc_icon || isset( $icons[ $acc_icon ] ) ) ? $acc_icon : '',
					'visible' => empty( $acc_conf['visible'] ) ? 0 : 1,
					'order'   => absint( $acc_conf['order'] ?? 0 ),
				);
			}
			$out['account_menu'] = $acc_menu;
		} else {
			$out['account_menu'] = self::get( 'account_menu', array() );
		}

		// Custom quick-link cards: one per line, "عنوان | https://example.com".
		$out['account_custom_links'] = sanitize_textarea_field( (string) ( $input['account_custom_links'] ?? '' ) );

		$out['account_tickets_enabled'] = empty( $input['account_tickets_enabled'] ) ? 0 : 1;

		$out['admin_font']   = self::sanitize_font_slug( $input['admin_font'] ?? 'yekanbakh' );
		$out['account_font'] = self::sanitize_font_slug( $input['account_font'] ?? 'yekanbakh' );

		$out['admin_chrome_theme'] = empty( $input['admin_chrome_theme'] ) ? 0 : 1;

		$out['debug_logging']            = empty( $input['debug_logging'] ) ? 0 : 1;
		$out['delete_data_on_uninstall'] = empty( $input['delete_data_on_uninstall'] ) ? 0 : 1;

		Salesbin_Cache::bump_version();

		return $out;
	}

	/**
	 * Whitelist a font slug.
	 *
	 * @param string $slug Raw slug.
	 * @return string
	 */
	private static function sanitize_font_slug( $slug ) {
		$slug = sanitize_key( (string) $slug );
		return isset( self::fonts()[ $slug ] ) ? $slug : 'yekanbakh';
	}

	/**
	 * Public update from REST.
	 *
	 * @param array $input Input.
	 * @return array
	 */
	public function update_from_rest( $input ) {
		$clean = $this->sanitize( wp_parse_args( $input, self::all() ) );
		update_option( self::OPTION_KEY, $clean );
		self::forget();
		return $clean;
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( Salesbin_Capabilities::required() ) ) {
			return;
		}
		include SALESBIN_PATH . 'admin/views/settings.php';
	}
}
