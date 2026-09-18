<?php
/**
 * Date range resolver in WordPress timezone. Backend stores Gregorian.
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Salesbin_Date_Range
 */
class Salesbin_Date_Range {

	/**
	 * Parse request into current + previous comparable periods.
	 *
	 * @param string      $preset  7d|30d|90d|1y|custom.
	 * @param string|null $start   Y-m-d for custom.
	 * @param string|null $end     Y-m-d for custom.
	 * @return array|WP_Error
	 */
	public static function resolve( $preset, $start = null, $end = null ) {
		$preset = sanitize_key( (string) $preset );
		$tz     = wp_timezone();
		$now    = new DateTimeImmutable( 'now', $tz );

		try {
			if ( 'custom' === $preset ) {
				if ( ! $start || ! $end ) {
					return new WP_Error( 'salesbin_invalid_date_range', __( 'بازه زمانی سفارشی نامعتبر است.', 'salesbin' ), array( 'status' => 400 ) );
				}
				$start_dt = DateTimeImmutable::createFromFormat( 'Y-m-d', sanitize_text_field( $start ), $tz );
				$end_dt   = DateTimeImmutable::createFromFormat( 'Y-m-d', sanitize_text_field( $end ), $tz );
				if ( ! $start_dt || ! $end_dt ) {
					return new WP_Error( 'salesbin_invalid_date_range', __( 'قالب تاریخ نامعتبر است.', 'salesbin' ), array( 'status' => 400 ) );
				}
				$start_dt = $start_dt->setTime( 0, 0, 0 );
				$end_dt   = $end_dt->setTime( 23, 59, 59 );
				if ( $end_dt < $start_dt ) {
					return new WP_Error( 'salesbin_invalid_date_range', __( 'تاریخ پایان باید بعد از تاریخ شروع باشد.', 'salesbin' ), array( 'status' => 400 ) );
				}
				$max = $start_dt->modify( '+2 years' );
				if ( $end_dt > $max ) {
					return new WP_Error( 'salesbin_invalid_date_range', __( 'حداکثر بازه مجاز دو سال است.', 'salesbin' ), array( 'status' => 400 ) );
				}
			} else {
				$map = array(
					'7d'  => '6 days',
					'30d' => '29 days',
					'90d' => '89 days',
					'1y'  => '1 year -1 day',
				);
				if ( ! isset( $map[ $preset ] ) ) {
					$preset = '30d';
				}
				$end_dt   = $now->setTime( 23, 59, 59 );
				$start_dt = $now->modify( '-' . $map[ $preset ] )->setTime( 0, 0, 0 );
			}
		} catch ( Exception $e ) {
			return new WP_Error( 'salesbin_invalid_date_range', __( 'بازه زمانی نامعتبر است.', 'salesbin' ), array( 'status' => 400 ) );
		}

		$duration   = $end_dt->getTimestamp() - $start_dt->getTimestamp() + 1;
		$prev_end   = $start_dt->modify( '-1 second' );
		$prev_start = $prev_end->modify( '-' . ( $duration - 1 ) . ' seconds' )->setTime( 0, 0, 0 );

		$today_start = $now->setTime( 0, 0, 0 );
		$today_end   = $now->setTime( 23, 59, 59 );

		return array(
			'preset'           => $preset,
			'start'            => $start_dt,
			'end'              => $end_dt,
			'previous_start'   => $prev_start,
			'previous_end'     => $prev_end,
			'today_start'      => $today_start,
			'today_end'        => $today_end,
			'start_gmt'        => $start_dt->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
			'end_gmt'          => $end_dt->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
			'previous_start_gmt' => $prev_start->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
			'previous_end_gmt' => $prev_end->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
			'today_start_gmt'  => $today_start->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
			'today_end_gmt'    => $today_end->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
			'start_local'      => $start_dt->format( 'Y-m-d H:i:s' ),
			'end_local'        => $end_dt->format( 'Y-m-d H:i:s' ),
			'label_start'      => $start_dt->format( 'Y-m-d' ),
			'label_end'        => $end_dt->format( 'Y-m-d' ),
		);
	}

	/**
	 * Bucket size for charts.
	 *
	 * @param DateTimeInterface $start Start.
	 * @param DateTimeInterface $end   End.
	 * @return string day|week|month
	 */
	public static function bucket( DateTimeInterface $start, DateTimeInterface $end ) {
		$days = ( $end->getTimestamp() - $start->getTimestamp() ) / DAY_IN_SECONDS;
		if ( $days > 180 ) {
			return 'month';
		}
		if ( $days > 60 ) {
			return 'week';
		}
		return 'day';
	}
}
