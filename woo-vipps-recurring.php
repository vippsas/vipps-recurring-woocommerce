<?php

/**
 * Plugin Name: Vipps/MobilePay recurring payments for WooCommerce
 * Description: Offer recurring payments with Vipps MobilePay for WooCommerce Subscriptions
 * Author: Everyday AS
 * Author URI: https://everyday.no
 * Version: 1.20.1
 * Requires at least: 6.1
 * Tested up to: 6.5
 * WC tested up to: 8.6
 * Text Domain: vipps-recurring-payments-gateway-for-woocommerce
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Files.FileName

define( 'WC_VIPPS_RECURRING_VERSION', '1.20.1' );

add_action( 'plugins_loaded', 'woocommerce_gateway_vipps_recurring_init' );

/**
 * Polyfills
 */
if ( ! function_exists( 'array_key_first' ) ) {
	function array_key_first( array $arr ) {
		foreach ( $arr as $key => $unused ) {
			return $key;
		}

		return null;
	}
}

if ( ! function_exists( 'array_key_last' ) ) {
	function array_key_last( array $array ) {
		end( $array );

		return key( $array );
	}
}

/**
 * Activation hooks
 */
register_activation_hook( __FILE__, 'woocommerce_gateway_vipps_recurring_activate' );

function woocommerce_gateway_vipps_recurring_activate() {
	add_option( 'woo-vipps-recurring-version', WC_VIPPS_RECURRING_VERSION );
}

/**
 * Initialize our plugin
 */
function woocommerce_gateway_vipps_recurring_init() {
	$active_plugins      = apply_filters( 'active_plugins', get_option( 'active_plugins' ) );
	$active_site_plugins = apply_filters( 'active_sitewide_plugins', get_site_option( 'active_sitewide_plugins' ) );
	if ( $active_site_plugins ) {
		$active_plugins = array_merge( $active_plugins, array_keys( $active_site_plugins ) );
	}

	if ( ! in_array( 'woocommerce/woocommerce.php', $active_plugins, true ) ) {
		return;
	}

	register_deactivation_hook( __FILE__, [ 'WC_Vipps_Recurring', 'deactivate' ] );

	if ( ! class_exists( 'WC_Vipps_Recurring' ) ) {
		/*
		 * Required minimums and constants
		 */
		define( 'WC_VIPPS_RECURRING_MIN_PHP_VER', '7.4.0' );
		define( 'WC_VIPPS_RECURRING_MIN_WC_VER', '3.0.0' );
		define( 'WC_VIPPS_RECURRING_MAIN_FILE', __FILE__ );
		define( 'WC_VIPPS_RECURRING_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
		define( 'WC_VIPPS_RECURRING_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

		/*
		 * Amount of days to retry a payment when creating a charge in the Vipps/MobilePay API
		 */
		if ( ! defined( 'WC_VIPPS_RECURRING_RETRY_DAYS' ) ) {
			define( 'WC_VIPPS_RECURRING_RETRY_DAYS', 4 );
		}

		/*
		 * Whether to put the plugin into test mode. This is only useful for developers.
		 */
		if ( ! defined( 'WC_VIPPS_RECURRING_TEST_MODE' ) ) {
			define( 'WC_VIPPS_RECURRING_TEST_MODE', false );
		}

		class WC_Vipps_Recurring {
			/**
			 * The reference the *Singleton* instance of this class
			 */
			private static ?WC_Vipps_Recurring $instance = null;

			public WC_Vipps_Recurring_Admin_Notices $notices;

			public WC_Gateway_Vipps_Recurring $gateway;

			public array $ajax_config = [];

			/**
			 * Returns the *Singleton* instance of this class.
			 *
			 * @return WC_Vipps_Recurring
			 */
			public static function get_instance(): WC_Vipps_Recurring {
				if ( null === self::$instance ) {
					self::$instance = new self();
				}

				return self::$instance;
			}

			/**
			 * Private clone method to prevent cloning of the instance of the
			 * *Singleton* instance.
			 *
			 * @return void
			 */
			private function __clone() {
			}

			/**
			 * Private un-serialize method to prevent un-serializing of the *Singleton*
			 * instance.
			 *
			 * @return void
			 */
			public function __wakeup() {
			}

			/**
			 * Protected constructor to prevent creating a new instance of the
			 * *Singleton* via the `new` operator from outside of this class.
			 */
			private function __construct() {
				add_action( 'admin_init', [ $this, 'install' ] );
				$this->init();
			}

			public static function deactivate() {
				self::get_instance()->gateway->webhook_teardown();
			}

			/**
			 * Init the plugin after plugins_loaded so environment variables are set.
			 *
			 * @since 1.0.0
			 * @version 4.0.0
			 */
			public function init() {
				require_once __DIR__ . '/includes/wc-vipps-recurring-helper.php';
				require_once __DIR__ . '/includes/wc-vipps-recurring-logger.php';
				require_once __DIR__ . '/includes/wc-gateway-vipps-recurring.php';
				require_once __DIR__ . '/includes/wc-vipps-recurring-admin-notices.php';
				require_once __DIR__ . '/includes/wc-vipps-recurring-rest-api.php';

				self::load_plugin_textdomain( 'vipps-recurring-payments-gateway-for-woocommerce', false, plugin_basename( __DIR__ ) . '/languages' );

				$this->notices = WC_Vipps_Recurring_Admin_Notices::get_instance( __FILE__ );
				$this->gateway = WC_Gateway_Vipps_Recurring::get_instance();
				WC_Vipps_Recurring_Rest_Api::get_instance();

				add_action( 'wp_enqueue_scripts', [ $this, 'wp_enqueue_scripts' ] );

				if ( is_admin() ) {
					add_action( 'admin_init', [ $this, 'admin_init' ] );
					add_action( 'admin_menu', [ $this, 'admin_menu' ] );
					add_action( 'wp_ajax_vipps_recurring_force_check_charge_statuses', [
						$this,
						'wp_ajax_vipps_recurring_force_check_charge_statuses'
					] );
				}

				add_filter( 'woocommerce_payment_gateways', [ $this, 'add_gateways' ] );

				add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [
					$this,
					'plugin_action_links'
				] );

				// Add custom cron schedules for Vipps/MobilePay charge polling
				add_filter( 'cron_schedules', [
					$this,
					'woocommerce_vipps_recurring_add_cron_schedules'
				] );

				// schedule recurring payment charge status checking event
				if ( ! wp_next_scheduled( 'woocommerce_vipps_recurring_check_order_statuses' ) ) {
					wp_schedule_event( time(), 'one_minute', 'woocommerce_vipps_recurring_check_order_statuses' );
				}

				add_action( 'woocommerce_vipps_recurring_check_order_statuses', [
					$this,
					'check_order_statuses'
				] );

				// Schedule checking if gateway change went through
				if ( ! wp_next_scheduled( 'woocommerce_vipps_recurring_check_gateway_change_request' ) ) {
					wp_schedule_event( time(), 'one_minute', 'woocommerce_vipps_recurring_check_gateway_change_request' );
				}

				add_action( 'woocommerce_vipps_recurring_check_gateway_change_request', [
					$this,
					'check_gateway_change_agreement_statuses'
				] );

				// Schedule checking for updating payment details
				if ( ! wp_next_scheduled( 'woocommerce_vipps_recurring_update_subscription_details_in_app' ) ) {
					wp_schedule_event( time(), 'one_minute', 'woocommerce_vipps_recurring_update_subscription_details_in_app' );
				}

				add_action( 'woocommerce_vipps_recurring_update_subscription_details_in_app', [
					$this,
					'update_subscription_details_in_app'
				] );

				// Add custom product settings for Vipps Recurring.
				add_filter( 'woocommerce_product_data_tabs', [ $this, 'woocommerce_product_data_tabs' ] );
				add_filter( 'woocommerce_product_data_panels', [ $this, 'woocommerce_product_data_panels' ] );
				add_filter( 'woocommerce_process_product_meta', [ $this, 'woocommerce_process_product_meta' ] );

				// Disable this gateway unless we're purchasing at least one subscription product.
				add_filter( 'woocommerce_available_payment_gateways', [ $this, 'maybe_disable_gateway' ] );

				// Add our own ajax actions
				add_action( 'wp_ajax_woo_vipps_recurring_order_action', [
					$this,
					'order_handle_vipps_recurring_action'
				] );

				add_action( 'woocommerce_api_wc_gateway_vipps_recurring', [ $this, 'handle_webhook_callback' ] );

				// Custom actions
				add_filter( 'generate_rewrite_rules', [ $this, 'custom_action_endpoints' ] );
				add_filter( 'query_vars', [ $this, 'custom_action_query_vars' ] );
				add_filter( 'template_include', [ $this, 'custom_action_page_template' ] );
			}

			/**
			 * Admin only dashboard
			 */
			public function admin_init() {
				add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );

				add_action( 'admin_head', [ $this, 'admin_head' ] );

				if ( ! class_exists( 'WC_Subscriptions' ) ) {
					// translators: %s link to WooCommerce Subscription's purchase page
					$notice = sprintf( esc_html__( 'Vipps/MobilePay Recurring Payments requires WooCommerce Subscriptions to be installed and active. You can purchase and download %s here.', 'vipps-recurring-payments-gateway-for-woocommerce' ), '<a href="https://woocommerce.com/products/woocommerce-subscriptions/" target="_blank">WooCommerce Subscriptions</a>' );
					$this->notices->error( $notice );

					return;
				}

				// Add capture button if order is not captured
				add_action( 'woocommerce_order_item_add_action_buttons', [
					$this,
					'order_item_add_action_buttons'
				] );

				if ( $this->gateway->test_mode ) {
					$notice = __( 'Vipps/MobilePay Recurring Payments is currently in test mode - no real transactions will occur. Disable this in your wp_config when you are ready to go live!', 'vipps-recurring-payments-gateway-for-woocommerce' );
					$this->notices->warning( $notice );
				}

				// Load correct list table classes for current screen.
				add_action( 'current_screen', [ $this, 'setup_screen' ] );

				if ( isset( $_REQUEST['statuses_checked'] ) ) {
					$this->notices->success( __( 'Successfully checked the status of these charges', 'vipps-recurring-payments-gateway-for-woocommerce' ) );
				}

				// Initialize webhooks if we haven't already
				if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
					if ( WC_Vipps_Recurring_Helper::is_connected() ) {
						if ( empty( get_option( WC_Vipps_Recurring_Helper::OPTION_WEBHOOKS ) ) ) {
							$this->gateway->webhook_initialize();
						} else {
							$ok = $this->gateway->webhook_ensure_this_site();
							if ( ! $ok ) {
								$this->gateway->webhook_initialize();
							}
						}
					}
				}

				// Show Vipps Login notice for a maximum of 10 days
				// 1636066799 = 04-11-2021 23:59:59 UTC
//				if ( ! class_exists( 'VippsWooLogin' ) && time() < 1636066799 ) {
//					$vipps_login_plugin_url = 'https://wordpress.org/plugins/login-with-vipps';
//					if ( get_locale() === 'nb_NO' ) {
//						$vipps_login_plugin_url = 'https://nb.wordpress.org/plugins/login-with-vipps';
//					}
//
//					$this->notices->campaign(
//					/* translators: %1$s URL to login-with-vipps, %2$s translation for "here" */
//						sprintf( __( 'Login with Vipps is available for WooCommerce. Super-easy and safer login for your customers - no more usernames and passwords. Get started <a href="%1$s" target="_blank">%2$s</a>!', 'vipps-recurring-payments-gateway-for-woocommerce' ), $vipps_login_plugin_url, __( 'here', 'vipps-recurring-payments-gateway-for-woocommerce' ) ),
//						'login_promotion',
//						true,
//						'assets/images/vipps-logg-inn-neg.png',
//						'login-promotion'
//					);
//				}
			}

			/**
			 * Upgrade routines
			 */
			public function upgrade() {
				global $wpdb;

				$version = get_option( 'woo-vipps-recurring-version' );

				// Update 1.8.1: add back _vipps_recurring_pending_charge and _charge_id
				if ( version_compare( $version, '1.8.1', '<' ) ) {
					$results = $wpdb->get_results( "SELECT wp_posts.id FROM (
						SELECT DISTINCT post_id as id FROM wp_postmeta as m
						WHERE EXISTS (SELECT * FROM wp_postmeta WHERE post_id = m.post_id AND meta_key = '_vipps_recurring_failed_charge_reason')
						AND NOT EXISTS (SELECT * FROM wp_postmeta WHERE post_id = m.post_id AND meta_key = '_vipps_recurring_pending_charge')
					) as lookup
					JOIN wp_posts ON (wp_posts.id = lookup.id)
					ORDER BY wp_posts.post_date DESC", ARRAY_A );

					WC_Vipps_Recurring_Logger::log( sprintf( 'Running 1.8.1 update, affecting orders with IDs: %s', implode( ',', array_map( function ( $item ) {
						return $item['id'];
					}, $results ) ) ) );

					foreach ( $results as $row ) {
						$order = wc_get_order( $row['id'] );
						WC_Vipps_Recurring_Helper::set_order_charge_not_failed( $order, WC_Vipps_Recurring_Helper::get_transaction_id_for_order( $order ) );
						$order->save();
					}
				}

				// Update 1.8.2: migrate failed statuses to subscription too
				if ( version_compare( $version, '1.8.2', '<' ) ) {
					$results = $wpdb->get_results( "SELECT wp_posts.id
						FROM (
						         SELECT DISTINCT post_id as id
						         FROM wp_postmeta as m
						         WHERE EXISTS(SELECT *
						                      FROM wp_postmeta
						                      WHERE post_id = m.post_id
						                        AND meta_key = '_vipps_recurring_failed_charge_reason')
						     ) as lookup
						         JOIN wp_posts ON (wp_posts.id = lookup.id)
						ORDER BY wp_posts.post_date DESC", ARRAY_A );

					WC_Vipps_Recurring_Logger::log( sprintf( 'Running 1.8.2 update, affecting orders with IDs: %s', implode( ',', array_map( function ( $item ) {
						return $item['id'];
					}, $results ) ) ) );

					foreach ( $results as $row ) {
						$order = wc_get_order( $row['id'] );

						$subscriptions = WC_Vipps_Recurring_Helper::get_subscriptions_for_order( $order );
						$subscription  = $subscriptions[ array_key_first( $subscriptions ) ];

						if ( ! $subscription ) {
							continue;
						}

						$failure_reason = WC_Vipps_Recurring_Helper::get_meta( $order, WC_Vipps_Recurring_Helper::META_CHARGE_FAILED_REASON );
						if ( $failure_reason ) {
							WC_Vipps_Recurring_Helper::update_meta_data( $subscription, WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_LATEST_FAILED_CHARGE_REASON, $failure_reason );
						}

						$failure_description = WC_Vipps_Recurring_Helper::get_meta( $order, WC_Vipps_Recurring_Helper::META_CHARGE_FAILED_DESCRIPTION );
						if ( $failure_description ) {
							WC_Vipps_Recurring_Helper::update_meta_data( $subscription, WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_LATEST_FAILED_CHARGE_DESCRIPTION, $failure_description );
						}

						$subscription->save();
					}
				}

//				if ( version_compare( $version, '1.13.0', '<' ) ) {
//					$subscriptions = wcs_get_subscriptions( [
//						'payment_method' => 'kco'
//					] );
//
//					WC_Vipps_Recurring_Logger::log( sprintf( 'Running 1.13.0 update, affecting subscriptions with IDs: %s', implode( ',', array_map( function ( $item ) {
//						return $item->get_id();
//					}, $subscriptions ) ) ) );
//
//					foreach ( $subscriptions as $subscription ) {
//						if ( $subscription->get_payment_method() === 'kco' && ! empty( WC_Vipps_Recurring_Helper::get_agreement_id_from_order( $subscription ) ) ) {
//							$subscription->set_payment_method( 'vipps_recurring' );
//							$subscription->save();
//						}
//					}
//				}

				if ( $version !== WC_VIPPS_RECURRING_VERSION ) {
					update_option( 'woo-vipps-recurring-version', WC_VIPPS_RECURRING_VERSION );
				}
			}

			/**
			 * Inject admin ahead
			 */
			public function admin_head() {
				$icon = plugins_url( 'assets/images/' . $this->gateway->brand . '-mark-icon.svg', __FILE__ );

				?>
				<style>
					#woocommerce-product-data ul.wc-tabs li.wc_vipps_recurring_options a:before {
						background-image: url( <?php echo $icon ?> );
					}
				</style>
				<?php
			}

			public function gateway_should_be_active( array $methods = [] ) {
				// The only two reasons to not show our gateway is if the cart supports being purchased by the standard Vipps MobilePay gateway
				// Or if the cart does not contain a subscription product
				$active = ! isset( $methods['vipps'] ) && WC_Subscriptions_Cart::cart_contains_subscription();

				return apply_filters( 'wc_vipps_recurring_cart_has_subscription_product', $active, WC()->cart->get_cart_contents() );
			}

			public function maybe_disable_gateway( $methods ) {
				if ( is_admin() || ! is_checkout() ) {
					return $methods;
				}

				$show_gateway = $this->gateway_should_be_active( $methods );

				if ( ! $show_gateway ) {
					unset( $methods['vipps_recurring'] );
				}

				return $methods;
			}

			/**
			 * @return string
			 */
			public function handle_check_statuses_bulk_action(): string {
				$sendback = remove_query_arg( [ 'orders' ], wp_get_referer() );

				if ( isset( $_GET['orders'] ) ) {
					$order_ids = $_GET['orders'];

					foreach ( $order_ids as $order_id ) {
						// check charge status
						$this->gateway->check_charge_status( $order_id );
					}

					$sendback = add_query_arg( 'statuses_checked', 1, $sendback );
				}

				return $sendback;
			}

			/**
			 * Setup the screen for our special setting and action tables
			 */
			public function setup_screen() {
				global $wc_vipps_recurring_list_table_pending_charges,
					   $wc_vipps_recurring_list_table_failed_charges;

				$screen_id = false;

				if ( function_exists( 'get_current_screen' ) ) {
					$screen    = get_current_screen();
					$screen_id = isset( $screen, $screen->id ) ? $screen->id : '';
				}

				if ( ! empty( $_REQUEST['screen'] ) ) {
					$screen_id = wc_clean( wp_unslash( $_REQUEST['screen'] ) );
				}

				if ( $screen_id === 'settings_page_woo-vipps-recurring' ) {
					include_once 'includes/admin/list-tables/wc-vipps-recurring-list-table-pending-charges.php';
					include_once 'includes/admin/list-tables/wc-vipps-recurring-list-table-failed-charges.php';

					$wc_vipps_recurring_list_table_pending_charges = new WC_Vipps_Recurring_Admin_List_Pending_Charges( [
						'screen' => $screen_id . '_pending-charges'
					] );
					$wc_vipps_recurring_list_table_failed_charges  = new WC_Vipps_Recurring_Admin_List_Failed_Charges( [
						'screen' => $screen_id . '_failed-charges'
					] );
				}

				if ( $wc_vipps_recurring_list_table_pending_charges
					 && $wc_vipps_recurring_list_table_pending_charges->current_action()
					 && $wc_vipps_recurring_list_table_pending_charges->current_action() === 'check_status' ) {
					$sendback = $this->handle_check_statuses_bulk_action();

					wp_redirect( $sendback );
				}

				if ( $wc_vipps_recurring_list_table_failed_charges
					 && $wc_vipps_recurring_list_table_failed_charges->current_action()
					 && $wc_vipps_recurring_list_table_failed_charges->current_action() === 'check_status' ) {
					$sendback = $this->handle_check_statuses_bulk_action();

					wp_redirect( $sendback );
				}

				// Ensure the table handler is only loaded once. Prevents multiple loads if a plugin calls check_ajax_referer many times.
				remove_action( 'current_screen', [ $this, 'setup_screen' ] );
			}

			/**
			 * Make admin menu entry
			 */
			public function admin_menu() {
				add_options_page(
					__( 'Vipps/MobilePay Recurring Payments', 'vipps-recurring-payments-gateway-for-woocommerce' ),
					__( 'Vipps/MobilePay Recurring Payments', 'vipps-recurring-payments-gateway-for-woocommerce' ),
					'manage_options',
					'woo-vipps-recurring',
					[ $this, 'admin_menu_page_html' ]
				);
			}

			/**
			 * Admin menu page HTML
			 */
			public function admin_menu_page_html() {
				if ( ! current_user_can( 'manage_options' ) ) {
					return;
				}

				include __DIR__ . '/includes/pages/admin/vipps-recurring-admin-menu-page.php';
			}

			public function custom_action_endpoints( $rewrites ) {
				$rewrites->rules = array_merge(
					[ 'vipps-mobilepay-recurring-payment/?$' => 'index.php?vipps_recurring_action=payment-redirect' ],
					$rewrites->rules
				);

				return $rewrites;
			}

			public function custom_action_query_vars( $query_vars ) {
				$query_vars[] = 'vipps_recurring_action';

				return $query_vars;
			}

			public function custom_action_page_template( $original_template ) {
				$custom_action = get_query_var( 'vipps_recurring_action' );

				if ( ! empty ( $custom_action ) ) {
					include WC_VIPPS_RECURRING_PLUGIN_PATH . '/includes/pages/payment-redirect-page.php';
					die;
				}

				return $original_template;
			}

			/**
			 * Force check status of all pending charges
			 */
			public function wp_ajax_vipps_recurring_force_check_charge_statuses(): void {
				try {
					/* translators: amount of orders checked */
					echo sprintf( __( 'Done. Checked the status of %s orders', 'vipps-recurring-payments-gateway-for-woocommerce' ), count( $this->check_order_statuses( - 1 ) ) );
				} catch ( Exception $e ) {
					echo __( 'Failed to finish checking the status of all orders. Please try again.', 'vipps-recurring-payments-gateway-for-woocommerce' );
				}

				wp_die();
			}

			/**
			 * @param $tabs
			 *
			 * @return mixed
			 */
			public function woocommerce_product_data_tabs( $tabs ) {
				$tabs['wc_vipps_recurring'] = [
					'label'    => __( 'Vipps/MobilePay Recurring Payments', 'vipps-recurring-payments-gateway-for-woocommerce' ),
					'target'   => 'wc_vipps_recurring_product_data',
					'priority' => 100,
				];

				return $tabs;
			}

			/**
			 * Tab content
			 */
			public function woocommerce_product_data_panels(): void {
				echo '<div id="wc_vipps_recurring_product_data" class="panel woocommerce_options_panel hidden">';

				woocommerce_wp_checkbox( [
					'id'          => WC_Vipps_Recurring_Helper::META_PRODUCT_DIRECT_CAPTURE,
					'value'       => get_post_meta( get_the_ID(), WC_Vipps_Recurring_Helper::META_PRODUCT_DIRECT_CAPTURE, true ),
					'label'       => __( 'Capture payment instantly', 'vipps-recurring-payments-gateway-for-woocommerce' ),
					'description' => __( 'Capture payment instantly even if the product is not virtual. Please make sure you are following the local jurisdiction in your country when using this option.', 'vipps-recurring-payments-gateway-for-woocommerce' ),
					'desc_tip'    => true,
				] );

				woocommerce_wp_select( [
					'id'          => WC_Vipps_Recurring_Helper::META_PRODUCT_DESCRIPTION_SOURCE,
					'value'       => get_post_meta( get_the_ID(), WC_Vipps_Recurring_Helper::META_PRODUCT_DESCRIPTION_SOURCE, true ) ?: 'title',
					'label'       => __( 'Description source', 'vipps-recurring-payments-gateway-for-woocommerce' ),
					'description' => __( 'Where we should source the agreement description from. Displayed in the Vipps/MobilePay app.', 'vipps-recurring-payments-gateway-for-woocommerce' ),
					'desc_tip'    => true,
					'options'     => [
						'none'              => __( 'None', 'vipps-recurring-payments-gateway-for-woocommerce' ),
						'short_description' => __( 'Product short description', 'vipps-recurring-payments-gateway-for-woocommerce' ),
						'custom'            => __( 'Custom', 'vipps-recurring-payments-gateway-for-woocommerce' )
					]
				] );

				woocommerce_wp_text_input( [
					'id'          => WC_Vipps_Recurring_Helper::META_PRODUCT_DESCRIPTION_TEXT,
					'value'       => get_post_meta( get_the_ID(), WC_Vipps_Recurring_Helper::META_PRODUCT_DESCRIPTION_TEXT, true ),
					'label'       => __( 'Custom description', 'vipps-recurring-payments-gateway-for-woocommerce' ),
					'description' => __( 'If the description source is set to "custom" this text will be used.', 'vipps-recurring-payments-gateway-for-woocommerce' ),
					'placeholder' => __( 'Max 100 characters', 'vipps-recurring-payments-gateway-for-woocommerce' ),
					'desc_tip'    => true,
				] );

				echo '</div>';
			}

			/**
			 * Save our custom fields
			 *
			 * @param $post_id
			 */
			public function woocommerce_process_product_meta( $post_id ) {
				$capture_instantly = isset( $_POST[ WC_Vipps_Recurring_Helper::META_PRODUCT_DIRECT_CAPTURE ] ) ? 'yes' : 'no';
				update_post_meta( $post_id, WC_Vipps_Recurring_Helper::META_PRODUCT_DIRECT_CAPTURE, $capture_instantly );

				update_post_meta( $post_id, WC_Vipps_Recurring_Helper::META_PRODUCT_DESCRIPTION_SOURCE, $_POST[ WC_Vipps_Recurring_Helper::META_PRODUCT_DESCRIPTION_SOURCE ] );
				update_post_meta( $post_id, WC_Vipps_Recurring_Helper::META_PRODUCT_DESCRIPTION_TEXT, $_POST[ WC_Vipps_Recurring_Helper::META_PRODUCT_DESCRIPTION_TEXT ] ?? '' );
			}

			/**
			 * @param $order
			 */
			public function order_item_add_action_buttons( $order ): void {
				$this->order_item_add_capture_button( $order );
			}

			/**
			 * @param $order
			 */
			public function order_item_add_capture_button( $order ): void {
				if ( $order->get_type() !== 'shop_order' ) {
					return;
				}

				$payment_method = WC_Vipps_Recurring_Helper::get_payment_method( $order );
				if ( $payment_method !== $this->gateway->id ) {
					// If this is not the payment method, an agreement would not be available.
					return;
				}

				$show_capture_button = WC_Vipps_Recurring_Helper::can_capture_charge_for_order( $order );

				if ( ! apply_filters( 'wc_vipps_recurring_show_capture_button', $show_capture_button, $order ) ) {
					return;
				}

				$is_captured = WC_Vipps_Recurring_Helper::is_charge_captured_for_order( $order );

				if ( $show_capture_button && ! $is_captured ) {
					$logo = plugins_url( 'assets/images/' . $this->gateway->brand . '-logo-white.svg', __FILE__ );

					print '<button type="button" data-order-id="' . $order->get_id() . '" data-action="capture_payment" class="button generate-items capture-payment-button ' . $this->gateway->brand . '"><img border="0" style="display:inline;height:2ex;vertical-align:text-bottom" class="inline" alt="0" src="' . $logo . '"/> ' . __( 'Capture payment', 'vipps-recurring-payments-gateway-for-woocommerce' ) . '</button>';
				}
			}

			public function order_handle_vipps_recurring_action() {
				check_ajax_referer( 'vipps_recurring_ajax_nonce', 'nonce' );

				$order = wc_get_order( intval( $_REQUEST['orderId'] ) );
				if ( ! is_a( $order, 'WC_Order' ) ) {
					return;
				}

				if ( $order->get_payment_method() != $this->gateway->id ) {
					return;
				}

				$action = isset( $_REQUEST['do'] ) ? sanitize_title( $_REQUEST['do'] ) : 'none';

				if ( $action == 'capture_payment' ) {
					$this->gateway->maybe_capture_payment( $order->get_id() );
				}

				print "1";
			}

			/**
			 * Check charge statuses scheduled action
			 *
			 * @param int|null $limit
			 *
			 * @return array
			 */
			public function check_order_statuses( $limit = '' ): array {
				if ( empty( $limit ) ) {
					$limit = $this->gateway->check_charges_amount;
				}

				$options = [
					'limit'          => $limit,
					'type'           => 'shop_order',
					'meta_key'       => WC_Vipps_Recurring_Helper::META_CHARGE_PENDING,
					'meta_compare'   => '=',
					'meta_value'     => 1,
					'return'         => 'ids',
					'payment_method' => $this->gateway->id
				];

				if ( $this->gateway->check_charges_sort_order === 'rand' ) {
					$options['orderby'] = 'rand';
				} else {
					$options['orderby'] = 'post_date';
					$options['order']   = $this->gateway->check_charges_sort_order;
				}

				remove_all_filters( 'posts_orderby' );
				$order_ids = wc_get_orders( $options );

				foreach ( $order_ids as $order_id ) {
					// check charge status
					$this->gateway->check_charge_status( $order_id );
				}

				return $order_ids;
			}

			/**
			 * Check the status of gateway change requests
			 */
			public function check_gateway_change_agreement_statuses() {
				$posts = get_posts( [
					'post_type'    => 'shop_subscription',
					'post_status'  => [ 'wc-active', 'wc-pending', 'wc-on-hold' ],
					'meta_key'     => WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_WAITING_FOR_GATEWAY_CHANGE,
					'meta_compare' => '=',
					'meta_value'   => 1,
					'return'       => 'ids',
				] );

				foreach ( $posts as $post ) {
					// check charge status
					$this->gateway->maybe_process_gateway_change( $post->ID );
				}
			}

			/**
			 * Update a subscription's details in the app
			 */
			public function update_subscription_details_in_app() {
				$posts = get_posts( [
					'limit'        => 5,
					'post_type'    => 'shop_subscription',
					'post_status'  => [ 'wc-active', 'wc-pending-cancel', 'wc-cancelled', 'wc-on-hold' ],
					'meta_key'     => WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_UPDATE_IN_APP,
					'meta_compare' => '=',
					'meta_value'   => 1,
					'return'       => 'ids',
				] );

				foreach ( $posts as $post ) {
					// check charge status
					$this->gateway->maybe_update_subscription_details_in_app( $post->ID );
				}
			}

			/**
			 * Adds plugin action links.
			 *
			 * @since 1.0.0
			 * @version 4.0.0
			 */
			public function plugin_action_links( $links ): array {
				$plugin_links = [
					'<a href="admin.php?page=wc-settings&tab=checkout&section=vipps_recurring">' . esc_html__( 'Settings', 'vipps-recurring-payments-gateway-for-woocommerce' ) . '</a>',
				];

				return array_merge( $plugin_links, $links );
			}

			/**
			 * Handles upgrade routines.
			 *
			 * @since 3.1.0
			 * @version 3.1.0
			 */
			public function install() {
				$this->upgrade();
			}

			/**
			 * Add the gateways to WooCommerce.
			 *
			 * @param $methods
			 *
			 * @return array
			 * @since 1.0.0
			 * @version 4.0.0
			 */
			public function add_gateways( $methods ): array {
				if ( function_exists( 'wcs_create_renewal_order' ) && class_exists( 'WC_Subscriptions_Order' ) ) {
					$methods[] = WC_Gateway_Vipps_Recurring::get_instance();
				}

				return $methods;
			}

			/**
			 * Enqueue our CSS and other assets.
			 */
			public function wp_enqueue_scripts() {
				wp_enqueue_style(
					'woo-vipps-recurring',

					WC_VIPPS_RECURRING_PLUGIN_URL . '/assets/build/index.css', [],
					filemtime( WC_VIPPS_RECURRING_PLUGIN_PATH . '/assets/build/index.css' )
				);

				$asset = require WC_VIPPS_RECURRING_PLUGIN_PATH . '/assets/build/index.asset.php';

				wp_enqueue_script(
					'woo-vipps-recurring',
					WC_VIPPS_RECURRING_PLUGIN_URL . '/assets/build/index.js',
					$asset['dependencies'],
					filemtime( WC_VIPPS_RECURRING_PLUGIN_PATH . '/assets/build/index.js' ),
					true
				);

				wp_localize_script( 'woo-vipps-recurring', 'VippsMobilePaySettings', [
					'logo' => WC_VIPPS_RECURRING_PLUGIN_URL . '/assets/images/' . $this->gateway->brand . '-logo.svg'
				] );

				wp_set_script_translations( 'woo-vipps-recurring', 'vipps-recurring-payments-gateway-for-woocommerce', WC_VIPPS_RECURRING_PLUGIN_PATH . '/languages' );
			}

			/**
			 * Enqueue our CSS and other assets.
			 */
			public function admin_enqueue_scripts() {
				wp_enqueue_style( 'woo-vipps-recurring', plugins_url( 'assets/css/vipps-recurring-admin.css', __FILE__ ), [],
					filemtime( __DIR__ . '/assets/css/vipps-recurring-admin.css' ) );

				$this->ajax_config['nonce']    = wp_create_nonce( 'vipps_recurring_ajax_nonce' );
				$this->ajax_config['currency'] = get_woocommerce_currency();

				wp_enqueue_script(
					'woo-vipps-recurring-admin',
					plugins_url( 'assets/js/vipps-recurring-admin.js', __FILE__ ),
					[ 'wp-i18n' ],
					filemtime( dirname( __FILE__ ) . "/assets/js/vipps-recurring-admin.js" ),
					true
				);

				wp_localize_script( 'woo-vipps-recurring-admin', 'VippsRecurringConfig', $this->ajax_config );
				wp_set_script_translations( 'woo-vipps-recurring-admin', 'vipps-recurring-payments-gateway-for-woocommerce', WC_VIPPS_RECURRING_PLUGIN_PATH . '/languages' );
			}

			/**
			 * @param $schedules
			 *
			 * @return mixed
			 */
			public function woocommerce_vipps_recurring_add_cron_schedules( $schedules ) {
				$schedules['one_minute'] = [
					'interval' => 60,
					'display'  => esc_html__( 'Every One Minute' ),
				];

				return $schedules;
			}

			public static function load_plugin_textdomain( $domain, $deprecated = false, $plugin_rel_path = false ): bool {
				$use_plugin_translations = apply_filters( 'woo_vipps_recurring_use_plugin_translations', false );

				// Use plugin translations if this is a WP installation older than 6.1.0
				if ( $use_plugin_translations || WC_Vipps_Recurring_Helper::is_wp_lt( '6.1.0' ) ) {
					return load_plugin_textdomain( $domain, $deprecated, $plugin_rel_path );
				}

				global $wp_textdomain_registry;

				$locale  = apply_filters( 'plugin_locale', determine_locale(), $domain );
				$mo_file = $domain . '-' . $locale . '.mo';

				$path = WP_PLUGIN_DIR . '/' . trim( $plugin_rel_path, '/' );
				$wp_textdomain_registry->set_custom_path( $domain, $path );

				return load_textdomain( $domain, $path . '/' . $mo_file, $locale );
			}

			public function handle_webhook_callback() {
				wc_nocache_headers();
				status_header( 202, "Accepted" );

				$raw_input = @file_get_contents( 'php://input' );
				$result    = @json_decode( $raw_input, true );

				$callback = $_REQUEST['callback'] ?? "";

				if ( $callback !== "webhook" ) {
					return;
				}

				if ( ! $result ) {
					$error = json_last_error_msg();
					WC_Vipps_Recurring_Logger::log( sprintf( "Did not understand callback from Vipps/MobilePay with body: %s – error: %s", empty( $raw_input ) ? "(empty string)" : $raw_input, $error ) );

					return;
				}

				$local_webhooks = $this->gateway->webhook_get_local()[ $this->gateway->merchant_serial_number ];
				$local_webhook  = array_pop( $local_webhooks );
				$secret         = $local_webhook ? ( $local_webhook['secret'] ?? false ) : false;

				$orderId = $result['referenceId'];

				if ( ! $secret ) {
					WC_Vipps_Recurring_Logger::log( sprintf( "Cannot verify webhook callback for order %s - this shop does not know the secret. You should delete all unwanted webhooks. If you are using the same MSN on several shops, this callback is probably for one of the others.", $orderId ) );
				}

				if ( ! $this->verify_webhook( $raw_input, $secret ) ) {
					return;
				}

				do_action( "woo_vipps_recurring_webhook_callback", $result, $raw_input );

				// We now have a validated webhook
				$this->gateway->handle_webhook_callback( $result );
			}

			public function verify_webhook( $serialized, $secret ): bool {
				$expected_auth = $_SERVER['HTTP_AUTHORIZATION'] ?? ( $_SERVER['HTTP_X_VIPPS_AUTHORIZATION'] ?? "" );
				$expected_date = $_SERVER['HTTP_X_MS_DATE'] ?? '';

				$hashed_payload = base64_encode( hash( 'sha256', $serialized, true ) );
				$path_and_query = $_SERVER['REQUEST_URI'];
				$host           = $_SERVER['HTTP_HOST'];
				$toSign         = "POST\n{$path_and_query}\n$expected_date;$host;$hashed_payload";
				$signature      = base64_encode( hash_hmac( 'sha256', $toSign, $secret, true ) );
				$auth           = "HMAC-SHA256 SignedHeaders=x-ms-date;host;x-ms-content-sha256&Signature=$signature";

				return ( $auth == $expected_auth );
			}
		}

		global $vipps_recurring;
		$vipps_recurring = WC_Vipps_Recurring::get_instance();

		require_once __DIR__ . '/includes/wc-vipps-recurring-compatibility.php';
	}
}

// Declare compatibility with WooCommerce HPOS
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__ );
	}
} );

// Declare compatibility with the WooCommerce checkout block
add_action( 'woocommerce_blocks_loaded', function () {
	if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		require_once dirname( __FILE__ ) . '/includes/wc-vipps-recurring-blocks-support.php';

		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function ( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
				$payment_method_registry->register( new WC_Vipps_Recurring_Blocks_Support() );
			}
		);
	}
} );
