<?php
/**
 * Dashboard KPI stats.
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Salesbin_Stats_Service
 */
class Salesbin_Stats_Service {

	/**
	 * @param array    $range    Date range.
	 * @param string[] $statuses Statuses.
	 * @return array
	 */
	public function get( array $range, array $statuses = array() ) {
		$key_parts = array( $range['start_gmt'], $range['end_gmt'], $statuses, 'stats' );
		return Salesbin_Cache::remember(
			'sales',
			$key_parts,
			function () use ( $range, $statuses ) {
				$current  = Salesbin_Order_Query::aggregate( $range['start_gmt'], $range['end_gmt'], $statuses );
				$previous = Salesbin_Order_Query::aggregate( $range['previous_start_gmt'], $range['previous_end_gmt'], $statuses );
				$low      = ( new Salesbin_Stock_Service() )->count_low_stock();

				$map = array(
					'net_sales'     => array( $current['net'], $previous['net'] ),
					'gross_sales'   => array( $current['gross'], $previous['gross'] ),
					'orders'        => array( $current['orders'], $previous['orders'] ),
					'customers'     => array( $current['customers'], $previous['customers'] ),
					'new_customers' => array( $current['new_customers'], $previous['new_customers'] ),
					'aov'           => array( $current['aov'], $previous['aov'] ),
					'items'         => array( $current['items'], $previous['items'] ),
					'products'      => array( $current['products'], $previous['products'] ),
				);

				$kpis = array();
				foreach ( $map as $id => $pair ) {
					$kpis[ $id ] = Salesbin_Sales_Calculator::compare( $pair[0], $pair[1] );
				}

				$kpis['low_stock'] = Salesbin_Sales_Calculator::compare( $low, $low );
				$kpis['low_stock']['growth'] = null;
				$kpis['low_stock']['trend']  = $low > 0 ? 'warn' : 'flat';

				return array(
					'kpis'     => $kpis,
					'metrics'  => array(
						'total_sales_means' => 'net',
						'definition'        => 'Net sales = gross totals of qualifying orders minus refunds in the period. Cancelled and failed orders are excluded by default.',
					),
					'currency' => Salesbin_Helpers::currency_meta(),
				);
			}
		);
	}
}
