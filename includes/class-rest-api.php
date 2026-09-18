<?php
/**
 * REST API.
 *
 * @package Salesbin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Salesbin_Rest_API
 */
class Salesbin_Rest_API {

	const NAMESPACE = 'salesbin/v1';

	/**
	 * @var Salesbin_Rest_API|null
	 */
	private static $instance = null;

	/**
	 * @return Salesbin_Rest_API
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
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'rest_post_dispatch', array( $this, 'normalize_errors' ), 10, 3 );
	}

	/**
	 * Normalize WP_Error REST payloads to the Salesbin envelope.
	 *
	 * @param mixed            $result  Result.
	 * @param WP_REST_Server   $server  Server.
	 * @param WP_REST_Request  $request Request.
	 * @return mixed
	 */
	public function normalize_errors( $result, $server, $request ) {
		unset( $server );
		$route = $request instanceof WP_REST_Request ? $request->get_route() : '';
		if ( 0 !== strpos( (string) $route, '/' . self::NAMESPACE ) ) {
			return $result;
		}
		if ( ! ( $result instanceof WP_REST_Response ) ) {
			return $result;
		}
		$data = $result->get_data();
		$code = $result->get_status();
		if ( $code >= 400 && is_array( $data ) && empty( $data['success'] ) ) {
			$result->set_data(
				array(
					'success' => false,
					'code'    => isset( $data['code'] ) ? $data['code'] : 'salesbin_error',
					'message' => isset( $data['message'] ) ? wp_strip_all_tags( (string) $data['message'] ) : __( 'خطایی رخ داد.', 'salesbin' ),
				)
			);
		}
		return $result;
	}

	/**
	 * Permission + nonce check.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function permissions( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'salesbin_unauthorized', __( 'احراز هویت الزامی است.', 'salesbin' ), array( 'status' => 401 ) );
		}
		if ( ! Salesbin_Capabilities::current_user_can_view() ) {
			return new WP_Error( 'salesbin_forbidden', __( 'شما اجازه مشاهده اطلاعات فروش را ندارید.', 'salesbin' ), array( 'status' => 403 ) );
		}

		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce ) {
			$nonce = $request->get_param( '_wpnonce' );
		}
		if ( ! wp_verify_nonce( (string) $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'salesbin_invalid_nonce', __( 'درخواست نامعتبر است. صفحه را تازه‌سازی کنید.', 'salesbin' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$perm = array( $this, 'permissions' );

		register_rest_route(
			self::NAMESPACE,
			'/bootstrap',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'bootstrap' ),
				'permission_callback' => $perm,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/stats',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'stats' ),
				'permission_callback' => $perm,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/sales-chart',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'chart' ),
				'permission_callback' => $perm,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/orders',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'orders' ),
				'permission_callback' => $perm,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/products',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'products' ),
				'permission_callback' => $perm,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/customers',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'customers' ),
				'permission_callback' => $perm,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/categories',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'categories' ),
				'permission_callback' => $perm,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/payments',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'payments' ),
				'permission_callback' => $perm,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/heatmap',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'heatmap' ),
				'permission_callback' => $perm,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/stock',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'stock' ),
				'permission_callback' => $perm,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/goal',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'goal' ),
				'permission_callback' => $perm,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/admin-bar',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'admin_bar' ),
				'permission_callback' => $perm,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/notifications',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'notifications' ),
				'permission_callback' => $perm,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/notifications/read-all',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'notifications_read_all' ),
				'permission_callback' => $perm,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/notifications/(?P<id>\d+)/read',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'notification_read' ),
				'permission_callback' => $perm,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => $perm,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'save_settings' ),
					'permission_callback' => $perm,
				),
			)
		);
	}

	/**
	 * Success envelope.
	 *
	 * @param mixed $data Data.
	 * @param array $meta Meta.
	 * @return WP_REST_Response
	 */
	private function ok( $data, array $meta = array() ) {
		$meta['generated_at'] = wp_date( 'c' );
		$meta['hpos']         = Salesbin_HPOS::enabled();
		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
				'meta'    => $meta,
			),
			200
		);
	}

	/**
	 * Parse range from request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	private function range( WP_REST_Request $request ) {
		$preset = sanitize_key( (string) $request->get_param( 'range' ) );
		if ( ! $preset ) {
			$preset = Salesbin_Settings::get( 'default_date_range', '30d' );
		}
		return Salesbin_Date_Range::resolve(
			$preset,
			$request->get_param( 'start' ),
			$request->get_param( 'end' )
		);
	}

	/**
	 * Status filter for analytics (empty = default sales statuses).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return string[]
	 */
	private function statuses( WP_REST_Request $request ) {
		$raw = $request->get_param( 'status' );
		if ( empty( $raw ) ) {
			return Salesbin_Helpers::default_sales_statuses();
		}
		if ( 'all' === $raw ) {
			return array_keys( Salesbin_Helpers::all_order_statuses() );
		}
		$parsed = Salesbin_Helpers::parse_statuses( $raw );
		return empty( $parsed ) ? Salesbin_Helpers::default_sales_statuses() : $parsed;
	}

	/**
	 * Bootstrap payload — the full first paint in one request so the SPA
	 * doesn't fire a REST call per widget on every dashboard load.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function bootstrap( WP_REST_Request $request ) {
		$range = $this->range( $request );
		if ( is_wp_error( $range ) ) {
			return $range;
		}
		$statuses = $this->statuses( $request );
		$is_home  = ( 'home' === sanitize_key( (string) $request->get_param( 'layout' ) ) );

		$data = array(
			'stats'         => $this->section( function () use ( $range, $statuses ) {
				return ( new Salesbin_Stats_Service() )->get( $range, $statuses );
			} ),
			'chart'         => $this->section( function () use ( $range, $statuses ) {
				return ( new Salesbin_Chart_Service() )->get( $range, $statuses );
			} ),
			'orders'        => $this->section( function () use ( $request, $range ) {
				return ( new Salesbin_Orders_Service() )->list_orders(
					$range,
					array(
						'page'     => $request->get_param( 'page' ),
						'per_page' => $request->get_param( 'per_page' ),
						'orderby'  => $request->get_param( 'orderby' ),
						'order'    => $request->get_param( 'order' ),
						'status'   => $request->get_param( 'status' ),
					)
				);
			} ),
			'products'      => $this->section( function () use ( $request, $range, $statuses ) {
				$sort = sanitize_key( (string) $request->get_param( 'sort' ) );
				return ( new Salesbin_Products_Service() )->get( $range, $statuses, $sort, 10 );
			} ),
			'notifications' => $this->section( function () {
				return Salesbin_Notification_Store::query( array( 'per_page' => 12 ) );
			} ),
		);

		if ( ! $is_home ) {
			$data['goal']       = $this->section( function () use ( $range ) {
				return ( new Salesbin_Goal_Service() )->get( $range );
			} );
			$data['stock']      = $this->section( function () {
				return ( new Salesbin_Stock_Service() )->list_low_stock( 8 );
			} );
			$data['customers']  = $this->section( function () use ( $range, $statuses ) {
				return ( new Salesbin_Customers_Service() )->get( $range, $statuses, 10 );
			} );
			$data['categories'] = $this->section( function () use ( $range, $statuses ) {
				return ( new Salesbin_Categories_Service() )->get( $range, $statuses );
			} );
			$data['payments']   = $this->section( function () use ( $range, $statuses ) {
				return ( new Salesbin_Payments_Service() )->get( $range, $statuses );
			} );
			$data['heatmap']    = $this->section( function () use ( $request, $range, $statuses ) {
				$metric = sanitize_key( (string) $request->get_param( 'metric' ) );
				return ( new Salesbin_Heatmap_Service() )->get( $range, $statuses, $metric );
			} );
		}

		return $this->ok(
			$data,
			array(
				'range'    => array(
					'preset' => $range['preset'],
					'start'  => $range['label_start'],
					'end'    => $range['label_end'],
				),
				'statuses' => $statuses,
			)
		);
	}

	/**
	 * Wrap a widget section so one failing widget can't kill the whole payload.
	 *
	 * @param callable $cb Producer.
	 * @return mixed
	 */
	private function section( $cb ) {
		try {
			return call_user_func( $cb );
		} catch ( Exception $e ) {
			Salesbin_Logger::debug( 'Bootstrap section failed: ' . $e->getMessage() );
			return array( 'error' => true );
		} catch ( Throwable $e ) {
			Salesbin_Logger::debug( 'Bootstrap section failed: ' . $e->getMessage() );
			return array( 'error' => true );
		}
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function stats( WP_REST_Request $request ) {
		$range = $this->range( $request );
		if ( is_wp_error( $range ) ) {
			return $range;
		}
		return $this->ok( ( new Salesbin_Stats_Service() )->get( $range, $this->statuses( $request ) ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function chart( WP_REST_Request $request ) {
		$range = $this->range( $request );
		if ( is_wp_error( $range ) ) {
			return $range;
		}
		return $this->ok( ( new Salesbin_Chart_Service() )->get( $range, $this->statuses( $request ) ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function orders( WP_REST_Request $request ) {
		$range = $this->range( $request );
		if ( is_wp_error( $range ) ) {
			return $range;
		}
		$service = new Salesbin_Orders_Service();
		return $this->ok(
			$service->list_orders(
				$range,
				array(
					'page'     => $request->get_param( 'page' ),
					'per_page' => $request->get_param( 'per_page' ),
					'orderby'  => $request->get_param( 'orderby' ),
					'order'    => $request->get_param( 'order' ),
					'status'   => $request->get_param( 'status' ),
				)
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function products( WP_REST_Request $request ) {
		$range = $this->range( $request );
		if ( is_wp_error( $range ) ) {
			return $range;
		}
		$sort = sanitize_key( (string) $request->get_param( 'sort' ) );
		return $this->ok( ( new Salesbin_Products_Service() )->get( $range, $this->statuses( $request ), $sort, 10 ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function customers( WP_REST_Request $request ) {
		$range = $this->range( $request );
		if ( is_wp_error( $range ) ) {
			return $range;
		}
		return $this->ok( ( new Salesbin_Customers_Service() )->get( $range, $this->statuses( $request ), 10 ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function categories( WP_REST_Request $request ) {
		$range = $this->range( $request );
		if ( is_wp_error( $range ) ) {
			return $range;
		}
		return $this->ok( ( new Salesbin_Categories_Service() )->get( $range, $this->statuses( $request ) ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function payments( WP_REST_Request $request ) {
		$range = $this->range( $request );
		if ( is_wp_error( $range ) ) {
			return $range;
		}
		return $this->ok( ( new Salesbin_Payments_Service() )->get( $range, $this->statuses( $request ) ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function heatmap( WP_REST_Request $request ) {
		$range = $this->range( $request );
		if ( is_wp_error( $range ) ) {
			return $range;
		}
		$metric = sanitize_key( (string) $request->get_param( 'metric' ) );
		return $this->ok( ( new Salesbin_Heatmap_Service() )->get( $range, $this->statuses( $request ), $metric ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function stock( WP_REST_Request $request ) {
		unset( $request );
		return $this->ok( ( new Salesbin_Stock_Service() )->list_low_stock( 20 ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function goal( WP_REST_Request $request ) {
		$range = $this->range( $request );
		if ( is_wp_error( $range ) ) {
			return $range;
		}
		return $this->ok( ( new Salesbin_Goal_Service() )->get( $range ) );
	}

	/**
	 * Compact admin bar payload.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function admin_bar( WP_REST_Request $request ) {
		$range = Salesbin_Date_Range::resolve( '7d' );
		if ( is_wp_error( $range ) ) {
			return $range;
		}
		$goal  = ( new Salesbin_Goal_Service() )->get( $range );
		$chart = ( new Salesbin_Chart_Service() )->get( $range, Salesbin_Helpers::default_sales_statuses() );
		$stock = 0;
		if ( Salesbin_Settings::get( 'admin_bar_show_low_stock', 1 ) ) {
			$stock = ( new Salesbin_Stock_Service() )->count_low_stock();
		}
		$mini = array();
		if ( ! empty( $chart['series'] ) ) {
			$slice = array_slice( $chart['series'], -7 );
			foreach ( $slice as $p ) {
				$mini[] = $p['sales'];
			}
		}
		unset( $request );
		return $this->ok(
			array(
				'today_sales'   => $goal['sales'],
				'today_html'    => $goal['sales_html'],
				'today_orders'  => $goal['orders'],
				'goal_bar'      => $goal['bar'],
				'low_stock'     => $stock,
				'show_stock'    => (bool) Salesbin_Settings::get( 'admin_bar_show_low_stock', 1 ),
				'series'        => $mini,
				'status'        => $goal['exceeded'] ? 'good' : ( $goal['bar'] >= 50 ? 'ok' : 'warn' ),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function notifications( WP_REST_Request $request ) {
		return $this->ok(
			Salesbin_Notification_Store::query(
				array(
					'page'     => $request->get_param( 'page' ),
					'per_page' => $request->get_param( 'per_page' ),
					'unread'   => $request->get_param( 'unread' ),
				)
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function notification_read( WP_REST_Request $request ) {
		$id = absint( $request['id'] );
		Salesbin_Notification_Store::mark_read( $id );
		return $this->ok( array( 'id' => $id, 'is_read' => true ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function notifications_read_all( WP_REST_Request $request ) {
		unset( $request );
		$count = Salesbin_Notification_Store::mark_all_read();
		return $this->ok( array( 'updated' => $count ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_settings( WP_REST_Request $request ) {
		unset( $request );
		return $this->ok( Salesbin_Settings::all() );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function save_settings( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}
		$saved = Salesbin_Settings::instance()->update_from_rest( $params );
		return $this->ok( $saved );
	}
}
