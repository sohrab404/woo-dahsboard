<?php
/**
 * Notifications facade.
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Salesbin_Notifications
 */
class Salesbin_Notifications {

	/**
	 * @var Salesbin_Notifications|null
	 */
	private static $instance = null;

	/**
	 * @return Salesbin_Notifications
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	const CRON_HOOK = 'salesbin_daily_maintenance';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		( new Salesbin_Notification_Hooks() )->hooks();
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( self::CRON_HOOK, array( $this, 'run_daily_maintenance' ) );
		add_action( 'admin_init', array( $this, 'maybe_schedule_maintenance' ) );
	}

	/**
	 * Ensure the daily maintenance cron exists (self-healing after updates).
	 *
	 * @return void
	 */
	public function maybe_schedule_maintenance() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Prune old notifications per retention setting.
	 *
	 * @return void
	 */
	public function run_daily_maintenance() {
		$days = (int) Salesbin_Settings::get( 'notifications_retention_days', 90 );
		$pruned = Salesbin_Notification_Store::prune( $days );
		if ( $pruned > 0 ) {
			Salesbin_Logger::debug( sprintf( 'Pruned %d old notifications.', $pruned ) );
		}
	}

	/**
	 * Enqueue notifications page script.
	 *
	 * @param string $hook Hook.
	 * @return void
	 */
	public function enqueue( $hook ) {
		if ( false === strpos( (string) $hook, 'salesbin-notifications' ) ) {
			return;
		}
		wp_enqueue_style( 'salesbin-dashboard', SALESBIN_URL . 'assets/css/dashboard.css', array(), SALESBIN_VERSION );
		wp_enqueue_script( 'salesbin-notifications', SALESBIN_URL . 'assets/js/notifications.js', array(), SALESBIN_VERSION, true );
		wp_localize_script( 'salesbin-notifications', 'salesbinApp', Salesbin_Dashboard::instance()->js_config() );
	}
}
