<?php
/**
 * Sales by product category.
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Salesbin_Categories_Service
 */
class Salesbin_Categories_Service {

	/**
	 * @param array    $range    Range.
	 * @param string[] $statuses Statuses.
	 * @return array
	 */
	public function get( array $range, array $statuses ) {
		return Salesbin_Cache::remember(
			'products',
			array( 'cats', $range['start_gmt'], $range['end_gmt'], $statuses ),
			function () use ( $range, $statuses ) {
				if ( empty( $statuses ) ) {
					$statuses = Salesbin_Helpers::default_sales_statuses();
				}
				$lookup = Salesbin_HPOS::product_lookup_table();
				$rows   = $lookup ? $this->from_lookup( $lookup, $range, $statuses ) : array();

				$total = 0.0;
				foreach ( $rows as $row ) {
					$total += (float) $row['revenue'];
				}
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
	 * @param string   $lookup   Table.
	 * @param array    $range    Range.
	 * @param string[] $statuses Statuses.
	 * @return array
	 */
	private function from_lookup( $lookup, array $range, array $statuses ) {
		global $wpdb;
		$prefixed = Salesbin_Order_Query::prefixed( $statuses );
		$in       = Salesbin_Order_Query::in_placeholders( $prefixed );
		$stats    = Salesbin_HPOS::order_stats_table();
		$term     = $wpdb->term_relationships;
		$tt       = $wpdb->term_taxonomy;
		$terms    = $wpdb->terms;

		if ( $stats ) {
			$sql = "SELECT t.term_id AS id, t.name AS name,
					COALESCE(SUM(l.product_qty), 0) AS qty,
					COALESCE(SUM(l.product_net_revenue), 0) AS revenue
				FROM {$lookup} l
				INNER JOIN {$stats} s ON s.order_id = l.order_id AND s.status IN ($in)
				INNER JOIN {$term} tr ON tr.object_id = l.product_id
				INNER JOIN {$tt} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'product_cat'
				INNER JOIN {$terms} t ON t.term_id = tt.term_id
				WHERE l.date_created >= %s AND l.date_created <= %s
				GROUP BY t.term_id, t.name
				ORDER BY revenue DESC
				LIMIT 30";
			$params = array_merge( $prefixed, array( $range['start_gmt'], $range['end_gmt'] ) );
		} else {
			$sql = "SELECT t.term_id AS id, t.name AS name,
					COALESCE(SUM(l.product_qty), 0) AS qty,
					COALESCE(SUM(l.product_net_revenue), 0) AS revenue
				FROM {$lookup} l
				INNER JOIN {$term} tr ON tr.object_id = l.product_id
				INNER JOIN {$tt} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'product_cat'
				INNER JOIN {$terms} t ON t.term_id = tt.term_id
				WHERE l.date_created >= %s AND l.date_created <= %s
				GROUP BY t.term_id, t.name
				ORDER BY revenue DESC
				LIMIT 30";
			$params = array( $range['start_gmt'], $range['end_gmt'] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$result = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

		$out = array();
		if ( is_array( $result ) ) {
			foreach ( $result as $row ) {
				$out[] = array(
					'id'      => absint( $row['id'] ),
					'name'    => $row['name'],
					'qty'     => (float) $row['qty'],
					'revenue' => (float) $row['revenue'],
				);
			}
		}
		return $out;
	}
}
