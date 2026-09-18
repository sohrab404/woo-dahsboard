<?php
/**
 * OTP service for SMS login — ported from the NewLogin Otp service.
 * Kept the security model: HMAC-hashed codes (never raw), per-code attempt limit,
 * server-side resend cooldown, hourly per-IP quota. Storage moved from a custom
 * PDO table to WordPress transients so it needs no extra schema.
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Salesbin_Login_Otp
 */
class Salesbin_Login_Otp {

	const CODE_SIZE      = 6;    // digits per code.
	const MAX_ATTEMPTS   = 5;    // wrong tries before the code is voided.
	const IP_HOURLY_LIMIT = 10;  // sends per IP per hour.

	/**
	 * Code lifetime in seconds (settings: login_otp_expiry, default 180).
	 *
	 * @return int
	 */
	public static function expiry() {
		return max( 60, min( 900, (int) Salesbin_Settings::get( 'login_otp_expiry', 180 ) ) );
	}

	/**
	 * Resend cooldown in seconds (settings: login_otp_cooldown, default 60).
	 *
	 * @return int
	 */
	public static function cooldown() {
		return max( 15, min( 300, (int) Salesbin_Settings::get( 'login_otp_cooldown', 60 ) ) );
	}

	/**
	 * Generate a CSPRNG code.
	 *
	 * @return string
	 */
	public static function generate_code() {
		$code = '';
		for ( $i = 0; $i < self::CODE_SIZE; $i++ ) {
			$code .= (string) random_int( 0, 9 );
		}
		return $code;
	}

	/**
	 * HMAC the code against a phone pair.
	 *
	 * @param string $cc       Country code.
	 * @param string $national National number.
	 * @param string $code     OTP.
	 * @return string
	 */
	public static function hash( $cc, $national, $code ) {
		return hash_hmac( 'sha256', $cc . '|' . $national . '|' . $code, wp_salt( 'auth' ) );
	}

	/**
	 * Transient key for a phone pair.
	 *
	 * @param string $cc       Country code.
	 * @param string $national National number.
	 * @return string
	 */
	private static function key_for( $cc, $national ) {
		return 'salesbin_otp_' . md5( $cc . '|' . $national );
	}

	/**
	 * Create and send a code.
	 *
	 * Goes through the Salesbin_SMS provider factory so any gateway
	 * (default: Melipayamak) can be swapped via the salesbin_sms_provider
	 * filter without touching this flow. Raw codes are never stored or
	 * logged — only hooks receive the masked number.
	 *
	 * @param string $cc       Country code.
	 * @param string $national National number.
	 * @param string $ip       Client IP.
	 * @return array{ok:bool,message:string,status:int,resend_after?:int,expires_in?:int,debug_code?:string}
	 */
	public static function create( $cc, $national, $ip ) {
		$expiry   = self::expiry();
		$cooldown = self::cooldown();

		// Hourly per-IP quota (anti SMS-pumping).
		$ip_key   = 'salesbin_otp_ip_' . md5( (string) $ip );
		$sent     = (int) get_transient( $ip_key );
		if ( $sent >= self::IP_HOURLY_LIMIT ) {
			return array(
				'ok'      => false,
				'status'  => 429,
				'message' => __( 'تعداد درخواست‌های شما زیاد است؛ لطفاً کمی بعد دوباره تلاش کنید.', 'salesbin' ),
			);
		}

		// Server-side resend cooldown.
		$cool_key = 'salesbin_otp_cool_' . md5( $cc . '|' . $national );
		if ( false !== get_transient( $cool_key ) ) {
			return array(
				'ok'           => false,
				'status'       => 429,
				'message'      => sprintf( __( 'کد قبلاً ارسال شده است؛ %d ثانیه دیگر دوباره تلاش کنید.', 'salesbin' ), $cooldown ),
				'resend_after' => $cooldown,
			);
		}

		$code  = self::generate_code();
		$debug = (bool) Salesbin_Settings::get( 'login_sms_debug', 0 );

		if ( ! $debug ) {
			$sent_res = Salesbin_SMS::provider()->send_otp( Salesbin_Phone::to_gateway( $cc, $national ), $code, $cc );
			if ( empty( $sent_res['ok'] ) ) {
				Salesbin_Logger::debug( sprintf( 'OTP send failed for %s.', Salesbin_Phone::mask( $cc, $national ) ) );
				return array(
					'ok'      => false,
					'status'  => 502,
					'message' => isset( $sent_res['message'] ) ? (string) $sent_res['message'] : __( 'ارسال کد تایید با خطا مواجه شد.', 'salesbin' ),
				);
			}
		}

		// One live code per number: overwrite the previous one.
		set_transient(
			self::key_for( $cc, $national ),
			array(
				'h'        => self::hash( $cc, $national, $code ),
				'attempts' => 0,
				'expires'  => time() + $expiry,
			),
			$expiry
		);
		set_transient( $cool_key, 1, $cooldown );
		set_transient( $ip_key, $sent + 1, HOUR_IN_SECONDS );

		/**
		 * Fired after an OTP was issued. Receives only the masked number —
		 * never the raw code.
		 *
		 * @param string $cc       Country code.
		 * @param string $national National number.
		 * @param string $masked   Masked display number.
		 */
		do_action( 'salesbin_login_otp_send', $cc, $national, Salesbin_Phone::mask( $cc, $national ) );

		$out = array(
			'ok'           => true,
			'status'       => 200,
			'message'      => __( 'کد تایید پیامک شد.', 'salesbin' ),
			'resend_after' => $cooldown,
			'expires_in'   => $expiry,
		);
		if ( $debug ) {
			$out['debug_code'] = $code; // Shown only when the admin enabled debug mode.
		}
		return $out;
	}

	/**
	 * Verify a code.
	 *
	 * @param string $cc       Country code.
	 * @param string $national National number.
	 * @param string $code     User-supplied code.
	 * @param bool   $consume  Invalidate on success.
	 * @return true|string True on success, Persian error message otherwise.
	 */
	public static function verify( $cc, $national, $code, $consume = true ) {
		$code = preg_replace( '/\D/', '', Salesbin_Phone::to_latin_digits( (string) $code ) );
		if ( '' === $code ) {
			return __( 'کد تایید را وارد کنید.', 'salesbin' );
		}

		$key = self::key_for( $cc, $national );
		$row = get_transient( $key );
		if ( ! is_array( $row ) || empty( $row['h'] ) ) {
			return __( 'ابتدا کد تایید را درخواست کنید.', 'salesbin' );
		}

		if ( (int) $row['attempts'] >= self::MAX_ATTEMPTS ) {
			delete_transient( $key );
			return __( 'تعداد تلاش‌های نامعتبر زیاد است؛ کد جدید درخواست کنید.', 'salesbin' );
		}

		if ( hash_equals( (string) $row['h'], self::hash( $cc, $national, $code ) ) ) {
			if ( $consume ) {
				delete_transient( $key );
			}

			/**
			 * Fired after an OTP was verified successfully.
			 *
			 * @param string $cc       Country code.
			 * @param string $national National number.
			 * @param string $masked   Masked display number.
			 */
			do_action( 'salesbin_login_otp_verify', $cc, $national, Salesbin_Phone::mask( $cc, $national ) );

			return true;
		}

		$attempts = (int) $row['attempts'] + 1;
		if ( $attempts >= self::MAX_ATTEMPTS ) {
			delete_transient( $key );
			return __( 'کد تایید اشتباه است؛ کد جدید درخواست کنید.', 'salesbin' );
		}
		$row['attempts'] = $attempts;
		// Keep the original expiry — wrong attempts must not extend the code's life.
		$remaining = empty( $row['expires'] ) ? self::expiry() : max( 1, (int) $row['expires'] - time() );
		set_transient( $key, $row, min( self::expiry(), $remaining ) );

		return sprintf( __( 'کد تایید اشتباه است. (%d تلاش باقی مانده)', 'salesbin' ), self::MAX_ATTEMPTS - $attempts );
	}
}
