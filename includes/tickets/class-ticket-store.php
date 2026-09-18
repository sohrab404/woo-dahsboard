<?php
/**
 * Ticket storage — two custom tables via dbDelta:
 *   {prefix}salesbin_tickets         id, user_id, subject, status, created_at, updated_at
 *   {prefix}salesbin_ticket_replies  id, ticket_id, user_id, is_admin, message, created_at
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Salesbin_Ticket_Store
 */
class Salesbin_Ticket_Store {

	/**
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'salesbin_tickets';
	}

	/**
	 * @return string
	 */
	public static function replies_table() {
		global $wpdb;
		return $wpdb->prefix . 'salesbin_ticket_replies';
	}

	/**
	 * Install/upgrade tables.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$collate = $wpdb->get_charset_collate();
		$tickets = self::table();
		$replies = self::replies_table();

		dbDelta( "CREATE TABLE {$tickets} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			subject varchar(190) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'open',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY status (status),
			KEY updated_at (updated_at)
		) {$collate};" );

		dbDelta( "CREATE TABLE {$replies} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			ticket_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			is_admin tinyint(1) NOT NULL DEFAULT 0,
			message text NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY ticket_id (ticket_id)
		) {$collate};" );
	}

	/**
	 * Create a ticket with its first message.
	 *
	 * @param int    $user_id Author.
	 * @param string $subject Subject.
	 * @param string $message First message.
	 * @return int Ticket id (0 on failure).
	 */
	public static function add_ticket( $user_id, $subject, $message ) {
		global $wpdb;
		$now = current_time( 'mysql' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = $wpdb->insert(
			self::table(),
			array(
				'user_id'    => (int) $user_id,
				'subject'    => sanitize_text_field( $subject ),
				'status'     => 'open',
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);
		if ( ! $ok ) {
			return 0;
		}
		$ticket_id = (int) $wpdb->insert_id;
		self::add_reply( $ticket_id, $user_id, false, $message );
		return $ticket_id;
	}

	/**
	 * Append a reply and keep the ticket status in sync.
	 *
	 * @param int    $ticket_id Ticket.
	 * @param int    $user_id   Author.
	 * @param bool   $is_admin  Admin reply?
	 * @param string $message   Body.
	 * @return bool
	 */
	public static function add_reply( $ticket_id, $user_id, $is_admin, $message ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = $wpdb->insert(
			self::replies_table(),
			array(
				'ticket_id'  => (int) $ticket_id,
				'user_id'    => (int) $user_id,
				'is_admin'   => $is_admin ? 1 : 0,
				'message'    => sanitize_textarea_field( $message ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s', '%s' )
		);
		if ( ! $ok ) {
			return false;
		}
		self::set_status( $ticket_id, $is_admin ? 'answered' : 'open' );
		return true;
	}

	/**
	 * Update ticket status/timestamp.
	 *
	 * @param int    $ticket_id Ticket.
	 * @param string $status    open|answered|closed.
	 * @return void
	 */
	public static function set_status( $ticket_id, $status ) {
		global $wpdb;
		$status = in_array( $status, array( 'open', 'answered', 'closed' ), true ) ? $status : 'open';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			self::table(),
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => (int) $ticket_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Query tickets.
	 *
	 * @param array $args user_id (optional), status (optional), page, per_page.
	 * @return array{items:array,total:int,page:int,pages:int}
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		$page     = max( 1, absint( $args['page'] ?? 1 ) );
		$per_page = max( 1, min( 50, absint( $args['per_page'] ?? 15 ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$where  = '1=1';
		$params = array();
		if ( ! empty( $args['user_id'] ) ) {
			$where   .= ' AND user_id = %d';
			$params[] = (int) $args['user_id'];
		}
		if ( ! empty( $args['status'] ) && in_array( $args['status'], array( 'open', 'answered', 'closed' ), true ) ) {
			$where   .= ' AND status = %s';
			$params[] = $args['status'];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . self::table() . " WHERE {$where}", $params ) );

		$sql    = "SELECT * FROM " . self::table() . " WHERE {$where} ORDER BY updated_at DESC LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$params = array_merge( $params, array( $per_page, $offset ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

		return array(
			'items' => is_array( $rows ) ? $rows : array(),
			'total' => $total,
			'page'  => $page,
			'pages' => (int) ceil( $total / $per_page ),
		);
	}

	/**
	 * One ticket.
	 *
	 * @param int $id Ticket id.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', absint( $id ) ), ARRAY_A );
		return $row ? $row : null;
	}

	/**
	 * Replies of a ticket, oldest first.
	 *
	 * @param int $ticket_id Ticket.
	 * @return array
	 */
	public static function replies( $ticket_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::replies_table() . ' WHERE ticket_id = %d ORDER BY created_at ASC, id ASC', absint( $ticket_id ) ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}
}
