<?php
/**
 * My Orders — card-based replacement for WooCommerce's orders table,
 * served while the Woodesh account panel is active. Uses real customer data
 * and the standard WC pagination.
 *
 * @package Salesbin
 */

defined( 'ABSPATH' ) || exit;

$customer_orders = wc_get_orders(
	array(
		'customer' => get_current_user_id(),
		'limit'    => 10,
		'paged'    => max( 1, (int) get_query_var( 'paged' ) ),
		'orderby'  => 'date',
		'order'    => 'DESC',
		'type'     => 'shop_order',
	)
);
$customer_orders = is_array( $customer_orders ) ? $customer_orders : array();
$status_labels   = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array();
?>

<div class="sb-account__dash sb-account__orders-view">

	<section class="sb-account__hero sb-magic">
		<div>
			<h2><?php esc_html_e( 'سفارش‌های من', 'salesbin' ); ?></h2>
			<p class="salesbin-muted"><?php esc_html_e( 'تاریخچه سفارش‌ها، وضعیت ارسال و لینک پرداخت هر سفارش.', 'salesbin' ); ?></p>
		</div>
	</section>

	<?php if ( empty( $customer_orders ) ) : ?>
		<section class="sb-account__card">
			<div class="sb-account__empty">
				<?php esc_html_e( 'هنوز سفارشی ثبت نکرده‌اید.', 'salesbin' ); ?>
				<a class="salesbin-btn salesbin-btn--primary" href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ) ); ?>"><?php esc_html_e( 'شروع خرید', 'salesbin' ); ?></a>
			</div>
		</section>
	<?php else : ?>
		<section class="sb-account__card sb-magic sb-account__card--orders">
			<div class="sb-account__orders-list">
				<?php foreach ( $customer_orders as $order ) : ?>
					<?php if ( ! $order instanceof WC_Order ) { continue; } ?>
					<div class="sb-account__order">
						<div class="sb-account__order-main">
							<strong><?php echo esc_html( sprintf( __( 'سفارش %s', 'salesbin' ), $order->get_order_number() ) ); ?></strong>
							<div class="salesbin-muted">
								<?php echo esc_html( $order->get_date_created() ? $order->get_date_created()->date_i18n( 'Y/m/d — H:i' ) : '' ); ?>
								<?php $item_count = $order->get_item_count(); if ( $item_count ) : ?>
									· <?php echo esc_html( sprintf( _n( '%d قلم کالا', '%d قلم کالا', $item_count, 'salesbin' ), $item_count ) ); ?>
								<?php endif; ?>
							</div>
						</div>
						<div class="sb-account__order-meta">
							<span class="sb-account__status is-<?php echo esc_attr( $order->get_status() ); ?>">
								<?php echo esc_html( isset( $status_labels[ 'wc-' . $order->get_status() ] ) ? $status_labels[ 'wc-' . $order->get_status() ] : $order->get_status() ); ?>
							</span>
							<strong><?php echo esc_html( wp_strip_all_tags( wc_price( $order->get_total() ) ) ); ?></strong>
							<?php if ( $order->needs_payment() ) : ?>
								<a class="salesbin-btn salesbin-btn--primary" href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>"><?php esc_html_e( 'پرداخت', 'salesbin' ); ?></a>
							<?php endif; ?>
							<a class="sb-account__more" href="<?php echo esc_url( $order->get_view_order_url() ); ?>"><?php esc_html_e( 'جزئیات', 'salesbin' ); ?></a>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</section>

		<?php
		$count_query = wc_get_orders(
			array(
				'customer' => get_current_user_id(),
				'limit'    => -1,
				'return'   => 'ids',
				'type'     => 'shop_order',
			)
		);
		$total  = is_array( $count_query ) ? count( $count_query ) : 0;
		$pages  = (int) ceil( $total / 10 );
		if ( $pages > 1 ) {
			echo '<nav class="sb-account__pager">';
			echo paginate_links(
				array(
					'base'      => esc_url_raw( wc_get_account_endpoint_url( 'orders' ) ) . '%_%',
					'format'    => user_trailingslashit( '%#%', 'paged' ),
					'current'   => max( 1, (int) get_query_var( 'paged' ) ),
					'total'     => $pages,
					'prev_text' => '‹',
					'next_text' => '›',
					'type'      => 'list',
				)
			);
			echo '</nav>';
		}
		?>
	<?php endif; ?>
</div>
