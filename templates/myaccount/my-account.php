<?php
/**
 * My Account page shell — faithful to WooCommerce core's myaccount/my-account.php.
 * Served by Salesbin_Account_Panel to guarantee the standard hook points exist
 * even when the active theme overrides the template. Theme overrides of the
 * form partials (myaccount/forms/*.php) still load from the theme as usual.
 *
 * @package Salesbin
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );

do_action( 'woocommerce_before_main_content' );
?>

<?php do_action( 'woocommerce_account_navigation' ); ?>

<div class="woocommerce-MyAccount-content">
	<?php do_action( 'woocommerce_account_content' ); ?>
</div>

<?php
do_action( 'woocommerce_after_main_content' );

get_footer( 'shop' );
