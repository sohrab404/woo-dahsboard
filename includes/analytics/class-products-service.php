<?php
/**
 * Top products.
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Salesbin_Products_Service
 */
class Salesbin_Products_Service {

	/**
	 * @param array    $range    Range.
	 * @param string[] $statuses Statuses.
	 * @param string   $sort     qty|revenue.
	 * @param int      $limit    Limit.
	 * @return array
	 */
	public function get( array $range, array $statuses, $sort = 'revenue', $limit = 10 ) {
		$sort  = 'qty' === $sort ? 'qty' : 'revenue';
		$limit = max( 1, min( 50, absint( $limit ) ) );

		return Salesbin_Cache::remember(
			'products',
			array( $range['start_gmt'], $range['end_gmt'], $statuses, $sort, $limit ),
			function () use ( $range, $statuses, $sort, $limit ) {
				if ( empty( $statuses ) ) {
					$statuses = Salesbin_Helpers::default_sales_statuses();
				}
				$lookup = Salesbin_HPOS::product_lookup_table();
				if ( $lookup ) {
					$rows = $this->from_lookup( $lookup, $range, $statuses, $sort, $limit );
				} else {
					$rows = $this->from_items( $range, $statuses, $sort, $limit );
				}

				$total_rev = 0.0;
				foreach ( $rows as $row ) {
					$total_rev += (float) $row['revenue'];
				}
				foreach ( $rows as &$row ) {
					$row['share'] = $total_rev > 0 ? round( ( (float) $row['revenue'] / $total_rev ) * 100, 1 ) : 0.0;
					$row['revenue_html'] = Salesbin_Helpers::format_money( $row['revenue'] );
				}
				unset( $row );

				return array(
					'items'    => $rows,
					'sort'     => $sort,
					'currency' => Salesbin_Helpers::currency_meta(),
				);
			}
		);
	}

	/**
	 * @param string   $lookup   Table.
	 * @param array    $range    Range.
	 * @param string[] $statuses Statuses.
	 * @param string   $sort     Sort.
	 * @param int      $limit    Limit.
	 * @return array
	 */
	private function from_lookup( $lookup, array $range, array $statuses, $sort, $limit ) {
		global $wpdb;
		$prefixed = Salesbin_Order_Query::prefixed( $statuses );
		$in       = Salesbin_Order_Query::in_placeholders( $prefixed );
		$order_by = 'qty' === $sort ? 'qty DESC' : 'revenue DESC';
		$stats    = Salesbin_HPOS::order_stats_table();

		if ( $stats ) {
			$sql = "SELECT l.product_id AS product_id,
					COALESCE(SUM(l.product_qty), 0) AS qty,
					COALESCE(SUM(l.product_net_revenue), 0) AS revenue
				FROM {$lookup} l
				INNER JOIN {$stats} s ON s.order_id = l.order_id
				WHERE l.date_created >= %s AND l.date_created <= %s
				AND s.status IN ($in)
				GROUP BY l.product_id
				ORDER BY {$order_by}
				LIMIT %d";
			$params = array_merge( array( $range['start_gmt'], $range['end_gmt'] ), $prefixed, array( $limit ) );
		} else {
			$sql    = "SELECT product_id,
					COALESCE(SUM(product_qty), 0) AS qty,
					COALESCE(SUM(product_net_revenue), 0) AS revenue
				FROM {$lookup}
				WHERE date_created >= %s AND date_created <= %s
				GROUP BY product_id
				ORDER BY {$order_by}
				LIMIT %d";
			$params = array( $range['start_gmt'], $range['end_gmt'], $limit );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		return $this->hydrate_products( is_array( $rows ) ? $rows : array() );
	}

	/**
	 * Fallback via order items + HPOS/posts join.
	 *
	 * @param array    $range    Range.
	 * @param string[] $statuses Statuses.
	 * @param string   $sort     Sort.
	 * @param int      $limit    Limit.
	 * @return array
	 */
	private function from_items( array $range, array $statuses, $sort, $limit ) {
		global $wpdb;
		$prefixed = Salesbin_Order_Query::prefixed( $statuses );
		$in       = Salesbin_Order_Query::in_placeholders( $prefixed );
		$order_by = 'qty' === $sort ? 'qty DESC' : 'revenue DESC';

		if ( Salesbin_HPOS::enabled() ) {
			$orders = Salesbin_HPOS::orders_table();
			$sql    = "SELECT CAST(pid.meta_value AS UNSIGNED) AS product_id,
					COALESCE(SUM(CAST(qty.meta_value AS DECIMAL(18,4))), 0) AS qty,
					COALESCE(SUM(CAST(total.meta_value AS DECIMAL(18,4))), 0) AS revenue
				FROM {$wpdb->prefix}woocommerce_order_items items
				INNER JOIN {$orders} o ON o.id = items.order_id
				INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta pid ON pid.order_item_id = items.order_item_id AND pid.meta_key = '_product_id'
				INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta qty ON qty.order_item_id = items.order_item_id AND qty.meta_key = '_qty'
				INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta total ON total.order_item_id = items.order_item_id AND total.meta_key = '_line_total'
				WHERE items.order_item_type = 'line_item'
				AND o.type = 'shop_order'
				AND o.status IN ($in)
				AND o.date_created_gmt >= %s AND o.date_created_gmt <= %s
				GROUP BY product_id
				ORDER BY {$order_by}
				LIMIT %d";
			$params = array_merge( $prefixed, array( $range['start_gmt'], $range['end_gmt'], $limit ) );
		} else {
			$sql    = "SELECT CAST(pid.meta_value AS UNSIGNED) AS product_id,
					COALESCE(SUM(CAST(qty.meta_value AS DECIMAL(18,4))), 0) AS qty,
					COALESCE(SUM(CAST(total.meta_value AS DECIMAL(18,4))), 0) AS revenue
				FROM {$wpdb->prefix}woocommerce_order_items items
				INNER JOIN {$wpdb->posts} p ON p.ID = items.order_id
				INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta pid ON pid.order_item_id = items.order_item_id AND pid.meta_key = '_product_id'
				INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta qty ON qty.order_item_id = items.order_item_id AND qty.meta_key = '_qty'
				INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta total ON total.order_item_id = items.order_item_id AND total.meta_key = '_line_total'
				WHERE items.order_item_type = 'line_item'
				AND p.post_type = 'shop_order'
				AND p.post_status IN ($in)
				AND p.post_date_gmt >= %s AND p.post_date_gmt <= %s
				GROUP BY product_id
				ORDER BY {$order_by}
				LIMIT %d";
			$params = array_merge( $prefixed, array( $range['start_gmt'], $range['end_gmt'], $limit ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		return $this->hydrate_products( is_array( $rows ) ? $rows : array() );
	}

	/**
	 * @param array $rows Rows.
	 * @return array
	 */
	private function hydrate_products( array $rows ) {
		$out = array();
		foreach ( $rows as $row ) {
			$id   = absint( $row['product_id'] );
			$prod = $id ? wc_get_product( $id ) : false;
			$out[] = array(
				'id'      => $id,
				'name'    => $prod ? $prod->get_name() : __( '(حذف‌شده)', 'salesbin' ),
				'qty'     => (float) $row['qty'],
				'revenue' => (float) $row['revenue'],
			);
		}
		return $out;
	}
}
