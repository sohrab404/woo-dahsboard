<?php
/**
 * Settings form (Settings API) — sectioned dark UI with live theme preview.
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$s      = Salesbin_Settings::all();
$option = Salesbin_Settings::OPTION_KEY;
$login  = Salesbin_Login_Module::instance();
?>
<div class="wrap salesbin-settings" dir="rtl">
	<header class="sb-settings-header">
		<div class="sb-settings-brand">
			<span class="salesbin-logo salesbin-logo--dv" aria-hidden="true">DV</span>
			<div>
				<h1 class="salesbin-title"><?php echo esc_html__( 'تنظیمات وودش', 'salesbin' ); ?></h1>
				<p class="salesbin-subtitle"><?php echo esc_html__( 'پیکربندی داشبورد، ظاهر، اعلان‌ها و ماژول ورود', 'salesbin' ); ?></p>
			</div>
		</div>
		<p class="sb-settings-note"><?php echo esc_html__( 'پیش‌نمایش ظاهر، بلافاصله با تغییر تم اعمال می‌شود.', 'salesbin' ); ?></p>
	</header>

	<?php if ( isset( $_GET['sb_rebuilt'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<?php
		$sb_result = sanitize_key( wp_unslash( $_GET['sb_rebuilt'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$sb_ok     = in_array( $sb_result, array( 'login', 'account' ), true );
		$sb_texts  = array(
			'login'        => __( 'صفحه ورود و ثبت‌نام با موفقیت بازسازی شد.', 'salesbin' ),
			'account'      => __( 'صفحه «حساب کاربری من» با موفقیت بازسازی شد.', 'salesbin' ),
			'login-failed' => __( 'بازسازی صفحه ورود انجام نشد؛ دوباره تلاش کنید.', 'salesbin' ),
		);
		?>
		<div class="salesbin-alert salesbin-alert--<?php echo $sb_ok ? 'ok' : 'error'; ?>" role="status">
			<?php echo esc_html( isset( $sb_texts[ $sb_result ] ) ? $sb_texts[ $sb_result ] : '' ); ?>
		</div>
	<?php endif; ?>

	<?php $sb_sms_test = get_transient( Salesbin_Login_Module::TEST_TRANSIENT ); ?>
	<?php if ( is_array( $sb_sms_test ) ) : ?>
		<div class="salesbin-alert salesbin-alert--<?php echo empty( $sb_sms_test['ok'] ) ? 'error' : 'ok'; ?>" role="status">
			<strong><?php echo esc_html__( 'تست ملی‌پیامک:', 'salesbin' ); ?></strong>
			<?php echo esc_html( (string) ( $sb_sms_test['message'] ?? '' ) ); ?>
		</div>
		<?php delete_transient( Salesbin_Login_Module::TEST_TRANSIENT ); ?>
	<?php endif; ?>

	<nav class="sb-tabs" role="tablist" aria-label="<?php echo esc_attr__( 'بخش‌های تنظیمات', 'salesbin' ); ?>">
		<button type="button" class="sb-tab is-active" data-tab="general" role="tab"><?php echo esc_html__( 'عمومی', 'salesbin' ); ?></button>
		<button type="button" class="sb-tab" data-tab="appearance" role="tab"><?php echo esc_html__( 'ظاهر', 'salesbin' ); ?></button>
		<button type="button" class="sb-tab" data-tab="notifications" role="tab"><?php echo esc_html__( 'اعلان‌ها', 'salesbin' ); ?></button>
		<button type="button" class="sb-tab" data-tab="account" role="tab"><?php echo esc_html__( 'پنل حساب', 'salesbin' ); ?></button>
		<button type="button" class="sb-tab" data-tab="login" role="tab"><?php echo esc_html__( 'صفحه ورود', 'salesbin' ); ?></button>
		<button type="button" class="sb-tab" data-tab="advanced" role="tab"><?php echo esc_html__( 'پیشرفته', 'salesbin' ); ?></button>
	</nav>

	<form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post">
		<?php settings_fields( 'salesbin' ); ?>

		<!-- ==================== GENERAL ==================== -->
		<section class="sb-panel is-active" data-panel="general" role="tabpanel">
			<div class="sb-card">
				<h2><?php echo esc_html__( 'داشبورد', 'salesbin' ); ?></h2>
				<div class="sb-field">
					<label for="salesbin_range"><?php echo esc_html__( 'بازه پیش‌فرض داشبورد', 'salesbin' ); ?></label>
					<select name="<?php echo esc_attr( $option ); ?>[default_date_range]" id="salesbin_range" class="salesbin-select">
						<option value="7d" <?php selected( $s['default_date_range'], '7d' ); ?>><?php echo esc_html__( '۷ روز', 'salesbin' ); ?></option>
						<option value="30d" <?php selected( $s['default_date_range'], '30d' ); ?>><?php echo esc_html__( '۳۰ روز', 'salesbin' ); ?></option>
						<option value="90d" <?php selected( $s['default_date_range'], '90d' ); ?>><?php echo esc_html__( '۹۰ روز', 'salesbin' ); ?></option>
						<option value="1y" <?php selected( $s['default_date_range'], '1y' ); ?>><?php echo esc_html__( '۱ سال', 'salesbin' ); ?></option>
					</select>
				</div>
				<div class="sb-field">
					<label for="salesbin_chart"><?php echo esc_html__( 'نوع نمودار پیش‌فرض', 'salesbin' ); ?></label>
					<select name="<?php echo esc_attr( $option ); ?>[default_chart_type]" id="salesbin_chart" class="salesbin-select">
						<option value="line" <?php selected( $s['default_chart_type'], 'line' ); ?>><?php echo esc_html__( 'خطی', 'salesbin' ); ?></option>
						<option value="bar" <?php selected( $s['default_chart_type'], 'bar' ); ?>><?php echo esc_html__( 'میله‌ای', 'salesbin' ); ?></option>
					</select>
				</div>
				<div class="sb-field">
					<label for="salesbin_ppp"><?php echo esc_html__( 'سفارش در هر صفحه', 'salesbin' ); ?></label>
					<select name="<?php echo esc_attr( $option ); ?>[orders_per_page]" id="salesbin_ppp" class="salesbin-select">
						<?php foreach ( array( 10, 20, 50, 100 ) as $n ) : ?>
							<option value="<?php echo esc_attr( $n ); ?>" <?php selected( (int) $s['orders_per_page'], $n ); ?>><?php echo esc_html( (string) $n ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="sb-field">
					<label for="salesbin_refresh"><?php echo esc_html__( 'رفرش خودکار داشبورد', 'salesbin' ); ?></label>
					<select name="<?php echo esc_attr( $option ); ?>[auto_refresh_interval]" id="salesbin_refresh" class="salesbin-select">
						<option value="0" <?php selected( (int) $s['auto_refresh_interval'], 0 ); ?>><?php echo esc_html__( 'غیرفعال', 'salesbin' ); ?></option>
						<option value="30" <?php selected( (int) $s['auto_refresh_interval'], 30 ); ?>><?php echo esc_html__( 'هر ۳۰ ثانیه', 'salesbin' ); ?></option>
						<option value="60" <?php selected( (int) $s['auto_refresh_interval'], 60 ); ?>><?php echo esc_html__( 'هر ۱ دقیقه', 'salesbin' ); ?></option>
						<option value="120" <?php selected( (int) $s['auto_refresh_interval'], 120 ); ?>><?php echo esc_html__( 'هر ۲ دقیقه', 'salesbin' ); ?></option>
						<option value="300" <?php selected( (int) $s['auto_refresh_interval'], 300 ); ?>><?php echo esc_html__( 'هر ۵ دقیقه', 'salesbin' ); ?></option>
					</select>
					<p class="description"><?php echo esc_html__( 'در تب‌های غیرفعال مرورگر رفرش متوقف می‌شود.', 'salesbin' ); ?></p>
				</div>
			</div>

			<div class="sb-card">
				<h2><?php echo esc_html__( 'هدف فروش', 'salesbin' ); ?></h2>
				<div class="sb-field">
					<label for="salesbin_goal"><?php echo esc_html__( 'هدف فروش روزانه', 'salesbin' ); ?></label>
					<input name="<?php echo esc_attr( $option ); ?>[daily_sales_goal]" id="salesbin_goal" type="number" min="0" step="0.01" class="regular-text sb-input" value="<?php echo esc_attr( $s['daily_sales_goal'] ); ?>" />
					<p class="description"><?php echo esc_html( sprintf( __( 'واحد پول فروشگاه: %s', 'salesbin' ), function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '' ) ); ?></p>
				</div>
			</div>
		</section>

		<!-- ==================== APPEARANCE ==================== -->
		<section class="sb-panel" data-panel="appearance" role="tabpanel" hidden>
			<div class="sb-card">
				<h2><?php echo esc_html__( 'تم رنگی و حالت شب/روز', 'salesbin' ); ?></h2>
				<div class="sb-field">
					<label for="salesbin_theme"><?php echo esc_html__( 'تم پیش‌فرض کاربران', 'salesbin' ); ?></label>
					<select name="<?php echo esc_attr( $option ); ?>[theme]" id="salesbin_theme" class="salesbin-select" data-sb-theme-control></select>
					<p class="description"><?php echo esc_html__( 'هر کاربر می‌تواند از هدر داشبورد تم و حالت دلخواه خودش را انتخاب کند؛ این گزینه فقط پیش‌فرض است.', 'salesbin' ); ?></p>
				</div>
				<div class="sb-field">
					<label for="salesbin_mode"><?php echo esc_html__( 'حالت پیش‌فرض', 'salesbin' ); ?></label>
					<select name="<?php echo esc_attr( $option ); ?>[mode]" id="salesbin_mode" class="salesbin-select" data-sb-mode-control>
						<option value="dark" <?php selected( $s['mode'], 'dark' ); ?>><?php echo esc_html__( 'شب (تیره)', 'salesbin' ); ?></option>
						<option value="light" <?php selected( $s['mode'], 'light' ); ?>><?php echo esc_html__( 'روز (روشن)', 'salesbin' ); ?></option>
					</select>
				</div>
				<div class="sb-swatches" aria-hidden="true">
					<?php
					$swatches = array(
						'woodesh'  => '#8b5cf6',
						'ocean'    => '#0ea5e9',
						'emerald'  => '#10b981',
						'rose'     => '#f43f5e',
						'sunset'   => '#f59e0b',
						'graphite' => '#9ca3af',
					);
					foreach ( $swatches as $slug => $color ) :
						?>
						<span class="sb-swatch<?php echo $slug === $s['theme'] ? ' is-active' : ''; ?>" data-swatch="<?php echo esc_attr( $slug ); ?>" style="--sw: <?php echo esc_attr( $color ); ?>;"><?php echo esc_html( $slug ); ?></span>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="sb-card">
				<h2><?php echo esc_html__( 'ویجت نوار مدیریت', 'salesbin' ); ?></h2>
				<div class="sb-field">
					<label for="salesbin_admin_font"><?php echo esc_html__( 'فونت پنل گزارش‌گیری (پیشخوان مدیر)', 'salesbin' ); ?></label>
					<select name="<?php echo esc_attr( $option ); ?>[admin_font]" id="salesbin_admin_font" class="salesbin-select">
						<?php foreach ( Salesbin_Settings::fonts() as $acc_font_slug => $acc_font ) : ?>
							<option value="<?php echo esc_attr( $acc_font_slug ); ?>" <?php selected( $s['admin_font'], $acc_font_slug ); ?>><?php echo esc_html( $acc_font['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<label class="sb-check">
					<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[admin_chrome_theme]" value="1" <?php checked( 1, (int) $s['admin_chrome_theme'] ); ?> />
					<?php echo esc_html__( 'اعمال تم وودش روی کل پیشخوان وردپرس (همه صفحه‌های مدیریتی)', 'salesbin' ); ?>
				</label>
				<label class="sb-check">
					<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[admin_bar_enabled]" value="1" <?php checked( 1, (int) $s['admin_bar_enabled'] ); ?> />
					<?php echo esc_html__( 'فعال‌سازی ویجت Admin Bar (فروش امروز + نمودار کوچک)', 'salesbin' ); ?>
				</label>
				<label class="sb-check">
					<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[admin_bar_show_low_stock]" value="1" <?php checked( 1, (int) $s['admin_bar_show_low_stock'] ); ?> />
					<?php echo esc_html__( 'نمایش تعداد کم‌موجودی‌ها در Admin Bar', 'salesbin' ); ?>
				</label>
			</div>
		</section>

		<!-- ==================== NOTIFICATIONS ==================== -->
		<section class="sb-panel" data-panel="notifications" role="tabpanel" hidden>
			<div class="sb-card">
				<h2><?php echo esc_html__( 'انواع اعلان', 'salesbin' ); ?></h2>
				<?php
				$checks = array(
					'notify_new_order'        => __( 'سفارش جدید', 'salesbin' ),
					'notify_low_stock'        => __( 'موجودی کم', 'salesbin' ),
					'notify_pending_review'   => __( 'نظر در انتظار تایید', 'salesbin' ),
					'notify_needs_attention'  => __( 'سفارش نیازمند بررسی', 'salesbin' ),
					'notify_high_value'       => __( 'سفارش با مبلغ بالا', 'salesbin' ),
					'notify_refund'           => __( 'مرجوعی / بازپرداخت', 'salesbin' ),
				);
				foreach ( $checks as $key => $label ) :
					?>
					<label class="sb-check">
						<input type="checkbox" name="<?php echo esc_attr( $option . '[' . $key . ']' ); ?>" value="1" <?php checked( 1, (int) $s[ $key ] ); ?> />
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>
			</div>

			<div class="sb-card">
				<h2><?php echo esc_html__( 'آستانه‌ها', 'salesbin' ); ?></h2>
				<div class="sb-field">
					<label for="salesbin_low_stock"><?php echo esc_html__( 'آستانه موجودی کم', 'salesbin' ); ?></label>
					<input name="<?php echo esc_attr( $option ); ?>[low_stock_threshold]" id="salesbin_low_stock" type="number" min="0" class="regular-text sb-input" value="<?php echo esc_attr( $s['low_stock_threshold'] ); ?>" />
					<p class="description"><?php echo esc_html__( 'اگر برای یک محصول «آستانه کم موجودی» اختصاصی ثبت شده باشد، همان اولویت دارد.', 'salesbin' ); ?></p>
				</div>
				<div class="sb-field">
					<label for="salesbin_high"><?php echo esc_html__( 'آستانه سفارش با مبلغ بالا', 'salesbin' ); ?></label>
					<input name="<?php echo esc_attr( $option ); ?>[high_value_order_threshold]" id="salesbin_high" type="number" min="0" step="0.01" class="regular-text sb-input" value="<?php echo esc_attr( $s['high_value_order_threshold'] ); ?>" />
				</div>
				<div class="sb-field">
					<label for="salesbin_window"><?php echo esc_html__( 'پنجره اعلان سفارش جدید (دقیقه)', 'salesbin' ); ?></label>
					<input name="<?php echo esc_attr( $option ); ?>[new_order_notification_window]" id="salesbin_window" type="number" min="1" class="regular-text sb-input" value="<?php echo esc_attr( $s['new_order_notification_window'] ); ?>" />
				</div>
				<div class="sb-field">
					<label for="salesbin_retention"><?php echo esc_html__( 'مدت نگهداری اعلان‌ها (روز)', 'salesbin' ); ?></label>
					<input name="<?php echo esc_attr( $option ); ?>[notifications_retention_days]" id="salesbin_retention" type="number" min="7" max="365" class="regular-text sb-input" value="<?php echo esc_attr( $s['notifications_retention_days'] ); ?>" />
					<p class="description"><?php echo esc_html__( 'روزانه اعلان‌های قدیمی‌تر از این بازه به‌صورت خودکار پاک می‌شوند.', 'salesbin' ); ?></p>
				</div>
			</div>
		</section>

		<!-- ==================== ACCOUNT PANEL ==================== -->
		<section class="sb-panel" data-panel="account" role="tabpanel" hidden>
			<div class="sb-card">
				<h2><?php echo esc_html__( 'داشبورد حساب کاربری ووکامرس', 'salesbin' ); ?></h2>
				<p class="description"><?php echo esc_html__( 'صفحه حساب کاربری خریداران به یک داشبورد مدرن هم‌سبک با پنل وودش تبدیل می‌شود. قابلیت‌های ووکامرس (سفارش‌ها، دانلودها، آدرس‌ها، جزئیات حساب و همه endpointهای سفارشی قالب/افزونه‌های دیگر) دست‌نخورده باقی می‌مانند.', 'salesbin' ); ?></p>
				<label class="sb-check">
					<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[account_panel_enabled]" value="1" <?php checked( 1, (int) $s['account_panel_enabled'] ); ?> />
					<?php echo esc_html__( 'فعال‌سازی پنل حساب کاربری جدید', 'salesbin' ); ?>
				</label>
				<p class="description"><?php echo esc_html__( 'با فعال‌سازی، پنل حساب هر قالبی (پیش‌فرض یا اختصاصی) جایگزین می‌شود. خروج اضطراری برای تست سازگاری: افزودن ?woodesh-account=0 به آدرس صفحه حساب.', 'salesbin' ); ?></p>
				<a class="salesbin-btn" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=salesbin_rebuild_account_page' ), 'salesbin_rebuild_account_page' ) ); ?>">
					<?php echo esc_html__( 'بازسازی صفحه «حساب کاربری من» ووکامرس', 'salesbin' ); ?>
				</a>
				<p class="description"><?php echo esc_html__( 'اگر صفحه حساب کاربری حذف شده باشد، دوباره ساخته و به ووکامرس متصل می‌شود.', 'salesbin' ); ?></p>
				<div class="sb-field">
					<label for="salesbin_acc_mode"><?php echo esc_html__( 'حالت پیش‌فرض (رنگ پس‌زمینه)', 'salesbin' ); ?></label>
					<select name="<?php echo esc_attr( $option ); ?>[account_panel_mode]" id="salesbin_acc_mode" class="salesbin-select">
						<option value="dark" <?php selected( $s['account_panel_mode'], 'dark' ); ?>><?php echo esc_html__( 'شب (تیره)', 'salesbin' ); ?></option>
						<option value="light" <?php selected( $s['account_panel_mode'], 'light' ); ?>><?php echo esc_html__( 'روز (روشن)', 'salesbin' ); ?></option>
					</select>
					<p class="description"><?php echo esc_html__( 'بازدیدکننده می‌تواند با دکمه ماه/خورشید در هدر، حالت دلخواه خودش را انتخاب کند.', 'salesbin' ); ?></p>
				</div>
				<div class="sb-field">
					<label for="salesbin_acc_accent"><?php echo esc_html__( 'رنگ اصلی داشبورد', 'salesbin' ); ?></label>
					<select name="<?php echo esc_attr( $option ); ?>[account_panel_accent]" id="salesbin_acc_accent" class="salesbin-select">
						<?php
						$acc_labels = array(
							'woodesh'  => __( 'بنفش وودش', 'salesbin' ),
							'ocean'    => __( 'اقیانوس', 'salesbin' ),
							'emerald'  => __( 'زمرد', 'salesbin' ),
							'rose'     => __( 'رز', 'salesbin' ),
							'sunset'   => __( 'غروب', 'salesbin' ),
							'graphite' => __( 'گرافیت', 'salesbin' ),
						);
						foreach ( $acc_labels as $acc_slug => $acc_label ) :
							?>
							<option value="<?php echo esc_attr( $acc_slug ); ?>" <?php selected( $s['account_panel_accent'], $acc_slug ); ?>><?php echo esc_html( $acc_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="sb-field">
					<label for="salesbin_acc_font"><?php echo esc_html__( 'فونت پنل کاربران', 'salesbin' ); ?></label>
					<select name="<?php echo esc_attr( $option ); ?>[account_font]" id="salesbin_acc_font" class="salesbin-select">
						<?php foreach ( Salesbin_Settings::fonts() as $acc_font_slug => $acc_font ) : ?>
							<option value="<?php echo esc_attr( $acc_font_slug ); ?>" <?php selected( $s['account_font'], $acc_font_slug ); ?>><?php echo esc_html( $acc_font['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<label class="sb-check">
					<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[account_panel_welcome]" value="1" <?php checked( 1, (int) $s['account_panel_welcome'] ); ?> />
					<?php echo esc_html__( 'نمایش بخش خوشامدگویی', 'salesbin' ); ?>
				</label>
				<label class="sb-check">
					<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[account_panel_stats]" value="1" <?php checked( 1, (int) $s['account_panel_stats'] ); ?> />
					<?php echo esc_html__( 'نمایش کارت‌های آماری', 'salesbin' ); ?>
				</label>
				<label class="sb-check">
					<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[account_panel_breakdown]" value="1" <?php checked( 1, (int) $s['account_panel_breakdown'] ); ?> />
					<?php echo esc_html__( 'نمایش وضعیت سفارش‌ها', 'salesbin' ); ?>
				</label>
				<label class="sb-check">
					<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[account_panel_recent]" value="1" <?php checked( 1, (int) $s['account_panel_recent'] ); ?> />
					<?php echo esc_html__( 'نمایش سفارش‌های اخیر', 'salesbin' ); ?>
				</label>
				<label class="sb-check">
					<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[account_panel_quick]" value="1" <?php checked( 1, (int) $s['account_panel_quick'] ); ?> />
					<?php echo esc_html__( 'نمایش دسترسی سریع', 'salesbin' ); ?>
				</label>
				<label class="sb-check">
					<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[account_panel_messages]" value="1" <?php checked( 1, (int) $s['account_panel_messages'] ); ?> />
					<?php echo esc_html__( 'نمایش پیام‌های مهم (پرداخت در انتظار، دانلودها، آدرس ناقص)', 'salesbin' ); ?>
				</label>
			</div>

			<div class="sb-card">
				<h2><?php echo esc_html__( 'آیتم‌های منوی حساب', 'salesbin' ); ?></h2>
				<p class="description"><?php echo esc_html__( 'همه endpointهای ووکامرس و هر endpoint سفارشی که قالب یا افزونه‌های دیگر ثبت کرده‌اند اینجا لیست می‌شوند. عنوان دلخواه بدهید، آیکون انتخاب کنید، نمایش بدهید/پنهان کنید و ترتیب را تغییر دهید. عدد کوچکتر بالاتر قرار می‌گیرد.', 'salesbin' ); ?></p>
				<?php if ( ! function_exists( 'wc_get_account_menu_items' ) ) : ?>
					<p class="description"><?php echo esc_html__( 'برای مدیریت منو، WooCommerce باید فعال باشد.', 'salesbin' ); ?></p>
				<?php else : ?>
					<?php
					$acc_items  = wc_get_account_menu_items();
					$acc_cfg    = (array) $s['account_menu'];
					$acc_icons  = Salesbin_Account_Panel::icons();
					$acc_index  = 0;
					?>
					<div class="sb-menu-manager">
						<?php foreach ( $acc_items as $acc_endpoint => $acc_label ) : ?>
							<?php
							$acc_row = isset( $acc_cfg[ $acc_endpoint ] ) && is_array( $acc_cfg[ $acc_endpoint ] ) ? $acc_cfg[ $acc_endpoint ] : array();
							$acc_visible = ! isset( $acc_row['visible'] ) || ! empty( $acc_row['visible'] );
							$acc_order   = isset( $acc_row['order'] ) ? (int) $acc_row['order'] : $acc_index * 10;
							$acc_index++;
							?>
							<div class="sb-menu-row">
								<label class="sb-check">
									<input type="checkbox" name="<?php echo esc_attr( $option . '[account_menu][' . $acc_endpoint . '][visible]' ); ?>" value="1" <?php checked( true, $acc_visible ); ?> />
								</label>
								<span class="sb-menu-row__endpoint"><?php echo esc_html( $acc_endpoint ); ?></span>
								<input type="text" class="sb-input sb-menu-row__label" name="<?php echo esc_attr( $option . '[account_menu][' . $acc_endpoint . '][label]' ); ?>" value="<?php echo esc_attr( $acc_row['label'] ?? '' ); ?>" placeholder="<?php echo esc_attr( $acc_label ); ?>" />
								<select class="salesbin-select sb-menu-row__icon" name="<?php echo esc_attr( $option . '[account_menu][' . $acc_endpoint . '][icon]' ); ?>">
									<option value=""><?php echo esc_html__( 'آیکون خودکار', 'salesbin' ); ?></option>
									<?php foreach ( array_keys( $acc_icons ) as $acc_icon_name ) : ?>
										<option value="<?php echo esc_attr( $acc_icon_name ); ?>" <?php selected( $acc_row['icon'] ?? '', $acc_icon_name ); ?>><?php echo esc_html( $acc_icon_name ); ?></option>
									<?php endforeach; ?>
								</select>
								<input type="number" class="sb-input sb-menu-row__order" name="<?php echo esc_attr( $option . '[account_menu][' . $acc_endpoint . '][order]' ); ?>" value="<?php echo esc_attr( (string) $acc_order ); ?>" min="0" step="1" dir="ltr" />
							</div>
						<?php endforeach; ?>
					</div>
					<p class="description"><?php echo esc_html__( 'عنوان خالی = همان عنوان پیش‌فرض ووکامرس.', 'salesbin' ); ?></p>
				<?php endif; ?>
			</div>

			<div class="sb-card">
				<h2><?php echo esc_html__( 'تیکتینگ پشتیبانی', 'salesbin' ); ?></h2>
				<label class="sb-check">
					<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[account_tickets_enabled]" value="1" <?php checked( 1, (int) $s['account_tickets_enabled'] ); ?> />
					<?php echo esc_html__( 'فعال‌سازی سیستم تیکتینگ پشتیبانی', 'salesbin' ); ?>
				</label>
				<p class="description"><?php echo esc_html__( 'آیتم «تیکت‌های پشتیبانی» به پنل کاربر و صفحه مدیریت تیکت‌ها به منوی وودش اضافه می‌شود. پس از تغییر این گزینه یک‌بار تنظیمات را ذخیره کنید تا لینک‌ها تازه شوند.', 'salesbin' ); ?></p>
			</div>

			<div class="sb-card">
				<h2><?php echo esc_html__( 'افزودن اپشن به داشبورد کاربر', 'salesbin' ); ?></h2>
				<p class="description"><?php echo esc_html__( 'هر خط یک کارت دسترسی سریع جدید: «عنوان | آدرس». برای اپشن‌های پیشرفته‌تر (کیف پول، امتیاز و...) فیلتر salesbin_account_widgets در اختیار توسعه‌دهنده است.', 'salesbin' ); ?></p>
				<div class="sb-field">
					<label for="salesbin_acc_links"><?php echo esc_html__( 'لینک‌های اختصاصی داشبورد کاربر', 'salesbin' ); ?></label>
					<textarea name="<?php echo esc_attr( $option ); ?>[account_custom_links]" id="salesbin_acc_links" class="large-text sb-textarea" rows="4" placeholder="<?php echo esc_attr( "پیگیری تیکت | https://example.com/tickets\nکیف پول | https://example.com/wallet" ); ?>"><?php echo esc_textarea( $s['account_custom_links'] ); ?></textarea>
				</div>
			</div>

			<div class="sb-card sb-card--soon">
				<h2><?php echo esc_html__( 'توسعه آینده', 'salesbin' ); ?></h2>
				<p class="description"><?php echo esc_html__( 'ویجت‌های سفارشی و کارت‌های آماری جدید (کیف پول، امتیاز وفاداری و...) از طریق فیلترهای salesbin_account_widgets و salesbin_account_stats قابل اضافه‌شدن هستند.', 'salesbin' ); ?></p>
			</div>
		</section>

		<!-- ==================== LOGIN MODULE ==================== -->
		<section class="sb-panel" data-panel="login" role="tabpanel" hidden>
			<div class="sb-card">
				<h2><?php echo esc_html__( 'صفحه ورود و ثبت‌نام — متصل به پنل کاربری', 'salesbin' ); ?></h2>
				<?php
				$sb_main_url   = $login->main_url();
				$sb_panel_on   = Salesbin_Account_Panel::instance()->is_enabled();
				$sb_endpoint   = $login->default_endpoint_url();
				$sb_page_id    = (int) get_option( 'woocommerce_myaccount_page_id' );
				$sb_page_ok    = $sb_page_id && 'publish' === get_post_status( $sb_page_id );
				?>
				<p class="description"><?php echo esc_html__( 'یک آدرس واحد برای همه‌چیز: مهمان در همین صفحه فرم ورود اختصاصی وودش را می‌بیند و بلافاصله پس از ورود، همان آدرس به پنل کاربری اختصاصی تبدیل می‌شود. لینک جداگانه‌ای وجود ندارد.', 'salesbin' ); ?></p>

				<div class="sb-field">
					<label><?php echo esc_html__( 'لینک اصلی ورود و پنل کاربری', 'salesbin' ); ?></label>
					<?php if ( $sb_main_url ) : ?>
						<p class="description">
							<a class="salesbin-link" href="<?php echo esc_url( $sb_main_url ); ?>" target="_blank" rel="noopener" dir="ltr"><?php echo esc_html( $sb_main_url ); ?></a>
						</p>
					<?php else : ?>
						<p class="description"><?php echo esc_html__( 'صفحه حساب کاربری ووکامرس یافت نشد؛ با دکمه «بازسازی» زیر آن را بسازید.', 'salesbin' ); ?></p>
					<?php endif; ?>
					<p class="description">
						<?php echo esc_html__( 'وضعیت صفحه حساب:', 'salesbin' ); ?>
						<strong><?php echo $sb_page_ok ? esc_html__( 'سالم و متصل', 'salesbin' ) : esc_html__( 'خراب یا حذف‌شده', 'salesbin' ); ?></strong>
						|
						<?php echo esc_html__( 'پنل کاربری اختصاصی:', 'salesbin' ); ?>
						<strong><?php echo $sb_panel_on ? esc_html__( 'فعال', 'salesbin' ) : esc_html__( 'غیرفعال (تب «پنل حساب»)', 'salesbin' ); ?></strong>
					</p>
					<?php if ( $sb_endpoint ) : ?>
						<p class="description">
							<?php echo esc_html__( 'لینک قدیمی (سازگاری با نسخه‌های قبل):', 'salesbin' ); ?>
							<a class="salesbin-link" href="<?php echo esc_url( $sb_endpoint ); ?>" target="_blank" rel="noopener" dir="ltr"><?php echo esc_html( $sb_endpoint ); ?></a>
						</p>
					<?php endif; ?>
				</div>

				<label class="sb-check">
					<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[login_enabled]" value="1" <?php checked( 1, (int) $s['login_enabled'] ); ?> />
					<?php echo esc_html__( 'فعال‌سازی ماژول صفحه ورود', 'salesbin' ); ?>
				</label>
				<label class="sb-check">
					<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[login_show_register]" value="1" <?php checked( 1, (int) $s['login_show_register'] ); ?> />
					<?php echo esc_html__( 'نمایش تب ثبت‌نام در صفحه ورود', 'salesbin' ); ?>
				</label>
				<label class="sb-check">
					<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[login_replace_default]" value="1" <?php checked( 1, (int) $s['login_replace_default'] ); ?> />
					<?php echo esc_html__( 'جایگزینی لینک ورود پیش‌فرض وردپرس و ووکامرس با همین صفحه', 'salesbin' ); ?>
				</label>

				<a class="salesbin-btn" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=salesbin_rebuild_login_page' ), 'salesbin_rebuild_login_page' ) ); ?>">
					<?php echo esc_html__( 'بازسازی صفحه ورود و ثبت‌نام', 'salesbin' ); ?>
				</a>
				<p class="description"><?php echo esc_html__( 'صفحه «حساب کاربری من» را بررسی و در صورت نیاز می‌سازد، به ووکامرس متصل می‌کند و لینک‌ها را تازه می‌کند.', 'salesbin' ); ?></p>
			</div>

			<div class="sb-card">
				<h2><?php echo esc_html__( 'ورود با شماره موبایل (ملی‌پیامک) — اختیاری', 'salesbin' ); ?></h2>
				<p class="description"><?php echo esc_html__( 'بدون این بخش هم ورود و ثبت‌نام با رمز عبور کامل کار می‌کند. تا وقتی اطلاعات ملی‌پیامک وارد و اتصال تایید نشده باشد، روش پیامکی در صفحه ورود نشان داده نمی‌شود. جریان یکپارچه است: هر شماره معتبر کد می‌گیرد و در صورت ثبت‌نام نبودن، حساب به‌صورت خودکار ساخته می‌شود.', 'salesbin' ); ?></p>
				<label class="sb-check">
					<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[login_sms_enabled]" value="1" <?php checked( 1, (int) $s['login_sms_enabled'] ); ?> />
					<?php echo esc_html__( 'فعال‌سازی ورود با کد پیامکی', 'salesbin' ); ?>
				</label>
				<label class="sb-check">
					<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[login_sms_autoregister]" value="1" <?php checked( 1, (int) $s['login_sms_autoregister'] ); ?> />
					<?php echo esc_html__( 'ثبت‌نام خودکار شماره‌های جدید (در غیر این صورت فقط شماره‌های ثبت‌شده می‌توانند وارد شوند)', 'salesbin' ); ?>
				</label>
				<div class="sb-field">
					<label for="salesbin_otp_expiry"><?php echo esc_html__( 'اعتبار کد تایید (ثانیه)', 'salesbin' ); ?></label>
					<input name="<?php echo esc_attr( $option ); ?>[login_otp_expiry]" id="salesbin_otp_expiry" type="number" min="60" max="900" step="30" class="regular-text sb-input" value="<?php echo esc_attr( (int) $s['login_otp_expiry'] ); ?>" dir="ltr" />
				</div>
				<div class="sb-field">
					<label for="salesbin_otp_cooldown"><?php echo esc_html__( 'فاصله ارسال مجدد (ثانیه)', 'salesbin' ); ?></label>
					<input name="<?php echo esc_attr( $option ); ?>[login_otp_cooldown]" id="salesbin_otp_cooldown" type="number" min="15" max="300" step="5" class="regular-text sb-input" value="<?php echo esc_attr( (int) $s['login_otp_cooldown'] ); ?>" dir="ltr" />
				</div>
				<div class="sb-field">
					<label for="salesbin_mp_user"><?php echo esc_html__( 'نام کاربری پنل ملی‌پیامک', 'salesbin' ); ?></label>
					<input name="<?php echo esc_attr( $option ); ?>[melipayamak_username]" id="salesbin_mp_user" type="text" class="regular-text sb-input" value="<?php echo esc_attr( $s['melipayamak_username'] ); ?>" autocomplete="off" />
				</div>
				<div class="sb-field">
					<label for="salesbin_mp_pass"><?php echo esc_html__( 'رمز عبور / ApiKey', 'salesbin' ); ?></label>
					<input name="<?php echo esc_attr( $option ); ?>[melipayamak_password]" id="salesbin_mp_pass" type="password" class="regular-text sb-input" value="<?php echo esc_attr( $s['melipayamak_password'] ); ?>" autocomplete="new-password" />
					<p class="description"><?php echo esc_html__( 'پنل‌های جدید ملی‌پیامک به‌جای رمز، ApiKey صادر می‌کنند؛ همان را اینجا وارد کنید.', 'salesbin' ); ?></p>
				</div>
				<div class="sb-field">
					<label for="salesbin_mp_from"><?php echo esc_html__( 'شماره خط ارسال (عادی)', 'salesbin' ); ?></label>
					<input name="<?php echo esc_attr( $option ); ?>[melipayamak_from]" id="salesbin_mp_from" type="text" class="regular-text sb-input" value="<?php echo esc_attr( $s['melipayamak_from'] ); ?>" placeholder="5000..." dir="ltr" />
				</div>
				<label class="sb-check">
					<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[melipayamak_khadamati]" value="1" <?php checked( 1, (int) $s['melipayamak_khadamati'] ); ?> />
					<?php echo esc_html__( 'استفاده از خط خدماتی (BaseServiceNumber)', 'salesbin' ); ?>
				</label>
				<div class="sb-field">
					<label for="salesbin_mp_bodyid"><?php echo esc_html__( 'کد متن خدماتی (bodyId)', 'salesbin' ); ?></label>
					<input name="<?php echo esc_attr( $option ); ?>[melipayamak_body_id]" id="salesbin_mp_bodyid" type="text" class="regular-text sb-input" value="<?php echo esc_attr( $s['melipayamak_body_id'] ); ?>" dir="ltr" />
					<p class="description"><?php echo esc_html__( 'فقط برای خط خدماتی؛ کد متن تاییدشده در پنل ملی‌پیامک.', 'salesbin' ); ?></p>
				</div>
				<div class="sb-field">
					<label for="salesbin_mp_tpl"><?php echo esc_html__( 'متن پیامک', 'salesbin' ); ?></label>
					<input name="<?php echo esc_attr( $option ); ?>[melipayamak_template]" id="salesbin_mp_tpl" type="text" class="regular-text sb-input" value="<?php echo esc_attr( $s['melipayamak_template'] ); ?>" />
					<p class="description"><?php echo esc_html__( 'جای‌نگاره‌ها: {OTP} و {DOMAIN}', 'salesbin' ); ?></p>
				</div>
				<label class="sb-check">
					<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[login_sms_debug]" value="1" <?php checked( 1, (int) $s['login_sms_debug'] ); ?> />
					<?php echo esc_html__( 'حالت تست (کد در صفحه نمایش داده می‌شود و پیامکی ارسال نمی‌شود)', 'salesbin' ); ?>
				</label>
				<a class="salesbin-btn" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=salesbin_test_melipayamak' ), 'salesbin_test_melipayamak' ) ); ?>">
					<?php echo esc_html__( 'تست اتصال به ملی‌پیامک', 'salesbin' ); ?>
				</a>
				<p class="description"><?php echo esc_html__( 'اعتبار وب‌سرویس پنل را از سرور ملی‌پیامک می‌پرسد؛ اگر خطای «IP مجاز نیست» گرفتید، IP سرور سایت را در بخش محدودسازی IP پنل ملی‌پیامک اضافه کنید. اگر خطای اتصال گرفتید، فایروال هاست را برای خروجی HTTPS بررسی کنید.', 'salesbin' ); ?></p>
			</div>
		</section>

		<!-- ==================== ADVANCED ==================== -->
		<section class="sb-panel" data-panel="advanced" role="tabpanel" hidden>
			<div class="sb-card">
				<h2><?php echo esc_html__( 'عملکرد', 'salesbin' ); ?></h2>
				<div class="sb-field">
					<label for="salesbin_cache"><?php echo esc_html__( 'مدت کش گزارش‌ها (ثانیه)', 'salesbin' ); ?></label>
					<input name="<?php echo esc_attr( $option ); ?>[cache_duration]" id="salesbin_cache" type="number" min="0" max="86400" class="regular-text sb-input" value="<?php echo esc_attr( $s['cache_duration'] ); ?>" />
				</div>
			</div>

			<div class="sb-card">
				<h2><?php echo esc_html__( 'اشکال‌زدایی', 'salesbin' ); ?></h2>
				<label class="sb-check">
					<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[debug_logging]" value="1" <?php checked( 1, (int) $s['debug_logging'] ); ?> />
					<?php echo esc_html__( 'ثبت لاگ توسعه (بدون اطلاعات مشتری)', 'salesbin' ); ?>
				</label>
			</div>

			<div class="sb-card sb-card--danger">
				<h2><?php echo esc_html__( 'حذف هنگام Uninstall', 'salesbin' ); ?></h2>
				<label class="sb-check">
					<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[delete_data_on_uninstall]" value="1" <?php checked( 1, (int) $s['delete_data_on_uninstall'] ); ?> />
					<?php echo esc_html__( 'با حذف افزونه، تنظیمات، جدول اعلان‌ها و صفحه ورود ساخته‌شده پاک شود', 'salesbin' ); ?>
				</label>
			</div>
		</section>

		<div class="sb-submit-bar">
			<p class="sb-submit-bar__hint"><?php echo esc_html__( 'پس از ذخیره، تغییرات برای همه کاربران اعمال می‌شود.', 'salesbin' ); ?></p>
			<?php submit_button( __( 'ذخیره تنظیمات', 'salesbin' ), 'primary sb-save-btn', 'submit', false ); ?>
		</div>
	</form>
</div>
