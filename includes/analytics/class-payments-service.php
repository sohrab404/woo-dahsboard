<?php
/**
 * Payment gateway analytics. Titles come from WooCommerce, not hardcoded.
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Salesbin_Payments_Service
 */
class Salesbin_Payments_Service {

	/**
	 * @param array    $range    Range.
	 * @param string[] $statuses Statuses.
	 * @return array
	 */
	public function get( array $range, array $statuses ) {
		return Salesbin_Cache::remember(
			'sales',
			array( 'pay', $range['start_gmt'], $range['end_gmt'], $statuses ),
			function () use ( $range, $statuses ) {
				if ( empty( $statuses ) ) {
					$statuses = Salesbin_Helpers::default_sales_statuses();
				}
				$rows = $this->query( $range, $statuses );
				$titles = $this->gateway_titles();

				$total = 0.0;
				foreach ( $rows as &$row ) {
					$id = (string) $row['method'];
					$row['label'] = isset( $titles[ $id ] ) && $titles[ $id ] ? $titles[ $id ] : ( $id ? $id : __( 'نامشخص', 'salesbin' ) );
					$total += (float) $row['revenue'];
				}
				unset( $row );

				foreach ( $rows as &$row ) {
					$row['share']        = $total > 0 ? round( ( (float) $row['revenue'] / $total ) * 100, 1 ) : 0.0;
					$row['revenue_html'] = Salesbin_Helpers::format_money( $row['revenue'] );
				}
				unset( $row );

				return array(
					'items'    => $rows,
					'currency' => Salesbin_Helpers::currency_meta(),
				);
			}
		);
	}

	/**
	 * Gateway id => title from WC.
	 *
	 * @return array<string,string>
	 */
	private function gateway_titles() {
		$titles = array();
		if ( function_exists( 'WC' ) && WC()->payment_gateways() ) {
			foreach ( WC()->payment_gateways()->payment_gateways() as $id => $gateway ) {
				$titles[ $id ] = $gateway->get_title();
			}
		}
		return $titles;
	}

	/**
	 * @param array    $range    Range.
	 * @param string[] $statuses Statuses.
	 * @return array
	 */
	private function query( array $range, array $statuses ) {
		global $wpdb;
		$prefixed = Salesbin_Order_Query::prefixed( $statuses );
		$in       = Salesbin_Order_Query::in_placeholders( $prefixed );

		if ( Salesbin_HPOS::enabled() ) {
			$table = Salesbin_HPOS::orders_table();
			$sql   = "SELECT payment_method AS method,
					COUNT(*) AS orders,
					COALESCE(SUM(total_amount), 0) AS revenue
				FROM {$table}
				WHERE type = 'shop_order'
				AND status IN ($in)
				AND date_created_gmt >= %s AND date_created_gmt <= %s
				GROUP BY payment_method
				ORDER BY revenue DESC";
			$params = array_merge( $prefixed, array( $range['start_gmt'], $range['end_gmt'] ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		} else {
			$sql    = "SELECT method.meta_value AS method,
					COUNT(p.ID) AS orders,
					COALESCE(SUM(CAST(totals.meta_value AS DECIMAL(18,4))), 0) AS revenue
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} totals ON totals.post_id = p.ID AND totals.meta_key = '_order_total'
				LEFT JOIN {$wpdb->postmeta} method ON method.post_id = p.ID AND method.meta_key = '_payment_method'
				WHERE p.post_type = 'shop_order'
				AND p.post_status IN ($in)
				AND p.post_date_gmt >= %s AND p.post_date_gmt <= %s
				GROUP BY method.meta_value
				ORDER BY revenue DESC";
			$params = array_merge( $prefixed, array( $range['start_gmt'], $range['end_gmt'] ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		}

		$out = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$out[] = array(
					'method'  => (string) $row['method'],
					'orders'  => (int) $row['orders'],
					'revenue' => (float) $row['revenue'],
				);
			}
		}
		return $out;
	}
}
