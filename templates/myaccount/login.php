<?php
/**
 * My Account page — logged-out state. Served by Salesbin_Login_Module when the
 * module is enabled and a guest opens /my-account/: renders the redesigned
 * login card (same markup as the [salesbin_login] shortcode) instead of the
 * default WooCommerce login form. Once logged in, Salesbin_Account_Panel
 * takes the same URL over with the customer dashboard.
 *
 * @package Salesbin
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );

do_action( 'woocommerce_before_main_content' );

if ( function_exists( 'wc_print_notices' ) ) {
	wc_print_notices();
}

echo Salesbin_Login_Module::instance()->render_shortcode(); // phpcs:ignore WordPress.Security.EscapeOutput -- fully escaped inside the view

do_action( 'woocommerce_after_main_content' );

get_footer( 'shop' );
