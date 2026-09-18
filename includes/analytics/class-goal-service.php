<?php
/**
 * Daily sales goal.
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Salesbin_Goal_Service
 */
class Salesbin_Goal_Service {

	/**
	 * Today's net sales vs daily goal.
	 *
	 * @param array $range Full range (uses today_* keys).
	 * @return array
	 */
	public function get( array $range ) {
		$goal = (float) Salesbin_Settings::get( 'daily_sales_goal', 0 );
		$today = Salesbin_Helpers::timezone()->format( 'Y-m-d' );

		$agg = Salesbin_Cache::remember(
			'sales',
			array( 'today-agg', $today, $goal > 0 ),
			function () use ( $range ) {
				return Salesbin_Order_Query::aggregate( $range['today_start_gmt'], $range['today_end_gmt'], Salesbin_Helpers::default_sales_statuses() );
			},
			MINUTE_IN_SECONDS
		);
		$sales = (float) $agg['net'];

		$pct = 0.0;
		if ( $goal > 0 ) {
			$pct = ( $sales / $goal ) * 100;
		}
		$bar = min( 100, $pct );

		return array(
			'sales'          => $sales,
			'sales_html'     => Salesbin_Helpers::format_money( $sales ),
			'goal'           => $goal,
			'goal_html'      => Salesbin_Helpers::format_money( $goal ),
			'progress'       => round( $pct, 1 ),
			'bar'            => round( $bar, 1 ),
			'remaining'      => max( 0, $goal - $sales ),
			'remaining_html' => Salesbin_Helpers::format_money( max( 0, $goal - $sales ) ),
			'exceeded'       => $goal > 0 && $sales > $goal,
			'goal_zero'      => $goal <= 0,
			'orders'         => $agg['orders'],
			'currency'       => Salesbin_Helpers::currency_meta(),
		);
	}
}

