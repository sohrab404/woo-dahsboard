<?php
/**
 * Dashboard shell. Data is hydrated via REST without full page reloads.
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="salesbin-app" id="salesbin-app" dir="rtl" lang="fa" data-layout="<?php echo esc_attr( isset( $salesbin_layout ) ? $salesbin_layout : 'full' ); ?>">
	<a class="salesbin-skip" href="#salesbin-main"><?php echo esc_html__( 'پرش به محتوا', 'salesbin' ); ?></a>

	<header class="salesbin-header">
		<div class="salesbin-brand">
			<div class="salesbin-logo salesbin-logo--dv" aria-hidden="true">DV</div>
			<div>
				<h1 class="salesbin-title"><?php echo esc_html__( 'وودش', 'salesbin' ); ?></h1>
				<p class="salesbin-subtitle"><?php echo esc_html__( 'داشبورد مانیتورینگ و تحلیل فروش', 'salesbin' ); ?></p>
			</div>
		</div>
		<div class="salesbin-toolbar">
			<div class="salesbin-range" role="group" aria-label="<?php echo esc_attr__( 'بازه زمانی', 'salesbin' ); ?>">
				<button type="button" class="salesbin-chip is-active" data-range="7d">۷ روز</button>
				<button type="button" class="salesbin-chip" data-range="30d">۳۰ روز</button>
				<button type="button" class="salesbin-chip" data-range="90d">۹۰ روز</button>
				<button type="button" class="salesbin-chip" data-range="1y">۱ سال</button>
				<button type="button" class="salesbin-chip" data-range="custom" id="salesbin-custom-btn"><?php echo esc_html__( 'بازه دلخواه', 'salesbin' ); ?></button>
			</div>
			<button type="button" class="salesbin-btn salesbin-btn--primary" id="salesbin-refresh"><?php echo esc_html__( 'به‌روزرسانی', 'salesbin' ); ?></button>
			<select id="salesbin-theme" data-sb-theme-control class="salesbin-select" aria-label="<?php echo esc_attr__( 'تم رنگی', 'salesbin' ); ?>"></select>
			<button type="button" class="salesbin-btn salesbin-btn--ghost" id="salesbin-mode" data-sb-mode-toggle aria-label="<?php echo esc_attr__( 'حالت شب و روز', 'salesbin' ); ?>">☀️</button>
			<button type="button" class="salesbin-btn salesbin-btn--ghost" id="salesbin-bell" aria-haspopup="true" aria-expanded="false">
				<?php echo esc_html__( 'اعلان‌ها', 'salesbin' ); ?>
				<span class="salesbin-badge" id="salesbin-bell-count" hidden>0</span>
			</button>
		</div>
	</header>

	<div class="salesbin-custom" id="salesbin-custom" hidden>
		<label>
			<span><?php echo esc_html__( 'از', 'salesbin' ); ?></span>
			<input type="text" id="salesbin-start" class="salesbin-input" inputmode="numeric" autocomplete="off" />
		</label>
		<label>
			<span><?php echo esc_html__( 'تا', 'salesbin' ); ?></span>
			<input type="text" id="salesbin-end" class="salesbin-input" inputmode="numeric" autocomplete="off" />
		</label>
		<button type="button" class="salesbin-btn" id="salesbin-apply-custom"><?php echo esc_html__( 'اعمال', 'salesbin' ); ?></button>
		<p class="salesbin-hint"><?php echo esc_html__( 'تاریخ را به صورت شمسی وارد کنید؛ مثلاً ۱۴۰۳/۰۶/۰۱', 'salesbin' ); ?></p>
	</div>

	<div class="salesbin-drawer" id="salesbin-drawer" hidden>
		<div class="salesbin-drawer__head">
			<strong><?php echo esc_html__( 'اعلان‌ها', 'salesbin' ); ?></strong>
			<button type="button" class="salesbin-link" id="salesbin-read-all"><?php echo esc_html__( 'خواندن همه', 'salesbin' ); ?></button>
		</div>
		<div id="salesbin-note-list"></div>
	</div>

	<main id="salesbin-main" class="salesbin-main">
		<section class="salesbin-kpis" id="salesbin-kpis" aria-label="<?php echo esc_attr__( 'شاخص‌های کلیدی', 'salesbin' ); ?>"></section>

		<div class="salesbin-grid salesbin-grid--2">
			<section class="salesbin-card" id="salesbin-chart-card">
				<div class="salesbin-card__head">
					<h2><?php echo esc_html__( 'نمودار فروش', 'salesbin' ); ?></h2>
					<div class="salesbin-inline">
						<select id="salesbin-metric" class="salesbin-select" aria-label="<?php echo esc_attr__( 'معیار نمودار', 'salesbin' ); ?>"></select>
						<select id="salesbin-chart-type" class="salesbin-select" aria-label="<?php echo esc_attr__( 'نوع نمودار', 'salesbin' ); ?>"></select>
						<select id="salesbin-status" class="salesbin-select" aria-label="<?php echo esc_attr__( 'وضعیت سفارش', 'salesbin' ); ?>"></select>
					</div>
				</div>
				<div class="salesbin-chart" id="salesbin-chart"></div>
			</section>

			<section class="salesbin-card" id="salesbin-orders-card">
				<div class="salesbin-card__head">
					<h2><?php echo esc_html__( 'سفارشات اخیر', 'salesbin' ); ?></h2>
					<div class="salesbin-inline">
						<label class="salesbin-mini">
							<?php echo esc_html__( 'در هر صفحه', 'salesbin' ); ?>
							<select id="salesbin-per-page" class="salesbin-select"></select>
						</label>
					</div>
				</div>
				<div id="salesbin-orders" class="salesbin-table-wrap"></div>
			</section>
		</div>

		<div class="salesbin-grid salesbin-grid--2">
			<section class="salesbin-card" id="salesbin-products-card">
				<div class="salesbin-card__head">
					<h2><?php echo esc_html__( 'محصولات پرفروش', 'salesbin' ); ?></h2>
					<select id="salesbin-product-sort" class="salesbin-select"></select>
				</div>
				<div id="salesbin-products"></div>
			</section>
			<section class="salesbin-card salesbin-full-only" id="salesbin-customers-card">
				<div class="salesbin-card__head">
					<h2><?php echo esc_html__( 'مشتریان برتر', 'salesbin' ); ?></h2>
				</div>
				<div id="salesbin-customers"></div>
			</section>
		</div>

		<div class="salesbin-grid salesbin-grid--2 salesbin-full-only">
			<section class="salesbin-card" id="salesbin-cats-card">
				<div class="salesbin-card__head">
					<h2><?php echo esc_html__( 'فروش بر اساس دسته', 'salesbin' ); ?></h2>
				</div>
				<div id="salesbin-cats"></div>
			</section>
			<section class="salesbin-card" id="salesbin-pay-card">
				<div class="salesbin-card__head">
					<h2><?php echo esc_html__( 'روش‌های پرداخت', 'salesbin' ); ?></h2>
				</div>
				<div id="salesbin-pay"></div>
			</section>
		</div>

		<section class="salesbin-card salesbin-full-only" id="salesbin-heat-card">
			<div class="salesbin-card__head">
				<h2><?php echo esc_html__( 'ساعت‌های اوج خرید', 'salesbin' ); ?></h2>
				<select id="salesbin-heat-metric" class="salesbin-select"></select>
			</div>
			<div id="salesbin-heat" class="salesbin-heat"></div>
		</section>

		<div class="salesbin-grid salesbin-grid--2 salesbin-full-only">
			<section class="salesbin-card" id="salesbin-stock-card">
				<div class="salesbin-card__head">
					<h2><?php echo esc_html__( 'هشدار موجودی کم', 'salesbin' ); ?></h2>
				</div>
				<div id="salesbin-stock"></div>
			</section>
			<section class="salesbin-card" id="salesbin-goal-card">
				<div class="salesbin-card__head">
					<h2><?php echo esc_html__( 'هدف فروش روزانه', 'salesbin' ); ?></h2>
				</div>
				<div id="salesbin-goal"></div>
			</section>
		</div>
	</main>

	<footer class="salesbin-footer">
		<div>
			<button type="button" class="salesbin-link" id="salesbin-export-orders"><?php echo esc_html__( 'خروجی سفارشات CSV', 'salesbin' ); ?></button>
			<button type="button" class="salesbin-link" id="salesbin-export-products"><?php echo esc_html__( 'خروجی محصولات CSV', 'salesbin' ); ?></button>
			<a class="salesbin-link" href="<?php echo esc_url( admin_url( 'admin.php?page=salesbin-settings' ) ); ?>"><?php echo esc_html__( 'تنظیمات', 'salesbin' ); ?></a>
		</div>
		<p id="salesbin-updated"></p>
	</footer>
</div>
