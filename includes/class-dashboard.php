<?php
/**
 * Dashboard admin page and WordPress home dashboard.
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Salesbin_Dashboard
 */
class Salesbin_Dashboard {

	/**
	 * @var Salesbin_Dashboard|null
	 */
	private static $instance = null;

	/**
	 * @return Salesbin_Dashboard
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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_head', array( $this, 'hide_wp_chrome' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'replace_wp_dashboard' ), 999 );
		add_action( 'load-index.php', array( $this, 'disable_welcome_panel' ) );
		add_action( 'all_admin_notices', array( $this, 'render_wp_home' ), 0 );
		add_action( 'in_admin_header', array( $this, 'suppress_native_notices' ), 1 );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'chrome_enqueue' ), -5 );
	}

	/**
	 * Dark layout class on WP home and plugin screens.
	 *
	 * @param string $classes Classes.
	 * @return string
	 */
	public function body_class( $classes ) {
		if ( $this->is_wp_home() || $this->is_plugin_screen() ) {
			$classes .= ' salesbin-dark-admin';
		}
		return $classes;
	}

	/**
	 * Main WordPress dashboard screen.
	 *
	 * @return bool
	 */
	public function is_wp_home() {
		if ( ! is_admin() ) {
			return false;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && 'dashboard' === $screen->id;
	}

	/**
	 * Plugin pages except settings.
	 *
	 * @return bool
	 */
	public function is_plugin_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return false;
		}
		return in_array(
			$screen->id,
			array( 'toplevel_page_salesbin', 'salesbin_page_salesbin-notifications', 'salesbin_page_salesbin-settings' ),
			true
		);
	}

	/**
	 * Hide native WP widgets on the home dashboard.
	 *
	 * @return void
	 */
	public function replace_wp_dashboard() {
		if ( ! Salesbin_Capabilities::current_user_can_view() ) {
			return;
		}
		global $wp_meta_boxes;
		$wp_meta_boxes['dashboard'] = array();
	}

	/**
	 * Hide the default WordPress welcome panel.
	 *
	 * @return void
	 */
	public function disable_welcome_panel() {
		if ( Salesbin_Capabilities::current_user_can_view() ) {
			remove_action( 'welcome_panel', 'wp_welcome_panel' );
		}
	}

	/**
	 * Prevent core/plugin notices from printing on the home dashboard.
	 * They are surfaced inside the Woodesh notification drawer instead.
	 *
	 * @return void
	 */
	public function suppress_native_notices() {
		if ( ! $this->is_wp_home() || ! Salesbin_Capabilities::current_user_can_view() ) {
			return;
		}
		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'user_admin_notices' );
		remove_all_actions( 'network_admin_notices' );
	}

	/**
	 * Print Woodesh home layout above the empty dashboard wrap.
	 *
	 * @return void
	 */
	public function render_wp_home() {
		if ( ! $this->is_wp_home() || ! Salesbin_Capabilities::current_user_can_view() ) {
			return;
		}
		if ( ! Salesbin_Plugin::instance()->is_woocommerce_ready() ) {
			include SALESBIN_PATH . 'admin/views/woocommerce-missing.php';
			return;
		}
		$salesbin_layout = 'home';
		include SALESBIN_PATH . 'admin/views/dashboard.php';
	}

	/**
	 * Hide leftover WP chrome.
	 *
	 * @return void
	 */
	public function hide_wp_chrome() {
		if ( ! $this->is_wp_home() && ! $this->is_plugin_screen() ) {
			return;
		}
		echo '<style id="salesbin-wp-chrome">';
		if ( $this->is_plugin_screen() ) {
			echo '.toplevel_page_salesbin #wpcontent,.salesbin_page_salesbin-notifications #wpcontent{padding-left:0;padding-right:0;}';
			echo 'body.rtl.toplevel_page_salesbin #wpcontent,body.rtl.salesbin_page_salesbin-notifications #wpcontent{padding-right:0;}';
		}
		if ( $this->is_wp_home() ) {
			echo 'body.index-php .wrap > h1,body.index-php #welcome-panel,body.index-php #dashboard-widgets-wrap,body.index-php .notice,body.index-php .update-nag,body.index-php .updated,body.index-php .error,body.index-php .woocommerce-message,body.index-php .woocommerce-info,body.index-php .woocommerce-BlankState{display:none !important;}';
		}
		echo '</style>';
	}

	/**
	 * Enqueue assets.
	 *
	 * @param string $hook Hook.
	 * @return void
	 */
	public function enqueue( $hook ) {
		$is_home   = ( 'index.php' === $hook );
		$is_plugin = ( false !== strpos( (string) $hook, 'salesbin' ) && false === strpos( (string) $hook, 'salesbin-settings' ) );
		if ( ! $is_home && ! $is_plugin && 'toplevel_page_salesbin' !== $hook ) {
			return;
		}
		if ( false !== strpos( (string) $hook, 'salesbin-settings' ) ) {
			return;
		}
		if ( $is_home && ! Salesbin_Capabilities::current_user_can_view() ) {
			return;
		}

		wp_enqueue_style( 'salesbin-fonts', SALESBIN_URL . 'assets/css/fonts.css', array(), SALESBIN_VERSION );
		wp_enqueue_style( 'salesbin-dashboard', SALESBIN_URL . 'assets/css/dashboard.css', array( 'salesbin-fonts' ), SALESBIN_VERSION );
		wp_add_inline_style(
			'salesbin-dashboard',
			'.salesbin-app{--sb-font:' . Salesbin_Settings::font_stack( Salesbin_Settings::get( 'admin_font', 'yekanbakh' ) ) . ';}'
		);
		wp_enqueue_script( 'salesbin-theme', SALESBIN_URL . 'assets/js/theme.js', array(), SALESBIN_VERSION, true );
		wp_enqueue_script( 'salesbin-fx', SALESBIN_URL . 'assets/js/effects.js', array(), SALESBIN_VERSION, true );
		wp_enqueue_script( 'salesbin-jalali', SALESBIN_URL . 'assets/js/jalali.js', array(), SALESBIN_VERSION, true );
		wp_enqueue_script( 'salesbin-charts', SALESBIN_URL . 'assets/js/charts.js', array(), SALESBIN_VERSION, true );
		wp_enqueue_script(
			'salesbin-dashboard',
			SALESBIN_URL . 'assets/js/dashboard.js',
			array( 'salesbin-theme', 'salesbin-fx', 'salesbin-jalali', 'salesbin-charts' ),
			SALESBIN_VERSION,
			true
		);
		$config           = $this->js_config();
		$config['layout'] = $is_home ? 'home' : 'full';
		wp_localize_script( 'salesbin-dashboard', 'salesbinApp', $config );
	}

	/**
	 * Theme the whole wp-admin (menu, bar, cards, tables, forms) on every
	 * admin page with the server-printed palette — so leaving the dashboard
	 * doesn't fall back to the default WordPress look.
	 *
	 * @return void
	 */
	public function chrome_enqueue() {
		if ( ! (int) Salesbin_Settings::get( 'admin_chrome_theme', 1 ) ) {
			return;
		}
		if ( ! Salesbin_Capabilities::current_user_can_view() ) {
			return;
		}

		$theme = Salesbin_Settings::get( 'theme', 'woodesh' );
		$mode  = Salesbin_Settings::get( 'mode', 'dark' );

		wp_enqueue_style( 'salesbin-admin-chrome', SALESBIN_URL . 'assets/css/admin-chrome.css', array(), SALESBIN_VERSION );

		// Server-printed palette on the body: no JS needed anywhere in wp-admin.
		$inline = 'body.sb-admin-themed{' . Salesbin_Settings::palette_css( $theme, $mode )
			. 'background:radial-gradient(1100px 460px at 100% -8%, rgba(var(--sb-accent-rgb), .10), transparent 55%), var(--sb-bg) !important;}';
		wp_add_inline_style( 'salesbin-admin-chrome', $inline );

		// Woodesh pages run theme.js with a live mode toggle — the chrome body
		// class would fight it there, so they are excluded from sb-admin-themed
		// via JS-free means: class added only when the page isn't ours.
		add_filter( 'admin_body_class', array( $this, 'chrome_body_class' ), 20 );
	}

	/**
	 * Add the chrome marker class on non-Woodesh admin pages.
	 *
	 * @param string $classes Classes string.
	 * @return string
	 */
	public function chrome_body_class( $classes ) {
		if ( ! $this->is_wp_home() && ! $this->is_plugin_screen() ) {
			$classes .= ' sb-admin-themed';
		}
		return $classes;
	}

	/**
	 * WordPress core/plugin/theme update alerts for the notification drawer.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function wordpress_alerts() {
		$items = array();
		if ( ! current_user_can( 'update_plugins' ) && ! current_user_can( 'update_core' ) && ! current_user_can( 'update_themes' ) ) {
			return $items;
		}

		$updates = function_exists( 'wp_get_update_data' ) ? wp_get_update_data() : array();
		$counts  = isset( $updates['counts'] ) && is_array( $updates['counts'] ) ? $updates['counts'] : array();
		$core    = isset( $counts['wordpress'] ) ? (int) $counts['wordpress'] : 0;
		$plugins = isset( $counts['plugins'] ) ? (int) $counts['plugins'] : 0;
		$themes  = isset( $counts['themes'] ) ? (int) $counts['themes'] : 0;

		if ( $core > 0 ) {
			$items[] = array(
				'id'          => 'wp-core',
				'type'        => 'wp_update',
				'title'       => __( 'به‌روزرسانی وردپرس', 'salesbin' ),
				'description' => __( 'نسخه جدیدی از وردپرس در دسترس است.', 'salesbin' ),
				'link'        => admin_url( 'update-core.php' ),
				'is_read'     => false,
				'created_at'  => '',
			);
		}
		if ( $plugins > 0 ) {
			$items[] = array(
				'id'          => 'wp-plugins',
				'type'        => 'wp_update',
				'title'       => __( 'به‌روزرسانی افزونه‌ها', 'salesbin' ),
				'description' => sprintf(
					/* translators: %d plugin update count */
					_n( '%d افزونه نیاز به به‌روزرسانی دارد.', '%d افزونه نیاز به به‌روزرسانی دارند.', $plugins, 'salesbin' ),
					$plugins
				),
				'link'        => admin_url( 'plugins.php' ),
				'is_read'     => false,
				'created_at'  => '',
			);
		}
		if ( $themes > 0 ) {
			$items[] = array(
				'id'          => 'wp-themes',
				'type'        => 'wp_update',
				'title'       => __( 'به‌روزرسانی قالب‌ها', 'salesbin' ),
				'description' => sprintf(
					/* translators: %d theme update count */
					_n( '%d قالب نیاز به به‌روزرسانی دارد.', '%d قالب نیاز به به‌روزرسانی دارند.', $themes, 'salesbin' ),
					$themes
				),
				'link'        => admin_url( 'themes.php' ),
				'is_read'     => false,
				'created_at'  => '',
			);
		}

		return $items;
	}

	/**
	 * Localized JS config (no hardcoded Persian outside i18n).
	 *
	 * @return array
	 */
	public function js_config() {
		$statuses = array(
			array(
				'slug'  => 'all',
				'label' => __( 'همه', 'salesbin' ),
			),
		);
		foreach ( Salesbin_Helpers::all_order_statuses() as $slug => $label ) {
			$statuses[] = array(
				'slug'  => $slug,
				'label' => $label,
			);
		}

		return array(
			'restUrl'      => esc_url_raw( rest_url( Salesbin_Rest_API::NAMESPACE . '/' ) ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'exportNonce'  => wp_create_nonce( 'salesbin_export' ),
			'exportUrl'    => esc_url_raw( admin_url( 'admin-post.php' ) ),
			'settingsUrl'  => esc_url_raw( admin_url( 'admin.php?page=salesbin-settings' ) ),
			'autoRefresh'  => (int) Salesbin_Settings::get( 'auto_refresh_interval', 0 ),
			'defaultTheme' => Salesbin_Settings::get( 'theme', 'woodesh' ),
			'defaultMode'  => Salesbin_Settings::get( 'mode', 'dark' ),
			'layout'       => 'full',
			'wpAlerts'     => $this->wordpress_alerts(),
			'defaultRange' => Salesbin_Settings::get( 'default_date_range', '30d' ),
			'chartType'    => Salesbin_Settings::get( 'default_chart_type', 'line' ),
			'perPage'      => (int) Salesbin_Settings::get( 'orders_per_page', 10 ),
			'currency'     => Salesbin_Helpers::currency_meta(),
			'statuses'     => $statuses,
			'i18n'         => array(
				'title'            => __( 'وودش', 'salesbin' ),
				'subtitle'         => __( 'داشبورد مانیتورینگ و تحلیل فروش', 'salesbin' ),
				'refresh'          => __( 'به‌روزرسانی', 'salesbin' ),
				'retry'            => __( 'تلاش مجدد', 'salesbin' ),
				'loading'          => __( 'در حال بارگذاری…', 'salesbin' ),
				'error'            => __( 'بارگذاری این بخش با خطا مواجه شد.', 'salesbin' ),
				'emptyOrders'      => __( 'در این بازه سفارشی برای نمایش وجود ندارد.', 'salesbin' ),
				'emptyProducts'    => __( 'در این بازه محصول فروخته‌شده‌ای ثبت نشده است.', 'salesbin' ),
				'emptyCustomers'   => __( 'در این بازه مشتری برتری وجود ندارد.', 'salesbin' ),
				'emptyCats'        => __( 'فروشی بر اساس دسته‌بندی یافت نشد.', 'salesbin' ),
				'emptyPay'         => __( 'داده‌ای برای روش پرداخت وجود ندارد.', 'salesbin' ),
				'emptyStock'       => __( 'محصول کم‌موجودی یافت نشد.', 'salesbin' ),
				'emptyNotes'       => __( 'اعلانی وجود ندارد.', 'salesbin' ),
				'netSales'         => __( 'فروش خالص', 'salesbin' ),
				'grossSales'       => __( 'فروش ناخالص', 'salesbin' ),
				'orders'           => __( 'تعداد سفارشات', 'salesbin' ),
				'customers'        => __( 'تعداد مشتریان', 'salesbin' ),
				'newCustomers'     => __( 'مشتریان جدید', 'salesbin' ),
				'aov'              => __( 'میانگین مبلغ سفارش', 'salesbin' ),
				'items'            => __( 'کالاهای فروخته‌شده', 'salesbin' ),
				'products'         => __( 'محصولات فروش‌رفته', 'salesbin' ),
				'lowStock'         => __( 'محصولات کم‌موجودی', 'salesbin' ),
				'salesChart'       => __( 'نمودار فروش', 'salesbin' ),
				'recentOrders'     => __( 'سفارشات اخیر', 'salesbin' ),
				'topProducts'      => __( 'محصولات پرفروش', 'salesbin' ),
				'topCustomers'     => __( 'مشتریان برتر', 'salesbin' ),
				'byCategory'       => __( 'فروش بر اساس دسته', 'salesbin' ),
				'byPayment'        => __( 'روش‌های پرداخت', 'salesbin' ),
				'heatmap'          => __( 'ساعت‌های اوج خرید', 'salesbin' ),
				'dailyGoal'        => __( 'هدف فروش روزانه', 'salesbin' ),
				'todaySales'       => __( 'فروش امروز', 'salesbin' ),
				'remaining'        => __( 'باقی‌مانده تا هدف', 'salesbin' ),
				'notifications'    => __( 'اعلان‌ها', 'salesbin' ),
				'markRead'         => __( 'خوانده شد', 'salesbin' ),
				'markAll'          => __( 'خواندن همه', 'salesbin' ),
				'exportOrders'     => __( 'خروجی سفارشات CSV', 'salesbin' ),
				'exportProducts'   => __( 'خروجی محصولات CSV', 'salesbin' ),
				'range7'           => __( '۷ روز', 'salesbin' ),
				'range30'          => __( '۳۰ روز', 'salesbin' ),
				'range90'          => __( '۹۰ روز', 'salesbin' ),
				'range1y'          => __( '۱ سال', 'salesbin' ),
				'rangeCustom'      => __( 'بازه دلخواه', 'salesbin' ),
				'apply'            => __( 'اعمال', 'salesbin' ),
				'cancel'           => __( 'انصراف', 'salesbin' ),
				'from'             => __( 'از', 'salesbin' ),
				'to'               => __( 'تا', 'salesbin' ),
				'metricSales'      => __( 'فروش', 'salesbin' ),
				'metricOrders'     => __( 'سفارش', 'salesbin' ),
				'metricAov'        => __( 'میانگین سفارش', 'salesbin' ),
				'chartLine'        => __( 'خطی', 'salesbin' ),
				'chartBar'         => __( 'میله‌ای', 'salesbin' ),
				'sortQty'          => __( 'بر اساس تعداد', 'salesbin' ),
				'sortRevenue'      => __( 'بر اساس مبلغ', 'salesbin' ),
				'orderNumber'      => __( 'شماره سفارش', 'salesbin' ),
				'customer'         => __( 'مشتری', 'salesbin' ),
				'amount'           => __( 'مبلغ', 'salesbin' ),
				'status'           => __( 'وضعیت', 'salesbin' ),
				'date'             => __( 'تاریخ', 'salesbin' ),
				'actions'          => __( 'عملیات', 'salesbin' ),
				'view'             => __( 'مشاهده', 'salesbin' ),
				'prev'             => __( 'قبلی', 'salesbin' ),
				'next'             => __( 'بعدی', 'salesbin' ),
				'new'              => __( 'جدید', 'salesbin' ),
				'dash'             => '—',
				'lastUpdated'      => __( 'آخرین به‌روزرسانی', 'salesbin' ),
				'netHint'          => __( 'فروش کل در داشبورد برابر فروش خالص است: مجموع سفارش‌های معتبر منهای مرجوعی. سفارش‌های لغو شده و ناموفق لحاظ نمی‌شوند.', 'salesbin' ),
				'goalZero'         => __( 'هدف روزانه روی صفر تنظیم شده است.', 'salesbin' ),
				'perPage'          => __( 'در هر صفحه', 'salesbin' ),
				'guest'            => __( 'مهمان', 'salesbin' ),
				'share'            => __( 'سهم', 'salesbin' ),
				'qty'              => __( 'تعداد', 'salesbin' ),
				'avg'              => __( 'میانگین', 'salesbin' ),
				'stock'            => __( 'موجودی', 'salesbin' ),
				'previousPeriod'   => __( 'دوره قبل', 'salesbin' ),
				'theme'            => __( 'تم رنگی', 'salesbin' ),
				'lightMode'        => __( 'حالت روز', 'salesbin' ),
				'darkMode'         => __( 'حالت شب', 'salesbin' ),
			),
		);
	}

	/**
	 * Render full plugin view.
	 *
	 * @return void
	 */
	public function render() {
		$salesbin_layout = 'full';
		include SALESBIN_PATH . 'admin/views/dashboard.php';
	}
}
