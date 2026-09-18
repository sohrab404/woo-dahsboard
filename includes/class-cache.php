<?php
/**
 * Versioned cache layer (object cache + transients).
 *
 * Invalidation uses a version bump so only Salesbin keys become stale.
 * Old transients expire naturally and are never site-wide flushed.
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Salesbin_Cache
 */
class Salesbin_Cache {

	const VERSION_OPTION = 'salesbin_cache_version';
	const GROUP          = 'salesbin';

	/**
	 * Instance.
	 *
	 * @var Salesbin_Cache|null
	 */
	private static $instance = null;

	/**
	 * @return Salesbin_Cache
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * WooCommerce hooks that should invalidate analytics cache.
	 * Only events that actually change the numbers — high-frequency hooks
	 * (update_order, thankyou, product_object_updated_props) are deliberately
	 * excluded so a busy store doesn't write the version option constantly.
	 *
	 * @return void
	 */
	public function hooks() {
		$invalidators = array(
			'woocommerce_new_order',
			'woocommerce_order_status_changed',
			'woocommerce_order_refunded',
			'woocommerce_refund_created',
			'woocommerce_delete_order',
			'woocommerce_trash_order',
			'woocommerce_reduce_order_stock',
			'woocommerce_restore_order_stock',
			'woocommerce_product_set_stock',
			'woocommerce_variation_set_stock',
		);

		foreach ( $invalidators as $hook ) {
			add_action( $hook, array( $this, 'on_commerce_change' ), 20, 0 );
		}
	}

	/**
	 * Invalidate Salesbin cache only.
	 *
	 * @return void
	 */
	public function on_commerce_change() {
		self::bump_version();
		Salesbin_Logger::debug( 'Cache version bumped after commerce event.' );
	}

	/**
	 * Increment cache version.
	 *
	 * @return int
	 */
	public static function bump_version() {
		$version = (int) get_option( self::VERSION_OPTION, 1 );
		$version++;
		update_option( self::VERSION_OPTION, $version, false );
		wp_cache_set( 'version', $version, self::GROUP, 0 );
		return $version;
	}

	/**
	 * Current version.
	 *
	 * @return int
	 */
	public static function version() {
		$cached = wp_cache_get( 'version', self::GROUP );
		if ( false !== $cached ) {
			return (int) $cached;
		}
		$version = (int) get_option( self::VERSION_OPTION, 1 );
		wp_cache_set( 'version', $version, self::GROUP, 0 );
		return $version;
	}

	/**
	 * Build a namespaced key.
	 *
	 * @param string $prefix Prefix like sales, orders.
	 * @param array  $parts  Distinguishing parts.
	 * @return string
	 */
	public static function key( $prefix, array $parts = array() ) {
		$hash = md5( wp_json_encode( $parts ) );
		return 'salesbin_' . sanitize_key( $prefix ) . '_v' . self::version() . '_' . $hash;
	}

	/**
	 * Get cached value.
	 *
	 * @param string $key Key.
	 * @return mixed|false
	 */
	public static function get( $key ) {
		$found = wp_cache_get( $key, self::GROUP );
		if ( false !== $found ) {
			return $found;
		}
		$transient = get_transient( $key );
		if ( false !== $transient ) {
			wp_cache_set( $key, $transient, self::GROUP, self::ttl() );
		}
		return $transient;
	}

	/**
	 * Store value.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 * @param int    $ttl   TTL override.
	 * @return void
	 */
	public static function set( $key, $value, $ttl = null ) {
		$ttl = null === $ttl ? self::ttl() : (int) $ttl;
		wp_cache_set( $key, $value, self::GROUP, $ttl );
		if ( $ttl > 0 ) {
			set_transient( $key, $value, $ttl );
		}
	}

	/**
	 * Remember callback result.
	 *
	 * @param string   $prefix Prefix.
	 * @param array    $parts  Key parts.
	 * @param callable $cb     Producer.
	 * @param int|null $ttl    Optional TTL override in seconds.
	 * @return mixed
	 */
	public static function remember( $prefix, array $parts, $cb, $ttl = null ) {
		$ttl = null === $ttl ? self::ttl() : (int) $ttl;
		$key = self::key( $prefix, $parts );
		if ( $ttl <= 0 ) {
			return call_user_func( $cb );
		}
		$cached = self::get( $key );
		if ( false !== $cached ) {
			return $cached;
		}
		$value = call_user_func( $cb );
		self::set( $key, $value, $ttl );
		return $value;
	}

	/**
	 * TTL from settings.
	 *
	 * @return int
	 */
	public static function ttl() {
		return (int) Salesbin_Settings::get( 'cache_duration', 300 );
	}
}
