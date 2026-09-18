<?php
/**
 * Uninstall handler. Data is removed only when the user opted in.
 *
 * @package Salesbin
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'salesbin_settings', array() );
$purge    = ! empty( $settings['delete_data_on_uninstall'] );

if ( ! $purge ) {
	return;
}

global $wpdb;

// Always drop the maintenance cron, even when data is kept.
wp_clear_scheduled_hook( 'salesbin_daily_maintenance' );

delete_option( 'salesbin_settings' );
delete_option( 'salesbin_db_version' );
delete_option( 'salesbin_cache_version' );
delete_option( 'salesbin_login_flushed' );
delete_option( 'salesbin_tickets_db' );

// Remove the auto-created login page when opted in.
$settings = is_array( $settings ) ? $settings : array();
if ( ! empty( $settings['login_page_id'] ) ) {
	wp_delete_post( (int) $settings['login_page_id'], true );
}

$table = $wpdb->prefix . 'salesbin_notifications';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

$ticket_table = $wpdb->prefix . 'salesbin_tickets';
$reply_table  = $wpdb->prefix . 'salesbin_ticket_replies';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
$wpdb->query( "DROP TABLE IF EXISTS {$ticket_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
$wpdb->query( "DROP TABLE IF EXISTS {$reply_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

$like = $wpdb->esc_like( '_transient_salesbin_' ) . '%';
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

$like_timeout = $wpdb->esc_like( '_transient_timeout_salesbin_' ) . '%';
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like_timeout ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
