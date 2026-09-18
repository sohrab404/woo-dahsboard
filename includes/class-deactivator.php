<?php
/**
 * Plugin deactivation.
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Salesbin_Deactivator
 */
class Salesbin_Deactivator {

	/**
	 * Run on deactivation. Settings and notifications are retained.
	 *
	 * @return void
	 */
	public static function deactivate() {
		Salesbin_Cache::bump_version();
		wp_clear_scheduled_hook( Salesbin_Notifications::CRON_HOOK );
	}
}
