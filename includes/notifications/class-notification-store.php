<?php
/**
 * Notification custom table.
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Salesbin_Notification_Store
 */
class Salesbin_Notification_Store {

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'salesbin_notifications';
	}

	/**
	 * Install via dbDelta.
	 *
	 * @return void
	 */
	public static function install_table() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$collate = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			type varchar(50) NOT NULL,
			title varchar(255) NOT NULL,
			description text NULL,
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			link varchar(500) NOT NULL DEFAULT '',
			is_read tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY type (type),
			KEY is_read (is_read),
			KEY created_at (created_at),
			KEY object_type (object_id, type)
		) {$collate};";
		dbDelta( $sql );
	}

	/**
	 * Insert if not duplicate for object+type recently.
	 *
	 * @param array $data Data.
	 * @return int
	 */
	public static function add( array $data ) {
		global $wpdb;
		$type = sanitize_key( $data['type'] ?? '' );
		if ( ! $type ) {
			return 0;
		}

		$object_id = absint( $data['object_id'] ?? 0 );
		if ( $object_id && self::exists_recent( $type, $object_id ) ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = $wpdb->insert(
			self::table(),
			array(
				'type'        => $type,
				'title'       => sanitize_text_field( $data['title'] ?? '' ),
				'description' => sanitize_textarea_field( $data['description'] ?? '' ),
				'object_id'   => $object_id,
				'link'        => esc_url_raw( $data['link'] ?? '' ),
				'is_read'     => 0,
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%d', '%s', '%d', '%s' )
		);

		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Avoid duplicate notifications for same object/type within 24h.
	 *
	 * @param string $type      Type.
	 * @param int    $object_id Object.
	 * @return bool
	 */
	public static function exists_recent( $type, $object_id ) {
		global $wpdb;
		$table = self::table();
		$since = wp_date( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS, wp_timezone() );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE type = %s AND object_id = %d AND created_at >= %s LIMIT 1",
				$type,
				$object_id,
				$since
			)
		);
		return ! empty( $id );
	}

	/**
	 * Query notifications.
	 *
	 * @param array $args Args.
	 * @return array
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		$table    = self::table();
		$page     = max( 1, absint( $args['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, absint( $args['per_page'] ?? 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;
		$unread   = isset( $args['unread'] ) ? (int) $args['unread'] : -1;

		$where  = '1=1';
		$params = array();
		if ( 1 === $unread ) {
			$where .= ' AND is_read = 0';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$params[] = $per_page;
		$params[] = $offset;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

		$items = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$items[] = self::shape( $row );
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$unread_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE is_read = 0" );

		return array(
			'items'        => $items,
			'total'        => $total,
			'page'         => $page,
			'per_page'     => $per_page,
			'unread_count' => $unread_count,
		);
	}

	/**
	 * @param array $row Row.
	 * @return array
	 */
	public static function shape( array $row ) {
		return array(
			'id'          => absint( $row['id'] ),
			'type'        => $row['type'],
			'title'       => $row['title'],
			'description' => $row['description'],
			'object_id'   => absint( $row['object_id'] ),
			'link'        => $row['link'],
			'is_read'     => (int) $row['is_read'] === 1,
			'created_at'  => $row['created_at'],
		);
	}

	/**
	 * Mark one as read.
	 *
	 * @param int $id ID.
	 * @return bool
	 */
	public static function mark_read( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->update( self::table(), array( 'is_read' => 1 ), array( 'id' => absint( $id ) ), array( '%d' ), array( '%d' ) );
	}

	/**
	 * Mark all read.
	 *
	 * @return int
	 */
	public static function mark_all_read() {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "UPDATE {$table} SET is_read = 1 WHERE is_read = 0" );
		return (int) $wpdb->rows_affected;
	}

	/**
	 * Delete notifications older than the retention window.
	 *
	 * @param int $days Retention in days.
	 * @return int Rows deleted.
	 */
	public static function prune( $days = 90 ) {
		global $wpdb;
		$days = max( 7, (int) $days );
		$since = wp_date( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ), wp_timezone() );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table() . ' WHERE created_at < %s', $since ) );
		return (int) $deleted;
	}
}
