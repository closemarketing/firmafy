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

		// Pedidos.
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'new_order_column' ) );
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_firmafy_status_column_header' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'add_firmafy_status_column_content' ) );
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
		$order          = wc_get_order( $post->ID );
		$firmafy_csv    = $order->get_meta( '_firmafy_csv' );
		$firmafy_status = $order->get_meta( '_firmafy_status' );

		echo '<table>';
		echo '<tr><td colspan="2"><strong>' . esc_html__( 'Data:', 'firmafy' ) . '</strong></td></tr>';
		echo '<tr><td>CSV:</td>';
		echo '<td>' . esc_html( $firmafy_csv ) . '</td>';
		echo '</tr>';

		// Status.
		echo '<tr><td>' . esc_html__( 'Status', 'firmafy' ) . ' :</td>';
		echo '<td>' . esc_html( $firmafy_status ) . '</td>';
		echo '</tr>';

		echo '</table>';
	}

	/**
	 * Nueva columna en pedidos.
	 *
	 * @param array $columns Columnas.
	 *
	 * @return array
	 */
	public function new_order_column( $columns ) {
		$columns['firmafy_status'] = 'Firmafy';
		return $columns;
	}

	/**
	 * Añade el ID de Grao en la cabecera de la columna.
	 *
	 * @param array $columns Columnas.
	 *
	 * @return array
	 */
	public function add_firmafy_status_column_header( $columns ) {
		$new_columns = array();
		foreach ( $columns as $column_name => $column_info ) {
			$new_columns[ $column_name ] = $column_info;
			if ( 'order_number' === $column_name ) {
				$new_columns['firmafy_status'] = 'Firmafy';
			}
		}
		return $new_columns;
	}

	/**
	 * Añade el ID de Grao en la columna.
	 *
	 * @param string $column Columna.
	 */
	public function add_firmafy_status_column_content( $column ) {
		global $post;
		if ( 'firmafy_status' === $column ) {
			$order          = wc_get_order( $post->ID );
			$firmafy_status = $order->get_meta( '_firmafy_status', true );

			echo esc_html( $firmafy_status );
		}
	}

	/**
	 * Añade el ID de Grao en la cabecera de la columna.
	 *
	 * @param array $columns Columnas.
	 *
	 * @return array
	 */
	public function add_firmafy_status_subs_column_header( $columns ) {
		$new_columns = array();
		foreach ( $columns as $column_name => $column_info ) {
			$new_columns[ $column_name ] = $column_info;
			if ( 'status' === $column_name ) {
				$new_columns['firmafy_status'] = 'Graó';
			}
		}
		return $new_columns;
	}
}

if ( is_admin() ) {
	new Firmafy_Widgets_ECommerce();
}
