<?php
/**
 * Hour-of-day heatmap using WordPress timezone (not UTC mixed with local).
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Salesbin_Heatmap_Service
 */
class Salesbin_Heatmap_Service {

	/**
	 * @param array    $range    Range.
	 * @param string[] $statuses Statuses.
	 * @param string   $metric   orders|sales.
	 * @return array
	 */
	public function get( array $range, array $statuses, $metric = 'orders' ) {
		$metric = 'sales' === $metric ? 'sales' : 'orders';
		return Salesbin_Cache::remember(
			'sales',
			array( 'heat', $range['start_gmt'], $range['end_gmt'], $statuses, $metric ),
			function () use ( $range, $statuses, $metric ) {
				if ( empty( $statuses ) ) {
					$statuses = Salesbin_Helpers::default_sales_statuses();
				}
				$hours = $this->query_hours( $range, $statuses );
				$max   = 0;
				foreach ( $hours as $h ) {
					$val = 'sales' === $metric ? $h['sales'] : $h['orders'];
					if ( $val > $max ) {
						$max = $val;
					}
				}
				$points = array();
				for ( $i = 0; $i < 24; $i++ ) {
					$row    = isset( $hours[ $i ] ) ? $hours[ $i ] : array( 'orders' => 0, 'sales' => 0.0 );
					$value  = 'sales' === $metric ? (float) $row['sales'] : (int) $row['orders'];
					$points[] = array(
						'hour'       => $i,
						'label'      => sprintf( '%02d:00', $i ),
						'orders'     => (int) $row['orders'],
						'sales'      => (float) $row['sales'],
						'sales_html' => Salesbin_Helpers::format_money( $row['sales'] ),
						'intensity'  => $max > 0 ? round( $value / $max, 4 ) : 0,
					);
				}
				return array(
					'metric'   => $metric,
					'points'   => $points,
					'currency' => Salesbin_Helpers::currency_meta(),
				);
			}
		);
	}

	/**
	 * @param array    $range    Range.
	 * @param string[] $statuses Statuses.
	 * @return array<int,array{orders:int,sales:float}>
	 */
	private function query_hours( array $range, array $statuses ) {
		global $wpdb;
		$offset   = (int) Salesbin_Helpers::gmt_offset_seconds( $range['start_gmt'] );
		$prefixed = Salesbin_Order_Query::prefixed( $statuses );
		$in       = Salesbin_Order_Query::in_placeholders( $prefixed );
		$hour_sql = "HOUR(DATE_ADD(%COL%, INTERVAL {$offset} SECOND))";
		$stats    = Salesbin_HPOS::order_stats_table();

		if ( $stats ) {
			$col  = str_replace( '%COL%', 'date_created', $hour_sql );
			$sql  = "SELECT {$col} AS h,
					COUNT(DISTINCT CASE WHEN parent_id = 0 THEN order_id END) AS orders,
					COALESCE(SUM(net_total), 0) AS sales
				FROM {$stats}
				WHERE date_created >= %s AND date_created <= %s
				AND status IN ($in)
				GROUP BY h";
			$params = array_merge( array( $range['start_gmt'], $range['end_gmt'] ), $prefixed );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		} elseif ( Salesbin_HPOS::enabled() ) {
			$table = Salesbin_HPOS::orders_table();
			$col   = str_replace( '%COL%', 'date_created_gmt', $hour_sql );
			// Refund rows carry negative totals; including them yields net sales per hour.
			$sql   = "SELECT {$col} AS h,
					COUNT(CASE WHEN type = 'shop_order' THEN 1 END) AS orders,
					COALESCE(SUM(total_amount), 0) AS sales
				FROM {$table}
				WHERE (type = 'shop_order_refund' OR (type = 'shop_order' AND status IN ($in)))
				AND date_created_gmt >= %s AND date_created_gmt <= %s
				GROUP BY h";
			$params = array_merge( $prefixed, array( $range['start_gmt'], $range['end_gmt'] ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		} else {
			$col = str_replace( '%COL%', 'p.post_date_gmt', $hour_sql );
			$sql = "SELECT {$col} AS h, COUNT(p.ID) AS orders, COALESCE(SUM(CAST(totals.meta_value AS DECIMAL(18,4))), 0) AS sales
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} totals ON totals.post_id = p.ID AND totals.meta_key = '_order_total'
				WHERE p.post_type = 'shop_order'
				AND p.post_status IN ($in)
				AND p.post_date_gmt >= %s AND p.post_date_gmt <= %s
				GROUP BY h";
			$params = array_merge( $prefixed, array( $range['start_gmt'], $range['end_gmt'] ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

			$refund_sql = "SELECT {$col} AS h, COALESCE(SUM(CAST(m.meta_value AS DECIMAL(18,4))), 0) AS refund
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_refund_amount'
				WHERE p.post_type = 'shop_order_refund'
				AND p.post_date_gmt >= %s AND p.post_date_gmt <= %s
				GROUP BY h";
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$refunds = $wpdb->get_results( $wpdb->prepare( $refund_sql, $range['start_gmt'], $range['end_gmt'] ), ARRAY_A );

			$refund_map = array();
			if ( is_array( $refunds ) ) {
				foreach ( $refunds as $row ) {
					$refund_map[ (int) $row['h'] ] = (float) $row['refund'];
				}
			}
			if ( is_array( $rows ) ) {
				foreach ( $rows as &$row ) {
					$h = (int) $row['h'];
					if ( isset( $refund_map[ $h ] ) ) {
						$row['sales'] = (float) $row['sales'] - abs( $refund_map[ $h ] );
					}
				}
				unset( $row );
			}
		}

		$map = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$h = (int) $row['h'];
				if ( $h < 0 || $h > 23 ) {
					continue;
				}
				$map[ $h ] = array(
					'orders' => (int) $row['orders'],
					'sales'  => (float) $row['sales'],
				);
			}
		}
		return $map;
	}
}
