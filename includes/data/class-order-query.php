<?php
/**
 * HPOS-aware order SQL fragments for analytics.
 *
 * Metrics definitions (documented):
 * - Gross sales: SUM(order total) for sales statuses, parent orders only.
 * - Net sales: gross minus refund amounts attributed to those orders.
 * - Orders: count of parent shop orders in the status set.
 * - AOV: net sales / orders (0 if no orders).
 *
 * Direct SQL is used only for aggregations on large catalogs.
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Salesbin_Order_Query
 */
class Salesbin_Order_Query {

	/**
	 * Placeholders for IN list.
	 *
	 * @param string[] $items Values.
	 * @return string
	 */
	public static function in_placeholders( array $items ) {
		$count = count( $items );
		if ( $count < 1 ) {
			return '%s';
		}
		return implode( ',', array_fill( 0, $count, '%s' ) );
	}

	/**
	 * Prefix statuses with wc- for posts / stats tables that store it that way.
	 *
	 * @param string[] $statuses Statuses.
	 * @return string[]
	 */
	public static function prefixed( array $statuses ) {
		$out = array();
		foreach ( $statuses as $status ) {
			$out[] = 0 === strpos( $status, 'wc-' ) ? $status : 'wc-' . $status;
		}
		return $out;
	}

	/**
	 * Aggregate sales/orders from the fastest available store.
	 *
	 * @param string   $start_gmt GMT start.
	 * @param string   $end_gmt   GMT end.
	 * @param string[] $statuses  Unprefixed statuses. Empty = default sales statuses.
	 * @return array{orders:int,gross:float,net:float,items:int,customers:int,new_customers:int,products:int,aov:float}
	 */
	public static function aggregate( $start_gmt, $end_gmt, array $statuses = array() ) {
		if ( empty( $statuses ) ) {
			$statuses = Salesbin_Helpers::default_sales_statuses();
		}

		$stats_table = Salesbin_HPOS::order_stats_table();
		if ( $stats_table ) {
			return self::aggregate_from_stats( $stats_table, $start_gmt, $end_gmt, $statuses );
		}
		if ( Salesbin_HPOS::enabled() ) {
			return self::aggregate_from_hpos( $start_gmt, $end_gmt, $statuses );
		}
		return self::aggregate_from_posts( $start_gmt, $end_gmt, $statuses );
	}

	/**
	 * @param string   $table     Stats table.
	 * @param string   $start_gmt GMT.
	 * @param string   $end_gmt   GMT.
	 * @param string[] $statuses  Statuses.
	 * @return array
	 */
	private static function aggregate_from_stats( $table, $start_gmt, $end_gmt, array $statuses ) {
		global $wpdb;
		$prefixed = self::prefixed( $statuses );
		$in       = self::in_placeholders( $prefixed );
		$sql      = "SELECT
				COUNT(DISTINCT CASE WHEN parent_id = 0 THEN order_id END) AS order_count,
				COALESCE(SUM(CASE WHEN parent_id = 0 THEN total_sales ELSE 0 END), 0) AS gross_sales,
				COALESCE(SUM(net_total), 0) AS net_sales,
				COALESCE(SUM(CASE WHEN parent_id = 0 THEN num_items_sold ELSE 0 END), 0) AS items_sold,
				COUNT(DISTINCT CASE WHEN parent_id = 0 AND customer_id > 0 THEN customer_id END) AS customers
			FROM {$table}
			WHERE date_created >= %s AND date_created <= %s
			AND status IN ($in)";

		$params = array_merge( array( $start_gmt, $end_gmt ), $prefixed );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( $sql, $params ), ARRAY_A );
		return self::normalize_aggregate_row( $row, $start_gmt, $end_gmt, $statuses );
	}

	/**
	 * HPOS aggregation.
	 *
	 * @param string   $start_gmt Start.
	 * @param string   $end_gmt   End.
	 * @param string[] $statuses  Statuses.
	 * @return array
	 */
	private static function aggregate_from_hpos( $start_gmt, $end_gmt, array $statuses ) {
		global $wpdb;
		$table    = Salesbin_HPOS::orders_table();
		$prefixed = self::prefixed( $statuses );
		$in       = self::in_placeholders( $prefixed );

		$sql = "SELECT
				COUNT(*) AS order_count,
				COALESCE(SUM(total_amount), 0) AS gross_sales,
				COUNT(DISTINCT CASE WHEN customer_id > 0 THEN customer_id END) AS customers
			FROM {$table}
			WHERE type = 'shop_order'
			AND status IN ($in)
			AND date_created_gmt >= %s AND date_created_gmt <= %s";

		$params = array_merge( $prefixed, array( $start_gmt, $end_gmt ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( $sql, $params ), ARRAY_A );

		$refund_sql = "SELECT COALESCE(SUM(total_amount), 0) FROM {$table}
			WHERE type = 'shop_order_refund'
			AND date_created_gmt >= %s AND date_created_gmt <= %s";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$refunds = (float) $wpdb->get_var( $wpdb->prepare( $refund_sql, $start_gmt, $end_gmt ) );
		$gross   = isset( $row['gross_sales'] ) ? (float) $row['gross_sales'] : 0.0;
		$net     = $gross + $refunds; // refunds stored negative in HPOS.

		$items = self::count_items_hpos( $start_gmt, $end_gmt, $prefixed );

		return self::normalize_aggregate_row(
			array(
				'order_count' => $row['order_count'] ?? 0,
				'gross_sales' => $gross,
				'net_sales'   => $net,
				'items_sold'  => $items,
				'customers'   => $row['customers'] ?? 0,
			),
			$start_gmt,
			$end_gmt,
			$statuses
		);
	}

	/**
	 * Count line items via order item table joined to HPOS.
	 *
	 * @param string   $start_gmt Start.
	 * @param string   $end_gmt   End.
	 * @param string[] $prefixed  Prefixed statuses.
	 * @return int
	 */
	private static function count_items_hpos( $start_gmt, $end_gmt, array $prefixed ) {
		global $wpdb;
		$orders = Salesbin_HPOS::orders_table();
		$in     = self::in_placeholders( $prefixed );
		$sql    = "SELECT COALESCE(SUM(CAST(meta.meta_value AS DECIMAL(18,4))), 0)
			FROM {$wpdb->prefix}woocommerce_order_items items
			INNER JOIN {$orders} o ON o.id = items.order_id
			INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta meta ON meta.order_item_id = items.order_item_id AND meta.meta_key = '_qty'
			WHERE items.order_item_type = 'line_item'
			AND o.type = 'shop_order'
			AND o.status IN ($in)
			AND o.date_created_gmt >= %s AND o.date_created_gmt <= %s";
		$params = array_merge( $prefixed, array( $start_gmt, $end_gmt ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Legacy CPT aggregation.
	 *
	 * @param string   $start_gmt Start.
	 * @param string   $end_gmt   End.
	 * @param string[] $statuses  Statuses.
	 * @return array
	 */
	private static function aggregate_from_posts( $start_gmt, $end_gmt, array $statuses ) {
		global $wpdb;
		$prefixed = self::prefixed( $statuses );
		$in       = self::in_placeholders( $prefixed );

		$sql = "SELECT
				COUNT(p.ID) AS order_count,
				COALESCE(SUM(CAST(totals.meta_value AS DECIMAL(18,4))), 0) AS gross_sales,
				COUNT(DISTINCT CASE WHEN CAST(cust.meta_value AS UNSIGNED) > 0 THEN cust.meta_value END) AS customers
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} totals ON totals.post_id = p.ID AND totals.meta_key = '_order_total'
			LEFT JOIN {$wpdb->postmeta} cust ON cust.post_id = p.ID AND cust.meta_key = '_customer_user'
			WHERE p.post_type = 'shop_order'
			AND p.post_status IN ($in)
			AND p.post_date_gmt >= %s AND p.post_date_gmt <= %s";

		$params = array_merge( $prefixed, array( $start_gmt, $end_gmt ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( $sql, $params ), ARRAY_A );

		$refund_sql = "SELECT COALESCE(SUM(CAST(m.meta_value AS DECIMAL(18,4))), 0)
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_refund_amount'
			WHERE p.post_type = 'shop_order_refund'
			AND p.post_date_gmt >= %s AND p.post_date_gmt <= %s";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$refunds = (float) $wpdb->get_var( $wpdb->prepare( $refund_sql, $start_gmt, $end_gmt ) );

		$gross = isset( $row['gross_sales'] ) ? (float) $row['gross_sales'] : 0.0;
		$net   = $gross - abs( $refunds );

		$items_sql = "SELECT COALESCE(SUM(CAST(meta.meta_value AS DECIMAL(18,4))), 0)
			FROM {$wpdb->prefix}woocommerce_order_items items
			INNER JOIN {$wpdb->posts} p ON p.ID = items.order_id
			INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta meta ON meta.order_item_id = items.order_item_id AND meta.meta_key = '_qty'
			WHERE items.order_item_type = 'line_item'
			AND p.post_type = 'shop_order'
			AND p.post_status IN ($in)
			AND p.post_date_gmt >= %s AND p.post_date_gmt <= %s";
		$iparams = array_merge( $prefixed, array( $start_gmt, $end_gmt ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$items = (int) $wpdb->get_var( $wpdb->prepare( $items_sql, $iparams ) );

		return self::normalize_aggregate_row(
			array(
				'order_count' => $row['order_count'] ?? 0,
				'gross_sales' => $gross,
				'net_sales'   => $net,
				'items_sold'  => $items,
				'customers'   => $row['customers'] ?? 0,
			),
			$start_gmt,
			$end_gmt,
			$statuses
		);
	}

	/**
	 * Normalize and add derived metrics.
	 *
	 * @param array    $row       Raw row.
	 * @param string   $start_gmt Start.
	 * @param string   $end_gmt   End.
	 * @param string[] $statuses  Statuses.
	 * @return array
	 */
	private static function normalize_aggregate_row( $row, $start_gmt, $end_gmt, array $statuses ) {
		$orders = isset( $row['order_count'] ) ? (int) $row['order_count'] : 0;
		$gross  = isset( $row['gross_sales'] ) ? (float) $row['gross_sales'] : 0.0;
		$net    = isset( $row['net_sales'] ) ? (float) $row['net_sales'] : $gross;
		$items  = isset( $row['items_sold'] ) ? (int) $row['items_sold'] : 0;
		$cust   = isset( $row['customers'] ) ? (int) $row['customers'] : 0;
		$aov    = $orders > 0 ? ( $net / $orders ) : 0.0;

		$products      = self::count_distinct_products( $start_gmt, $end_gmt, $statuses );
		$new_customers = self::count_new_customers( $start_gmt, $end_gmt, $statuses );

		return array(
			'orders'        => $orders,
			'gross'         => round( $gross, 4 ),
			'net'           => round( $net, 4 ),
			'items'         => $items,
			'customers'     => $cust,
			'new_customers' => $new_customers,
			'products'      => $products,
			'aov'           => round( $aov, 4 ),
		);
	}

	/**
	 * Distinct products sold.
	 *
	 * @param string   $start_gmt Start.
	 * @param string   $end_gmt   End.
	 * @param string[] $statuses  Statuses.
	 * @return int
	 */
	public static function count_distinct_products( $start_gmt, $end_gmt, array $statuses ) {
		$lookup = Salesbin_HPOS::product_lookup_table();
		if ( $lookup ) {
			global $wpdb;
			$prefixed = self::prefixed( $statuses );
			$stats    = Salesbin_HPOS::order_stats_table();
			if ( $stats ) {
				$in  = self::in_placeholders( $prefixed );
				$sql = "SELECT COUNT(DISTINCT l.product_id)
					FROM {$lookup} l
					INNER JOIN {$stats} s ON s.order_id = l.order_id
					WHERE l.date_created >= %s AND l.date_created <= %s
					AND s.status IN ($in)";
				$params = array_merge( array( $start_gmt, $end_gmt ), $prefixed );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
				return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT product_id) FROM {$lookup} WHERE date_created >= %s AND date_created <= %s",
					$start_gmt,
					$end_gmt
				)
			);
		}
		return 0;
	}

	/**
	 * Customers whose first qualifying order falls in range.
	 *
	 * @param string   $start_gmt Start.
	 * @param string   $end_gmt   End.
	 * @param string[] $statuses  Statuses.
	 * @return int
	 */
	public static function count_new_customers( $start_gmt, $end_gmt, array $statuses ) {
		global $wpdb;
		$prefixed = self::prefixed( $statuses );
		$in       = self::in_placeholders( $prefixed );
		$stats    = Salesbin_HPOS::order_stats_table();

		if ( $stats ) {
			$sql = "SELECT COUNT(*) FROM (
				SELECT customer_id, MIN(date_created) AS first_order
				FROM {$stats}
				WHERE parent_id = 0 AND customer_id > 0 AND status IN ($in)
				GROUP BY customer_id
			) t WHERE first_order >= %s AND first_order <= %s";
			$params = array_merge( $prefixed, array( $start_gmt, $end_gmt ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
		}

		if ( Salesbin_HPOS::enabled() ) {
			$table = Salesbin_HPOS::orders_table();
			$sql   = "SELECT COUNT(*) FROM (
				SELECT customer_id, MIN(date_created_gmt) AS first_order
				FROM {$table}
				WHERE type = 'shop_order' AND customer_id > 0 AND status IN ($in)
				GROUP BY customer_id
			) t WHERE first_order >= %s AND first_order <= %s";
			$params = array_merge( $prefixed, array( $start_gmt, $end_gmt ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
		}

		$sql = "SELECT COUNT(*) FROM (
			SELECT cust.meta_value AS customer_id, MIN(p.post_date_gmt) AS first_order
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} cust ON cust.post_id = p.ID AND cust.meta_key = '_customer_user'
			WHERE p.post_type = 'shop_order' AND p.post_status IN ($in) AND CAST(cust.meta_value AS UNSIGNED) > 0
			GROUP BY cust.meta_value
		) t WHERE first_order >= %s AND first_order <= %s";
		$params = array_merge( $prefixed, array( $start_gmt, $end_gmt ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Time series buckets.
	 *
	 * @param string   $start_gmt Start.
	 * @param string   $end_gmt   End.
	 * @param string[] $statuses  Statuses.
	 * @param string   $bucket    day|week|month.
	 * @return array<int,array{date:string,sales:float,orders:int,aov:float}>
	 */
	public static function series( $start_gmt, $end_gmt, array $statuses, $bucket ) {
		if ( empty( $statuses ) ) {
			$statuses = Salesbin_Helpers::default_sales_statuses();
		}
		$expr = self::bucket_sql_expr( $bucket );
		$stats = Salesbin_HPOS::order_stats_table();
		if ( $stats ) {
			return self::series_from_stats( $stats, $start_gmt, $end_gmt, $statuses, $expr );
		}
		if ( Salesbin_HPOS::enabled() ) {
			return self::series_from_hpos( $start_gmt, $end_gmt, $statuses, $expr );
		}
		return self::series_from_posts( $start_gmt, $end_gmt, $statuses, $expr );
	}

	/**
	 * SQL date expression in site timezone.
	 *
	 * @param string $bucket Bucket.
	 * @return string
	 */
	private static function bucket_sql_expr( $bucket ) {
		$offset = (int) Salesbin_Helpers::gmt_offset_seconds();
		$local  = "DATE_ADD(%COL%, INTERVAL {$offset} SECOND)";
		if ( 'month' === $bucket ) {
			return "DATE_FORMAT({$local}, '%Y-%m-01')";
		}
		if ( 'week' === $bucket ) {
			return "DATE(DATE_SUB({$local}, INTERVAL WEEKDAY({$local}) DAY))";
		}
		return "DATE({$local})";
	}

	/**
	 * @param string   $table     Table.
	 * @param string   $start_gmt Start.
	 * @param string   $end_gmt   End.
	 * @param string[] $statuses  Statuses.
	 * @param string   $expr      Expr with %COL%.
	 * @return array
	 */
	private static function series_from_stats( $table, $start_gmt, $end_gmt, array $statuses, $expr ) {
		global $wpdb;
		$prefixed = self::prefixed( $statuses );
		$in       = self::in_placeholders( $prefixed );
		$bucket   = str_replace( '%COL%', 'date_created', $expr );
		$sql      = "SELECT {$bucket} AS bucket,
				COALESCE(SUM(net_total), 0) AS sales,
				COUNT(DISTINCT CASE WHEN parent_id = 0 THEN order_id END) AS orders
			FROM {$table}
			WHERE date_created >= %s AND date_created <= %s
			AND status IN ($in)
			GROUP BY bucket
			ORDER BY bucket ASC";
		$params = array_merge( array( $start_gmt, $end_gmt ), $prefixed );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		return self::map_series( $rows );
	}

	/**
	 * @param string   $start_gmt Start.
	 * @param string   $end_gmt   End.
	 * @param string[] $statuses  Statuses.
	 * @param string   $expr      Expr.
	 * @return array
	 */
	private static function series_from_hpos( $start_gmt, $end_gmt, array $statuses, $expr ) {
		global $wpdb;
		$table    = Salesbin_HPOS::orders_table();
		$prefixed = self::prefixed( $statuses );
		$in       = self::in_placeholders( $prefixed );
		$bucket   = str_replace( '%COL%', 'date_created_gmt', $expr );
		// Refund rows carry negative totals; including them yields net sales per bucket.
		$sql      = "SELECT {$bucket} AS bucket,
				COALESCE(SUM(total_amount), 0) AS sales,
				COUNT(CASE WHEN type = 'shop_order' THEN 1 END) AS orders
			FROM {$table}
			WHERE (type = 'shop_order_refund' OR (type = 'shop_order' AND status IN ($in)))
			AND date_created_gmt >= %s AND date_created_gmt <= %s
			GROUP BY bucket
			ORDER BY bucket ASC";
		$params = array_merge( $prefixed, array( $start_gmt, $end_gmt ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		return self::map_series( $rows );
	}

	/**
	 * @param string   $start_gmt Start.
	 * @param string   $end_gmt   End.
	 * @param string[] $statuses  Statuses.
	 * @param string   $expr      Expr.
	 * @return array
	 */
	private static function series_from_posts( $start_gmt, $end_gmt, array $statuses, $expr ) {
		global $wpdb;
		$prefixed = self::prefixed( $statuses );
		$in       = self::in_placeholders( $prefixed );
		$bucket   = str_replace( '%COL%', 'p.post_date_gmt', $expr );
		$sql      = "SELECT {$bucket} AS bucket,
				COALESCE(SUM(CAST(totals.meta_value AS DECIMAL(18,4))), 0) AS sales,
				COUNT(p.ID) AS orders
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} totals ON totals.post_id = p.ID AND totals.meta_key = '_order_total'
			WHERE p.post_type = 'shop_order'
			AND p.post_status IN ($in)
			AND p.post_date_gmt >= %s AND p.post_date_gmt <= %s
			GROUP BY bucket
			ORDER BY bucket ASC";
		$params = array_merge( $prefixed, array( $start_gmt, $end_gmt ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

		// Refunds live in separate posts; bucket them and subtract for net sales.
		$refund_sql = "SELECT {$bucket} AS bucket,
				COALESCE(SUM(CAST(m.meta_value AS DECIMAL(18,4))), 0) AS refund
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_refund_amount'
			WHERE p.post_type = 'shop_order_refund'
			AND p.post_date_gmt >= %s AND p.post_date_gmt <= %s
			GROUP BY bucket";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$refunds = $wpdb->get_results( $wpdb->prepare( $refund_sql, $start_gmt, $end_gmt ), ARRAY_A );

		$refund_map = array();
		if ( is_array( $refunds ) ) {
			foreach ( $refunds as $row ) {
				$refund_map[ (string) $row['bucket'] ] = (float) $row['refund'];
			}
		}

		if ( is_array( $rows ) ) {
			foreach ( $rows as &$row ) {
				$key = (string) $row['bucket'];
				if ( isset( $refund_map[ $key ] ) ) {
					$row['sales'] = (float) $row['sales'] - abs( $refund_map[ $key ] );
				}
			}
			unset( $row );
		}
		return self::map_series( $rows );
	}

	/**
	 * @param array $rows Rows.
	 * @return array
	 */
	private static function map_series( $rows ) {
		$out = array();
		if ( ! is_array( $rows ) ) {
			return $out;
		}
		foreach ( $rows as $row ) {
			$orders = (int) $row['orders'];
			$sales  = (float) $row['sales'];
			$out[]  = array(
				'date'   => (string) $row['bucket'],
				'sales'  => round( $sales, 4 ),
				'orders' => $orders,
				'aov'    => $orders > 0 ? round( $sales / $orders, 4 ) : 0.0,
			);
		}
		return $out;
	}
}
