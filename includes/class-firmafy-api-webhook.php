<?php
/**
 * Firmafy API Webhook service
 *
 * @package    WordPress
 * @author     David Perez <david@close.technology>
 * @copyright  2023 Closemarketing
 * @version    1.0
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;


/**
 * Webhook.
 *
 * @since 1.0.0
 */
class Firmafy_API_Webhook {
	/**
	 * Construct of Class
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_webhook' ) );
	}

	/**
	 * Registers webhook for Firmafy
	 *
	 * @return void
	 */
	public function register_webhook() {
		register_rest_route(
			'firmafy/v1',
			'/webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'process_webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Process webhook
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function process_webhook( $request ) {
		$body = $request->get_body();
		$body = json_decode( $body, true );
		$body = isset( $body['data'] ) ? $body['data'] : $body;

		if ( ! isset( $body['csv'] ) ) {
			return new WP_REST_Response( 'Invalid request. CSV not founded', 400 );
		}

		$sign_csv    = sanitize_text_field( $body['csv'] );
		$sign_status = sanitize_text_field( $body['status'] );
		if ( isset( $body['signer'] ) ) {
			$body['signer'] = json_decode( $body['signer'], true );
		}

		// Search order by meta.
		$args = array(
			'meta_key'     => '_firmafy_csv',
			'meta_value'   => $sign_csv,
			'meta_compare' => '=',
			'return'       => 'ids',
		);
		$orders = wc_get_orders( $args );

		do_action( 'firmafy_webhook_received', $body );

		if ( empty( $orders ) ) {
			$log        = new WC_Logger();
			$log_entry  = __( 'Order not found asked from Firmafy', 'firmafy' );
			$log_entry .= ' CSV: ' . $sign_csv . ' Status: ' . $sign_status;
			$log->log( 'firmafy', $log_entry );
			return new WP_REST_Response( 'Order not found', 404 );
		}
		foreach ( $orders as $order ) {
			$order = wc_get_order( $order );
			$order->update_meta_data( '_firmafy_status', $sign_status );
			$order->update_meta_data( '_firmafy_data', $this->recursive_sanitize_array( $body ) );
			$order->save();
		}

		return new WP_REST_Response( 'OK', 200 );
	}

	/**
	 * Sanitizes Array
	 *
	 * @param array $array Array to sanitize.
	 * @return array
	 */
	private function recursive_sanitize_array( $array ) {
		foreach ( $array as $key => &$value ) {
			if ( is_array( $value ) ) {
				$value = $this->recursive_sanitize_array( $value );
			} else {
				$value = sanitize_text_field( $value );
			}
		}

		return $array;
	}
}

new Firmafy_API_Webhook();
