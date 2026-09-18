<?php
/**
 * Access control.
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Salesbin_Capabilities
 */
class Salesbin_Capabilities {

	/**
	 * Default capability required to view dashboard and REST data.
	 *
	 * Filterable via salesbin_required_capability.
	 */
	const CAPABILITY = 'manage_woocommerce';

	/**
	 * Resolved capability.
	 *
	 * @return string
	 */
	public static function required() {
		$cap = apply_filters( 'salesbin_required_capability', self::CAPABILITY );
		return is_string( $cap ) && $cap ? $cap : self::CAPABILITY;
	}

	/**
	 * Whether current user can view sales data (including PII).
	 *
	 * @return bool
	 */
	public static function current_user_can_view() {
		return current_user_can( self::required() );
	}

	/**
	 * Whether current user can see customer PII (email, phone).
	 * Same as dashboard capability by default.
	 *
	 * @return bool
	 */
	public static function current_user_can_view_pii() {
		return (bool) apply_filters( 'salesbin_can_view_customer_pii', self::current_user_can_view() );
	}
}
