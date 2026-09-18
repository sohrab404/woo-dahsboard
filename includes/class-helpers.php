<?php
/**
 * Shared helpers.
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Salesbin_Helpers
 */
class Salesbin_Helpers {

	/**
	 * Client IP for rate limiting (never logged raw alongside OTP data).
	 *
	 * Trusts Cloudflare's connecting-IP header only when the request
	 * actually came through Cloudflare (CF-RAY present); never trusts
	 * X-Forwarded-For blindly. Falls back to 'unknown'.
	 *
	 * @return string
	 */
	public static function client_ip() {
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) && ! empty( $_SERVER['HTTP_CF_RAY'] ) ) {
			$cf = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
			if ( filter_var( $cf, FILTER_VALIDATE_IP ) ) {
				return $cf;
			}
		}
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return substr( $ip, 0, 45 );
			}
		}
		return 'unknown';
	}

	/**
	 * Growth percentage. Previous = 0 yields null (frontend shows New / —).
	 *
	 * @param float $current  Current value.
	 * @param float $previous Previous value.
	 * @return float|null
	 */
	public static function growth_percent( $current, $previous ) {
		$current  = (float) $current;
		$previous = (float) $previous;
		if ( abs( $previous ) < 0.00001 ) {
			return null;
		}
		return ( ( $current - $previous ) / $previous ) * 100;
	}

	/**
	 * Site timezone object.
	 *
	 * @return DateTimeZone
	 */
	public static function timezone() {
		return wp_timezone();
	}

	/**
	 * Offset in seconds for site timezone at a given GMT datetime.
	 *
	 * @param string $gmt_datetime GMT datetime.
	 * @return int
	 */
	public static function gmt_offset_seconds( $gmt_datetime = 'now' ) {
		try {
			$dt = new DateTime( $gmt_datetime, new DateTimeZone( 'UTC' ) );
			$dt->setTimezone( self::timezone() );
			return (int) $dt->getOffset();
		} catch ( Exception $e ) {
			return (int) get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS;
		}
	}

	/**
	 * Format money for API display string (escaped later in UI).
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	public static function format_money( $amount ) {
		if ( function_exists( 'wc_price' ) ) {
			return wp_strip_all_tags( html_entity_decode( wc_price( $amount ) ) );
		}
		return (string) $amount;
	}

	/**
	 * Persian (Jalali) date from a timestamp — server-side port of jalali.js.
	 *
	 * @param int|null $timestamp Unix timestamp (defaults to now).
	 * @return string Y/m/d in Jalali with Persian digits.
	 */
	public static function jalali_date( $timestamp = null ) {
		$timestamp = $timestamp ?: time();
		$gy = (int) wp_date( 'Y', $timestamp );
		$gm = (int) wp_date( 'n', $timestamp );
		$gd = (int) wp_date( 'j', $timestamp );

		$g_d_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
		$gy2   = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
		$days  = 355666 + ( 365 * $gy ) + intdiv( $gy2 + 3, 4 ) - intdiv( $gy2 + 99, 100 ) + intdiv( $gy2 + 399, 400 ) + $gd + $g_d_m[ $gm - 1 ];
		$jy    = -1595 + ( 33 * intdiv( $days, 12053 ) );
		$days %= 12053;
		$jy   += 4 * intdiv( $days, 1461 );
		$days %= 1461;
		if ( $days > 365 ) {
			$jy  += intdiv( $days - 1, 365 );
			$days = ( $days - 1 ) % 365;
		}
		if ( $days < 186 ) {
			$jm = 1 + intdiv( $days, 31 );
			$jd = 1 + ( $days % 31 );
		} else {
			$jm = 7 + intdiv( $days - 186, 30 );
			$jd = 1 + ( ( $days - 186 ) % 30 );
		}
		return self::fa_num( $jy ) . '/' . self::fa_num( sprintf( '%02d', $jm ) ) . '/' . self::fa_num( sprintf( '%02d', $jd ) );
	}

	/**
	 * Currency meta for API.
	 *
	 * @return array<string,string>
	 */
	public static function currency_meta() {
		$code   = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'IRT';
		$symbol = function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( get_woocommerce_currency_symbol() ) : '';
		return array(
			'code'   => $code,
			'symbol' => $symbol,
		);
	}

	/**
	 * Paid-like statuses used for default sales metrics.
	 *
	 * Net/gross sales exclude cancelled, failed, trash, checkout-draft, pending.
	 * on-hold is included to match WooCommerce Analytics defaults.
	 *
	 * @return string[] Statuses without wc- prefix.
	 */
	public static function default_sales_statuses() {
		$paid = function_exists( 'wc_get_is_paid_statuses' ) ? wc_get_is_paid_statuses() : array( 'processing', 'completed' );
		$statuses = array_unique( array_merge( $paid, array( 'on-hold' ) ) );
		return apply_filters( 'salesbin_sales_statuses', array_values( $statuses ) );
	}

	/**
	 * All registered order statuses (no wc- prefix).
	 *
	 * @return array<string,string> slug => label
	 */
	public static function all_order_statuses() {
		$raw = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array();
		$out = array();
		foreach ( $raw as $key => $label ) {
			$slug         = 0 === strpos( $key, 'wc-' ) ? substr( $key, 3 ) : $key;
			$out[ $slug ] = $label;
		}
		return $out;
	}

	/**
	 * Normalize status list from request.
	 *
	 * @param mixed $input Input.
	 * @return string[] Empty means default sales statuses for metrics, or all for listings.
	 */
	public static function parse_statuses( $input ) {
		$allowed = array_keys( self::all_order_statuses() );
		if ( is_string( $input ) && '' !== $input && 'all' !== $input ) {
			$input = array_map( 'trim', explode( ',', $input ) );
		}
		if ( ! is_array( $input ) ) {
			return array();
		}
		$clean = array();
		foreach ( $input as $status ) {
			$status = sanitize_key( (string) $status );
			$status = 0 === strpos( $status, 'wc-' ) ? substr( $status, 3 ) : $status;
			if ( in_array( $status, $allowed, true ) ) {
				$clean[] = $status;
			}
		}
		return array_values( array_unique( $clean ) );
	}

	/**
	 * Prevent CSV formula injection.
	 *
	 * @param string $value Cell value.
	 * @return string
	 */
	public static function csv_safe( $value ) {
		$value = (string) $value;
		if ( $value === '' ) {
			return $value;
		}
		$first = substr( $value, 0, 1 );
		if ( in_array( $first, array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			return "'" . $value;
		}
		return $value;
	}

	/**
	 * Persian-digit number formatting.
	 *
	 * @param mixed $number Number.
	 * @return string
	 */
	public static function fa_num( $number ) {
		return str_replace(
			array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ),
			array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ),
			(string) round( (float) $number )
		);
	}

	/**
	 * Whether a lookup table exists. Memoized per request so repeated checks
	 * never hit SHOW TABLES more than once per table per request.
	 *
	 * @param string $table Full table name.
	 * @return bool
	 */
	public static function table_exists( $table ) {
		static $memo = array();
		if ( isset( $memo[ $table ] ) ) {
			return (bool) $memo[ $table ];
		}

		global $wpdb;
		$cache_key = 'salesbin_tbl_' . md5( $table );
		$cached    = wp_cache_get( $cache_key, Salesbin_Cache::GROUP );
		if ( false !== $cached ) {
			$memo[ $table ] = (bool) $cached;
			return (bool) $cached;
		}
		$like = $wpdb->esc_like( $table );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
		$exists = ( $found === $table );
		wp_cache_set( $cache_key, $exists ? 1 : 0, Salesbin_Cache::GROUP, HOUR_IN_SECONDS );
		$memo[ $table ] = $exists;
		return $exists;
	}
}
