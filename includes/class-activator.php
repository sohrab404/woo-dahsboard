<?php
/**
 * Plugin activation.
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Salesbin_Activator
 */
class Salesbin_Activator {

	/**
	 * Run on activation.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		Salesbin_Settings::maybe_seed_defaults();
		Salesbin_Notification_Store::install_table();
		Salesbin_Ticket_Store::install();
		update_option( 'salesbin_db_version', SALESBIN_DB_VERSION );
		Salesbin_Cache::bump_version();

		// The login surface is /my-account/ now — just flush so the legacy
		// endpoint rewrite registers on next load.
		if ( (int) Salesbin_Settings::get( 'login_enabled', 0 ) ) {
			flush_rewrite_rules( false );
			update_option( 'salesbin_login_flushed', 1, false );
		}
	}
}
