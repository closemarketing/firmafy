<?php
/**
 * WooCommerce Wrapper
 *
 * @package   WordPress
 * @author    David Perez <david@closemarketing.es>
 * @copyright 2022 Closemarketing
 * @version   1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Library for Contact Forms Settings
 *
 * @package    WordPress
 * @author     David Perez <david@closemarketing.es>
 * @copyright  2022 Closemarketing
 * @version    1.0
 */
class Firmafy_WooCommerce {
	/**
	 * Construct of class
	 */
	public function __construct() {
		$settings = get_option( 'firmafy_options' );
		$firmafy_woocommerce = isset( $settings['woocommerce'] ) ? $settings['woocommerce'] : 'no';
		if ( 'yes' === $firmafy_woocommerce ) {
			add_action( 'woocommerce_new_order', array( $this, 'process_entry' ), 10, 2 );

			// EU VAT.
			add_filter( 'woocommerce_billing_fields', array( $this, 'add_billing_fields' ) );
			add_filter( 'woocommerce_admin_billing_fields', array( $this, 'add_billing_shipping_fields_admin' ) );
			add_filter( 'woocommerce_admin_shipping_fields', array( $this, 'add_billing_shipping_fields_admin' ) );
			add_filter( 'woocommerce_load_order_data', array( $this, 'add_var_load_order_data' ) );
			add_action( 'woocommerce_email_after_order_table', array( $this, 'email_key_notification' ), 10, 1 );
			add_filter( 'wpo_wcpdf_billing_address', array( $this, 'add_vat_invoices' ) );
		}
	}

	/**
	 * Process the entry.
	 *
	 * @param int    $order_id Order ID.
	 * @param object $order Order object.
	 * @return void
	 */
	public function process_entry( $order_id, $order ) {
		global $helpers_firmafy;
		$merge_vars = array(
			array(
				'name'  => 'pedido_numero',
				'value' => $order->get_id(),
			),
			array(
				'name'  => 'pedido_fecha',
				'value' => $order->get_date_created()->date( 'd/m/Y' ),
			),
			array(
				'name'  => 'pedido_total',
				'value' => $order->get_total(),
			),
			array(
				'name'  => 'pedido_nota',
				'value' => $order->get_customer_note(),
			),
			array(
				'name'  => 'metodo_pago',
				'value' => $order->get_payment_method_title(),
			),
			array(
				'name'  => 'nombre',
				'value' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			),
			array(
				'name'  => 'nif',
				'value' => $order->get_meta( '_billing_vat', true ),
			),
			array(
				'name'  => 'empresa',
				'value' => $order->get_billing_company(),
			),
			array(
				'name'  => 'email',
				'value' => $order->get_billing_email(),
			),
			array(
				'name'  => 'telefono',
				'value' => $order->get_billing_phone(),
			),
			array(
				'name'  => 'direccion_1',
				'value' => $order->get_billing_address_1(),
			),
			array(
				'name'  => 'direccion_2',
				'value' => $order->get_billing_address_2(),
			),
			array(
				'name'  => 'ciudad',
				'value' => $order->get_billing_city(),
			),
			array(
				'name'  => 'provincia',
				'value' => $order->get_billing_state(),
			),
			array(
				'name'  => 'codigo_postal',
				'value' => $order->get_billing_postcode(),
			),
			array(
				'name'  => 'pais',
				'value' => $order->get_billing_country(),
			),
		);

		$template_id     = wc_terms_and_conditions_page_id();
		$response_result = $helpers_firmafy->create_entry( $template_id, $merge_vars, true );

		if ( 'error' === $response_result['status'] ) {
			$order_msg = __( 'Order sent correctly to Firmafy', 'firmafy' );
		} else {
			$order_msg = __( 'There was an error sending the order to Firmafy', 'firmafy' );
		}
		$order->add_order_note( $order_msg );
	}

	/**
	 * Insert element before of a specific array position
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function array_splice_assoc( &$source, $need, $previous ) {
		$return = array();

		foreach ( $source as $key => $value ) {
			if ( $key == $previous ) {
				$need_key   = array_keys( $need );
				$key_need   = array_shift( $need_key );
				$value_need = $need[ $key_need ];

				$return[ $key_need ] = $value_need;
			}

			$return[ $key ] = $value;
		}

		$source = $return;
	}

	public function add_billing_fields( $fields ) {

		$field = array(
			'billing_vat' => array(
				'label'       => apply_filters( 'vatssn_label', __( 'VAT No', 'firmafy' ) ),
				'placeholder' => apply_filters( 'vatssn_label_x', __( 'VAT No', 'firmafy' ) ),
				'required'    => true,
				'class'       => array( 'form-row-wide' ),
				'clear'       => true,
			),
		);

		$this->array_splice_assoc( $fields, $field, 'billing_address_1' );
		return $fields;
	}

	public function add_billing_shipping_fields_admin( $fields ) {
		$fields['vat'] = array(
			'label' => apply_filters( 'vatssn_label', __( 'VAT No', 'firmafy' ) ),
		);

		return $fields;
	}

	public function add_var_load_order_data( $fields ) {
		$fields['billing_vat'] = '';
		return $fields;
	}

	/**
	 * Adds NIF in email notification
	 *
	 * @param object $order Order object.
	 * @return void
	 */
	public function email_key_notification( $order ) {
		echo '<p><strong>' . __( 'VAT No', 'firmafy' ) .':</strong> ';
		echo esc_html( get_post_meta( $order->get_id(), '_billing_vat', true ) ) . '</p>';
	}

	/**
	 * Adds VAT info in WooCommerce PDF Invoices & Packing Slips
	 */
	public function add_vat_invoices( $address ) {
		global $wpo_wcpdf;

		echo $address . '<p>';
		$wpo_wcpdf->custom_field( 'billing_vat', __( 'VAT info:', 'firmafy' ) );
		echo '</p>';
	}
}

new Firmafy_WooCommerce();
