<?php
/**
 * PSR-4-like autoloader for Salesbin classes.
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Salesbin_Autoloader
 */
class Salesbin_Autoloader {

	/**
	 * Map of class names to relative file paths.
	 *
	 * @var array<string,string>
	 */
	private static $map = array(
		'Salesbin_Plugin'                 => 'includes/class-plugin.php',
		'Salesbin_Activator'              => 'includes/class-activator.php',
		'Salesbin_Deactivator'            => 'includes/class-deactivator.php',
		'Salesbin_Capabilities'           => 'includes/class-capabilities.php',
		'Salesbin_Settings'               => 'includes/class-settings.php',
		'Salesbin_Cache'                  => 'includes/class-cache.php',
		'Salesbin_Logger'                 => 'includes/class-logger.php',
		'Salesbin_Helpers'                => 'includes/class-helpers.php',
		'Salesbin_Rest_API'               => 'includes/class-rest-api.php',
		'Salesbin_Dashboard'              => 'includes/class-dashboard.php',
		'Salesbin_Admin_Bar_Widget'       => 'includes/class-admin-bar-widget.php',
		'Salesbin_Export'                 => 'includes/class-export.php',
		'Salesbin_Notifications'          => 'includes/class-notifications.php',
		'Salesbin_Notification_Store'     => 'includes/notifications/class-notification-store.php',
		'Salesbin_Notification_Hooks'     => 'includes/notifications/class-notification-hooks.php',
		'Salesbin_HPOS'                   => 'includes/data/class-hpos.php',
		'Salesbin_Date_Range'             => 'includes/data/class-date-range.php',
		'Salesbin_Order_Query'            => 'includes/data/class-order-query.php',
		'Salesbin_Sales_Calculator'       => 'includes/analytics/class-sales-calculator.php',
		'Salesbin_Stats_Service'          => 'includes/analytics/class-stats-service.php',
		'Salesbin_Chart_Service'          => 'includes/analytics/class-chart-service.php',
		'Salesbin_Orders_Service'         => 'includes/analytics/class-orders-service.php',
		'Salesbin_Products_Service'       => 'includes/analytics/class-products-service.php',
		'Salesbin_Customers_Service'      => 'includes/analytics/class-customers-service.php',
		'Salesbin_Categories_Service'     => 'includes/analytics/class-categories-service.php',
		'Salesbin_Payments_Service'       => 'includes/analytics/class-payments-service.php',
		'Salesbin_Heatmap_Service'        => 'includes/analytics/class-heatmap-service.php',
		'Salesbin_Stock_Service'          => 'includes/analytics/class-stock-service.php',
		'Salesbin_Goal_Service'           => 'includes/analytics/class-goal-service.php',
		'Salesbin_Login_Module'           => 'includes/login/class-login-module.php',
		'Salesbin_Login_Otp'              => 'includes/login/class-login-otp.php',
		'Salesbin_SMS'                    => 'includes/login/class-sms.php',
		'Salesbin_SMS_Provider'           => 'includes/login/interface-sms-provider.php',
		'Salesbin_Account_Panel'          => 'includes/class-account-panel.php',
		'Salesbin_Ticket_Store'           => 'includes/tickets/class-ticket-store.php',
		'Salesbin_Tickets'                => 'includes/tickets/class-tickets.php',
		'Salesbin_Elementor'              => 'includes/elementor/class-elementor.php',
		'Salesbin_Melipayamak'            => 'includes/login/providers/class-melipayamak.php',
		'Salesbin_Phone'                  => 'includes/login/class-phone.php',
	);

	/**
	 * Register the autoloader.
	 *
	 * @return void
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'load' ) );
	}

	/**
	 * Load a class file.
	 *
	 * @param string $class Class name.
	 * @return void
	 */
	public static function load( $class ) {
		if ( ! isset( self::$map[ $class ] ) ) {
			return;
		}

		$file = SALESBIN_PATH . self::$map[ $class ];
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
