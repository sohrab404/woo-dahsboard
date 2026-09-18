<?php
/**
 * WooCommerce hooks that create notifications.
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Salesbin_Notification_Hooks
 */
class Salesbin_Notification_Hooks {

	/**
	 * Register.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'woocommerce_new_order', array( $this, 'on_new_order' ), 20, 2 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'on_status_changed' ), 20, 4 );
		add_action( 'woocommerce_order_refunded', array( $this, 'on_refund' ), 20, 2 );
		add_action( 'woocommerce_low_stock', array( $this, 'on_low_stock' ), 20, 1 );
		add_action( 'woocommerce_no_stock', array( $this, 'on_low_stock' ), 20, 1 );
		add_action( 'wp_insert_comment', array( $this, 'on_insert_comment' ), 20, 2 );
	}

	/**
	 * New order notifications.
	 *
	 * @param int           $order_id Order ID.
	 * @param WC_Order|null $order    Order.
	 * @return void
	 */
	public function on_new_order( $order_id, $order = null ) {
		if ( ! Salesbin_Settings::get( 'notify_new_order', 1 ) ) {
			return;
		}
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$window  = (int) Salesbin_Settings::get( 'new_order_notification_window', 60 );
		$created = $order->get_date_created();
		if ( $created && ( time() - $created->getTimestamp() ) > ( $window * MINUTE_IN_SECONDS ) ) {
			return;
		}

		Salesbin_Notification_Store::add(
			array(
				'type'        => 'new_order',
				'title'       => sprintf(
					/* translators: %s order number */
					__( 'سفارش جدید %s', 'salesbin' ),
					$order->get_order_number()
				),
				'description' => sprintf(
					/* translators: 1: total, 2: status */
					__( 'مبلغ %1$s — وضعیت: %2$s', 'salesbin' ),
					wp_strip_all_tags( wc_price( $order->get_total() ) ),
					wc_get_order_status_name( $order->get_status() )
				),
				'object_id'   => $order->get_id(),
				'link'        => $order->get_edit_order_url(),
			)
		);

		$this->maybe_high_value( $order );
	}

	/**
	 * Status attention + high value on processing/completed.
	 *
	 * @param int      $order_id Order ID.
	 * @param string   $from     From.
	 * @param string   $to       To.
	 * @param WC_Order $order    Order.
	 * @return void
	 */
	public function on_status_changed( $order_id, $from, $to, $order ) {
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order ) {
			return;
		}

		$attention = apply_filters( 'salesbin_attention_statuses', array( 'on-hold', 'pending', 'failed', 'cancelled' ) );
		if ( Salesbin_Settings::get( 'notify_needs_attention', 1 ) && in_array( $to, $attention, true ) ) {
			Salesbin_Notification_Store::add(
				array(
					'type'        => 'needs_attention',
					'title'       => sprintf(
						/* translators: %s order number */
						__( 'سفارش نیازمند بررسی %s', 'salesbin' ),
						$order->get_order_number()
					),
					'description' => wc_get_order_status_name( $to ),
					'object_id'   => $order->get_id(),
					'link'        => $order->get_edit_order_url(),
				)
			);
		}

		$this->maybe_high_value( $order );
	}

	/**
	 * High value threshold.
	 *
	 * @param WC_Order $order Order.
	 * @return void
	 */
	private function maybe_high_value( WC_Order $order ) {
		if ( ! Salesbin_Settings::get( 'notify_high_value', 1 ) ) {
			return;
		}
		$threshold = (float) Salesbin_Settings::get( 'high_value_order_threshold', 0 );
		if ( $threshold <= 0 ) {
			return;
		}
		if ( (float) $order->get_total() < $threshold ) {
			return;
		}
		Salesbin_Notification_Store::add(
			array(
				'type'        => 'high_value',
				'title'       => sprintf(
					/* translators: %s order number */
					__( 'سفارش با مبلغ بالا %s', 'salesbin' ),
					$order->get_order_number()
				),
				'description' => sprintf(
					/* translators: 1: total 2: threshold */
					__( 'مبلغ %1$s از آستانه %2$s بیشتر است.', 'salesbin' ),
					wp_strip_all_tags( wc_price( $order->get_total() ) ),
					wp_strip_all_tags( wc_price( $threshold ) )
				),
				'object_id'   => $order->get_id(),
				'link'        => $order->get_edit_order_url(),
			)
		);
	}

	/**
	 * Refund notification.
	 *
	 * @param int $order_id  Order ID.
	 * @param int $refund_id Refund ID.
	 * @return void
	 */
	public function on_refund( $order_id, $refund_id ) {
		if ( ! Salesbin_Settings::get( 'notify_refund', 1 ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		Salesbin_Notification_Store::add(
			array(
				'type'        => 'refund',
				'title'       => sprintf(
					/* translators: %s order number */
					__( 'مرجوعی سفارش %s', 'salesbin' ),
					$order->get_order_number()
				),
				'description' => __( 'یک بازپرداخت برای سفارش ثبت شد.', 'salesbin' ),
				'object_id'   => absint( $refund_id ? $refund_id : $order_id ),
				'link'        => $order->get_edit_order_url(),
			)
		);
	}

	/**
	 * Low stock.
	 *
	 * @param WC_Product $product Product.
	 * @return void
	 */
	public function on_low_stock( $product ) {
		if ( ! Salesbin_Settings::get( 'notify_low_stock', 1 ) ) {
			return;
		}
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		if ( ! $product->managing_stock() ) {
			return;
		}
		Salesbin_Notification_Store::add(
			array(
				'type'        => 'low_stock',
				'title'       => sprintf(
					/* translators: %s product name */
					__( 'موجودی کم: %s', 'salesbin' ),
					$product->get_name()
				),
				'description' => sprintf(
					/* translators: %d stock */
					__( 'موجودی فعلی: %d', 'salesbin' ),
					(int) $product->get_stock_quantity()
				),
				'object_id'   => $product->get_id(),
				'link'        => get_edit_post_link( $product->get_id(), 'raw' ),
			)
		);
	}

	/**
	 * Pending product reviews.
	 *
	 * @param int         $id      Comment ID.
	 * @param WP_Comment  $comment Comment.
	 * @return void
	 */
	public function on_insert_comment( $id, $comment ) {
		if ( ! Salesbin_Settings::get( 'notify_pending_review', 1 ) ) {
			return;
		}
		if ( ! $comment instanceof WP_Comment ) {
			return;
		}
		if ( 'product' !== get_post_type( $comment->comment_post_ID ) ) {
			return;
		}
		if ( '0' !== (string) $comment->comment_approved ) {
			return;
		}
		Salesbin_Notification_Store::add(
			array(
				'type'        => 'pending_review',
				'title'       => __( 'نظر در انتظار تایید', 'salesbin' ),
				'description' => get_the_title( $comment->comment_post_ID ),
				'object_id'   => absint( $id ),
				'link'        => admin_url( 'edit-comments.php?comment_status=moderated' ),
			)
		);
	}
}
