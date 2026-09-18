<?php
/**
 * Sales metric documentation helper.
 *
 * Total Sales shown on KPI cards = Net Sales:
 *   net = gross order totals for default sales statuses minus refunds in the same period.
 * Cancelled / Failed / Pending / Trash are excluded unless the chart status filter includes them.
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Salesbin_Sales_Calculator
 */
class Salesbin_Sales_Calculator {

	/**
	 * Build a KPI comparison payload.
	 *
	 * @param float $current  Current.
	 * @param float $previous Previous.
	 * @return array
	 */
	public static function compare( $current, $previous ) {
		$growth = Salesbin_Helpers::growth_percent( $current, $previous );
		$trend  = 'flat';
		if ( null === $growth ) {
			$trend = ( (float) $current > 0 ) ? 'new' : 'empty';
		} elseif ( $growth > 0.05 ) {
			$trend = 'up';
		} elseif ( $growth < -0.05 ) {
			$trend = 'down';
		}

		return array(
			'current'  => $current,
			'previous' => $previous,
			'growth'   => null === $growth ? null : round( $growth, 1 ),
			'trend'    => $trend,
		);
	}
}
