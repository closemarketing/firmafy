<?php
/**
 * Contact Forms 7 Wrapper
 *
 * @package   WordPress
 * @author    David Perez <david@closemarketing.es>
 * @copyright 2021 Closemarketing
 * @version   3.3
 */

defined( 'ABSPATH' ) || exit;

/**
 * Library for Contact Forms Settings
 *
 * @package    WordPress
 * @author     David Perez <david@closemarketing.es>
 * @copyright  2019 Closemarketing
 * @version    1.0
 */
class FormsCRM_WooCommerce {

	/**
	 * CRM LIB external
	 *
	 * @var obj
	 */
	private $crmlib;

	/**
	 * Construct of class
	 */
	public function __construct() {
		add_action( 'woocommerce_new_order', array( $this, 'crm_process_entry' ), 1, 1 );
	}

	/**
	 * Get Woocommerce Fields.
	 *
	 * @return array
	 */
	private function get_woocommerce_order_fields() {
		// Function name and Label.
		return array(
			''                             => '',
			'customer_note'                => __( 'Customer Note', 'formscrm' ),
			'billing_first_name'           => __( 'Billing First name', 'formscrm' ),
			'billing_last_name'            => __( 'Billing Last name', 'formscrm' ),
			'billing_company'              => __( 'Billing Company', 'formscrm' ),
			'billing_address_1'            => __( 'Billing Address 1', 'formscrm' ),
			'billing_address_2'            => __( 'Billing Address 2', 'formscrm' ),
			'billing_city'                 => __( 'Billing City', 'formscrm' ),
			'billing_state'                => __( 'Billing State', 'formscrm' ),
			'billing_postcode'             => __( 'Billing Postcode', 'formscrm' ),
			'billing_country'              => __( 'Billing Country', 'formscrm' ),
			'billing_email'                => __( 'Billing Email', 'formscrm' ),
			'billing_phone'                => __( 'Billing Phone', 'formscrm' ),
			'shipping_first_name'          => __( 'Shipping First name', 'formscrm' ),
			'shipping_last_name'           => __( 'Shipping Last name', 'formscrm' ),
			'shipping_company'             => __( 'Shipping Company', 'formscrm' ),
			'shipping_address_1'           => __( 'Shipping Address 1', 'formscrm' ),
			'shipping_address_2'           => __( 'Shipping Address 2', 'formscrm' ),
			'shipping_city'                => __( 'Shipping City', 'formscrm' ),
			'shipping_state'               => __( 'Shipping State', 'formscrm' ),
			'shipping_postcode'            => __( 'Shipping Postcode', 'formscrm' ),
			'shipping_country'             => __( 'Shipping Country', 'formscrm' ),
			'formatted_billing_full_name'  => __( 'Formatted Billing Full Name', 'formscrm' ),
			'formatted_shipping_full_name' => __( 'Formatted Shipping Full Name', 'formscrm' ),
			'customer_id'                  => __( 'Customer ID', 'formscrm' ),
			'user_id'                      => __( 'User ID', 'formscrm' ),
		);
	}

	/**
	 * Process the entry.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function crm_process_entry( $order_id ) {
		$wc_formscrm = get_option( 'wc_formscrm' );
		$order       = new WC_Order( $order_id );

		if ( $wc_formscrm ) {
			$this->include_library( $wc_formscrm['fc_crm_type'] );
			$merge_vars = $this->get_merge_vars( $wc_formscrm, $order );

			$response_result = $this->crmlib->create_entry( $wc_formscrm, $merge_vars );

			if ( 'error' === $response_result['status'] ) {
				formscrm_debug_email_lead( $wc_formscrm['fc_crm_type'], 'Error ' . $response_result['message'], $merge_vars );
			} else {
				error_log( $response_result['id'] );
			}
		}
	}

	/**
	 * Extract merge variables
	 *
	 * @param array  $wc_formscrm Array settings from CRM.
	 * @param object $order Submitted data.
	 * @return array
	 */
	private function get_merge_vars( $wc_formscrm, $order ) {
		$merge_vars = array();

		foreach ( $wc_formscrm as $key => $value ) {
			if ( false !== strpos( $key, 'fc_crm_field' ) ) {
				$crm_key   = str_replace( 'fc_crm_field-', '', $key );
				$method_wc = 'get_' . $value;
				if ( $method_wc && method_exists( $order, $method_wc ) ) {
					$merge_vars[] = array(
						'name'  => $crm_key,
						'value' => $order->$method_wc(),
					);
				}
			}
		}

		return $merge_vars;
	}
}

new FormsCRM_WooCommerce();
