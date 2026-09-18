<?php
/**
 * SMS provider factory. Resolves the active provider for the OTP login flow.
 * Default: Melipayamak. Swap/extend via the `salesbin_sms_provider` filter
 * (must implement Salesbin_SMS_Provider).
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Salesbin_SMS
 */
class Salesbin_SMS {

	/**
	 * Resolved provider instance (per request).
	 *
	 * @var Salesbin_SMS_Provider|null
	 */
	private static $provider = null;

	/**
	 * The active SMS provider.
	 *
	 * @return Salesbin_SMS_Provider
	 */
	public static function provider() {
		if ( null === self::$provider ) {
			$provider      = apply_filters( 'salesbin_sms_provider', null );
			self::$provider = ( $provider instanceof Salesbin_SMS_Provider )
				? $provider
				: new Salesbin_Melipayamak();
		}
		return self::$provider;
	}
}
