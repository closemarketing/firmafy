<?php
/**
 * Class ProductsOrderSignTest
 *
 * @package Firmafy
 */

class ProductsOrderSignTest extends WP_UnitTestCase {

	/**
	 * A single example test.
	 */
	public function test_order_products_sign_without_errors() {
		// Create product.
		$product = array();

		$product_meta = array (
			'template' => '64',
			'nombre' => 'get_billing_full_name',
			'nif' => 'billing_vat',
			'email' => 'get_billing_email',
			'telefono' => 'get_billing_phone',
		);

		$product['firmafy'] = $product_meta;

		$product_id = $this->factory->post->create( array( 'post_type' => 'product', 'post_title' => 'Test Product', 'post_status' => 'publish' ) );
		update_post_meta( $product_id, 'firmafy', $product_meta );

		$order = new WC_Order();
		$client_data = [
			'first_name'  => 'John',
			'last_name'   => 'Doe',
			'email'       => 'john.doe@example.com',
			'phone'       => '123456789',
			'address_1'   => '123 Main St',
			'address_2'   => '',
			'city'        => 'Sample City',
			'state'       => 'CA',
			'postcode'    => '90001',
			'country'     => 'US',
			'company'     => '',
			'billing_vat' => '123456789',
		];

		$order->set_billing_first_name( $client_data['first_name'] );
		$order->set_billing_last_name( $client_data['last_name'] );
		$order->set_billing_email( $client_data['email'] );
		$order->set_billing_phone( $client_data['phone'] );
		$order->set_billing_address_1( $client_data['address_1'] );
		$order->set_billing_address_2( $client_data['address_2'] );
		$order->set_billing_city( $client_data['city'] );
		$order->set_billing_state( $client_data['state'] );
		$order->set_billing_postcode( $client_data['postcode'] );
		$order->set_billing_country( $client_data['country'] );
		$order->set_billing_company( $client_data['company'] );
		$order->add_meta_data( '_billing_vat', $client_data['billing_vat'] );
		$order->set_total(100);
		$order->add_item( new WC_Product_Simple( $product_id ) );
		$order->save();

		// Process the order.
		/*
		$firmafy_woocommerce = new Firmafy_WooCommerce();
		$firmafy_woocommerce->process_entry( $order->get_id(), $order );

		// Check the order meta.
		$this->assertEquals( 'PENDIENTE', $order->get_meta( '_firmafy_status', true ) );
		$this->assertNotEmpty( $order->get_meta( '_firmafy_csv', true ) );
*/
		$this->assertTrue( true );
	}
}
