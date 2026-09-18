<?php
/**
 * Notifications page.
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="salesbin-app salesbin-app--page" dir="rtl">
	<header class="salesbin-header">
		<div>
			<h1 class="salesbin-title"><?php echo esc_html__( 'اعلان‌ها', 'salesbin' ); ?></h1>
			<p class="salesbin-subtitle"><?php echo esc_html__( 'مرکز اعلان‌های وودش', 'salesbin' ); ?></p>
		</div>
		<button type="button" class="salesbin-btn" id="salesbin-read-all"><?php echo esc_html__( 'خواندن همه', 'salesbin' ); ?></button>
	</header>
	<main class="salesbin-main">
		<section class="salesbin-card">
			<div id="salesbin-note-list" class="salesbin-notes"></div>
		</section>
	</main>
</div>
