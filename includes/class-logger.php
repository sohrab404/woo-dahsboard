<?php
/**
 * Debug logger. Never logs customer PII or order payloads.
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Salesbin_Logger
 */
class Salesbin_Logger {

	/**
	 * Write a debug message when enabled.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	public static function debug( $message ) {
		if ( ! self::enabled() ) {
			return;
		}
		$message = is_string( $message ) ? $message : '';
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[Salesbin] ' . $message );
		}
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->debug( $message, array( 'source' => 'salesbin' ) );
		}
	}

	/**
	 * @return bool
	 */
	public static function enabled() {
		$setting = (int) Salesbin_Settings::get( 'debug_logging', 0 );
		return ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || 1 === $setting;
	}
}
