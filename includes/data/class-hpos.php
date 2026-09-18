<?php
/**
 * HPOS / datastore detection.
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Salesbin_HPOS
 */
class Salesbin_HPOS {

	/**
	 * Whether custom order tables are in use.
	 *
	 * @return bool
	 */
	public static function enabled() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		}
		return false;
	}

	/**
	 * WooCommerce Analytics order stats table (best for aggregations).
	 *
	 * @return string|null
	 */
	public static function order_stats_table() {
		global $wpdb;
		$table = $wpdb->prefix . 'wc_order_stats';
		return Salesbin_Helpers::table_exists( $table ) ? $table : null;
	}

	/**
	 * Product lookup table.
	 *
	 * @return string|null
	 */
	public static function product_lookup_table() {
		global $wpdb;
		$table = $wpdb->prefix . 'wc_order_product_lookup';
		return Salesbin_Helpers::table_exists( $table ) ? $table : null;
	}

	/**
	 * HPOS orders table.
	 *
	 * @return string
	 */
	public static function orders_table() {
		global $wpdb;
		return $wpdb->prefix . 'wc_orders';
	}

	/**
	 * HPOS operational table (status etc.) — same as orders in current WC.
	 *
	 * @return string
	 */
	public static function orders_meta_table() {
		global $wpdb;
		return $wpdb->prefix . 'wc_orders_meta';
	}
}
