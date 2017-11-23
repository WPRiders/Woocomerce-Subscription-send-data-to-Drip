<?php
/**
 * Plugin Name: Do Woocommerce Drip
 * Plugin URI: http://www.wpriders.com
 * Description: Do Woocommerce Drip
 * Version: 1.0.0
 * Author: Ovidiu George Irodiu from WPRiders
 * Author URI: http://www.wpriders.com
 * License: GPL2
 */

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

if ( ! class_exists( 'Do_Woocommerce_Drip' ) ) {
	require_once( plugin_dir_path( __FILE__ ) . 'drip-api.php' );

	/**
	 * Class Wpr_Woocommerce_Drip
	 */
	class Do_Woocommerce_Drip {
		/**
		 * @var null
		 */
		protected $_drip_api = null;

		protected $_drip_account_id = null;

		protected $_drip_token = null;

		protected $options;

		/**
		 * Do_Woocommerce_Drip constructor.
		 */
		function __construct() {
			$drip_data = get_option( 'drip_options', true );
			$this->wp_drip_sync_api_call( $drip_data['drip_token'], $drip_data['drip_account_id'] );
			$this->options = get_option( 'drip_options' );

			add_action( 'woocommerce_subscription_payment_complete', array( &$this, 'do_process_subscription_payment' ), 10, 1 );

			add_action( 'admin_menu', array( &$this, 'drip_admin_menu' ) );
			add_action( 'admin_init', array( &$this, 'admin_register_settings' ) );
		}

		/**
		 * Drip API conection
		 *
		 * @param $api_key
		 */
		function wp_drip_sync_api_call( $_drip_token, $_drip_account_id ) {
			$this->_drip_token      = $_drip_token;
			$this->_drip_account_id = $_drip_account_id;
			$this->_drip_api        = new WP_Drip_API( array(
				'api_token'      => empty( $this->_drip_token ) ? null : $this->_drip_token,
				'api_account_id' => empty( $this->_drip_account_id ) ? null : $this->_drip_account_id,
			) );
		}

		/**
		 * Register admin menu
		 */
		public function drip_admin_menu() {
			add_options_page( 'Drip', 'Drip', 'manage_options', 'wp-drip', array( &$this, 'drip_options_page' ) );
		}

		/**
		 * Drip page template
		 */
		public function drip_options_page() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html( 'You do not have sufficient permissions to access this page . ' ) );
			}

			include( plugin_dir_path( __FILE__ ) . 'options-page.php' );
		}

		/**
		 * Register admin settings
		 */
		public function admin_register_settings() {
			register_setting( 'drip_options', 'drip_options', array( &$this, 'validate_settings' ) );
			add_settings_section( 'drip_settings', 'Drip account', array( &$this, 'admin_section_code_settings' ), 'wp-drip' );
			add_settings_field( 'drip_token', 'API Token', array( &$this, 'admin_option_token' ), 'wp-drip', 'drip_settings' );
			add_settings_field( 'drip_account_id', 'Account ID', array( &$this, 'admin_option_account_id' ), 'wp-drip', 'drip_settings' );
		}

		/**
		 * Validate form settings
		 *
		 * @param $input
		 *
		 * @return string
		 */
		public function validate_settings( $input ) {
			$options = $this->options;

			if ( isset( $input['drip_token'] ) ) {
				$drip_token = trim( $input['drip_token'] );

				$drip_api = new WP_Drip_API( array( 'api_token' => $drip_token ) );

				if ( '' !== $drip_token && $drip_api->drip_validate_token( $drip_token ) ) {
					$options['drip_token'] = $drip_token;
				} else {
					add_settings_error( 'drip_token', 'wp-drip_drip_token_error', 'Please enter a valid Drip token', 'error' );
				}
			}

			if ( isset( $input['drip_account_id'] ) ) {
				$drip_account_id = trim( $input['drip_account_id'] );

				if ( is_int( $drip_account_id ) || ctype_digit( $drip_account_id ) || '' === $drip_account_id ) {
					$options['drip_account_id'] = $drip_account_id;
				} else {
					add_settings_error( 'drip_account_id', 'wp-drip_drip_account_id_error', 'Please enter a valid Drip Account ID', 'error' );
				}
			}

			return $options;
		}

		/**
		 * Add Drip section name
		 */
		public function admin_section_code_settings() {
			echo '<p>' . esc_html( 'Insert Drip API Token & Account ID bellow' ) . '</p>';
		}

		/**
		 * Add Drip token input
		 */
		public function admin_option_token() {
			echo sprintf( "<input type='text' name='drip_options[drip_token]' size='20' value='%s' />", esc_attr( $this->get_option( 'drip_token' ) ) );
		}

		/**
		 * Add Drip account id input
		 */
		public function admin_option_account_id() {
			echo sprintf( "<input type='text' name='drip_options[drip_account_id]' size='20' value='%s' />", esc_attr( $this->get_option( 'drip_account_id' ) ) );
		}

		/**
		 * Send data to Drip on New Subscription or Renewal
		 *
		 * @param $subscription
		 */
		public function do_process_subscription_payment( $subscription ) {
			$subscription_id = $subscription;
			if ( is_object( $subscription ) ) {
				$subscription_id = $subscription->ID;
			}

			$subscription_ord    = wcs_get_subscription( $subscription_id );
			$subscription_orders = $subscription_ord->get_related_orders();

			$order_id = current( $subscription_orders );
			$order    = new WC_Order( $order_id );

			$user_id = $subscription_ord->get_user_id();
			if ( ! $user_id ) {
				return;
			}

			$billing_email = version_compare( WC_VERSION, '3.0', '<' ) ? $subscription_ord->billing_email : $subscription_ord->get_billing_email();

			$renewal = false;
			// Its first subscription
			$event_sale_name = apply_filters( 'do_woo_drip_action_order', __( 'Subscription', 'do_woo_drip' ) );
			// Its renewal
			if ( count( $subscription_orders ) > 1 ) {
				$renewal         = true;
				$event_sale_name = apply_filters( 'do_woo_drip_action_order_renew', __( 'Renew', 'do_woo_drip' ) );
			}

			// Subscriber Parameters
			$subscriber_params = array(
				'account_id'    => $this->_drip_account_id,
				'email'         => $billing_email,
				'time_zone'     => wc_timezone_string(),
				'custom_fields' => $this->drip_custom_fields( $order, $user_id, $renewal ),
				'tags'          => apply_filters( 'do_woo_drip_tag_customer', array(
					__( 'WooSubscriber', 'do_woo_drip' ),
				) )
			);

			$this->_drip_api->drip_create_or_update_subscriber( $this->_drip_account_id, array( 'subscribers' => array( $subscriber_params ) ) );

			// Product name
			$products = implode( ', ', array_map( function ( $product ) {
				return version_compare( WC_VERSION, '3.0', '<' ) ? $product['name'] : $product->get_name();
			}, $order->get_items() ) );

			// Product ID
			$product_ids = implode( ', ', array_filter( array_map( function ( $product ) {
				if ( is_a( $product, 'WC_Order_Item_Product' ) ) {
					return $product->get_product_id();
				}
			}, $order->get_items() ) ) );

			// Billing cycle & Variation ID
			$billing_cycle = $variation_id = $product_type = array();
			foreach ( $order->get_items() as $key => $val ) {
				$bill_type = wc_get_order_item_meta( $key, 'pa_billingcycle', true );
				if ( $bill_type ) {
					array_push( $billing_cycle, $bill_type );
				}

				$variation = wc_get_order_item_meta( $key, '_variation_id', true );
				if ( $variation > 0 ) {
					array_push( $variation_id, $variation );
				}

				$prodtype = wc_get_order_item_meta( $key, 'pa_producttype', true );
				if ( $prodtype ) {
					array_push( $product_type, $prodtype );
				} elseif ( $variation > 0 ) {
					$variation_name = get_post_meta( $variation, 'attribute_pa_producttype', true );
					if ( $variation_name ) {
						array_push( $product_type, $variation_name );
					}
				}
			}
			$billing_cycle = implode( ', ', array_filter( $billing_cycle ) );
			$variation_id  = implode( ', ', array_filter( $variation_id ) );
			$product_type  = implode( ', ', array_filter( $product_type ) );

			if ( empty( $billing_cycle ) ) {
				$billing_cycle = $subscription_ord->get_billing_period();
			}

			// $is_subscriber Variable
			$is_sub_action = $this->_drip_api->drip_fetch_subscriber( $this->_drip_account_id, $billing_email );

			if ( $is_sub_action ) {
				$is_subscriber = $is_sub_action['id'];
			} else {
				$is_subscriber = false;
			}

			$event_params = array(
				'account_id' => $this->_drip_account_id,
				'email'      => $billing_email,
				'action'     => $event_sale_name,
				'properties' => $this->drip_event_properties( $order->get_total(), $products, $order_id, $product_ids, $product_type, $billing_cycle, $variation_id ),
			);


			// Check if subscriber exists and if so, send data to Drip
			if ( $is_subscriber ) {

				$this->_drip_api->drip_record_event( $this->_drip_account_id, array( 'events' => array( $event_params ) ) );

			}
		}

		/**
		 * Drip Custom Fields
		 *
		 * @param $order
		 * @param $customer_id
		 * @param $renewal
		 *
		 * @return array
		 * @throws Exception
		 */
		public function drip_custom_fields( $order, $customer_id, $renewal ) {
			$email    = version_compare( WC_VERSION, '3.0', '<' ) ? $order->billing_email : $order->get_billing_email();
			$value    = $order->get_total();
			$products = $order->get_items();

			// Store lifetime_value field in variable
			$is_fetch_action = $this->_drip_api->drip_fetch_subscriber( $this->_drip_account_id, $email );

			if ( is_array( $is_fetch_action ) ) {
				$is_fetch_action = array_filter( $is_fetch_action );
			}

			$return_lifetime_value    = false;
			$return_previous_products = false;

			if ( ! empty( $is_fetch_action['custom_fields']['lifetime_value'] ) ) {
				$return_lifetime_value = $is_fetch_action['custom_fields']['lifetime_value'];
			}

			if ( ! empty( $is_fetch_action['custom_fields']['purchased_products'] ) ) {
				$return_previous_products = $is_fetch_action['custom_fields']['purchased_products'];
			}

			// Check for lifetime_value field
			if ( $return_lifetime_value ) {
				$lifetime_value = $return_lifetime_value;
			} else {
				$lifetime_value = 0;
			}

			// Add value to lifetime_value field
			$lifetime_value = $lifetime_value + $value;

			// Product IDs
			$product_ids = implode( ', ', array_reduce( $products, function ( $carry, $item ) {
				if ( is_a( $item, 'WC_Order_Item_Product' ) ) {
					$carry[] = $item->get_product_id();
				}

				return $carry;
			}, array() ) );

			// Determine and build list of total products, purchased before and now
			if ( $return_previous_products ) {
				// Is renewal?
				if ( $renewal ) {
					// Check if ID's exists
					$old_ids = explode( ', ', $return_previous_products );
					$new_ids = explode( ', ', $product_ids );

					$result      = array_diff( $new_ids, $old_ids );
					$product_ids = implode( ', ', $result );
				}

				if ( empty( $product_ids ) ) {
					$total_products = $return_previous_products;
				} else {
					$previous_products = $return_previous_products . ', ';
					$total_products    = $previous_products . $product_ids;
				}
			} else {
				$total_products = $product_ids;
			}

			// Build custom fields to attach to customer
			$content = apply_filters( 'do_woo_drip_custom_fields', array(
				'first_name'         => version_compare( WC_VERSION, '3.0', '<' ) ? $order->billing_first_name : $order->get_billing_first_name(),
				'last_name'          => version_compare( WC_VERSION, '3.0', '<' ) ? $order->billing_last_name : $order->get_billing_last_name(),
				'lifetime_value'     => $lifetime_value,
				'purchased_products' => $total_products,
				'country'            => $order->get_billing_country(),
				'state'              => $order->get_billing_state(),
			), $email, $lifetime_value, $products, $order );

			if ( $customer_id ) {
				$content['customer_id'] = $customer_id;
			}

			return $content;
		}

		/**
		 * Drip Event Fields
		 *
		 * @param $value
		 * @param $products
		 * @param $order_id
		 * @param $product_ids
		 * @param $product_type
		 * @param $billing_cycle
		 * @param $variation_id
		 *
		 * @return array|mixed|object
		 */
		public function drip_event_properties( $value, $products, $order_id, $product_ids, $product_type, $billing_cycle, $variation_id ) {
			$content = array(
				'value'         => $value * 100,
				'price'         => '$' . $value,
				'product_id'    => $product_ids,
				'product_names' => $products,
				'product_type'  => $product_type,
				'billing_cycle' => $billing_cycle,
				'variation_id'  => $variation_id,
				'order_id'      => $order_id,
			);

			$obj = json_decode( json_encode( $content ), false );

			return $obj;
		}

		/**
		 * Return wordpress option
		 *
		 * @param $key
		 *
		 * @return null
		 */
		public function get_option( $key ) {
			if ( isset( $this->options[ $key ] ) && '' != $this->options[ $key ] ) {
				return $this->options[ $key ];
			} else {
				return null;
			}
		}
	}
}

$do_woocommerce_drip = new Do_Woocommerce_Drip();
