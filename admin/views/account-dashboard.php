<?php
/**
 * Account dashboard endpoint content. Variables prepared by Salesbin_Account_Panel::render_dashboard():
 * $sb_user, $sb_data (stats), $sb_flags, $sb_widgets. All data is real (WC customer data).
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sb_name = $sb_user->exists() ? $sb_user->display_name : '';
$sb_hour = (int) current_time( 'G' );
if ( $sb_hour < 12 ) {
	$sb_greeting = __( 'صبح بخیر', 'salesbin' );
} elseif ( $sb_hour < 18 ) {
	$sb_greeting = __( 'بعدازظهر بخیر', 'salesbin' );
} else {
	$sb_greeting = __( 'شب بخیر', 'salesbin' );
}

$sb_icons    = Salesbin_Account_Panel::icons();
$sb_statuses = array(
	'completed'  => array( 'is-completed', 'card' ),
	'processing' => array( 'is-processing', 'box' ),
	'on-hold'    => array( 'is-on-hold', 'card' ),
	'pending'    => array( 'is-pending', 'wallet' ),
	'cancelled'  => array( 'is-cancelled', 'ticket' ),
	'refunded'   => array( 'is-refunded', 'star' ),
);

$sb_quick = array(
	array( 'orders', 'orders', __( 'سفارش‌های من', 'salesbin' ) ),
	array( 'downloads', 'downloads', __( 'فایل‌ها و دانلودها', 'salesbin' ) ),
	array( 'edit-address', 'address', __( 'آدرس‌های من', 'salesbin' ) ),
	array( 'edit-account', 'user', __( 'جزئیات حساب', 'salesbin' ) ),
	array( 'payment-methods', 'card', __( 'روش‌های پرداخت', 'salesbin' ) ),
	array( 'customer-logout', 'logout', __( 'خروج از حساب', 'salesbin' ) ),
);
?>
<div class="sb-account__dash">

	<?php if ( $sb_flags['welcome'] ) : ?>
		<section class="sb-account__hero sb-magic">
			<div>
				<h2><?php echo esc_html( sprintf( __( '%s، %s 👋', 'salesbin' ), $sb_greeting, $sb_name ) ); ?></h2>
				<p class="salesbin-muted"><?php echo esc_html__( 'به حساب کاربری خود خوش آمدید. وضعیت سفارش‌ها و فایل‌های شما اینجاست.', 'salesbin' ); ?></p>
			</div>
			<div class="sb-account__hero-since">
				<span class="salesbin-muted"><?php echo esc_html__( 'عضو از', 'salesbin' ); ?></span>
				<strong><?php echo esc_html( $sb_data['registered'] ); ?></strong>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $sb_flags['stats'] ) : ?>
		<section class="sb-account__kpis" aria-label="<?php esc_attr_e( 'آمار حساب', 'salesbin' ); ?>">
			<article class="sb-account__kpi sb-account__kpi--featured sb-magic">
				<p class="sb-account__kpi-label"><?php echo esc_html__( 'مجموع خرید شما', 'salesbin' ); ?></p>
				<p class="sb-account__kpi-value" data-sb-money><span data-sb-count="<?php echo esc_attr( (string) $sb_data['spent'] ); ?>">۰</span></p>
				<div class="sb-account__kpi-meta">
					<span class="salesbin-muted"><?php echo esc_html__( 'تقریبی، سفارش‌های پرداخت‌شده', 'salesbin' ); ?></span>
				</div>
			</article>
			<article class="sb-account__kpi sb-magic">
				<p class="sb-account__kpi-label"><?php echo esc_html__( 'تعداد سفارش‌ها', 'salesbin' ); ?></p>
				<p class="sb-account__kpi-value"><span data-sb-count="<?php echo esc_attr( (string) $sb_data['orders'] ); ?>">۰</span></p>
			</article>
			<article class="sb-account__kpi sb-magic">
				<p class="sb-account__kpi-label"><?php echo esc_html__( 'فایل‌های قابل دانلود', 'salesbin' ); ?></p>
				<p class="sb-account__kpi-value"><span data-sb-count="<?php echo esc_attr( (string) $sb_data['downloads'] ); ?>">۰</span></p>
			</article>
			<article class="sb-account__kpi sb-account__kpi--date sb-magic">
				<p class="sb-account__kpi-label"><?php echo esc_html__( 'امروز', 'salesbin' ); ?></p>
				<p class="sb-account__kpi-value sb-account__date-j"><?php echo esc_html( Salesbin_Helpers::jalali_date() ); ?></p>
				<p class="sb-account__date-g"><?php echo esc_html( wp_date( 'l — Y/m/d' ) ); ?></p>
			</article>
			<article class="sb-account__kpi sb-account__kpi--reviews sb-magic">
				<p class="sb-account__kpi-label"><?php echo esc_html__( 'نظرات شما', 'salesbin' ); ?></p>
				<p class="sb-account__kpi-value"><span data-sb-count="<?php echo esc_attr( (string) $sb_data['comments_total'] ); ?>">۰</span></p>
				<div class="sb-account__kpi-meta">
					<span class="salesbin-muted"><?php echo esc_html( sprintf( __( '%d نظر در انتظار تایید', 'salesbin' ), (int) $sb_data['comments_pending'] ) ); ?></span>
				</div>
			</article>
			<?php
			// Extension point: extra stat cards from integrations (wallet, loyalty, ...).
			foreach ( (array) $sb_data as $sb_key => $sb_val ) :
				if ( ! is_array( $sb_val ) || empty( $sb_val['stat_card'] ) || empty( $sb_val['label'] ) ) {
					continue;
				}
				?>
				<article class="sb-account__kpi sb-magic">
					<p class="sb-account__kpi-label"><?php echo esc_html( $sb_val['label'] ); ?></p>
					<p class="sb-account__kpi-value"><?php echo esc_html( isset( $sb_val['value'] ) ? (string) $sb_val['value'] : '' ); ?></p>
				</article>
			<?php endforeach; ?>
		</section>
	<?php endif; ?>

	<div class="sb-account__grid">

		<?php if ( $sb_flags['recent'] ) : ?>
			<section class="sb-account__card sb-magic sb-account__card--orders">
				<div class="sb-account__card-head">
					<h3><?php echo esc_html__( 'سفارش‌های اخیر', 'salesbin' ); ?></h3>
					<a class="sb-account__more" href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>"><?php echo esc_html__( 'مشاهده همه', 'salesbin' ); ?></a>
				</div>
				<?php if ( empty( $sb_data['recent'] ) ) : ?>
					<div class="sb-account__empty">
						<?php echo esc_html__( 'هنوز سفارشی ثبت نکرده‌اید.', 'salesbin' ); ?>
						<a class="salesbin-btn salesbin-btn--primary" href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ) ); ?>"><?php echo esc_html__( 'شروع خرید', 'salesbin' ); ?></a>
					</div>
				<?php else : ?>
					<div class="sb-account__orders">
						<?php foreach ( $sb_data['recent'] as $sb_o ) : ?>
							<div class="sb-account__order">
								<div>
									<strong><?php echo esc_html( sprintf( __( 'سفارش %s', 'salesbin' ), $sb_o['number'] ) ); ?></strong>
									<div class="salesbin-muted"><?php echo esc_html( $sb_o['date'] ); ?></div>
								</div>
								<div class="sb-account__order-meta">
									<span class="sb-account__status is-<?php echo esc_attr( $sb_o['status'] ); ?>"><?php echo esc_html( $sb_o['status_label'] ); ?></span>
									<strong><?php echo esc_html( $sb_o['total_html'] ); ?></strong>
									<?php if ( $sb_o['pay_url'] ) : ?>
										<a class="salesbin-btn salesbin-btn--primary" href="<?php echo esc_url( $sb_o['pay_url'] ); ?>"><?php echo esc_html__( 'پرداخت', 'salesbin' ); ?></a>
									<?php else : ?>
										<a class="sb-account__more" href="<?php echo esc_url( $sb_o['view_url'] ); ?>"><?php echo esc_html__( 'مشاهده', 'salesbin' ); ?></a>
									<?php endif; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<?php if ( $sb_flags['breakdown'] ) : ?>
			<section class="sb-account__card sb-magic">
				<div class="sb-account__card-head"><h3><?php echo esc_html__( 'وضعیت سفارش‌ها', 'salesbin' ); ?></h3></div>
				<?php if ( empty( $sb_data['breakdown'] ) ) : ?>
					<div class="sb-account__empty"><?php echo esc_html__( 'سفارشی برای نمایش وجود ندارد.', 'salesbin' ); ?></div>
				<?php else : ?>
					<div class="sb-account__mini-grid">
						<?php foreach ( $sb_data['breakdown'] as $sb_slug => $sb_row ) : ?>
							<?php
							$sb_style = isset( $sb_statuses[ $sb_slug ] ) ? $sb_statuses[ $sb_slug ] : array( '', 'box' );
							$sb_icon  = isset( $sb_icons[ $sb_style[1] ] ) ? $sb_icons[ $sb_style[1] ] : $sb_icons['box'];
							?>
							<div class="sb-account__mini <?php echo esc_attr( $sb_style[0] ); ?>">
								<span class="sb-account__mini-icon"><?php echo $sb_icon; // phpcs:ignore WordPress.Security.EscapeOutput -- Font Awesome markup built in-code ?></span>
								<strong><?php echo esc_html( Salesbin_Helpers::fa_num( $sb_row['count'] ) ); ?></strong>
								<span class="salesbin-muted"><?php echo esc_html( $sb_row['label'] ); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<?php if ( $sb_flags['messages'] ) : ?>
			<section class="sb-account__card sb-magic">
				<div class="sb-account__card-head"><h3><?php echo esc_html__( 'پیام‌های مهم', 'salesbin' ); ?></h3></div>
				<?php if ( empty( $sb_data['messages'] ) ) : ?>
					<div class="sb-account__empty"><?php echo esc_html__( 'پیام مهمی برای شما وجود ندارد — همه‌چیز مرتب است. ✅', 'salesbin' ); ?></div>
				<?php else : ?>
					<div class="sb-account__messages">
						<?php foreach ( $sb_data['messages'] as $sb_m ) : ?>
							<div class="sb-account__message is-<?php echo esc_attr( $sb_m['type'] ); ?>">
								<span class="sb-account__message-dot" aria-hidden="true"></span>
								<p><?php echo esc_html( $sb_m['text'] ); ?></p>
								<a class="sb-account__more" href="<?php echo esc_url( $sb_m['url'] ); ?>"><?php echo esc_html( $sb_m['cta'] ); ?></a>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<?php if ( $sb_flags['quick'] ) : ?>
			<section class="sb-account__card sb-magic">
				<div class="sb-account__card-head"><h3><?php echo esc_html__( 'دسترسی سریع', 'salesbin' ); ?></h3></div>
				<div class="sb-account__quick">
					<?php foreach ( $sb_quick as $sb_q ) : ?>
						<a class="sb-account__quick-item" href="<?php echo esc_url( wc_get_account_endpoint_url( $sb_q[0] ) ); ?>">
							<span class="sb-account__quick-icon"><?php echo isset( $sb_icons[ $sb_q[1] ] ) ? $sb_icons[ $sb_q[1] ] : $sb_icons['box']; // phpcs:ignore WordPress.Security.EscapeOutput -- Font Awesome markup built in-code ?></span>
							<span><?php echo esc_html( $sb_q[2] ); ?></span>
						</a>
					<?php endforeach; ?>
					<?php
					// Admin-defined option cards (settings → account panel → custom links).
					foreach ( Salesbin_Account_Panel::custom_links() as $sb_link ) :
						?>
						<a class="sb-account__quick-item" href="<?php echo esc_url( $sb_link['url'] ); ?>"<?php echo $sb_link['external'] ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
							<span class="sb-account__quick-icon"><i class="fa-solid fa-link" aria-hidden="true"></i></span>
							<span><?php echo esc_html( $sb_link['title'] ); ?></span>
						</a>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<?php
		// Extension point: registered widgets (wallet, loyalty points, plugin notices...).
		foreach ( $sb_widgets as $sb_widget ) :
			if ( empty( $sb_widget['callback'] ) || ! is_callable( $sb_widget['callback'] ) ) {
				continue;
			}
			?>
			<section class="sb-account__card sb-magic sb-account__widget">
				<?php if ( ! empty( $sb_widget['title'] ) ) : ?>
					<div class="sb-account__card-head"><h3><?php echo esc_html( (string) $sb_widget['title'] ); ?></h3></div>
				<?php endif; ?>
				<div class="sb-account__widget-body">
					<?php call_user_func( $sb_widget['callback'], $sb_user ); ?>
				</div>
			</section>
		<?php endforeach; ?>
	</div>
</div>
