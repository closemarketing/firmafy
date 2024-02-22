<?php
/**
 * Productos
 *
 * @package    WordPress
 * @author     David Perez <david@close.technology>
 * @copyright  2023 Closemarketing
 * @version    1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Mejoras productos.
 *
 * Description.
 *
 * @since Version 3 digits
 */
class Firmafy_Widgets_ECommerce {

	/**
	 * Construct of Class
	 */
	public function __construct() {
		// Register Meta box for post type product.
		add_action( 'add_meta_boxes', array( $this, 'metabox_firmafy' ) );
	}
	/**
	 * Adds metabox
	 *
	 * @return void
	 */
	public function metabox_firmafy() {
		add_meta_box(
			'firmafy-order-widget',
			__( 'Firmafy', 'firmafy' ),
			array( $this, 'metabox_show_order' ),
			array( 'shop_order' ),
			'side'
		);
	}

	/**
	 * Metabox inputs for post type.
	 *
	 * @param object $post Post object.
	 * @return void
	 */
	public function metabox_show_order( $post ) {
		$order       = wc_get_order( $post->ID );
		$firmafy_csv = $order->get_meta( '_firmafy_csv' );

		echo '<table>';
		echo '<tr><td colspan="2"><strong>' . esc_html__( 'Data:', 'firmafy' ) . '</strong></td></tr>';
		echo '<tr><td>CSV:</td>';
		echo '<td>' . esc_html( $firmafy_csv ) . '</td>';
		echo '</tr>';

		echo '</table>';
	}
}

if ( is_admin() ) {
	new Firmafy_Widgets_ECommerce();
}
