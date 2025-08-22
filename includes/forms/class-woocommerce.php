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

use \Firmafy\HELPER;

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
	 * Settings
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Construct of class
	 */
	public function __construct() {
		$this->settings      = get_option( 'firmafy_options' );
		$firmafy_woocommerce = isset( $this->settings['woocommerce'] ) ? $this->settings['woocommerce'] : 'no';
		if ( 'yes' === $firmafy_woocommerce ) {
			$firmafy_woo_when = isset( $this->settings['woocommerce_when'] ) ? $this->settings['woocommerce_when'] : 'new_order';
			add_action( 'woocommerce_' . $firmafy_woo_when, array( $this, 'process_entry' ), 10, 2 );

			// EU VAT.
			add_filter( 'woocommerce_billing_fields', array( $this, 'add_billing_fields' ) );
			add_filter( 'woocommerce_admin_billing_fields', array( $this, 'add_billing_shipping_fields_admin' ) );
			add_filter( 'woocommerce_admin_shipping_fields', array( $this, 'add_billing_shipping_fields_admin' ) );
			add_filter( 'woocommerce_load_order_data', array( $this, 'add_var_load_order_data' ) );
			add_action( 'woocommerce_email_after_order_table', array( $this, 'email_key_notification' ), 10, 1 );
			add_filter( 'wpo_wcpdf_billing_address', array( $this, 'add_vat_invoices' ) );

		}
		// Register Meta box for post type product.
		add_action( 'add_meta_boxes', array( $this, 'metabox_product' ) );
		add_action( 'save_post', array( $this, 'save_metaboxes_product' ) );

		// In construct Class.
		add_action( 'admin_enqueue_scripts', array( $this, 'scripts_firmafy_product_fields' ) );
		add_action( 'wp_ajax_firmafy_product_fields', array( $this, 'firmafy_product_fields' ) );
		add_action( 'wp_ajax_nopriv_firmafy_product_fields', array( $this, 'firmafy_product_fields' ) );
	}

	/**
	 * Process the entry.
	 *
	 * @param int    $order_id Order ID.
	 * @param object $order Order object.
	 * @return void
	 */
	public function process_entry( $order_id, $order ) {
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

		// Conditional in subcriptions not send recurrent orders to firmafy.
		if ( class_exists( 'WC_Subscriptions' ) && wcs_order_contains_subscription( $order_id ) ) {
			$subscriptions = wcs_get_subscriptions_for_order( $order_id );
			if ( ! empty( $subscriptions ) ) {
				foreach ( $subscriptions as $subscription ) {
					$parent_order_id = $subscription->get_parent_id();

					if ( $parent_order_id !== $order_id ) {
						return; // Do not process this order, it is a subscription renewal.
					}
				}
			}
		}

		$woocommerce_mode = isset( $this->settings['woocommerce_mode'] ) ? $this->settings['woocommerce_mode'] : 'all';

		// Terms and conditions Sign.
		if ( 'orders' === $woocommerce_mode || 'all' === $woocommerce_mode ) {
			$template_id     = wc_terms_and_conditions_page_id();
			$response_result = HELPER::create_entry( $template_id, $merge_vars, array(), $order_id, true );

			if ( 'error' === $response_result['status'] ) {
				$order_msg = __( 'Order sent correctly to Firmafy', 'firmafy' );
			} else {
				$order_msg = __( 'There was an error sending the order to Firmafy', 'firmafy' );
			}
			$order->add_order_note( $order_msg );
		}

		// Products.
		if ( 'products' === $woocommerce_mode || 'all' === $woocommerce_mode ) {
			$ordered_items = $order->get_items();
			foreach ( $ordered_items as $order_item ) {
				$product_id = $order_item['product_id'];

				$firmafy_options = get_post_meta( $product_id, 'firmafy', true );

				if ( empty( $firmafy_options ) ) {
					continue;
				}
				$template_id = isset( $firmafy_options['template'] ) ? $firmafy_options['template'] : 0;
				unset( $firmafy_options['template'] );

				$merge_vars = array();
				foreach ( $firmafy_options as $key => $function ) {
					if ( 'billing_vat' === $function ) {
						$value = $order->get_meta( '_billing_vat' );
					} elseif ( 'get_billing_full_name' === $function ) {
						$value = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
					} elseif ( method_exists( $order, $function ) ) {
						$value = $order->{$function}();
					} else {
						$value = '';
					}
					$merge_vars[] = array(
						'name'  => $key,
						'value' => $value,
					);
				}
				$response_result = HELPER::create_entry( $template_id, $merge_vars, array(), $order_id, true );

				if ( 'error' === $response_result['status'] ) {
					$order_msg  = __( 'There was an error sending the order to Firmafy', 'firmafy' );
					$order_msg .= ' ' . $response_result['data'];
				} else {
					$order_msg = __( 'Order sent correctly to Firmafy', 'firmafy' );
					$order->add_meta_data( '_firmafy_csv', $response_result['data'], true );
					$order->add_meta_data( '_firmafy_status', 'PENDIENTE', true );
				}
				$order->add_order_note( $order_msg );
			}
		}
	}

	/**
	 * Insert element before of a specific array position
	 *
	 * @param array $source   Source.
	 * @param array $need     Order.
	 * @param array $previous Order.
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
		echo '<p><strong>' . esc_html__( 'VAT No', 'firmafy' ) . ':</strong> ';
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

	/**
	 * Get WooCommerce fiedls
	 *
	 * @return array
	 */
	private function get_woocommerce_fields() {

		return array(
			'get_id'                  => __( 'Order ID', 'firmafy' ),
			'get_user_id'             => __( 'User ID', 'firmafy' ),
			'get_currency'            => __( 'Currency', 'firmafy' ),
			'get_billing_full_name'   => __( 'Billing Full name', 'firmafy' ),
			'billing_vat'             => __( 'Billing NIF', 'firmafy' ),
			'get_billing_company'     => __( 'Billing Company', 'firmafy' ),
			'get_billing_address_1'   => __( 'Billing Address 1', 'firmafy' ),
			'get_billing_address_2'   => __( 'Billing Address 2', 'firmafy' ),
			'get_billing_city'        => __( 'Billing Address City', 'firmafy' ),
			'get_billing_state'       => __( 'Billing State', 'firmafy' ),
			'get_billing_postcode'    => __( 'Billing Postcode', 'firmafy' ),
			'get_billing_country'     => __( 'Billing Country', 'firmafy' ),
			'get_billing_email'       => __( 'Billing Email', 'firmafy' ),
			'get_billing_phone'       => __( 'Billing Phone', 'firmafy' ),
			'get_shipping_first_name' => __( 'Shipping First name', 'firmafy' ),
			'get_shipping_last_name'  => __( 'Shipping Last name', 'firmafy' ),
			'get_shipping_company'    => __( 'Shipping Company', 'firmafy' ),
			'get_shipping_address_1'  => __( 'Shipping Address 1', 'firmafy' ),
			'get_shipping_address_2'  => __( 'Shipping Address 2', 'firmafy' ),
			'get_shipping_city'       => __( 'Shipping Address City', 'firmafy' ),
			'get_shipping_state'      => __( 'Shipping State', 'firmafy' ),
			'get_shipping_postcode'   => __( 'Shipping Postcode', 'firmafy' ),
			'get_shipping_country'    => __( 'Shipping Country', 'firmafy' ),
		);
	}

	/**
	 * Adds metabox
	 *
	 * @return void
	 */
	public function metabox_product() {
		add_meta_box(
			'product',
			__( 'Firmafy signature', 'firmafy' ),
			array( $this, 'metabox_show_product' ),
			'product',
			'normal'
		);
	}
	/**
	 * Metabox inputs for post type.
	 *
	 * @param object $post Post object.
	 * @return void
	 */
	public function metabox_show_product( $post ) {
		$firmafy_options  = get_post_meta( $post->ID, 'firmafy', true );
		$firmafy_template = isset( $firmafy_options['template'] ) ? $firmafy_options['template'] : 0;
		?>
		<table>
			<tr><!-- SELECT template-->
				<td>
					<label for="firmafy_template"><?php echo esc_html( 'Select the Firmafy template', 'firmafy' ); ?></label>
					<select id="firmafy_template" name="firmafy_template" data-post-id="<?php echo esc_html( $post->ID ); ?>">
					<?php
					$options = HELPER::get_templates();
					echo '<option value="" ' . ( empty( $firmafy_template ) ? 'selected="selected"' : null ) . '>' . esc_html__( 'Not use Fimafy template', 'firmafy' ) . '</option>';
					foreach ( $options as $option ) {
						echo '<option value="' . esc_html( $option['value'] ) . '" ' . ( $firmafy_template == $option['value'] ? 'selected="selected"' : null ) . '>' . esc_html( $option['label'] ) . '</option>';
					}
					?>
					</select>
				</td>
			</tr><!-- //SELECT template-->
		</table>
		<div id="firmafy-table-fields">
			<?php echo $this->get_table_fields( $firmafy_template, $post->ID ); ?>
		</div>
		<?php
	}

	/**
	 * Admin Scripts AJAX
	 *
	 * @return void
	 */
	public function scripts_firmafy_product_fields() {
		wp_enqueue_script(
			'firmafy-product',
			FIRMAFY_PLUGIN_URL . 'includes/assets/firmafy-product.js',
			array(),
			FIRMAFY_VERSION,
			true
		);

		wp_localize_script(
			'firmafy-product',
			'ajaxAction',
			array(
				'url'   => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'firmafy_product_fields_nonce' ),
			)
		);
	}

	/**
	 * Ajax function to load info
	 *
	 * @return void
	 */
	public function firmafy_product_fields() {
		$firmafy_template = isset( $_POST['firmafy_template'] ) ? (int) $_POST['firmafy_template'] : 0;
		$post_id          = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;

		check_ajax_referer( 'firmafy_product_fields_nonce', 'nonce' );
		if ( true ) {
			$html = $this->get_table_fields( $firmafy_template, $post_id );
			wp_send_json_success( $html );
		} else {
			wp_send_json_error( array( 'error' => 'Error' ) );
		}
	}

	/**
	 * Returns Table fields
	 *
	 * @param int $firmafy_template Firmafy template.
	 * @param int $post_id Post id.
	 *
	 * @return html
	 */
	private function get_table_fields( $firmafy_template, $post_id ) {
		if ( empty( $firmafy_template ) ) {
			return '';
		}
		$firmafy_options  = get_post_meta( $post_id, 'firmafy', true );

		$html = '<table>';
		$firmafy_fields = HELPER::get_variables_template( $firmafy_template );

		$html .= '<tr>';
		$html .= '<th>' . __( 'Template', 'firmafy' ) . '</th>';
		$html .= '<th>' . __( 'Order Field', 'firmafy' ) . '</th>';
		$html .= '</tr>';
		foreach ( $firmafy_fields as $firmafy_field ) {
			$html .= '<tr>';
			$html .= '<td>';
			$html .= '<label for="wpcf7-firmafy-field-' . esc_html__( $firmafy_field['name'] ). '">';
			$html .= esc_html__( $firmafy_field['label'] );
			if ( $firmafy_field['required'] ) {
				$html .= ' <span class="required">*</span>';
			}
			$html .= '</label>';
			$html .= '</td>';
			$html .= '<td>';
			$html .= '<select name="firmafy_field_' . esc_html__( $firmafy_field['name'] ) . '">';
			$field_post_value = isset( $firmafy_options[ esc_html__( $firmafy_field['name'] ) ] ) ? $firmafy_options[ esc_html__( $firmafy_field['name'] ) ] : '';
			$html .= '<option value="" ' . ( empty( $field_post_value ) ? 'selected="selected"' : null ) . '>' . __( 'Not use Fimafy template', 'firmafy' ) . '</option>';
			foreach ( $this->get_woocommerce_fields() as $key => $label ) {
				$html .= '<option value="' . $key . '" ' . ( $field_post_value == $key ? 'selected="selected"' : null ) . '>' . $label . '</option>';
			}
			$html .= '</select>';
			$html .= '</td>';
			$html .= '</tr>';
		}
		$html .= '</table>';

		return $html;
	}

	/**
	 * Save metaboxes
	 *
	 * @param int $post_id Post id.
	 * @return void
	 */
	public function save_metaboxes_product( $post_id ) {
		if ( isset( $_POST['firmafy_template'] ) ) {
			$firmafy_options = array(
				'template' => sanitize_text_field( $_POST['firmafy_template'] ),
			);
			$firmafy_fields = HELPER::get_variables_template( $firmafy_options['template'] );
			foreach ( $firmafy_fields as $field ) {
				if ( ! empty( $_POST[ 'firmafy_field_' . $field['name'] ] ) ) {
					$firmafy_options[ $field['name'] ] = sanitize_text_field( $_POST[ 'firmafy_field_' . $field['name'] ] );
				}
			}

			update_post_meta( $post_id, 'firmafy', $firmafy_options );
		}
	}
}

new Firmafy_WooCommerce();
