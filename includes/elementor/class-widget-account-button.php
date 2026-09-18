<?php
/**
 * Elementor widget: Woodesh account/login button.
 * Fully customizable: skin (3 designs), icon, texts, colors, radius, size,
 * alignment, avatar. Logged-in users go to their account; guests to the
 * Woodesh login page (manual URL or auto-created).
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
	return;
}

/**
 * Class Salesbin_Widget_Account_Button
 */
class Salesbin_Widget_Account_Button extends \Elementor\Widget_Base {

	/**
	 * @return string
	 */
	public function get_name() {
		return 'woodesh_account_button';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return __( 'دکمه حساب کاربری وودش', 'salesbin' );
	}

	/**
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-user-circle-o';
	}

	/**
	 * @return array
	 */
	public function get_categories() {
		return array( 'woodesh', 'general' );
	}

	/**
	 * @return array
	 */
	public function get_keywords() {
		return array( 'ورود', 'ثبت‌نام', 'حساب', 'login', 'account', 'woodesh', 'وودش' );
	}

	/**
	 * Controls.
	 *
	 * @return void
	 */
	protected function register_controls() {
		/* ---------- CONTENT ---------- */
		$this->start_controls_section(
			'section_content',
			array( 'label' => __( 'محتوا', 'salesbin' ) )
		);

		$this->add_control(
			'skin',
			array(
				'label'   => __( 'طرح دکمه', 'salesbin' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'glass',
				'options' => array(
					'glass'   => __( 'شیشه‌ای (Glass)', 'salesbin' ),
					'solid'   => __( 'توپر گرادیانی (Solid)', 'salesbin' ),
					'outline' => __( 'خطی (Outline)', 'salesbin' ),
					'minimal' => __( 'مینیمال فقط آیکون', 'salesbin' ),
				),
			)
		);

		$this->add_control(
			'text_login',
			array(
				'label'       => __( 'متن (مهمان)', 'salesbin' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( 'ورود / ثبت‌نام', 'salesbin' ),
				'label_block' => true,
			)
		);

		$this->add_control(
			'text_account',
			array(
				'label'       => __( 'متن (کاربر واردشده)', 'salesbin' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( 'حساب کاربری من', 'salesbin' ),
				'label_block' => true,
			)
		);

		$this->add_control(
			'selected_icon',
			array(
				'label'            => __( 'آیکون', 'salesbin' ),
				'type'             => \Elementor\Controls_Manager::ICONS,
				'fa4compatibility' => 'icon',
				'default'          => array(
					'value'   => 'fas fa-user',
					'library' => 'fa-solid',
				),
			)
		);

		$this->add_control(
			'show_avatar',
			array(
				'label'        => __( 'نمایش آواتار برای کاربر واردشده', 'salesbin' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => array( 'skin!' => 'minimal' ),
			)
		);

		$this->add_control(
			'link_login',
			array(
				'label'       => __( 'لینک صفحه ورود (اختیاری)', 'salesbin' ),
				'type'        => \Elementor\Controls_Manager::URL,
				'description' => __( 'خالی بگذارید تا لینک از تنظیمات پلاگین (لینک دستی یا صفحه خودکار) گرفته شود.', 'salesbin' ),
				'label_block' => true,
			)
		);

		$this->end_controls_section();

		/* ---------- STYLE ---------- */
		$this->start_controls_section(
			'section_style',
			array(
				'label' => __( 'استایل', 'salesbin' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'accent_color',
			array(
				'label'     => __( 'رنگ اصلی (اکسنت)', 'salesbin' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .sb-el-account' => '--sb-el-accent: {{VALUE}};',
				),
				'default'   => '#8b5cf6',
			)
		);

		$this->add_control(
			'text_color',
			array(
				'label'     => __( 'رنگ متن', 'salesbin' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .sb-el-account' => '--sb-el-text: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'bg_color',
			array(
				'label'     => __( 'رنگ پس‌زمینه (طرح توپر/شیشه‌ای)', 'salesbin' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .sb-el-account' => '--sb-el-bg: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'font_size',
			array(
				'label'      => __( 'اندازه فونت (px)', 'salesbin' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 10, 'max' => 32 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .sb-el-account' => 'font-size: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'radius',
			array(
				'label'      => __( 'گردی گوشه‌ها (px)', 'salesbin' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .sb-el-account' => 'border-radius: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'padding',
			array(
				'label'      => __( 'فاصله داخلی', 'salesbin' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .sb-el-account' => 'padding: {{TOP}}{{UNIT}} {{LEFT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{RIGHT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'align',
			array(
				'label'     => __( 'چیدمان', 'salesbin' ),
				'type'      => \Elementor\Controls_Manager::CHOOSE,
				'options'   => array(
					'start'   => array( 'title' => __( 'راست', 'salesbin' ), 'icon' => 'eicon-text-align-right' ),
					'center'  => array( 'title' => __( 'وسط', 'salesbin' ), 'icon' => 'eicon-text-align-center' ),
					'end'     => array( 'title' => __( 'چپ', 'salesbin' ), 'icon' => 'eicon-text-align-left' ),
				),
				'selectors' => array(
					'{{WRAPPER}} .sb-el-account-wrap' => 'display:flex; justify-content: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Target URL.
	 *
	 * @return string
	 */
	private function target_url() {
		$custom = $this->get_settings_for_display( 'link_login' );
		if ( is_array( $custom ) && ! empty( $custom['url'] ) ) {
			return $custom['url'];
		}
		if ( is_user_logged_in() && function_exists( 'wc_get_account_endpoint_url' ) ) {
			return wc_get_account_endpoint_url( 'dashboard' );
		}
		if ( class_exists( 'Salesbin_Login_Module' ) ) {
			$url = Salesbin_Login_Module::instance()->page_url();
			if ( $url ) {
				return $url;
			}
		}
		return function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url();
	}

	/**
	 * Render.
	 *
	 * @return void
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();
		$skin     = $settings['skin'] ?? 'glass';
		$logged   = is_user_logged_in();
		$text     = $logged ? ( $settings['text_account'] ?? '' ) : ( $settings['text_login'] ?? '' );
		$icon     = $settings['selected_icon'] ?? array();
		$icon_html = '';
		if ( ! empty( $icon['value'] ) && class_exists( '\Elementor\Icons_Manager' ) ) {
			ob_start();
			\Elementor\Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) );
			$icon_html = ob_get_clean();
		}

		$avatar = '';
		if ( $logged && 'yes' === ( $settings['show_avatar'] ?? 'yes' ) ) {
			$avatar = get_avatar( get_current_user_id(), 40, '', '', array( 'class' => 'sb-el-account__avatar' ) );
		}

		$url = esc_url( $this->target_url() );
		?>
		<div class="sb-el-account-wrap">
			<a class="sb-el-account sb-el-account--<?php echo esc_attr( $skin ); ?>" href="<?php echo $url; ?>">
				<?php if ( $avatar ) : ?>
					<?php echo $avatar; // phpcs:ignore WordPress.Security.EscapeOutput -- core avatar HTML ?>
				<?php elseif ( $icon_html ) : ?>
					<span class="sb-el-account__icon"><?php echo $icon_html; // phpcs:ignore WordPress.Security.EscapeOutput -- Elementor icon markup ?></span>
				<?php endif; ?>
				<?php if ( 'minimal' !== $skin && $text ) : ?>
					<span class="sb-el-account__text"><?php echo esc_html( $text ); ?></span>
				<?php endif; ?>
			</a>
		</div>
		<?php
	}
}
