<?php
/**
 * Mobile number normalization/validation for the SMS login flow.
 * Ported from the NewLogin Phone helper (Digits-compatible) onto plugin settings:
 * Persian/Arabic digits, Iranian +98 validation, E.164 keys.
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Salesbin_Phone
 */
class Salesbin_Phone {

	/**
	 * Convert Persian/Arabic digits to Latin.
	 *
	 * @param string $str Input.
	 * @return string
	 */
	public static function to_latin_digits( $str ) {
		$from = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
		$to   = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
		return str_replace( $from, $to, (string) $str );
	}

	/**
	 * Sanitize a mobile field: latin digits, strip separators, drop leading zeros.
	 *
	 * @param string $raw Raw input.
	 * @return string
	 */
	public static function sanitize_mobile( $raw ) {
		$raw    = self::to_latin_digits( $raw );
		$plus   = ( strpos( ltrim( $raw ), '+' ) === 0 ) ? '+' : '';
		$digits = preg_replace( '/[\s+()\-.]/', '', $raw );
		return $plus . ltrim( (string) $digits, '0' );
	}

	/**
	 * Sanitize a country code.
	 *
	 * @param string $raw Raw input.
	 * @return string
	 */
	public static function sanitize_country_code( $raw ) {
		$raw    = self::to_latin_digits( trim( (string) $raw ) );
		$plus   = ( strpos( $raw, '+' ) === 0 ) ? '+' : '';
		$digits = preg_replace( '/[^0-9]/', '', $raw );
		return $plus . $digits;
	}

	/**
	 * Allowed country codes (digits, no plus). Default: Iran only.
	 *
	 * @return string[]
	 */
	public static function allowed_country_codes() {
		$codes = apply_filters( 'salesbin_login_countrycodes', array( '98' ) );
		$clean = array();
		foreach ( (array) $codes as $code ) {
			$code = ltrim( self::sanitize_country_code( (string) $code ), '+' );
			if ( '' !== $code && ctype_digit( $code ) ) {
				$clean[] = $code;
			}
		}
		return empty( $clean ) ? array( '98' ) : array_values( array_unique( $clean ) );
	}

	/**
	 * Default country code.
	 *
	 * @return string
	 */
	public static function default_country_code() {
		$codes = self::allowed_country_codes();
		return $codes[0];
	}

	/**
	 * Whether a country code is allowed.
	 *
	 * @param string $cc Country code.
	 * @return bool
	 */
	public static function is_country_allowed( $cc ) {
		$cc = ltrim( self::sanitize_country_code( $cc ), '+' );
		if ( '' === $cc || ! ctype_digit( $cc ) ) {
			return false;
		}
		return in_array( $cc, self::allowed_country_codes(), true );
	}

	/**
	 * Validate a normalized pair.
	 *
	 * @param string $cc       Country code.
	 * @param string $national National number without leading zero.
	 * @return true|string True when valid, Persian error message otherwise.
	 */
	public static function validate( $cc, $national ) {
		$cc = ltrim( self::sanitize_country_code( $cc ), '+' );

		if ( '' === $cc || ! ctype_digit( $cc ) ) {
			return __( 'کد کشور معتبر نیست.', 'salesbin' );
		}
		if ( ! self::is_country_allowed( $cc ) ) {
			return __( 'ارسال به این کشور پشتیبانی نمی‌شود.', 'salesbin' );
		}
		$national = (string) $national;
		if ( '' === $national || ! ctype_digit( $national ) ) {
			return __( 'شماره موبایل را فقط با رقم وارد کنید.', 'salesbin' );
		}
		if ( '98' === $cc ) {
			if ( ! preg_match( '/^9\d{9}$/', $national ) ) {
				return __( 'شماره موبایل ایران باید ۱۰ رقم و بدون صفر ابتدایی باشد. مثال: 9123456789', 'salesbin' );
			}
			return true;
		}
		if ( strlen( $national ) < 7 || strlen( $national ) > 14 ) {
			return __( 'شماره موبایل معتبر نیست.', 'salesbin' );
		}
		return true;
	}

	/**
	 * Full international number: +98912... (user identity key).
	 *
	 * @param string $cc       Country code.
	 * @param string $national National number.
	 * @return string
	 */
	public static function e164( $cc, $national ) {
		return '+' . ltrim( self::sanitize_country_code( $cc ), '+' ) . ltrim( (string) $national, '0' );
	}

	/**
	 * Gateway number for Melipayamak: 98912... without plus.
	 *
	 * @param string $cc       Country code.
	 * @param string $national National number.
	 * @return string
	 */
	public static function to_gateway( $cc, $national ) {
		return ltrim( self::sanitize_country_code( $cc ), '+' ) . ltrim( (string) $national, '0' );
	}

	/**
	 * Masked display number for logs/UI: 0912****567 (never the full number).
	 *
	 * @param string $cc       Country code.
	 * @param string $national National number.
	 * @return string
	 */
	public static function mask( $cc, $national ) {
		$national = ltrim( (string) $national, '0' );
		if ( '98' === ltrim( self::sanitize_country_code( $cc ), '+' ) && strlen( $national ) >= 7 ) {
			return '0' . substr( $national, 0, 3 ) . '****' . substr( $national, -3 );
		}
		return substr( $national, 0, 3 ) . '****' . substr( $national, -3 );
	}

	/**
	 * Normalize raw request input to [cc, national] or throw.
	 *
	 * @param string $cc     Raw country code.
	 * @param string $mobile Raw mobile field.
	 * @return array{0:string,1:string}
	 * @throws InvalidArgumentException With a Persian message when invalid.
	 */
	public static function normalize( $cc, $mobile ) {
		$cc       = self::sanitize_country_code( $cc );
		$national = self::sanitize_mobile( $mobile );

		// The caller may have pasted the full number (+98912... or 98912...).
		if ( 0 === strpos( $national, '+' ) ) {
			$national = substr( $national, 1 );
		}
		$cc_digits = ltrim( $cc, '+' );
		if ( '' !== $cc_digits
			&& strlen( $national ) === strlen( $cc_digits ) + 10
			&& 0 === strpos( $national, $cc_digits ) ) {
			$national = substr( $national, strlen( $cc_digits ) );
		}

		$valid = self::validate( $cc, $national );
		if ( true !== $valid ) {
			throw new InvalidArgumentException( $valid );
		}
		return array( ltrim( $cc, '+' ), $national );
	}
}
