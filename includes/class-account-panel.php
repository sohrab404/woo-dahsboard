<?php
/**
 * WooCommerce My Account redesign — modern customer dashboard (uPanel-style).
 *
 * Non-destructive layer over WooCommerce:
 *  - Replaces only the default navigation render; content (forms, endpoints,
 *    theme overrides of myaccount/*.php) keeps rendering through WC itself.
 *  - Sidebar is built from wc_get_account_menu_items(), so custom endpoints
 *    registered by themes/plugins appear automatically.
 *  - The dashboard endpoint content is replaced by a stats overview built
 *    from real customer data (orders, spent, downloads — no fake numbers).
 *
 * Extension points:
 *  - salesbin_account_widgets  filter: registry of extra dashboard widgets
 *    (id, title, priority, callback). Built for wallet/loyalty integrations.
 *  - salesbin_account_stats    filter: append custom stat cards (label/value).
 *  - salesbin_account_panel_active filter: programmatic off-switch.
 *
 * Off by default (account_panel_enabled). Escape hatch for conflicts:
 * append ?woodesh-account=0 to any account URL to get the default UI.
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Salesbin_Account_Panel
 */
class Salesbin_Account_Panel {

	/**
	 * Instance.
	 *
	 * @var Salesbin_Account_Panel|null
	 */
	private static $instance = null;

	/**
	 * Whether the shell has been opened on this request.
	 *
	 * @var bool
	 */
	private $shell_opened = false;

	/**
	 * @return Salesbin_Account_Panel
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks (cheap; real work happens only on the account page).
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'wp', array( $this, 'maybe_boot' ) );
		add_action( 'admin_post_salesbin_rebuild_account_page', array( $this, 'handle_rebuild_account_page' ) );
	}

	/**
	 * Master switch from settings.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return (bool) Salesbin_Settings::get( 'account_panel_enabled', 0 );
	}

	/**
	 * Whether the new UI should render on this request.
	 *
	 * @return bool
	 */
	private function should_boot() {
		if ( ! $this->is_enabled() ) {
			return false;
		}
		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
			return false;
		}
		if ( ! is_user_logged_in() ) {
			return false;
		}
		// Escape hatch: ?woodesh-account=0 forces the default WooCommerce UI.
		if ( isset( $_GET['woodesh-account'] ) && '0' === sanitize_key( wp_unslash( $_GET['woodesh-account'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}
		return (bool) apply_filters( 'salesbin_account_panel_active', true );
	}

	/**
	 * Boot on the account page.
	 *
	 * @return void
	 */
	public function maybe_boot() {
		if ( $this->should_boot() ) {
			$this->boot();
		}
	}

	/**
	 * Swap the WC navigation for our shell and replace the dashboard endpoint.
	 *
	 * @return void
	 */
	private function boot() {
		$this->shell_opened = false;

		add_filter( 'body_class', array( $this, 'body_class' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );

		// Serve our own my-account.php template, bypassing theme overrides of
		// the account page (their form partials keep loading from the theme).
		add_filter( 'woocommerce_locate_template', array( $this, 'override_my_account_template' ), 100, 3 );

		// Hook surgery runs as late as possible: themes/plugins that ship a
		// custom My Account panel re-register their renderers after init, so
		// stripping at `wp` isn't enough. template_redirect (999999) is the
		// last stop before output.
		add_action( 'template_redirect', array( $this, 'late_boot' ), 999999 );
	}

	/**
	 * Final hook surgery: strip every competing account-panel renderer
	 * (default WC nav/dashboard + theme/plugin custom panels) and register ours.
	 *
	 * @return void
	 */
	public function late_boot() {
		if ( ! $this->should_boot() ) {
			return;
		}

		remove_all_actions( 'woocommerce_account_navigation' );
		remove_all_actions( 'woocommerce_account_dashboard_endpoint' );

		add_action( 'woocommerce_account_navigation', array( $this, 'render_shell' ) );
		add_action( 'wp_footer', array( $this, 'render_shell_close' ), 5 );
		add_action( 'woocommerce_account_dashboard_endpoint', array( $this, 'render_dashboard' ) );

		// Menu labels/icons/visibility/order from settings.
		add_filter( 'woocommerce_account_menu_items', array( $this, 'configure_menu' ), 20 );
	}

	/**
	 * Serve the bundled my-account shell so every theme renders the same
	 * hook structure while the panel is enabled.
	 *
	 * @param string $template     Resolved template path.
	 * @param string $template_name Template name (e.g. myaccount/my-account.php).
	 * @param string $template_path Theme template path.
	 * @return string
	 */
	public function override_my_account_template( $template, $template_name, $template_path ) {
		unset( $template_path );
		if ( 'myaccount/my-account.php' === $template_name ) {
			return SALESBIN_PATH . 'templates/myaccount/my-account.php';
		}
		if ( 'myaccount/orders.php' === $template_name ) {
			return SALESBIN_PATH . 'templates/myaccount/orders.php';
		}
		return $template;
	}

	/**
	 * Body markers.
	 *
	 * @param array $classes Classes.
	 * @return array
	 */
	public function body_class( $classes ) {
		$classes[] = 'sb-account-active';
		$classes[] = 'sb-account-mode-' . sanitize_key( Salesbin_Settings::get( 'account_panel_mode', 'dark' ) );
		return $classes;
	}

	/**
	 * Front assets + scoped palette.
	 *
	 * @return void
	 */
	public function enqueue() {
		$accent = sanitize_key( (string) Salesbin_Settings::get( 'account_panel_accent', 'woodesh' ) );
		$mode   = sanitize_key( (string) Salesbin_Settings::get( 'account_panel_mode', 'dark' ) );

		wp_enqueue_style( 'salesbin-fa', SALESBIN_URL . 'assets/fontawesome/css/all.min.css', array(), '6.5.2' );
		wp_enqueue_style( 'salesbin-fonts', SALESBIN_URL . 'assets/css/fonts.css', array(), SALESBIN_VERSION );
		wp_enqueue_style( 'salesbin-account', SALESBIN_URL . 'assets/css/account.css', array( 'salesbin-fa', 'salesbin-fonts' ), SALESBIN_VERSION );
		wp_add_inline_style( 'salesbin-account', $this->palette_css( $accent ) );
		wp_add_inline_style(
			'salesbin-account',
			'.sb-account{--sb-font:' . Salesbin_Settings::font_stack( Salesbin_Settings::get( 'account_font', 'yekanbakh' ) ) . ';}'
		);
		wp_enqueue_script( 'salesbin-fx', SALESBIN_URL . 'assets/js/effects.js', array(), SALESBIN_VERSION, true );
		wp_enqueue_script( 'salesbin-account', SALESBIN_URL . 'assets/js/account.js', array( 'salesbin-fx' ), SALESBIN_VERSION, true );
		wp_localize_script(
			'salesbin-account',
			'salesbinAccount',
			array(
				'mode'     => in_array( $mode, array( 'dark', 'light' ), true ) ? $mode : 'dark',
				'currency' => Salesbin_Helpers::currency_meta()['symbol'],
			)
		);
	}

	/**
	 * Accent + neutral palettes scoped to the panel root. Both modes are
	 * printed so the visitor toggle can flip them without a reload.
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

		$css  = '.sb-account[data-sb-accent="' . esc_attr( $accent ) . '"]{';
		$css .= '--sb-accent:' . $a[0] . ';--sb-accent-rgb:' . $a[1] . ';--sb-accent-2:' . $a[2] . ';}';
		// Always provide the default accent as a fallback for safety.
		$css .= '.sb-account{--sb-accent:' . $a[0] . ';--sb-accent-rgb:' . $a[1] . ';--sb-accent-2:' . $a[2] . ';}';

		$css .= '.sb-account[data-sb-mode="dark"]{'
			. '--sb-bg:#07080c;--sb-bg-2:#0e1018;--sb-card:#141722;--sb-card-2:#1a1e2b;'
			. '--sb-border:rgba(255,255,255,.07);--sb-text:#e8eaf2;--sb-muted:#8b91a7;'
			. '--sb-track:rgba(255,255,255,.06);--sb-sheen:rgba(255,255,255,.03);'
			. '--sb-success:#34d399;--sb-success-rgb:52,211,153;--sb-warn:#fbbf24;--sb-warn-rgb:251,191,36;'
			. '--sb-danger:#f87171;--sb-danger-rgb:248,113,113;--sb-info:#60a5fa;--sb-info-rgb:96,165,250;'
			. '--sb-shadow:0 10px 40px rgba(0,0,0,.35);--sb-glow:0 8px 24px rgba(var(--sb-accent-rgb),.35);'
			. 'color-scheme:dark;}';

		$css .= '.sb-account[data-sb-mode="light"]{'
			. '--sb-bg:#f6f7fb;--sb-bg-2:#ffffff;--sb-card:#ffffff;--sb-card-2:#eef0f7;'
			. '--sb-border:rgba(15,18,35,.10);--sb-text:#171a26;--sb-muted:#5b6172;'
			. '--sb-track:rgba(15,18,35,.08);--sb-sheen:rgba(var(--sb-accent-rgb),.04);'
			. '--sb-success:#059669;--sb-success-rgb:5,150,105;--sb-warn:#b45309;--sb-warn-rgb:180,83,9;'
			. '--sb-danger:#dc2626;--sb-danger-rgb:220,38,38;--sb-info:#2563eb;--sb-info-rgb:37,99,235;'
			. '--sb-shadow:0 10px 40px rgba(23,26,38,.10);--sb-glow:0 8px 24px rgba(var(--sb-accent-rgb),.22);'
			. 'color-scheme:light;}';

		return $css;
	}

	/**
	 * Icon library — Font Awesome Free (bundled locally, no CDN).
	 *
	 * @return array<string,string>
	 */
	public static function icons() {
		static $icons = null;
		if ( null !== $icons ) {
			return $icons;
		}
		$i = '<i class="fa-solid ';
		$icons = array(
			'dashboard' => $i . 'fa-gauge-high" aria-hidden="true"></i>',
			'orders'    => $i . 'fa-bag-shopping" aria-hidden="true"></i>',
			'downloads' => $i . 'fa-download" aria-hidden="true"></i>',
			'address'   => $i . 'fa-location-dot" aria-hidden="true"></i>',
			'user'      => $i . 'fa-user-pen" aria-hidden="true"></i>',
			'card'      => $i . 'fa-credit-card" aria-hidden="true"></i>',
			'logout'    => $i . 'fa-right-from-bracket" aria-hidden="true"></i>',
			'heart'     => $i . 'fa-heart" aria-hidden="true"></i>',
			'gift'      => $i . 'fa-gift" aria-hidden="true"></i>',
			'star'      => $i . 'fa-star" aria-hidden="true"></i>',
			'ticket'    => $i . 'fa-ticket" aria-hidden="true"></i>',
			'box'       => $i . 'fa-box" aria-hidden="true"></i>',
			'bell'      => $i . 'fa-bell" aria-hidden="true"></i>',
			'phone'     => $i . 'fa-phone" aria-hidden="true"></i>',
			'folder'    => $i . 'fa-folder" aria-hidden="true"></i>',
			'sliders'   => $i . 'fa-sliders" aria-hidden="true"></i>',
			'wallet'    => $i . 'fa-wallet" aria-hidden="true"></i>',
			'truck'     => $i . 'fa-truck-fast" aria-hidden="true"></i>',
			'tag'       => $i . 'fa-tag" aria-hidden="true"></i>',
			'comment'   => $i . 'fa-comment-dots" aria-hidden="true"></i>',
			'house'     => $i . 'fa-house" aria-hidden="true"></i>',
			'gear'      => $i . 'fa-gear" aria-hidden="true"></i>',
			'clipboard' => $i . 'fa-clipboard-list" aria-hidden="true"></i>',
			'bookmark'  => $i . 'fa-bookmark" aria-hidden="true"></i>',
		);
		return $icons;
	}

	/**
	 * Icon for an endpoint (settings override, then known map, then default).
	 *
	 * @param string $endpoint Endpoint slug.
	 * @return string SVG.
	 */
	private function endpoint_icon( $endpoint ) {
		$cfg = (array) Salesbin_Settings::get( 'account_menu', array() );
		if ( ! empty( $cfg[ $endpoint ]['icon'] ) ) {
			$icons = self::icons();
			$name  = sanitize_key( $cfg[ $endpoint ]['icon'] );
			if ( isset( $icons[ $name ] ) ) {
				return $icons[ $name ];
			}
		}
		$map = apply_filters(
			'salesbin_account_endpoint_icons',
			array(
				'dashboard'       => 'dashboard',
				'orders'          => 'orders',
				'downloads'       => 'downloads',
				'edit-address'    => 'address',
				'edit-account'    => 'user',
				'payment-methods' => 'card',
				'customer-logout' => 'logout',
			)
		);
		$icons = self::icons();
		$name  = isset( $map[ $endpoint ] ) ? $map[ $endpoint ] : 'box';
		return isset( $icons[ $name ] ) ? $icons[ $name ] : $icons['box'];
	}

	/**
	 * Configured menu: WC registry (with custom endpoints) merged with the
	 * admin settings (label/icon/visibility/order).
	 *
	 * @return array<int,array{endpoint:string,label:string,icon:string,classes:array,url:string}>
	 */
	public function menu_items() {
		$raw = function_exists( 'wc_get_account_menu_items' ) ? wc_get_account_menu_items() : array();
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}
		$cfg   = (array) Salesbin_Settings::get( 'account_menu', array() );
		$out   = array();
		$index = 0;

		foreach ( $raw as $endpoint => $label ) {
			$endpoint = (string) $endpoint;
			$conf     = isset( $cfg[ $endpoint ] ) && is_array( $cfg[ $endpoint ] ) ? $cfg[ $endpoint ] : array();

			if ( isset( $conf['visible'] ) && empty( $conf['visible'] ) ) {
				continue;
			}

			if ( function_exists( 'wc_get_account_menu_item_classes' ) ) {
				$classes = wc_get_account_menu_item_classes( $endpoint );
				// WC returns a space-separated string; normalize to an array.
				$classes = is_array( $classes ) ? $classes : array_filter( explode( ' ', (string) $classes ) );
			} else {
				$classes = array( $endpoint . '-link' );
				if ( function_exists( 'WC' ) && WC()->query && method_exists( WC()->query, 'get_current_endpoint' ) ) {
					if ( WC()->query->get_current_endpoint() === $endpoint ) {
						$classes[] = 'is-active';
					}
				}
			}

			$out[] = array(
				'endpoint' => $endpoint,
				'label'    => isset( $conf['label'] ) && '' !== trim( (string) $conf['label'] ) ? trim( (string) $conf['label'] ) : (string) $label,
				'icon'     => $this->endpoint_icon( $endpoint ),
				'classes'  => $classes,
				'url'      => function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( $endpoint ) : '',
				'order'    => isset( $conf['order'] ) ? (int) $conf['order'] : $index * 10,
			);
			$index++;
		}

		usort(
			$out,
			function ( $a, $b ) {
				return $a['order'] <=> $b['order'];
			}
		);
		return $out;
	}

	/**
	 * Settings-driven tweaks on the WC menu registry.
	 *
	 * @param array $items Items.
	 * @return array
	 */
	public function configure_menu( $items ) {
		$cfg = (array) Salesbin_Settings::get( 'account_menu', array() );
		if ( empty( $cfg ) ) {
			return $items;
		}
		foreach ( $cfg as $endpoint => $conf ) {
			if ( ! isset( $items[ $endpoint ] ) ) {
				continue;
			}
			if ( isset( $conf['visible'] ) && empty( $conf['visible'] ) ) {
				unset( $items[ $endpoint ] );
				continue;
			}
			if ( isset( $conf['label'] ) && '' !== trim( (string) $conf['label'] ) ) {
				$items[ $endpoint ] = trim( (string) $conf['label'] );
			}
		}
		return $items;
	}

	/**
	 * Shell open: right sidebar + content frame. No panel header — the site
	 * header belongs to the theme (Elementor). Divs are closed on wp_footer.
	 *
	 * @return void
	 */
	public function render_shell() {
		$this->shell_opened = true;
		$mode  = sanitize_key( (string) Salesbin_Settings::get( 'account_panel_mode', 'dark' ) );
		$items = $this->menu_items();
		?>
		<div class="sb-account" data-sb-accent="<?php echo esc_attr( sanitize_key( (string) Salesbin_Settings::get( 'account_panel_accent', 'woodesh' ) ) ); ?>" data-sb-mode="<?php echo esc_attr( $mode ); ?>">
			<div class="sb-account__frame">

				<header class="sb-account__top">
					<div class="sb-account__brand">
						<?php
						$logo_id  = (int) get_theme_mod( 'custom_logo' );
						$logo_img = $logo_id ? wp_get_attachment_image( $logo_id, 'thumbnail', false, array( 'class' => 'sb-account__brand-img' ) ) : '';
						if ( ! $logo_img && (int) get_option( 'site_icon' ) ) {
							$logo_img = wp_get_attachment_image( (int) get_option( 'site_icon' ), 'thumbnail', false, array( 'class' => 'sb-account__brand-img' ) );
						}
						if ( $logo_img ) {
							echo $logo_img; // phpcs:ignore WordPress.Security.EscapeOutput -- core attachment HTML
						} else {
							echo '<span class="sb-account__brand-logo--dv" aria-hidden="true">DV</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
						}
						?>
						<strong><?php echo esc_html( get_bloginfo( 'name' ) ); ?></strong>
					</div>
					<div class="sb-account__top-actions">
						<button type="button" class="sb-account__mode" data-sb-account-mode aria-label="<?php esc_attr_e( 'حالت شب و روز', 'salesbin' ); ?>">🌙</button>
						<button type="button" class="sb-account__burger" data-sb-account-burger aria-label="<?php esc_attr_e( 'منوی حساب', 'salesbin' ); ?>" aria-expanded="false"><span></span><span></span><span></span></button>
						<div class="sb-account__user">
							<button type="button" class="sb-account__user-btn" data-sb-account-user aria-haspopup="true" aria-expanded="false">
								<?php echo get_avatar( wp_get_current_user()->ID, 36, '', '', array( 'class' => 'sb-account__avatar' ) ); ?>
								<span class="sb-account__user-name"><?php echo esc_html( wp_get_current_user()->display_name ); ?></span>
								<span class="sb-account__caret" aria-hidden="true">▾</span>
							</button>
							<div class="sb-account__user-menu" hidden>
								<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-account' ) ); ?>"><?php echo esc_html__( 'جزئیات حساب', 'salesbin' ); ?></a>
								<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'customer-logout' ) ); ?>" class="sb-account__user-logout"><?php echo esc_html__( 'خروج از حساب', 'salesbin' ); ?></a>
							</div>
						</div>
					</div>
				</header>

			<div class="sb-account__scrim" data-sb-account-scrim hidden></div>

			<div class="sb-account__shell">
				<aside class="sb-account__side">
					<nav class="sb-account__nav" aria-label="<?php esc_attr_e( 'ناوبری حساب کاربری', 'salesbin' ); ?>">
						<ul>
							<?php foreach ( $items as $item ) : ?>
								<li class="<?php echo esc_attr( implode( ' ', array_map( 'esc_attr', (array) $item['classes'] ) ) ); ?>">
									<a href="<?php echo esc_url( $item['url'] ); ?>">
										<span class="sb-account__nav-icon"><?php echo $item['icon']; // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG built in-code ?></span>
										<span><?php echo esc_html( $item['label'] ); ?></span>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					</nav>
				</aside>
				<div class="sb-account__main">
		<?php
	}

	/**
	 * Close the shell opened in render_shell (printed at wp_footer).
	 *
	 * @return void
	 */
	public function render_shell_close() {
		if ( ! $this->shell_opened ) {
			return;
		}
		echo '</div></div></div></div>'; // main, shell, frame, sb-account.
	}

	/**
	 * Re-create the WooCommerce My Account page if it was deleted, and
	 * (re)point WooCommerce at it. Settings → Account panel → restore button.
	 *
	 * @return void
	 */
	public function handle_rebuild_account_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'دسترسی غیرمجاز.', 'salesbin' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'salesbin_rebuild_account_page' );

		$page_id = (int) get_option( 'woocommerce_myaccount_page_id' );
		$page    = $page_id ? get_post( $page_id ) : null;
		$valid   = $page && 'page' === $page->post_type && 'trash' !== $page->post_status;

		if ( ! $valid ) {
			$existing = get_page_by_path( 'my-account' );
			if ( $existing && 'trash' !== $existing->post_status ) {
				update_option( 'woocommerce_myaccount_page_id', (int) $existing->ID );
			} else {
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
				}
			}
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'salesbin-settings', 'sb_rebuilt' => 'account' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Admin-defined quick-link cards from settings ("عنوان | URL" per line).
	 *
	 * @return array<int,array{title:string,url:string,external:bool}>
	 */
	public static function custom_links() {
		$raw   = (string) Salesbin_Settings::get( 'account_custom_links', '' );
		$lines = preg_split( '/\r\n|\r|\n/', $raw ) ?: array();
		$out   = array();
		$site  = home_url();

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			$url   = isset( $parts[1] ) ? esc_url_raw( $parts[1] ) : '';
			if ( '' === $url || ! isset( $parts[0] ) || '' === $parts[0] ) {
				continue;
			}
			$out[] = array(
				'title'    => sanitize_text_field( $parts[0] ),
				'url'      => $url,
				'external' => 0 !== strpos( $url, $site ),
			);
			if ( count( $out ) >= 8 ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Dashboard endpoint: real customer data overview.
	 *
	 * @return void
	 */
	public function render_dashboard() {
		$user = wp_get_current_user();
		if ( ! $user->exists() ) {
			return;
		}

		$sb_flags = array(
			'welcome'   => (bool) Salesbin_Settings::get( 'account_panel_welcome', 1 ),
			'stats'     => (bool) Salesbin_Settings::get( 'account_panel_stats', 1 ),
			'breakdown' => (bool) Salesbin_Settings::get( 'account_panel_breakdown', 1 ),
			'recent'    => (bool) Salesbin_Settings::get( 'account_panel_recent', 1 ),
			'quick'     => (bool) Salesbin_Settings::get( 'account_panel_quick', 1 ),
			'messages'  => (bool) Salesbin_Settings::get( 'account_panel_messages', 1 ),
		);

		$sb_data = $this->get_stats( $user );
		$sb_data = apply_filters( 'salesbin_account_stats', $sb_data, $user->ID );

		$sb_widgets = apply_filters(
			'salesbin_account_widgets',
			array()
		);
		if ( is_array( $sb_widgets ) ) {
			uasort(
				$sb_widgets,
				function ( $a, $b ) {
					$pa = isset( $a['priority'] ) ? (int) $a['priority'] : 10;
					$pb = isset( $b['priority'] ) ? (int) $b['priority'] : 10;
					return $pa <=> $pb;
				}
			);
		} else {
			$sb_widgets = array();
		}

		include SALESBIN_PATH . 'admin/views/account-dashboard.php';
	}

	/**
	 * Real account stats (cached 5 min per user; version-bumped on order events).
	 *
	 * @param WP_User $user User.
	 * @return array
	 */
	private function get_stats( $user ) {
		$uid = $user->ID;

		return Salesbin_Cache::remember(
			'account',
			array( 'dashboard', $uid ),
			function () use ( $uid, $user ) {
				$orders_count = function_exists( 'wc_get_customer_order_count' ) ? (int) wc_get_customer_order_count( $uid ) : 0;
				$spent        = function_exists( 'wc_get_customer_total_spent' ) ? (float) wc_get_customer_total_spent( $uid ) : 0.0;
				$downloads    = function_exists( 'wc_get_customer_available_downloads' ) ? (array) wc_get_customer_available_downloads( $uid ) : array();

				$orders = wc_get_orders(
					array(
						'customer' => $uid,
						'limit'    => 200,
						'orderby'  => 'date',
						'order'    => 'DESC',
						'type'     => 'shop_order',
					)
				);
				$orders = is_array( $orders ) ? $orders : array();

				$labels = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array();

				// Status breakdown over the customer's recent orders.
				$breakdown = array();
				foreach ( $orders as $order ) {
					if ( ! $order instanceof WC_Order ) {
						continue;
					}
					$slug = $order->get_status();
					if ( ! isset( $breakdown[ $slug ] ) ) {
						$breakdown[ $slug ] = array(
							'count' => 0,
							'label' => isset( $labels[ 'wc-' . $slug ] ) ? $labels[ 'wc-' . $slug ] : $slug,
						);
					}
					$breakdown[ $slug ]['count']++;
				}
				uasort(
					$breakdown,
					function ( $a, $b ) {
						return $b['count'] <=> $a['count'];
					}
				);

				// Recent orders (5) with view/pay links.
				$recent = array();
				foreach ( array_slice( $orders, 0, 5 ) as $order ) {
					if ( ! $order instanceof WC_Order ) {
						continue;
					}
					$needs_payment = $order->needs_payment();
					$recent[]      = array(
						'id'        => $order->get_id(),
						'number'    => $order->get_order_number(),
						'status'    => $order->get_status(),
						'status_label' => isset( $labels[ 'wc-' . $order->get_status() ] ) ? $labels[ 'wc-' . $order->get_status() ] : $order->get_status(),
						'total'     => (float) $order->get_total(),
						'total_html' => wp_strip_all_tags( wc_price( $order->get_total() ) ),
						'date'      => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'Y/m/d' ) : '',
						'view_url'  => $order->get_view_order_url(),
						'pay_url'   => $needs_payment ? $order->get_checkout_payment_url() : '',
					);
				}

				// Real messages: orders awaiting payment, downloads, missing address.
				$comments_total   = (int) get_comments( array( 'user_id' => $uid, 'post_type' => 'product', 'count' => true, 'status' => 'approve' ) );
				$comments_pending = (int) get_comments( array( 'user_id' => $uid, 'post_type' => 'product', 'count' => true, 'status' => 'hold' ) );

				$messages = array();
				foreach ( $orders as $order ) {
					if ( $order instanceof WC_Order && $order->needs_payment() ) {
						$messages[] = array(
							'type' => 'warn',
							'text' => sprintf(
								/* translators: %s: order number */
								__( 'پرداخت سفارش %s هنوز تکمیل نشده است.', 'salesbin' ),
								$order->get_order_number()
							),
							'url'  => $order->get_checkout_payment_url(),
							'cta'  => __( 'پرداخت', 'salesbin' ),
						);
						if ( count( $messages ) >= 3 ) {
							break;
						}
					}
				}
				if ( ! empty( $downloads ) ) {
					$messages[] = array(
						'type' => 'info',
						'text' => sprintf(
							/* translators: %d: download count */
							__( '%d فایل قابل دانلود برای شما موجود است.', 'salesbin' ),
							count( $downloads )
						),
						'url'  => wc_get_account_endpoint_url( 'downloads' ),
						'cta'  => __( 'دانلودها', 'salesbin' ),
					);
				}
				if ( '' === (string) $user->billing_address_1 && '' === (string) $user->shipping_address_1 ) {
					$messages[] = array(
						'type' => 'ok',
						'text' => __( 'آدرس ارسال خود را تکمیل کنید تا خرید سریع‌تر شود.', 'salesbin' ),
						'url'  => wc_get_account_endpoint_url( 'edit-address' ),
						'cta'  => __( 'افزودن آدرس', 'salesbin' ),
					);
				}

				return array(
					'orders'      => $orders_count,
					'spent'       => $spent,
					'downloads'   => count( $downloads ),
					'registered'  => $user->user_registered ? mysql2date( 'Y/m/d', $user->user_registered ) : '',
					'breakdown'   => $breakdown,
					'recent'      => $recent,
					'messages'    => $messages,
					'downloads_items' => array_slice( $downloads, 0, 5 ),
					'comments_total'   => $comments_total,
					'comments_pending' => $comments_pending,
				);
			},
			5 * MINUTE_IN_SECONDS
		);
	}
}
