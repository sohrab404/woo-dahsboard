<?php
/**
 * Sales chart series.
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Salesbin_Chart_Service
 */
class Salesbin_Chart_Service {

	/**
	 * @param array    $range    Range.
	 * @param string[] $statuses Statuses.
	 * @return array
	 */
	public function get( array $range, array $statuses = array() ) {
		return Salesbin_Cache::remember(
			'sales',
			array( 'chart', $range['start_gmt'], $range['end_gmt'], $statuses ),
			function () use ( $range, $statuses ) {
				$bucket = Salesbin_Date_Range::bucket( $range['start'], $range['end'] );
				$series = Salesbin_Order_Query::series( $range['start_gmt'], $range['end_gmt'], $statuses, $bucket );
				$series = $this->fill_gaps( $series, $range, $bucket );
				return array(
					'bucket'   => $bucket,
					'series'   => $series,
					'currency' => Salesbin_Helpers::currency_meta(),
				);
			}
		);
	}

	/**
	 * Fill empty buckets with zeros so charts stay continuous.
	 *
	 * @param array  $series Series.
	 * @param array  $range  Range.
	 * @param string $bucket Bucket.
	 * @return array
	 */
	private function fill_gaps( array $series, array $range, $bucket ) {
		$indexed = array();
		foreach ( $series as $point ) {
			$indexed[ $point['date'] ] = $point;
		}

		$out    = array();
		$cursor = $range['start'];
		$end    = $range['end'];

		// Align the cursor to the bucket boundary so the last partial bucket is never skipped.
		if ( 'month' === $bucket ) {
			$cursor = $cursor->setDate( (int) $cursor->format( 'Y' ), (int) $cursor->format( 'n' ), 1 );
			$step   = '+1 month';
			$fmt    = 'Y-m-01';
		} elseif ( 'week' === $bucket ) {
			$cursor = $cursor->modify( '-' . ( (int) $cursor->format( 'N' ) - 1 ) . ' days' );
			$step   = '+1 week';
			$fmt    = 'Y-m-d';
		} else {
			$step = '+1 day';
			$fmt  = 'Y-m-d';
		}

		$guard = 0;
		while ( $cursor <= $end && $guard < 800 ) {
			$key = $cursor->format( $fmt );
			if ( isset( $indexed[ $key ] ) ) {
				$out[] = $indexed[ $key ];
			} else {
				$out[] = array(
					'date'   => $key,
					'sales'  => 0.0,
					'orders' => 0,
					'aov'    => 0.0,
				);
			}
			$cursor = $cursor->modify( $step );
			$guard++;
		}

		return $out;
	}
}
