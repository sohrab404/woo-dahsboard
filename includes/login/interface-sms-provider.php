<?php
/**
 * SMS provider contract for the OTP login flow.
 *
 * Melipayamak is only the SMS delivery provider — never the authentication
 * system (OTP verification, user lookup and auth cookies stay in
 * Salesbin_Login_Otp / Salesbin_Login_Module). Any gateway can be swapped in
 * via the `salesbin_sms_provider` filter without touching the login flow.
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface Salesbin_SMS_Provider
 */
interface Salesbin_SMS_Provider {

	/**
	 * Whether the provider has everything it needs to send (credentials etc).
	 *
	 * @return bool
	 */
	public function is_configured();

	/**
	 * Deliver an OTP message.
	 *
	 * @param string $to  Gateway number (e.g. 98912...).
	 * @param string $otp Code.
	 * @param string $cc  Country code digits (98 for domestic).
	 * @return array{ok:bool,message:string}
	 */
	public function send_otp( $to, $otp, $cc = '98' );

	/**
	 * Settings-screen connection test.
	 *
	 * @return array{ok:bool,message:string}
	 */
	public function test_connection();
}
