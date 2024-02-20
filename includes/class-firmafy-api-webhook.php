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
				'methods'  => 'POST',
				'callback' => array( $this, 'process_webhook' ),
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

		return new WP_REST_Response( 'OK', 200 );
	}
}

new Firmafy_API_Webhook();
