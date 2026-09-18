<?php
/**
 * Melipayamak REST client — rewritten to mirror the official SDK
 * (github.com/Melipayamak/melipayamak-php, src/SmsRest.php):
 *
 *   send($to, $from, $text, $isFlash)   → POST api/SendSMS/SendSMS
 *   sendByBaseNumber($text, $to, $bodyId) → POST api/SendSMS/BaseServiceNumber
 *   getCredit()                          → POST api/SendSMS/GetCredit (UserName/PassWord)
 *
 * Response JSON: { "Value": recId|errorCode, "RetStatus": 1|code, "StrRetStatus": "Ok" }.
 * Success = RetStatus == 1. Error dictionary covers all documented codes.
 *
 * Hardening: 15s timeout through the WordPress HTTP API (wp_remote_post —
 * survives hosts where raw cURL is blocked/unavailable), one-shot SSL-verify
 * fallback for hosts without a CA bundle, and a settings-screen connection test.
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Salesbin_Melipayamak
 *
 * Implements Salesbin_SMS_Provider — the OTP flow never talks to this class
 * directly; it goes through Salesbin_SMS::provider().
 */
class Salesbin_Melipayamak implements Salesbin_SMS_Provider {

	const API = 'https://rest.payamak-panel.com/api/SendSMS/%s';

	/**
	 * @var string
	 */
	private $username;

	/**
	 * @var string ApiKey or panel password.
	 */
	private $password;

	/**
	 * @var string
	 */
	private $from;

	/**
	 * @var bool
	 */
	private $khadamati;

	/**
	 * @var string
	 */
	private $body_id;

	/**
	 * @var string
	 */
	private $template;

	/**
	 * Config from plugin settings.
	 */
	public function __construct() {
		$this->username  = trim( (string) Salesbin_Settings::get( 'melipayamak_username', '' ) );
		$this->password  = trim( (string) Salesbin_Settings::get( 'melipayamak_password', '' ) );
		$this->from      = trim( (string) Salesbin_Settings::get( 'melipayamak_from', '' ) );
		$this->khadamati = (bool) Salesbin_Settings::get( 'melipayamak_khadamati', 0 );
		$this->body_id   = trim( (string) Salesbin_Settings::get( 'melipayamak_body_id', '' ) );
		$this->template  = (string) Salesbin_Settings::get( 'melipayamak_template', __( 'کد ورود شما: {OTP}', 'salesbin' ) );
	}

	/**
	 * Whether credentials exist.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return '' !== $this->username && '' !== $this->password;
	}

	/**
	 * Send an OTP message.
	 *
	 * @param string $to  Gateway number (98912...).
	 * @param string $otp Code.
	 * @param string $cc  Country code (98 for domestic).
	 * @return array{ok:bool,message:string}
	 */
	public function send_otp( $to, $otp, $cc = '98' ) {
		if ( ! $this->is_configured() ) {
			return array(
				'ok'      => false,
				'message' => __( 'اطلاعات ملی پیامک در تنظیمات وودش وارد نشده است.', 'salesbin' ),
			);
		}

		$cc = ltrim( (string) $cc, '+' );

		if ( '98' !== $cc ) {
			return array(
				'ok'      => false,
				'message' => __( 'ارسال پیامک به کشورهای دیگر فعال نیست.', 'salesbin' ),
			);
		}

		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$text = str_replace(
			array( '{NAME}', '{OTP}', '{DOMAIN}' ),
			array( (string) $host, (string) $otp, (string) $host ),
			$this->template
		);

		try {
			if ( $this->khadamati && '' !== $this->body_id ) {
				// Official SDK: sendByBaseNumber($text, $to, $bodyId).
				$res = $this->request(
					'BaseServiceNumber',
					array(
						'username' => $this->username,
						'password' => $this->password,
						'text'     => $text,
						'to'       => $to,
						'bodyId'   => $this->body_id,
					)
				);
			} else {
				if ( '' === $this->from ) {
					return array(
						'ok'      => false,
						'message' => __( 'شماره فرستنده (from) در تنظیمات وارد نشده است.', 'salesbin' ),
					);
				}
				// Official SDK: send($to, $from, $text, $isFlash).
				$res = $this->request(
					'SendSMS',
					array(
						'username' => $this->username,
						'password' => $this->password,
						'to'       => $to,
						'from'     => $this->from,
						'text'     => $text,
						'isflash'  => false,
					)
				);
			}
		} catch ( Exception $e ) {
			Salesbin_Logger::debug( 'Melipayamak transport error: ' . $e->getMessage() );
			return array(
				'ok'      => false,
				'message' => __( 'خطا در ارتباط با ملی پیامک؛ اتصال سرور را بررسی کنید.', 'salesbin' ),
			);
		}

		if ( ! is_array( $res ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'پاسخ نامعتبر از سرور ملی پیامک.', 'salesbin' ),
			);
		}

		// Official contract: success when RetStatus == 1; Value carries the
		// delivery recId or an error number.
		$ret_status = isset( $res['RetStatus'] ) ? (int) $res['RetStatus'] : -100;
		if ( 1 === $ret_status ) {
			Salesbin_Logger::debug( sprintf( 'Melipayamak sent. recId=%s', isset( $res['Value'] ) ? (string) $res['Value'] : '?' ) );
			return array( 'ok' => true, 'message' => __( 'ارسال شد.', 'salesbin' ) );
		}

		return array( 'ok' => false, 'message' => $this->error_message( $ret_status, $res ) );
	}

	/**
	 * Panel credit — admin diagnostics (matches official getCredit()).
	 *
	 * @return int Credit or -1 on failure.
	 */
	public function get_credit() {
		if ( ! $this->is_configured() ) {
			return -1;
		}
		try {
			$res = $this->request(
				'GetCredit',
				array(
					'UserName' => $this->username,
					'PassWord' => $this->password,
				)
			);
		} catch ( Exception $e ) {
			return -1;
		}
		return isset( $res['Value'] ) ? (int) $res['Value'] : -1;
	}

	/**
	 * Connection test for the settings screen: validates credentials exist,
	 * then calls GetCredit and reports the result in plain Persian.
	 *
	 * @return array{ok:bool,message:string}
	 */
	public function test_connection() {
		if ( ! $this->is_configured() ) {
			return array(
				'ok'      => false,
				'message' => __( 'نام کاربری و ApiKey/رمز عبور ملی‌پیامک را در تنظیمات وارد و ذخیره کنید.', 'salesbin' ),
			);
		}

		try {
			$res = $this->request(
				'GetCredit',
				array(
					'UserName' => $this->username,
					'PassWord' => $this->password,
				)
			);
		} catch ( Exception $e ) {
			Salesbin_Logger::debug( 'Melipayamak test failed: ' . $e->getMessage() );
			return array(
				'ok'      => false,
				'message' => sprintf(
					/* translators: %s: transport error details */
					__( 'اتصال به سرور ملی‌پیامک برقرار نشد (%s). فایروال سرور یا مجاز نبودن IP سرور در پنل را بررسی کنید.', 'salesbin' ),
					$e->getMessage()
				),
			);
		}

		if ( ! is_array( $res ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'پاسخ نامعتبر از سرور ملی‌پیامک دریافت شد؛ اعتبار وب‌سرویس پنل را بررسی کنید.', 'salesbin' ),
			);
		}

		$ret_status = isset( $res['RetStatus'] ) ? (int) $res['RetStatus'] : -100;
		if ( 1 !== $ret_status ) {
			return array(
				'ok'      => false,
				'message' => $this->error_message( $ret_status, $res ),
			);
		}

		$credit = isset( $res['Value'] ) ? (int) $res['Value'] : -1;
		return array(
			'ok'      => true,
			'message' => sprintf(
				/* translators: %s: panel credit */
				__( 'اتصال موفق بود! اعتبار پنل: %s ریال', 'salesbin' ),
				number_format( max( 0, $credit ) )
			),
		);
	}

	/**
	 * POST to the REST API through the WordPress HTTP API (works on hosts
	 * where raw cURL is unavailable/blocked, follows WP transport fallbacks),
	 * with a one-shot SSL-verify fallback, then JSON decode.
	 *
	 * @param string $method Method.
	 * @param array  $data   Payload.
	 * @return array|null Decoded JSON or null on non-JSON responses.
	 * @throws Exception On transport failure.
	 */
	private function request( $method, array $data ) {
		$url  = sprintf( self::API, $method );
		$args = array(
			'method'      => 'POST',
			'body'        => $data,
			'timeout'     => 15,
			'redirection' => 2,
			'sslverify'   => true,
			'headers'     => array( 'Accept' => 'application/json' ),
		);

		$response = wp_remote_post( $url, $args );

		// Hosts with an outdated CA bundle: fall back once without SSL verification.
		if ( is_wp_error( $response ) ) {
			$msg = $response->get_error_message();
			if ( false !== stripos( $msg, 'ssl' ) || false !== stripos( $msg, 'certificate' ) || false !== stripos( $msg, 'cURL 60' ) || false !== stripos( $msg, 'cURL 77' ) ) {
				$args['sslverify'] = false;
				$response = wp_remote_post( $url, $args );
			}
		}

		if ( is_wp_error( $response ) ) {
			throw new Exception( $response->get_error_message() );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status ) {
			throw new Exception( 'HTTP ' . $status );
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Melipayamak RetStatus / Value error dictionary (official codes).
	 *
	 * @param int   $code Status code.
	 * @param array $res  Raw response (Value may carry the error code too).
	 * @return string
	 */
	private function error_message( $code, $res = array() ) {
		$messages = array(
			'-110' => __( 'الزام استفاده از ApiKey به جای رمز عبور.', 'salesbin' ),
			'-109' => __( 'IP سرور شما در پنل ملی پیامک مجاز نشده است.', 'salesbin' ),
			'-108' => __( 'IP به دلیل تلاش ناموفق زیاد مسدود شده است.', 'salesbin' ),
			'-10'  => __( 'لینک در متغیرهای ارسالی وجود دارد.', 'salesbin' ),
			'-7'   => __( 'خطایی در شماره فرستنده رخ داده است.', 'salesbin' ),
			'-6'   => __( 'خطای داخلی پنل؛ بعداً تلاش کنید.', 'salesbin' ),
			'-5'   => __( 'متن با متغیرهای پیش‌فرض همخوانی ندارد.', 'salesbin' ),
			'-4'   => __( 'کد متن ارسالی صحیح نیست یا تایید نشده است.', 'salesbin' ),
			'-3'   => __( 'خط ارسالی در سیستم تعریف نشده است.', 'salesbin' ),
			'-2'   => __( 'محدودیت تعداد شماره (هر بار یک شماره).', 'salesbin' ),
			'-1'   => __( 'دسترسی به وب‌سرویس در پنل غیرفعال است.', 'salesbin' ),
			'0'    => __( 'نام کاربری یا رمز عبور (ApiKey) ملی پیامک اشتباه است.', 'salesbin' ),
			'2'    => __( 'اعتبار پنل پیامک کافی نیست.', 'salesbin' ),
			'3'    => __( 'محدودیت در ارسال روزانه.', 'salesbin' ),
			'4'    => __( 'محدودیت در حجم ارسال.', 'salesbin' ),
			'5'    => __( 'شماره فرستنده معتبر نیست.', 'salesbin' ),
			'6'    => __( 'سامانه در حال به‌روزرسانی است.', 'salesbin' ),
			'7'    => __( 'متن پیامک حاوی کلمه فیلترشده است.', 'salesbin' ),
			'9'    => __( 'ارسال از خطوط عمومی از طریق وب‌سرویس ممکن نیست.', 'salesbin' ),
			'10'   => __( 'کاربر پنل فعال نیست.', 'salesbin' ),
			'11'   => __( 'پیامک ارسال نشده است.', 'salesbin' ),
			'12'   => __( 'مدارک کاربر کامل نیست.', 'salesbin' ),
			'14'   => __( 'متن پیامک حاوی لینک است.', 'salesbin' ),
			'15'   => __( 'ارسال به بیش از یک شماره ممکن نیست.', 'salesbin' ),
			'16'   => __( 'شماره گیرنده یافت نشد.', 'salesbin' ),
			'17'   => __( 'متن پیامک خالی است.', 'salesbin' ),
			'18'   => __( 'شماره گیرنده نامعتبر است.', 'salesbin' ),
			'35'   => __( 'شماره گیرنده در لیست سیاه مخابرات است.', 'salesbin' ),
		);

		// Some panels return the error inside Value when RetStatus is missing.
		if ( ! isset( $messages[ (string) $code ] ) && isset( $res['Value'] ) && isset( $messages[ (string) $res['Value'] ] ) ) {
			return __( 'خطای ملی پیامک: ', 'salesbin' ) . $messages[ (string) $res['Value'] ];
		}

		$key = (string) $code;
		if ( isset( $messages[ $key ] ) ) {
			return __( 'خطای ملی پیامک: ', 'salesbin' ) . $messages[ $key ];
		}
		return sprintf( __( 'خطای ملی پیامک (کد %s).', 'salesbin' ), $key );
	}
}
