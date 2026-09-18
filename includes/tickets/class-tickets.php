<?php
/**
 * Ticketing module for the account panel — optional (settings toggle).
 *
 * - Account endpoint `tickets` (list / new / thread & reply)
 * - Admin page under the Woodesh menu (reply + status)
 * - Woodesh notification on new customer activity
 * - Rewrite rules flush when the toggle changes
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Salesbin_Tickets
 */
class Salesbin_Tickets {

	/**
	 * Instance.
	 *
	 * @var Salesbin_Tickets|null
	 */
	private static $instance = null;

	/**
	 * @return Salesbin_Tickets
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Enabled flag.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return (bool) Salesbin_Settings::get( 'account_tickets_enabled', 0 );
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		// Self-healing install for sites activated before this feature existed.
		add_action( 'admin_init', array( $this, 'maybe_install' ) );

		add_action( 'init', array( $this, 'register_endpoint' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'menu_item' ), 15 );
		add_action( 'woocommerce_account_tickets_endpoint', array( $this, 'render' ) );
		add_action( 'admin_post_salesbin_ticket_new', array( $this, 'handle_new' ) );
		add_action( 'admin_post_salesbin_ticket_reply', array( $this, 'handle_reply' ) );

		add_action( 'admin_menu', array( $this, 'admin_menu' ), 20 );
		add_action( 'admin_post_salesbin_ticket_admin_reply', array( $this, 'handle_admin_reply' ) );
		add_action( 'admin_post_salesbin_ticket_status', array( $this, 'handle_admin_status' ) );
	}

	/**
	 * Install tables once per plugin version.
	 *
	 * @return void
	 */
	public function maybe_install() {
		if ( get_option( 'salesbin_tickets_db' ) === SALESBIN_VERSION ) {
			return;
		}
		Salesbin_Ticket_Store::install();
		update_option( 'salesbin_tickets_db', SALESBIN_VERSION );
	}

	/**
	 * Register the account endpoint.
	 *
	 * @return void
	 */
	public function register_endpoint() {
		add_rewrite_endpoint( 'tickets', EP_PAGES );
	}

	/**
	 * Sidebar entry.
	 *
	 * @param array $items Menu items.
	 * @return array
	 */
	public function menu_item( $items ) {
		$items['tickets'] = __( 'تیکت‌های پشتیبانی', 'salesbin' );
		return $items;
	}

	/**
	 * Endpoint content: list, new form, or thread.
	 *
	 * @param mixed $value Endpoint value (ticket id).
	 * @return void
	 */
	public function render( $value = '' ) {
		$user = wp_get_current_user();
		if ( ! $user->exists() ) {
			return;
		}

		$action_url = admin_url( 'admin-post.php' );
		$view_ticket = absint( $value );
		$flash = '';
		if ( isset( $_GET['sb_ticket'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$flash = 'sent' === sanitize_key( wp_unslash( $_GET['sb_ticket'] ) ) ? __( 'با موفقیت ثبت شد.', 'salesbin' ) : '';
		}

		if ( $view_ticket ) {
			$ticket = Salesbin_Ticket_Store::get( $view_ticket );
			if ( ! $ticket || (int) $ticket['user_id'] !== (int) $user->ID ) {
				echo '<div class="sb-account__dash"><div class="sb-account__card"><div class="sb-account__empty">' . esc_html__( 'تیکت یافت نشد.', 'salesbin' ) . '</div></div></div>';
				return;
			}
			$replies = Salesbin_Ticket_Store::replies( $view_ticket );
			include SALESBIN_PATH . 'admin/views/account-ticket-thread.php';
			return;
		}

		$query = Salesbin_Ticket_Store::query( array( 'user_id' => $user->ID, 'page' => max( 1, absint( get_query_var( 'paged' ) ) ) ) );
		include SALESBIN_PATH . 'admin/views/account-tickets.php';
	}

	/**
	 * New ticket (front form).
	 *
	 * @return void
	 */
	public function handle_new() {
		$user = wp_get_current_user();
		if ( ! $user->exists() || ! $this->is_enabled() ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}
		if ( ! isset( $_POST['salesbin_ticket_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['salesbin_ticket_nonce'] ) ), 'salesbin_ticket' ) ) {
			wp_safe_redirect( wc_get_account_endpoint_url( 'tickets' ) );
			exit;
		}

		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$message = isset( $_POST['message'] ) ? wp_unslash( $_POST['message'] ) : '';
		if ( '' === $subject || '' === trim( $message ) ) {
			wp_safe_redirect( add_query_arg( 'sb_ticket', 'invalid', wc_get_account_endpoint_url( 'tickets' ) ) );
			exit;
		}

		$ticket_id = Salesbin_Ticket_Store::add_ticket( $user->ID, $subject, $message );
		if ( $ticket_id ) {
			Salesbin_Notification_Store::add(
				array(
					'type'        => 'new_ticket',
					'title'       => sprintf( __( 'تیکت جدید #%d', 'salesbin' ), $ticket_id ),
					'description' => $subject,
					'object_id'   => $ticket_id,
					'link'        => admin_url( 'admin.php?page=salesbin-tickets&ticket=' . $ticket_id ),
				)
			);
		}

		wp_safe_redirect( add_query_arg( 'sb_ticket', 'sent', wc_get_account_endpoint_url( 'tickets' ) ) );
		exit;
	}

	/**
	 * Customer reply.
	 *
	 * @return void
	 */
	public function handle_reply() {
		$user = wp_get_current_user();
		$back = wc_get_account_endpoint_url( 'tickets' );
		if ( ! $user->exists() || ! $this->is_enabled() ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}
		if ( ! isset( $_POST['salesbin_ticket_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['salesbin_ticket_nonce'] ) ), 'salesbin_ticket' ) ) {
			wp_safe_redirect( $back );
			exit;
		}
		$ticket_id = absint( $_POST['ticket_id'] ?? 0 );
		$ticket    = Salesbin_Ticket_Store::get( $ticket_id );
		if ( ! $ticket || (int) $ticket['user_id'] !== (int) $user->ID || '' === trim( (string) ( $_POST['message'] ?? '' ) ) ) {
			wp_safe_redirect( $back );
			exit;
		}
		Salesbin_Ticket_Store::add_reply( $ticket_id, $user->ID, false, wp_unslash( $_POST['message'] ) );

		Salesbin_Notification_Store::add(
			array(
				'type'        => 'new_ticket',
				'title'       => sprintf( __( 'پاسخ مشتری به تیکت #%d', 'salesbin' ), $ticket_id ),
				'description' => $ticket['subject'],
				'object_id'   => $ticket_id,
				'link'        => admin_url( 'admin.php?page=salesbin-tickets&ticket=' . $ticket_id ),
			)
		);

		wp_safe_redirect( add_query_arg( 'sb_ticket', 'sent', add_query_arg( 'ticket', $ticket_id, wc_get_account_endpoint_url( 'tickets' ) ) ) );
		exit;
	}

	/**
	 * Admin page.
	 *
	 * @return void
	 */
	public function admin_menu() {
		add_submenu_page(
			'salesbin',
			__( 'تیکت‌ها', 'salesbin' ),
			__( 'تیکت‌ها', 'salesbin' ),
			'manage_woocommerce',
			'salesbin-tickets',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Admin page render.
	 *
	 * @return void
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$view_ticket = isset( $_GET['ticket'] ) ? absint( $_GET['ticket'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status      = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page        = isset( $_GET['tpaged'] ) ? max( 1, absint( $_GET['tpaged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $view_ticket ) {
			$ticket  = Salesbin_Ticket_Store::get( $view_ticket );
			$replies = $ticket ? Salesbin_Ticket_Store::replies( $view_ticket ) : array();
		} else {
			$query = Salesbin_Ticket_Store::query( array( 'status' => $status, 'page' => $page ) );
		}

		$status_labels = array(
			'open'     => __( 'در انتظار بررسی', 'salesbin' ),
			'answered' => __( 'پاسخ داده شد', 'salesbin' ),
			'closed'   => __( 'بسته شده', 'salesbin' ),
		);
		$base_url = admin_url( 'admin.php?page=salesbin-tickets' );
		include SALESBIN_PATH . 'admin/views/admin-tickets.php';
	}

	/**
	 * Admin reply.
	 *
	 * @return void
	 */
	public function handle_admin_reply() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'دسترسی غیرمجاز.', 'salesbin' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'salesbin_ticket_admin' );

		$ticket_id = absint( $_POST['ticket_id'] ?? 0 );
		$ticket    = Salesbin_Ticket_Store::get( $ticket_id );
		if ( $ticket && '' !== trim( (string) ( $_POST['message'] ?? '' ) ) ) {
			Salesbin_Ticket_Store::add_reply( $ticket_id, get_current_user_id(), true, wp_unslash( $_POST['message'] ) );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=salesbin-tickets&ticket=' . $ticket_id ) );
		exit;
	}

	/**
	 * Admin status change.
	 *
	 * @return void
	 */
	public function handle_admin_status() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'دسترسی غیرمجاز.', 'salesbin' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'salesbin_ticket_admin' );

		$ticket_id = absint( $_POST['ticket_id'] ?? 0 );
		$status    = sanitize_key( $_POST['status'] ?? 'open' );
		if ( Salesbin_Ticket_Store::get( $ticket_id ) ) {
			Salesbin_Ticket_Store::set_status( $ticket_id, $status );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=salesbin-tickets&ticket=' . $ticket_id ) );
		exit;
	}
}
