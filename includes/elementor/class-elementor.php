<?php
/**
 * Elementor integration — registers the Woodesh account button widget.
 * Only active when Elementor is loaded.
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Salesbin_Elementor
 */
class Salesbin_Elementor {

	/**
	 * Instance.
	 *
	 * @var Salesbin_Elementor|null
	 */
	private static $instance = null;

	/**
	 * @return Salesbin_Elementor
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
		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
	}

	/**
	 * Widget category.
	 *
	 * @param object $elements_manager Manager.
	 * @return void
	 */
	public function register_category( $elements_manager ) {
		if ( method_exists( $elements_manager, 'add_category' ) ) {
			$elements_manager->add_category(
				'woodesh',
				array(
					'title' => __( 'وودش', 'salesbin' ),
					'icon'  => 'fa fa-user',
				)
			);
		}
	}

	/**
	 * Widgets.
	 *
	 * @param object $widgets_manager Manager.
	 * @return void
	 */
	public function register_widgets( $widgets_manager ) {
		$file = SALESBIN_PATH . 'includes/elementor/class-widget-account-button.php';
		if ( file_exists( $file ) ) {
			require_once $file;
			if ( class_exists( 'Salesbin_Widget_Account_Button' ) && method_exists( $widgets_manager, 'register' ) ) {
				$widgets_manager->register( new Salesbin_Widget_Account_Button() );
			}
		}
	}
}
