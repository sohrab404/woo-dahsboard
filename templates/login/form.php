<?php
/**
 * Front auth card — Woodesh glass redesign (v2).
 * Variables are prepared by Salesbin_Login_Module::render_shortcode().
 * One surface, one URL: this card renders on /my-account/ for guests and
 * (via shortcode/legacy endpoint) wherever else it is embedded.
 *
 * Flow: phone OTP first (auto login-or-register), password behind a toggle.
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salesbin_login = array(
	'error'       => isset( $error ) ? $error : '',
	'notice'      => isset( $notice ) ? $notice : '',
	'methods'     => isset( $methods ) && is_array( $methods ) ? $methods : array(),
	'show_reg'    => isset( $show_register ) ? $show_register : true,
	'action_url'  => isset( $action_url ) ? $action_url : admin_url( 'admin-post.php' ),
	'redirect_to' => isset( $redirect_to ) ? $redirect_to : '',
);

$salesbin_sms_active = ! empty( $salesbin_login['methods']['sms']['active'] );
$salesbin_otp_size   = max( 4, min( 8, (int) Salesbin_Login_Otp::CODE_SIZE ) );
$salesbin_mode       = Salesbin_Settings::get( 'mode', 'dark' );
$salesbin_mode       = 'light' === $salesbin_mode ? 'light' : 'dark';
$salesbin_site       = get_bloginfo( 'name' );
?>
<div class="salesbin-login-page" dir="rtl" data-sb-mode="<?php echo esc_attr( $salesbin_mode ); ?>">
	<div class="sb-login-bg" aria-hidden="true"><i></i><i></i><i></i></div>

	<div class="sb-login-wrap">
		<header class="sb-login-brand">
			<span class="sb-login-logo" aria-hidden="true"><i></i></span>
			<div>
				<strong class="sb-login-site"><?php echo esc_html( $salesbin_site ? $salesbin_site : __( 'حساب کاربری', 'salesbin' ) ); ?></strong>
				<h1 class="sb-login-title"><?php echo esc_html__( 'ورود | ثبت‌نام', 'salesbin' ); ?></h1>
			</div>
		</header>

		<main class="sb-login-card sb-magic">
			<button type="button" class="sb-login-mode" data-sb-login-theme aria-label="<?php echo esc_attr__( 'حالت شب و روز', 'salesbin' ); ?>">🌙</button>

			<?php if ( $salesbin_login['error'] ) : ?>
				<div class="sb-login-alert sb-login-alert--error" role="alert"><?php echo esc_html( $salesbin_login['error'] ); ?></div>
			<?php endif; ?>
			<?php if ( $salesbin_login['notice'] ) : ?>
				<div class="sb-login-alert sb-login-alert--ok" role="status"><?php echo esc_html( $salesbin_login['notice'] ); ?></div>
			<?php endif; ?>

			<div class="sb-login-tabs" data-sb-login-tabs role="tablist">
				<span class="sb-login-tabs__ind" aria-hidden="true"></span>
				<button type="button" class="sb-login-tab is-active" data-sb-login-tab="login" role="tab" aria-selected="true"><?php echo esc_html__( 'ورود', 'salesbin' ); ?></button>
				<?php if ( $salesbin_login['show_reg'] ) : ?>
					<button type="button" class="sb-login-tab" data-sb-login-tab="register" role="tab" aria-selected="false"><?php echo esc_html__( 'ثبت‌نام', 'salesbin' ); ?></button>
				<?php endif; ?>
			</div>

			<!-- ================= LOGIN TAB ================= -->
			<div class="sb-login-panel is-active" data-sb-login-panel="login">

				<?php if ( $salesbin_sms_active ) : ?>
					<!-- Mobile OTP (primary) -->
					<div class="sb-login-method" data-sb-login-method="sms" data-sb-sms>
						<div data-sb-sms-step="phone">
							<p class="sb-login-lead"><?php echo esc_html__( 'شماره موبایل خود را وارد کنید تا کد ورود پیامک شود.', 'salesbin' ); ?></p>
							<label class="sb-field">
								<span><?php echo esc_html__( 'شماره موبایل', 'salesbin' ); ?></span>
								<span class="sb-mobile">
									<span class="sb-mobile__cc" data-sb-sms-cc>+<?php echo esc_html( Salesbin_Phone::default_country_code() ); ?></span>
									<input type="tel" class="sb-input" data-sb-sms-mobile inputmode="tel" autocomplete="tel" placeholder="9123456789" />
								</span>
							</label>
							<button type="button" class="sb-btn sb-btn--primary" data-sb-sms-action="send"><?php echo esc_html__( 'دریافت کد تایید', 'salesbin' ); ?></button>
						</div>

						<div data-sb-sms-step="otp" hidden>
							<p class="sb-login-lead">
								<?php echo esc_html__( 'کد تایید پیامک‌شده به شماره', 'salesbin' ); ?>
								<strong class="sb-login-phone-echo" data-sb-sms-echo dir="ltr"></strong>
								<?php echo esc_html__( 'را وارد کنید.', 'salesbin' ); ?>
							</p>
							<div class="sb-otp-boxes" data-sb-otp-boxes>
								<?php for ( $sb_otp_i = 0; $sb_otp_i < $salesbin_otp_size; $sb_otp_i++ ) : ?>
									<input type="text" class="sb-otp-box" inputmode="numeric" autocomplete="one-time-code" maxlength="1" aria-label="<?php echo esc_attr( sprintf( __( 'رقم %d کد تایید', 'salesbin' ), $sb_otp_i + 1 ) ); ?>" />
								<?php endfor; ?>
								<input type="hidden" data-sb-sms-otp />
							</div>
							<label class="sb-check-inline">
								<input type="checkbox" data-sb-sms-remember checked />
								<?php echo esc_html__( 'مرا به خاطر بسپار', 'salesbin' ); ?>
							</label>
							<button type="button" class="sb-btn sb-btn--primary" data-sb-sms-action="verify"><?php echo esc_html__( 'تایید و ورود', 'salesbin' ); ?></button>
							<p class="sb-login-resend">
								<button type="button" class="sb-link" data-sb-sms-action="resend" disabled><?php echo esc_html__( 'ارسال مجدد کد', 'salesbin' ); ?></button>
								<span class="sb-muted sb-login-timer-wrap">(۰۰:<span data-sb-sms-timer>۶۰</span>)</span>
								<span class="sb-login-resend-sep" aria-hidden="true">|</span>
								<button type="button" class="sb-link" data-sb-sms-action="edit"><?php echo esc_html__( 'ویرایش شماره', 'salesbin' ); ?></button>
							</p>
						</div>

						<div class="sb-login-alert" data-sb-sms-message role="alert" hidden></div>
					</div>

					<!-- Password (secondary, toggled) -->
					<form class="sb-login-method" data-sb-login-method="password" method="post" action="<?php echo esc_url( $salesbin_login['action_url'] ); ?>" hidden>
						<input type="hidden" name="action" value="salesbin_login" />
						<?php wp_nonce_field( 'salesbin_login_form', 'salesbin_login_nonce' ); ?>
						<?php if ( $salesbin_login['redirect_to'] ) : ?>
							<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $salesbin_login['redirect_to'] ); ?>" />
						<?php endif; ?>

						<label class="sb-field">
							<span><?php echo esc_html__( 'نام کاربری یا ایمیل', 'salesbin' ); ?></span>
							<input type="text" name="log" class="sb-input" autocomplete="username" required />
						</label>
						<label class="sb-field">
							<span><?php echo esc_html__( 'رمز عبور', 'salesbin' ); ?></span>
							<input type="password" name="pwd" class="sb-input" autocomplete="current-password" required />
						</label>
						<label class="sb-check-inline">
							<input type="checkbox" name="rememberme" value="forever" />
							<?php echo esc_html__( 'مرا به خاطر بسپار', 'salesbin' ); ?>
						</label>
						<button type="submit" class="sb-btn sb-btn--primary"><?php echo esc_html__( 'ورود به حساب', 'salesbin' ); ?></button>
						<p class="sb-login-alt">
							<a class="sb-link" href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php echo esc_html__( 'رمز عبور را فراموش کرده‌اید؟', 'salesbin' ); ?></a>
						</p>
					</form>

					<div class="sb-login-method-foot">
						<button type="button" class="sb-link" data-sb-method-toggle><?php echo esc_html__( 'ورود با رمز عبور', 'salesbin' ); ?></button>
					</div>
				<?php else : ?>
					<!-- Password only -->
					<form class="sb-login-method" data-sb-login-method="password" method="post" action="<?php echo esc_url( $salesbin_login['action_url'] ); ?>">
						<input type="hidden" name="action" value="salesbin_login" />
						<?php wp_nonce_field( 'salesbin_login_form', 'salesbin_login_nonce' ); ?>
						<?php if ( $salesbin_login['redirect_to'] ) : ?>
							<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $salesbin_login['redirect_to'] ); ?>" />
						<?php endif; ?>

						<label class="sb-field">
							<span><?php echo esc_html__( 'نام کاربری یا ایمیل', 'salesbin' ); ?></span>
							<input type="text" name="log" class="sb-input" autocomplete="username" required />
						</label>
						<label class="sb-field">
							<span><?php echo esc_html__( 'رمز عبور', 'salesbin' ); ?></span>
							<input type="password" name="pwd" class="sb-input" autocomplete="current-password" required />
						</label>
						<label class="sb-check-inline">
							<input type="checkbox" name="rememberme" value="forever" />
							<?php echo esc_html__( 'مرا به خاطر بسپار', 'salesbin' ); ?>
						</label>
						<button type="submit" class="sb-btn sb-btn--primary"><?php echo esc_html__( 'ورود به حساب', 'salesbin' ); ?></button>
						<p class="sb-login-alt">
							<a class="sb-link" href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php echo esc_html__( 'رمز عبور را فراموش کرده‌اید؟', 'salesbin' ); ?></a>
						</p>
					</form>
				<?php endif; ?>
			</div>

			<!-- ================= REGISTER TAB ================= -->
			<?php if ( $salesbin_login['show_reg'] ) : ?>
				<form class="sb-login-panel" data-sb-login-panel="register" method="post" action="<?php echo esc_url( $salesbin_login['action_url'] ); ?>">
					<input type="hidden" name="action" value="salesbin_register" />
					<?php wp_nonce_field( 'salesbin_login_form', 'salesbin_login_nonce' ); ?>
					<?php if ( $salesbin_login['redirect_to'] ) : ?>
						<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $salesbin_login['redirect_to'] ); ?>" />
					<?php endif; ?>

					<p class="sb-login-lead"><?php echo $salesbin_sms_active ? esc_html__( 'یا با موبایل در تب ورود ثبت‌نام کنید (بدون رمز).', 'salesbin' ) : esc_html__( 'حساب کاربری جدید بسازید.', 'salesbin' ); ?></p>
					<label class="sb-field">
						<span><?php echo esc_html__( 'نام کاربری', 'salesbin' ); ?></span>
						<input type="text" name="user_login" class="sb-input" autocomplete="username" required />
					</label>
					<label class="sb-field">
						<span><?php echo esc_html__( 'ایمیل', 'salesbin' ); ?></span>
						<input type="email" name="email" class="sb-input" autocomplete="email" required />
					</label>
					<label class="sb-field">
						<span><?php echo esc_html__( 'رمز عبور', 'salesbin' ); ?></span>
						<input type="password" name="user_pass" class="sb-input" autocomplete="new-password" required minlength="6" />
					</label>
					<button type="submit" class="sb-btn sb-btn--primary"><?php echo esc_html__( 'ساخت حساب کاربری', 'salesbin' ); ?></button>
				</form>
			<?php endif; ?>
		</main>

		<p class="sb-login-foot sb-muted">
			<?php echo $salesbin_sms_active ? esc_html__( 'ورود امن با کد یکبارمصرف — بدون نیاز به رمز عبور', 'salesbin' ) : esc_html__( 'ورود امن به حساب کاربری فروشگاه', 'salesbin' ); ?>
		</p>
	</div>
</div>
