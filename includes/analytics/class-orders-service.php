<?php
/**
 * Paginated recent orders via WooCommerce CRUD (HPOS-safe).
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Salesbin_Orders_Service
 */
class Salesbin_Orders_Service {

	/**
	 * @param array $range   Range.
	 * @param array $args    Query args.
	 * @return array
	 */
	public function list_orders( array $range, array $args ) {
		$page     = max( 1, absint( $args['page'] ?? 1 ) );
		$per_page = absint( $args['per_page'] ?? Salesbin_Settings::get( 'orders_per_page', 10 ) );
		if ( ! in_array( $per_page, array( 10, 20, 50, 100 ), true ) ) {
			$per_page = 10;
		}
		$orderby = sanitize_key( $args['orderby'] ?? 'date' );
		$order   = strtoupper( sanitize_key( $args['order'] ?? 'DESC' ) );
		$order   = in_array( $order, array( 'ASC', 'DESC' ), true ) ? $order : 'DESC';
		$allowed_orderby = array( 'date', 'id', 'total' );
		if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
			$orderby = 'date';
		}

		$statuses = Salesbin_Helpers::parse_statuses( $args['status'] ?? array() );
		if ( empty( $statuses ) ) {
			$statuses = array_keys( Salesbin_Helpers::all_order_statuses() );
		}

		$query_args = array(
			'type'         => 'shop_order',
			'status'       => $statuses,
			'limit'        => $per_page,
			'page'         => $page,
			'paginate'     => true,
			'orderby'      => $orderby,
			'order'        => $order,
					'date_created' => $range['start_local'] . '...' . $range['end_local'],
			'return'       => 'objects',
		);

		$result = wc_get_orders( $query_args );
		$orders = array();
		$total  = 0;
		$pages  = 1;

		if ( is_object( $result ) && isset( $result->orders ) ) {
			$list  = $result->orders;
			$total = (int) $result->total;
			$pages = (int) $result->max_num_pages;
		} elseif ( is_array( $result ) ) {
			$list  = $result;
			$total = count( $result );
		} else {
			$list = array();
		}

		$labels = Salesbin_Helpers::all_order_statuses();
		$can_pii = Salesbin_Capabilities::current_user_can_view_pii();

		foreach ( $list as $order ) {
			if ( ! $order instanceof WC_Order ) {
				continue;
			}
			$status = $order->get_status();
			$name   = $order->get_formatted_billing_full_name();
			if ( ! $name ) {
				$name = __( 'مهمان', 'salesbin' );
			}
			$email = $can_pii ? $order->get_billing_email() : '';
			if ( 0 === (int) $order->get_customer_id() && $name ) {
				$name = sprintf(
					/* translators: %s customer name */
					__( '%s (مهمان)', 'salesbin' ),
					$name
				);
			}

			$orders[] = array(
				'id'           => $order->get_id(),
				'number'       => $order->get_order_number(),
				'customer'     => $name,
				'email'        => $email,
				'total'        => (float) $order->get_total(),
				'total_html'   => Salesbin_Helpers::format_money( $order->get_total() ),
				'status'       => $status,
				'status_label' => isset( $labels[ $status ] ) ? $labels[ $status ] : $status,
				'date'         => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'Y-m-d H:i' ) : '',
				'date_gmt'     => $order->get_date_created() ? $order->get_date_created()->date( 'c' ) : '',
				'edit_url'     => $order->get_edit_order_url(),
				'payment'      => $order->get_payment_method_title(),
			);
		}

		return array(
			'orders'    => $orders,
			'page'      => $page,
			'per_page'  => $per_page,
			'total'     => $total,
			'pages'     => max( 1, $pages ),
			'currency'  => Salesbin_Helpers::currency_meta(),
		);
	}
}
