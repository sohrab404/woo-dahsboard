<?php
/**
 * Main plugin orchestrator.
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Salesbin_Plugin
 */
class Salesbin_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Salesbin_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Whether WooCommerce is available.
	 *
	 * @var bool
	 */
	private $woocommerce_ready = false;

	/**
	 * Get singleton.
	 *
	 * @return Salesbin_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Run hooks.
	 *
	 * @return void
	 */
	public function run() {
		$this->woocommerce_ready = $this->is_woocommerce_ready();

		add_action( 'admin_notices', array( $this, 'maybe_woocommerce_notice' ) );
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_filter( 'plugin_action_links_' . SALESBIN_BASENAME, array( $this, 'action_links' ) );

		if ( ! $this->woocommerce_ready ) {
			return;
		}

		Salesbin_Settings::instance()->hooks();
		Salesbin_Dashboard::instance()->hooks();
		Salesbin_Rest_API::instance()->hooks();
		Salesbin_Notifications::instance()->hooks();
		Salesbin_Admin_Bar_Widget::instance()->hooks();
		Salesbin_Export::instance()->hooks();
		Salesbin_Cache::instance()->hooks();
		Salesbin_Login_Module::instance()->hooks();
		Salesbin_Login_Module::instance()->admin_hooks();
		Salesbin_Account_Panel::instance()->hooks();
		Salesbin_Tickets::instance()->hooks();
		Salesbin_Elementor::instance()->hooks();
	}

	/**
	 * Check WooCommerce availability without fatal errors.
	 *
	 * @return bool
	 */
	public function is_woocommerce_ready() {
		return class_exists( 'WooCommerce' ) && function_exists( 'WC' );
	}

	/**
	 * Admin notice when WooCommerce is missing.
	 *
	 * @return void
	 */
	public function maybe_woocommerce_notice() {
		if ( $this->woocommerce_ready ) {
			return;
		}
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'برای استفاده از وودش، نصب و فعال بودن WooCommerce الزامی است.', 'salesbin' );
		echo '</p></div>';
	}

	/**
	 * Register admin menus.
	 *
	 * @return void
	 */
	public function register_menu() {
		$cap = $this->woocommerce_ready ? Salesbin_Capabilities::required() : 'activate_plugins';

		add_menu_page(
			__( 'وودش', 'salesbin' ),
			__( 'وودش', 'salesbin' ),
			$cap,
			'salesbin',
			array( $this, 'render_dashboard_page' ),
			'dashicons-chart-area',
			56
		);

		add_submenu_page(
			'salesbin',
			__( 'داشبورد', 'salesbin' ),
			__( 'داشبورد', 'salesbin' ),
			$cap,
			'salesbin',
			array( $this, 'render_dashboard_page' )
		);

		add_submenu_page(
			'salesbin',
			__( 'اعلان‌ها', 'salesbin' ),
			__( 'اعلان‌ها', 'salesbin' ),
			$cap,
			'salesbin-notifications',
			array( $this, 'render_notifications_page' )
		);

		add_submenu_page(
			'salesbin',
			__( 'تنظیمات', 'salesbin' ),
			__( 'تنظیمات', 'salesbin' ),
			$cap,
			'salesbin-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Render dashboard or missing-WooCommerce screen.
	 *
	 * @return void
	 */
	public function render_dashboard_page() {
		if ( ! $this->guard_page() ) {
			return;
		}
		if ( ! $this->woocommerce_ready ) {
			include SALESBIN_PATH . 'admin/views/woocommerce-missing.php';
			return;
		}
		Salesbin_Dashboard::instance()->render();
	}

	/**
	 * Render notifications page.
	 *
	 * @return void
	 */
	public function render_notifications_page() {
		if ( ! $this->guard_page() ) {
			return;
		}
		if ( ! $this->woocommerce_ready ) {
			include SALESBIN_PATH . 'admin/views/woocommerce-missing.php';
			return;
		}
		include SALESBIN_PATH . 'admin/views/notifications.php';
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! $this->guard_page() ) {
			return;
		}
		if ( ! $this->woocommerce_ready ) {
			include SALESBIN_PATH . 'admin/views/woocommerce-missing.php';
			return;
		}
		Salesbin_Settings::instance()->render_page();
	}

	/**
	 * Capability guard for admin pages.
	 *
	 * @return bool
	 */
	private function guard_page() {
		if ( ! $this->woocommerce_ready ) {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				wp_die( esc_html__( 'شما اجازه دسترسی به این صفحه را ندارید.', 'salesbin' ) );
			}
			return true;
		}
		if ( ! current_user_can( Salesbin_Capabilities::required() ) ) {
			wp_die( esc_html__( 'شما اجازه دسترسی به این صفحه را ندارید.', 'salesbin' ) );
		}
		return true;
	}

	/**
	 * Plugin action links.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function action_links( $links ) {
		$url = admin_url( 'admin.php?page=salesbin' );
		array_unshift(
			$links,
			'<a href="' . esc_url( $url ) . '">' . esc_html__( 'داشبورد', 'salesbin' ) . '</a>'
		);
		return $links;
	}
}
