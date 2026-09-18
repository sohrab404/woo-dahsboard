<?php
/**
 * Admin tickets page (list + thread). Variables: $view_ticket, $ticket, $replies,
 * $query, $status, $page, $status_labels, $base_url.
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap" dir="rtl">
	<h1><?php esc_html_e( 'تیکت‌های پشتیبانی', 'salesbin' ); ?></h1>

	<?php if ( $view_ticket && $ticket ) : ?>

		<p><a class="button" href="<?php echo esc_url( $base_url ); ?>">← <?php esc_html_e( 'بازگشت به لیست', 'salesbin' ); ?></a></p>

		<div class="card" style="max-width:820px;padding:14px 18px;">
			<h2 style="margin-top:0;"><?php echo esc_html( sprintf( __( 'تیکت #%1$d — %2$s', 'salesbin' ), (int) $ticket['id'], $ticket['subject'] ) ); ?></h2>
			<p>
				<?php
				$author = get_userdata( (int) $ticket['user_id'] );
				echo esc_html( sprintf( __( 'مشتری: %s', 'salesbin' ), $author ? $author->display_name . ' (' . $author->user_email . ')' : '#' . $ticket['user_id'] ) );
				?>
				— <?php echo esc_html( $status_labels[ $ticket['status'] ] ?? $ticket['status'] ); ?>
			</p>

			<?php foreach ( $replies as $r ) : ?>
				<div style="border:1px solid #ccd0d4;border-radius:8px;padding:10px 14px;margin:10px 0;<?php echo $r['is_admin'] ? 'background:#f0f6fc;' : ''; ?>">
					<strong><?php echo $r['is_admin'] ? esc_html__( 'پشتیبانی', 'salesbin' ) : esc_html__( 'مشتری', 'salesbin' ); ?></strong>
					<span style="color:#777;font-size:12px;"><?php echo esc_html( $r['created_at'] ); ?></span>
					<p style="margin:6px 0 0;white-space:pre-wrap;"><?php echo esc_html( $r['message'] ); ?></p>
				</div>
			<?php endforeach; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:14px;">
				<input type="hidden" name="action" value="salesbin_ticket_admin_reply" />
				<input type="hidden" name="ticket_id" value="<?php echo esc_attr( (string) $ticket['id'] ); ?>" />
				<?php wp_nonce_field( 'salesbin_ticket_admin' ); ?>
				<label for="sb_admin_reply"><strong><?php esc_html_e( 'پاسخ شما', 'salesbin' ); ?></strong></label><br />
				<textarea name="message" id="sb_admin_reply" rows="4" class="large-text" required></textarea>
				<p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'ارسال پاسخ', 'salesbin' ); ?></button>
				</p>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:8px;align-items:center;">
				<input type="hidden" name="action" value="salesbin_ticket_status" />
				<input type="hidden" name="ticket_id" value="<?php echo esc_attr( (string) $ticket['id'] ); ?>" />
				<?php wp_nonce_field( 'salesbin_ticket_admin' ); ?>
				<select name="status">
					<?php foreach ( $status_labels as $sb_status => $sb_label ) : ?>
						<option value="<?php echo esc_attr( $sb_status ); ?>" <?php selected( $ticket['status'], $sb_status ); ?>><?php echo esc_html( $sb_label ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="button"><?php esc_html_e( 'تغییر وضعیت', 'salesbin' ); ?></button>
			</form>
		</div>

	<?php else : ?>

		<ul class="subsubsub" style="display:flex;gap:12px;list-style:none;margin:0 0 12px;">
			<?php
			$sb_filters = array( '' => __( 'همه', 'salesbin' ), 'open' => __( 'در انتظار بررسی', 'salesbin' ), 'answered' => __( 'پاسخ داده شد', 'salesbin' ), 'closed' => __( 'بسته شده', 'salesbin' ) );
			foreach ( $sb_filters as $sb_key => $sb_label ) :
				$sb_url = $sb_key ? add_query_arg( 'status', $sb_key, $base_url ) : $base_url;
				?>
				<li><a href="<?php echo esc_url( $sb_url ); ?>" <?php echo $status === $sb_key ? 'style="font-weight:700;color:#2271b1;"' : ''; ?>><?php echo esc_html( $sb_label ); ?></a></li>
			<?php endforeach; ?>
		</ul>

		<table class="widefat striped">
			<thead>
				<tr>
					<th>#</th>
					<th><?php esc_html_e( 'موضوع', 'salesbin' ); ?></th>
					<th><?php esc_html_e( 'مشتری', 'salesbin' ); ?></th>
					<th><?php esc_html_e( 'وضعیت', 'salesbin' ); ?></th>
					<th><?php esc_html_e( 'آخرین بروزرسانی', 'salesbin' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $query['items'] ) ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'تیکتی وجود ندارد.', 'salesbin' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $query['items'] as $t ) : ?>
						<?php
						$author = get_userdata( (int) $t['user_id'] );
						?>
						<tr>
							<td><?php echo esc_html( (string) $t['id'] ); ?></td>
							<td><strong><?php echo esc_html( $t['subject'] ); ?></strong></td>
							<td><?php echo esc_html( $author ? $author->display_name : '#' . $t['user_id'] ); ?></td>
							<td><?php echo esc_html( $status_labels[ $t['status'] ] ?? $t['status'] ); ?></td>
							<td><?php echo esc_html( $t['updated_at'] ); ?></td>
							<td><a class="button" href="<?php echo esc_url( add_query_arg( 'ticket', (int) $t['id'], $base_url ) ); ?>"><?php esc_html_e( 'مشاهده و پاسخ', 'salesbin' ); ?></a></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<?php if ( $query['pages'] > 1 ) : ?>
			<p style="margin-top:12px;">
				<?php for ( $sb_i = 1; $sb_i <= $query['pages']; $sb_i++ ) : ?>
					<?php $sb_link = add_query_arg( 'tpaged', $sb_i, $status ? add_query_arg( 'status', $status, $base_url ) : $base_url ); ?>
					<a class="button" style="<?php echo $sb_i === $query['page'] ? 'pointer-events:none;opacity:.5;' : ''; ?>" href="<?php echo esc_url( $sb_link ); ?>"><?php echo esc_html( (string) $sb_i ); ?></a>
				<?php endfor; ?>
			</p>
		<?php endif; ?>

	<?php endif; ?>
</div>
