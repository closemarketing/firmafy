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
		$order       = wc_get_order( $post->ID );
		$sign_csv    = $order->get_meta( '_firmafy_csv' );
		$sign_status = $order->get_meta( '_firmafy_status' );

		echo '<table>';
		echo '<tr><td colspan="2"><strong>' . esc_html__( 'Data:', 'firmafy' ) . '</strong></td></tr>';
		echo '<tr><td>CSV:</td>';
		echo '<td>' . esc_html( $sign_csv ) . '</td>';
		echo '</tr>';

		// Status.
		echo '<tr><td>' . esc_html__( 'Status', 'firmafy' ) . ' :</td>';
		echo '<td>' . esc_html( $sign_status ) . '</td>';
		echo '</tr>';

		// Signers.
		$sign_data = $order->get_meta( '_firmafy_data' );
		if ( ! empty( $sign_data['signer'] ) ) {
			echo '<tr><td colspan="2"><strong>' . esc_html__( 'Signers:', 'firmafy' ) . '</strong></td></tr>';
			foreach ( $sign_data['signer'] as $signer ) {
				echo '<tr>';
				echo '<td><strong>' . esc_html__( 'Name', 'firmafy' ) . ':</strong></td>';
				echo '<td>' . esc_html( $signer['name'] ) . '</td>';
				echo '</tr>';

				echo '<tr>';
				$label_status = ! empty( $signer['status'] ) ? __( 'Signed', 'firmafy' ) : __( 'Not signed', 'firmafy' );
				echo '<td colspan="2">' . esc_html( $label_status ) . '</td>';
				echo '</tr>';
			}
		}

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
