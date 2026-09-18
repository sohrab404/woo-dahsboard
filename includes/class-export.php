<?php
/**
 * Streaming CSV export.
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Salesbin_Export
 */
class Salesbin_Export {

	/**
	 * @var Salesbin_Export|null
	 */
	private static $instance = null;

	/**
	 * @return Salesbin_Export
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'admin_post_salesbin_export_orders', array( $this, 'export_orders' ) );
		add_action( 'admin_post_salesbin_export_products', array( $this, 'export_products' ) );
	}

	/**
	 * Guard request.
	 *
	 * @return void
	 */
	private function guard() {
		if ( ! Salesbin_Capabilities::current_user_can_view() ) {
			wp_die( esc_html__( 'دسترسی غیرمجاز.', 'salesbin' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'salesbin_export' );
	}

	/**
	 * Open CSV stream with UTF-8 BOM for Excel.
	 *
	 * @param string $filename Filename.
	 * @return resource
	 */
	private function open_stream( $filename ) {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'X-Content-Type-Options: nosniff' );

		$out = fopen( 'php://output', 'w' );
		if ( ! $out ) {
			wp_die( esc_html__( 'امکان ایجاد خروجی وجود ندارد.', 'salesbin' ) );
		}
		fwrite( $out, "\xEF\xBB\xBF" );
		return $out;
	}

	/**
	 * Write a CSV row with formula protection.
	 *
	 * @param resource $out  Stream.
	 * @param array    $row  Row.
	 * @return void
	 */
	private function put( $out, array $row ) {
		$safe = array();
		foreach ( $row as $cell ) {
			$safe[] = Salesbin_Helpers::csv_safe( (string) $cell );
		}
		fputcsv( $out, $safe );
	}

	/**
	 * Export orders in chunks.
	 *
	 * @return void
	 */
	public function export_orders() {
		$this->guard();

		$preset = sanitize_key( wp_unslash( $_GET['range'] ?? '30d' ) );
		$start  = isset( $_GET['start'] ) ? sanitize_text_field( wp_unslash( $_GET['start'] ) ) : null;
		$end    = isset( $_GET['end'] ) ? sanitize_text_field( wp_unslash( $_GET['end'] ) ) : null;
		$range  = Salesbin_Date_Range::resolve( $preset, $start, $end );
		if ( is_wp_error( $range ) ) {
			wp_die( esc_html( $range->get_error_message() ) );
		}

		$statuses = Salesbin_Helpers::parse_statuses( wp_unslash( $_GET['status'] ?? '' ) );
		if ( empty( $statuses ) ) {
			$statuses = array_keys( Salesbin_Helpers::all_order_statuses() );
		}

		$filename = 'salesbin-orders-' . $range['label_start'] . '-' . $range['label_end'] . '.csv';
		$out      = $this->open_stream( $filename );
		$this->put(
			$out,
			array(
				__( 'شناسه', 'salesbin' ),
				__( 'شماره', 'salesbin' ),
				__( 'تاریخ', 'salesbin' ),
				__( 'وضعیت', 'salesbin' ),
				__( 'مشتری', 'salesbin' ),
				__( 'ایمیل', 'salesbin' ),
				__( 'مبلغ', 'salesbin' ),
				__( 'ارز', 'salesbin' ),
				__( 'روش پرداخت', 'salesbin' ),
			)
		);

		$page     = 1;
		$per_page = 100;
		$pii      = Salesbin_Capabilities::current_user_can_view_pii();
		$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '';

		do {
			$result = wc_get_orders(
				array(
					'type'         => 'shop_order',
					'status'       => $statuses,
					'limit'        => $per_page,
					'page'         => $page,
					'paginate'     => true,
					'orderby'      => 'date',
					'order'        => 'DESC',
					'date_created' => $range['start_local'] . '...' . $range['end_local'],
					'return'       => 'objects',
				)
			);
			$orders = ( is_object( $result ) && isset( $result->orders ) ) ? $result->orders : array();
			$max    = ( is_object( $result ) && isset( $result->max_num_pages ) ) ? (int) $result->max_num_pages : 1;

			foreach ( $orders as $order ) {
				if ( ! $order instanceof WC_Order ) {
					continue;
				}
				$this->put(
					$out,
					array(
						$order->get_id(),
						$order->get_order_number(),
						$order->get_date_created() ? $order->get_date_created()->date_i18n( 'Y-m-d H:i:s' ) : '',
						$order->get_status(),
						$order->get_formatted_billing_full_name(),
						$pii ? $order->get_billing_email() : '',
						$order->get_total(),
						$currency,
						$order->get_payment_method_title(),
					)
				);
			}

			$page++;
		} while ( $page <= $max && $page < 10000 );

		fclose( $out );
		exit;
	}

	/**
	 * Export catalog products in chunks.
	 *
	 * @return void
	 */
	public function export_products() {
		$this->guard();

		$out = $this->open_stream( 'salesbin-products.csv' );
		$this->put(
			$out,
			array(
				__( 'شناسه', 'salesbin' ),
				__( 'نام', 'salesbin' ),
				__( 'SKU', 'salesbin' ),
				__( 'قیمت', 'salesbin' ),
				__( 'موجودی', 'salesbin' ),
				__( 'مدیریت موجودی', 'salesbin' ),
				__( 'وضعیت', 'salesbin' ),
			)
		);

		$page = 1;
		do {
			$query = new WP_Query(
				array(
					'post_type'      => 'product',
					'post_status'    => array( 'publish', 'private', 'draft' ),
					'posts_per_page' => 100,
					'paged'          => $page,
					'fields'         => 'ids',
					'orderby'        => 'ID',
					'order'          => 'ASC',
				)
			);
			foreach ( $query->posts as $id ) {
				$product = wc_get_product( $id );
				if ( ! $product ) {
					continue;
				}
				$stock = $product->managing_stock() ? (string) $product->get_stock_quantity() : '';
				$this->put(
					$out,
					array(
						$product->get_id(),
						$product->get_name(),
						$product->get_sku(),
						$product->get_price(),
						$stock,
						$product->managing_stock() ? 'yes' : 'no',
						$product->get_status(),
					)
				);
			}
			$max = (int) $query->max_num_pages;
			$page++;
			wp_reset_postdata();
		} while ( $page <= $max && $page < 10000 );

		fclose( $out );
		exit;
	}
}
