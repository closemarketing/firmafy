<?php
/**
 * Class ProductsOrderSignTest
 *
 * @package Firmafy
 */

/**
 * Sample test case.
 */
class ProductsOrderSignTest extends WP_UnitTestCase {

	/**
	 * A single example test.
	 */
	public function test_sample() {
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

		$this->assertTrue( true );
	}
}
