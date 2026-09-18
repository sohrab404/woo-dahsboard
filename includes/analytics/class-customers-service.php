<?php
/**
 * Top customers. Guests grouped by billing email.
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Salesbin_Customers_Service
 */
class Salesbin_Customers_Service {

	/**
	 * @param array    $range    Range.
	 * @param string[] $statuses Statuses.
	 * @param int      $limit    Limit.
	 * @return array
	 */
	public function get( array $range, array $statuses, $limit = 10 ) {
		$limit = max( 1, min( 50, absint( $limit ) ) );
		return Salesbin_Cache::remember(
			'customers',
			array( $range['start_gmt'], $range['end_gmt'], $statuses, $limit ),
			function () use ( $range, $statuses, $limit ) {
				if ( empty( $statuses ) ) {
					$statuses = Salesbin_Helpers::default_sales_statuses();
				}

				$query = wc_get_orders(
					array(
						'type'         => 'shop_order',
						'status'       => $statuses,
						'limit'        => 500,
						'date_created' => $range['start_local'] . '...' . $range['end_local'],
						'return'       => 'objects',
						'orderby'      => 'date',
						'order'        => 'DESC',
					)
				);

				$orders = is_array( $query ) ? $query : array();
				$groups = array();

				foreach ( $orders as $order ) {
					if ( ! $order instanceof WC_Order ) {
						continue;
					}
					$cid   = (int) $order->get_customer_id();
					$email = strtolower( (string) $order->get_billing_email() );
					$key   = $cid > 0 ? 'u:' . $cid : 'g:' . ( $email ? $email : 'order:' . $order->get_id() );
					if ( ! isset( $groups[ $key ] ) ) {
						$name = $order->get_formatted_billing_full_name();
						if ( ! $name ) {
							$name = $cid > 0 ? __( 'مشتری', 'salesbin' ) : __( 'مهمان', 'salesbin' );
						}
						$groups[ $key ] = array(
							'id'       => $cid,
							'guest'    => $cid <= 0,
							'name'     => $name,
							'email'    => Salesbin_Capabilities::current_user_can_view_pii() ? $email : '',
							'orders'   => 0,
							'revenue'  => 0.0,
						);
					}
					$groups[ $key ]['orders']++;
					$groups[ $key ]['revenue'] += (float) $order->get_total();
				}

				$list = array_values( $groups );
				usort(
					$list,
					function ( $a, $b ) {
						return $b['revenue'] <=> $a['revenue'];
					}
				);
				$list = array_slice( $list, 0, $limit );

				foreach ( $list as &$row ) {
					$row['aov']          = $row['orders'] > 0 ? round( $row['revenue'] / $row['orders'], 4 ) : 0.0;
					$row['revenue_html'] = Salesbin_Helpers::format_money( $row['revenue'] );
					$row['aov_html']     = Salesbin_Helpers::format_money( $row['aov'] );
				}
				unset( $row );

				return array(
					'items'    => $list,
					'note'     => __( 'برای عملکرد در فروشگاه‌های بسیار بزرگ، فقط ۵۰۰ سفارش اخیر بازه در رتبه‌بندی مشتریان لحاظ می‌شود.', 'salesbin' ),
					'currency' => Salesbin_Helpers::currency_meta(),
				);
			}
		);
	}
}
