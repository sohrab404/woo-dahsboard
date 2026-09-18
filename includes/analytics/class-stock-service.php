<?php
/**
 * Low-stock products. Unmanaged stock is never treated as low.
 * Honors per-product `_low_stock_amount` with the global threshold as fallback.
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Salesbin_Stock_Service
 */
class Salesbin_Stock_Service {

	/**
	 * Count low stock products (single COUNT query, no product hydration).
	 *
	 * @return int
	 */
	public function count_low_stock() {
		$threshold = (int) Salesbin_Settings::get( 'low_stock_threshold', 5 );
		return Salesbin_Cache::remember(
			'products',
			array( 'low-count', $threshold ),
			function () use ( $threshold ) {
				return $this->count_low_stock_exact( $threshold );
			}
		);
	}

	/**
	 * @param int $limit Limit.
	 * @return array
	 */
	public function list_low_stock( $limit = 20 ) {
		$limit     = max( 1, min( 100, absint( $limit ) ) );
		$threshold = (int) Salesbin_Settings::get( 'low_stock_threshold', 5 );

		return Salesbin_Cache::remember(
			'products',
			array( 'low', $threshold, $limit ),
			function () use ( $threshold, $limit ) {
				$ids   = $this->low_stock_ids( $threshold, $limit );
				$total = $this->count_low_stock_exact( $threshold );

				$items = array();
				foreach ( $ids as $id ) {
					$product = wc_get_product( $id );
					if ( ! $product || ! $product->managing_stock() ) {
						continue;
					}
					$qty = $product->get_stock_quantity();
					if ( null === $qty ) {
						continue;
					}
					$items[] = array(
						'id'        => $product->get_id(),
						'name'      => $product->get_name(),
						'stock'     => (int) $qty,
						'edit_url'  => get_edit_post_link( $product->get_id(), 'raw' ),
						'threshold' => (int) ( function_exists( 'wc_get_low_stock_amount' ) ? wc_get_low_stock_amount( $product ) : $threshold ),
					);
				}

				return array(
					'items'     => $items,
					'total'     => $total,
					'threshold' => $threshold,
				);
			}
		);
	}

	/**
	 * Product/variation IDs where stock <= COALESCE(_low_stock_amount, global threshold).
	 *
	 * @param int $threshold Global threshold.
	 * @param int $limit     Limit.
	 * @return int[]
	 */
	private function low_stock_ids( $threshold, $limit ) {
		global $wpdb;
		$sql = "SELECT p.ID
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} manage ON manage.post_id = p.ID AND manage.meta_key = '_manage_stock' AND manage.meta_value = 'yes'
			INNER JOIN {$wpdb->postmeta} stock ON stock.post_id = p.ID AND stock.meta_key = '_stock' AND stock.meta_value <> ''
			LEFT JOIN {$wpdb->postmeta} custom ON custom.post_id = p.ID AND custom.meta_key = '_low_stock_amount' AND custom.meta_value <> ''
			WHERE p.post_type IN ('product','product_variation')
			AND p.post_status = 'publish'
			AND CAST(stock.meta_value AS DECIMAL(18,4)) <= COALESCE(CAST(custom.meta_value AS DECIMAL(18,4)), %f)
			ORDER BY CAST(stock.meta_value AS DECIMAL(18,4)) ASC
			LIMIT %d";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return array_map( 'absint', (array) $wpdb->get_col( $wpdb->prepare( $sql, $threshold, $limit ) ) );
	}

	/**
	 * Exact count of low-stock rows using the same predicate as low_stock_ids().
	 *
	 * @param int $threshold Global threshold.
	 * @return int
	 */
	private function count_low_stock_exact( $threshold ) {
		global $wpdb;
		$sql = "SELECT COUNT(*)
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} manage ON manage.post_id = p.ID AND manage.meta_key = '_manage_stock' AND manage.meta_value = 'yes'
			INNER JOIN {$wpdb->postmeta} stock ON stock.post_id = p.ID AND stock.meta_key = '_stock' AND stock.meta_value <> ''
			LEFT JOIN {$wpdb->postmeta} custom ON custom.post_id = p.ID AND custom.meta_key = '_low_stock_amount' AND custom.meta_value <> ''
			WHERE p.post_type IN ('product','product_variation')
			AND p.post_status = 'publish'
			AND CAST(stock.meta_value AS DECIMAL(18,4)) <= COALESCE(CAST(custom.meta_value AS DECIMAL(18,4)), %f)";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $threshold ) );
	}
}
