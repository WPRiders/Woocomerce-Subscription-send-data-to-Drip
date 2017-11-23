<?php
/**
 * WP_Drip_Sync_API
 */
if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

if ( ! class_exists( 'WP_Drip_API' ) ) {
	/**
	 * Class WP_Drip_API
	 */
	class WP_Drip_API {
		/**
		 * @var null
		 */
		private $api_token = null;
		/**
		 * @var null
		 */
		private $api_account_id = null;
		/**
		 * @var string
		 */
		private $endpoint = 'https://api.getdrip.com/v2/';

		/**
		 * WP_Drip_Sync_API constructor.
		 *
		 * @param $args
		 */
		public function __construct( $args ) {
			$this->api_token      = $args['api_token'];
			$this->api_account_id = $args['api_account_id'];
		}

		/**
		 * @param $api_token
		 */
		public function drip_set_api_token( $api_token ) {

			$this->api_token = $api_token;

		}

		/**
		 * Validate Drip API token
		 *
		 * @param $api_token
		 *
		 * @return bool
		 */
		public function drip_validate_token( $api_token ) {
			$is_valid = false;

			$this->drip_set_api_token( $api_token );

			$accounts = $this->drip_list_accounts();

			if ( ! empty( $accounts ) ) {

				$is_valid = true;

			}

			return $is_valid;
		}

		/**
		 * List all accounts
		 *
		 * @return array
		 */
		public function drip_list_accounts() {

			$accounts = array();

			$api_params = array();

			$response = $this->drip_send_request( 'accounts', 'GET', $api_params );

			if ( $response['success'] ) {

				$accounts = $response['response'];

			}

			return $accounts;
		}

		/**
		 * Create or update a subscriber
		 *
		 * @param $account_id
		 * @param $subscriber_data
		 *
		 * @return array
		 */
		public function drip_create_or_update_subscriber( $account_id, $subscriber_data ) {

			$subscribers = array();

			$resource = "{$account_id}/subscribers";

			$response = $this->drip_send_request( $resource, 'POST', $subscriber_data );

			if ( $response['success'] ) {

				$subscribers = $response['response'];

			}

			return $subscribers;
		}

		/**
		 *
		 * Posts an event specified by the user.
		 *
		 * @param array $params
		 * @param bool
		 */
		public function drip_record_event( $account_id, $params ) {
			$status = false;

			$resource = "{$account_id}/events";

			$response = $this->drip_send_request( $resource, 'POST', $params );

			if ( $response['success'] ) {
				$status = $response['response'];
			}

			return $status;
		}

		/**
		 * Delete subscriber
		 *
		 * @param $account_id
		 * @param $subscriber_id
		 */
		public function drip_delete_subscriber( $account_id, $subscriber_id ) {
			$subscriber = array();

			$resource = "{$account_id}/subscribers/{$subscriber_id}";

			$response = $this->drip_send_request( $resource, 'DELETE', array() );

			if ( $response['success'] ) {

				$subscriber = $response['response'];

			}

			return $subscriber;
		}

		/**
		 * Fetch subscriber info
		 *
		 * @param $account_id
		 * @param $subscriber_id
		 */
		public function drip_fetch_subscriber( $account_id, $subscriber_id ) {
			$subscriber = array();

			$resource = "{$account_id}/subscribers/{$subscriber_id}";

			$response = $this->drip_send_request( $resource, 'GET', array() );

			if ( $response['success'] ) {

				$subscriber = $response['response']['subscribers'][0];

			}

			return $subscriber;
		}

		/**
		 * Send request to Drip API
		 *
		 * @param $resource
		 * @param $method
		 * @param $body
		 *
		 * @return array
		 */
		public function drip_send_request( $resource, $method, $body ) {

			$response = array();
			$success  = false;

			if ( ! empty( $this->api_token ) ) {

				$api_url = $this->endpoint . $resource;

				$arguments = array(
					'timeout'   => 30,
					'sslverify' => false,
					'headers'   => array(
						'Authorization' => 'Basic ' . base64_encode( "{$this->api_token}:" ),
						'Content-Type'  => 'application/vnd.api+json',
					),
					'body'      => empty( $body ) ? array() : wp_json_encode( $body ),
				);

				switch ( $method ) {

					case 'GET':

						$raw_response = wp_remote_get( $api_url, $arguments );

						break;

					case 'POST':

						$raw_response = wp_remote_post( $api_url, $arguments );

						break;

					default:

						$raw_response = wp_remote_request( $api_url, array_merge( array( 'method' => $method ), $arguments ) );

						break;
				}

				$response = json_decode( wp_remote_retrieve_body( $raw_response ), true );

				if ( is_wp_error( $raw_response ) || ( ! in_array( wp_remote_retrieve_response_code( $raw_response ), array(
						200,
						201,
						204
					) ) )
				) {

				} else {

					if ( empty( $response ) ) {

						$response = $raw_response['response']['message'];

					}
					$success = true;

				}
			}

			return array( 'success' => $success, 'response' => $response );

		}
	}
}