<?php
/**
 * Plugin Name:       وودش
 * Plugin URI:        https://404dev.it
 * Description:       داشبورد مانیتورینگ و تحلیل فروش WooCommerce — مدرن، RTL و فارسی.
 * Version:           1.9.1
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            404dev
 * Author URI:        https://404dev.it
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       salesbin
 * Domain Path:       /languages
 * WC requires at least: 7.0
 * WC tested up to:   9.3
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SALESBIN_VERSION', '1.9.1' );
define( 'SALESBIN_DB_VERSION', '1.0.0' );
define( 'SALESBIN_FILE', __FILE__ );
define( 'SALESBIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'SALESBIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SALESBIN_BASENAME', plugin_basename( __FILE__ ) );

require_once SALESBIN_PATH . 'includes/class-autoloader.php';

Salesbin_Autoloader::register();

register_activation_hook( __FILE__, array( 'Salesbin_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Salesbin_Deactivator', 'deactivate' ) );

add_action( 'before_woocommerce_init', 'salesbin_declare_hpos_compatibility' );
/**
 * Declare compatibility with WooCommerce High-Performance Order Storage.
 *
 * @return void
 */
function salesbin_declare_hpos_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', SALESBIN_FILE, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', SALESBIN_FILE, true );
	}
}

add_action( 'plugins_loaded', 'salesbin_boot' );
/**
 * Boot the plugin after all plugins are loaded.
 *
 * @return void
 */
function salesbin_boot() {
	load_plugin_textdomain( 'salesbin', false, dirname( SALESBIN_BASENAME ) . '/languages' );
	$plugin = Salesbin_Plugin::instance();
	$plugin->run();
}
