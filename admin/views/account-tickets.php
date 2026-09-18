<?php
/**
 * Customer tickets list + new ticket form. Rendered inside the account panel.
 * Variables: $query, $flash, $action_url.
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$status_labels = array(
	'open'     => __( 'در انتظار بررسی', 'salesbin' ),
	'answered' => __( 'پاسخ داده شد', 'salesbin' ),
	'closed'   => __( 'بسته شده', 'salesbin' ),
);
?>
<div class="sb-account__dash">

	<section class="sb-account__hero sb-magic">
		<div>
			<h2><?php esc_html_e( 'تیکت‌های پشتیبانی', 'salesbin' ); ?></h2>
			<p class="salesbin-muted"><?php esc_html_e( 'سوال یا مشکل خود را مطرح کنید؛ تیم پشتیبانی پاسخ می‌دهد.', 'salesbin' ); ?></p>
		</div>
	</section>

	<?php if ( $flash ) : ?>
		<div class="salesbin-alert salesbin-alert--ok" role="status"><?php echo esc_html( $flash ); ?></div>
	<?php endif; ?>

	<section class="sb-account__card sb-magic">
		<div class="sb-account__card-head"><h3><?php esc_html_e( 'تیکت جدید', 'salesbin' ); ?></h3></div>
		<form class="sb-ticket-form" method="post" action="<?php echo esc_url( $action_url ); ?>">
			<input type="hidden" name="action" value="salesbin_ticket_new" />
			<?php wp_nonce_field( 'salesbin_ticket', 'salesbin_ticket_nonce' ); ?>
			<label class="salesbin-field">
				<span><?php esc_html_e( 'موضوع', 'salesbin' ); ?></span>
				<input type="text" name="subject" class="salesbin-input" required maxlength="150" />
			</label>
			<label class="salesbin-field">
				<span><?php esc_html_e( 'متن پیام', 'salesbin' ); ?></span>
				<textarea name="message" class="salesbin-input" rows="4" required></textarea>
			</label>
			<button type="submit" class="salesbin-btn salesbin-btn--primary"><?php esc_html_e( 'ارسال تیکت', 'salesbin' ); ?></button>
		</form>
	</section>

	<section class="sb-account__card sb-magic">
		<div class="sb-account__card-head"><h3><?php esc_html_e( 'تیکت‌های من', 'salesbin' ); ?></h3></div>
		<?php if ( empty( $query['items'] ) ) : ?>
			<div class="sb-account__empty"><?php esc_html_e( 'هنوز تیکتی ثبت نکرده‌اید.', 'salesbin' ); ?></div>
		<?php else : ?>
			<div class="sb-account__orders-list">
				<?php foreach ( $query['items'] as $t ) : ?>
					<div class="sb-account__order">
						<div class="sb-account__order-main">
							<a class="sb-account__ticket-subject" href="<?php echo esc_url( add_query_arg( 'ticket', (int) $t['id'], wc_get_account_endpoint_url( 'tickets' ) ) ); ?>">
								<?php echo esc_html( sprintf( __( 'تیکت #%1$d — %2$s', 'salesbin' ), (int) $t['id'], $t['subject'] ) ); ?>
							</a>
							<div class="salesbin-muted"><?php echo esc_html( Salesbin_Helpers::jalali_date( strtotime( $t['updated_at'] ) ) ); ?></div>
						</div>
						<div class="sb-account__order-meta">
							<span class="sb-account__ticket-status is-<?php echo esc_attr( $t['status'] ); ?>"><?php echo esc_html( $status_labels[ $t['status'] ] ?? $t['status'] ); ?></span>
							<a class="sb-account__more" href="<?php echo esc_url( add_query_arg( 'ticket', (int) $t['id'], wc_get_account_endpoint_url( 'tickets' ) ) ); ?>"><?php esc_html_e( 'مشاهده گفتگو', 'salesbin' ); ?></a>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<?php if ( $query['pages'] > 1 ) : ?>
				<p class="salesbin-muted sb-ticket-pages"><?php echo esc_html( sprintf( __( 'صفحه %1$d از %2$d', 'salesbin' ), $query['page'], $query['pages'] ) ); ?></p>
			<?php endif; ?>
		<?php endif; ?>
	</section>
</div>
