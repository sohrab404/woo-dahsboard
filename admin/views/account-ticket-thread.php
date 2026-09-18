<?php
/**
 * Customer ticket thread + reply form.
 * Variables: $ticket, $replies, $action_url, $flash.
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
			<h2><?php echo esc_html( sprintf( __( 'تیکت #%1$d — %2$s', 'salesbin' ), (int) $ticket['id'], $ticket['subject'] ) ); ?></h2>
			<p class="salesbin-muted"><?php echo esc_html( sprintf( __( 'وضعیت: %s', 'salesbin' ), $status_labels[ $ticket['status'] ] ?? $ticket['status'] ) ); ?></p>
		</div>
		<a class="salesbin-btn" href="<?php echo esc_url( wc_get_account_endpoint_url( 'tickets' ) ); ?>"><?php esc_html_e( 'بازگشت به لیست', 'salesbin' ); ?></a>
	</section>

	<?php if ( $flash ) : ?>
		<div class="salesbin-alert salesbin-alert--ok" role="status"><?php echo esc_html( $flash ); ?></div>
	<?php endif; ?>

	<section class="sb-account__card sb-magic sb-ticket-thread">
		<?php foreach ( $replies as $r ) : ?>
			<div class="sb-ticket-reply<?php echo $r['is_admin'] ? ' is-admin' : ''; ?>">
				<div class="sb-ticket-reply__head">
					<strong><?php echo $r['is_admin'] ? esc_html__( 'پشتیبانی', 'salesbin' ) : esc_html__( 'شما', 'salesbin' ); ?></strong>
					<span class="salesbin-muted"><?php echo esc_html( Salesbin_Helpers::jalali_date( strtotime( $r['created_at'] ) ) ); ?></span>
				</div>
				<p><?php echo esc_html( $r['message'] ); ?></p>
			</div>
		<?php endforeach; ?>
	</section>

	<?php if ( 'closed' !== $ticket['status'] ) : ?>
		<section class="sb-account__card sb-magic">
			<div class="sb-account__card-head"><h3><?php esc_html_e( 'پاسخ جدید', 'salesbin' ); ?></h3></div>
			<form class="sb-ticket-form" method="post" action="<?php echo esc_url( $action_url ); ?>">
				<input type="hidden" name="action" value="salesbin_ticket_reply" />
				<input type="hidden" name="ticket_id" value="<?php echo esc_attr( (string) $ticket['id'] ); ?>" />
				<?php wp_nonce_field( 'salesbin_ticket', 'salesbin_ticket_nonce' ); ?>
				<label class="salesbin-field">
					<span><?php esc_html_e( 'متن پاسخ', 'salesbin' ); ?></span>
					<textarea name="message" class="salesbin-input" rows="4" required></textarea>
				</label>
				<button type="submit" class="salesbin-btn salesbin-btn--primary"><?php esc_html_e( 'ارسال پاسخ', 'salesbin' ); ?></button>
			</form>
		</section>
	<?php else : ?>
		<div class="sb-account__empty"><?php esc_html_e( 'این تیکت بسته شده است.', 'salesbin' ); ?></div>
	<?php endif; ?>
</div>
