<?php
/**
 * Admin bar widget.
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Salesbin_Admin_Bar_Widget
 */
class Salesbin_Admin_Bar_Widget {

	/**
	 * @var Salesbin_Admin_Bar_Widget|null
	 */
	private static $instance = null;

	/**
	 * @return Salesbin_Admin_Bar_Widget
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		if ( ! Salesbin_Settings::get( 'admin_bar_enabled', 1 ) ) {
			return;
		}
		add_action( 'admin_bar_menu', array( $this, 'add_node' ), 80 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueue widget assets on admin and front when bar is visible.
	 *
	 * @return void
	 */
	public function enqueue() {
		if ( ! is_admin_bar_showing() || ! Salesbin_Capabilities::current_user_can_view() ) {
			return;
		}
		wp_enqueue_style( 'salesbin-admin-bar', SALESBIN_URL . 'assets/css/admin-bar.css', array(), SALESBIN_VERSION );
		wp_enqueue_script( 'salesbin-admin-bar', SALESBIN_URL . 'assets/js/admin-bar.js', array(), SALESBIN_VERSION, true );
		wp_localize_script(
			'salesbin-admin-bar',
			'salesbinBar',
			array(
				'restUrl'     => esc_url_raw( rest_url( Salesbin_Rest_API::NAMESPACE . '/admin-bar' ) ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'dashboard'   => esc_url_raw( admin_url( 'index.php' ) ),
				'interval'    => 60,
				'i18n'        => array(
					'sales'  => __( 'فروش امروز', 'salesbin' ),
					'orders' => __( 'سفارش امروز', 'salesbin' ),
					'stock'  => __( 'کم‌موجودی', 'salesbin' ),
					'open'   => __( 'باز کردن داشبورد', 'salesbin' ),
				),
			)
		);
	}

	/**
	 * Add admin bar node.
	 *
	 * @param WP_Admin_Bar $bar Bar.
	 * @return void
	 */
	public function add_node( $bar ) {
		if ( ! Salesbin_Capabilities::current_user_can_view() ) {
			return;
		}
		$bar->add_node(
			array(
				'id'    => 'salesbin-bar',
				'title' => '<span class="salesbin-ab-label">' . esc_html__( 'وودش', 'salesbin' ) . '</span><span class="salesbin-ab-stats" id="salesbin-ab-stats">' . esc_html__( '…', 'salesbin' ) . '</span>',
				'href'  => admin_url( 'index.php' ),
				'meta'  => array(
					'class' => 'salesbin-ab-node',
				),
			)
		);
	}
}
