<?php
/**
 * Functions for CRM in Gravity Forms
 *
 * All helpers functions for Gravity Forms
 *
 * @package    WordPress
 * @author     David Perez <david@closemarketing.net>
 * @copyright  2019 Closemarketing
 * @version    1.0
 */

if ( ! class_exists( 'GFCRM' ) ) {
	GFForms::include_feed_addon_framework();
	global $firmafy_api;

	/**
	 * Class for Addon GravityForms
	 */
	class GFCRM extends GFFeedAddOn {

		protected $_version                  = FIRMAFY_VERSION;
		protected $_min_gravityforms_version = '1.9.0';
		protected $_slug                     = 'firmafy';
		protected $_path                     = 'firmafy/crm.php';
		protected $_full_path                = __FILE__;
		protected $_url                      = 'https://www.firmafy.com';
		protected $_title                    = 'Firmafy Add-On';
		protected $_short_title              = 'Firmafy';

		// Members plugin integration.
		protected $_capabilities = array(
			'firmafy',
			'firmafy_uninstall',
		);

		// Permissions.
		protected $_capabilities_settings_page = 'firmafy';
		protected $_capabilities_form_settings = 'firmafy';
		protected $_capabilities_uninstall     = 'firmafy_uninstall';
		protected $_enable_rg_autoupgrade      = true;

		private static $_instance = null;

		private $crmlib;

		public static function get_instance() {
			if ( self::$_instance == null ) {
				self::$_instance = new GFCRM();
			}

			return self::$_instance;
		}
		/**
		 * Init function of library
		 *
		 * @return void
		 */
		public function init() {

			parent::init();

		}

		public function init_admin() {
			parent::init_admin();

			$this->ensure_upgrade();
		}

		/**
		 * Forms Settings
		 *
		 * @param array  $form Form.
		 * @param string $feed_id Feed id.
		 * @return void
		 */
		public function feed_edit_page( $form, $feed_id ) {
			// Ensures valid credentials were entered in the settings page.
			/*
			if ( false == $this->login_api_crm() ) {
				?>
				<div class="notice notice-error">
					<?php 
					esc_html_e( 'We are unable to login to CRM.', 'firmafy' );
					echo ' <a href="' . esc_url( $this->get_plugin_settings_url() ) . '">' . esc_html__( 'Use Settings Page', 'firmafy' ) . '</a>';
					?>
				</div>
				<?php
				return;
			}
			*/

			echo '<script type="text/javascript">var form = ' . esc_html( GFCommon::json_encode( $form ) ) . ';</script>';

			parent::feed_edit_page( $form, $feed_id );
		}

		public function feed_settings_fields() {
			global $helpers_firmafy;
			$settings = $this->get_plugin_settings();

			return array(
				array(
					'title'       => __( 'Firmafy Feed', 'firmafy' ),
					'description' => '',
					'fields'      => array(
						array(
							'name'     => 'feedName',
							'label'    => __( 'Name', 'firmafy' ),
							'type'     => 'text',
							'required' => true,
							'class'    => 'medium',
							'tooltip'  => '<h6>' . __( 'Name', 'firmafy' ) . '</h6>' . __( 'Enter a template name to uniquely identify this setup.', 'firmafy' ),
						),
						array(
							'name'     => 'firmafy_template',
							'label'    => __( 'Template', 'firmafy' ),
							'type'     => 'select',
							'class'    => 'medium',
							'onchange' => 'jQuery(this).parents("form").submit();',
							'choices'  => $helpers_firmafy->get_templates(),
						),
						array(
							'name'       => 'listFields',
							'label'      => __( 'Map Fields', 'firmafy' ),
							'type'       => 'field_map',
							'dependency' => 'firmafy_template',
							'field_map'  => $helpers_firmafy->get_variables_template( $this->get_setting( 'firmafy_template' ) ),
							'tooltip'    => '<h6>' . __( 'Map Fields', 'firmafy' ) . '</h6>' . __('Associate your CRM custom fields to the appropriate Gravity Form fields by selecting the appropriate form field from the list.', 'firmafy' ),
						),
					),
				),
			);
		}

		/**
		 * Return the plugin's icon for the plugin/form settings menu.
		 *
		 * @since 1.8
		 *
		 * @return string
		 */
		public function get_menu_icon() {

			return file_get_contents( FIRMAFY_PLUGIN_PATH . 'includes/assets/imagotipo.svg' );

		}

		public function ensure_upgrade() {

			if (get_option('fc_crm_upgrade')) {
				return false;
			}

			$feeds = $this->get_feeds();
			if ( empty( $feeds ) ) {

				// Force Add-On framework upgrade.
				$this->upgrade( '2.0' );
			}

			update_option( 'fc_crm_upgrade', 1 );
		}

		public function feed_list_columns() {
			return array(
				'feedName' => __( 'Name', 'firmafy' ),
			);
		}

		public function process_feed( $feed, $entry, $form ) {
			global $helpers_firmafy;

			// Ensures valid credentials were entered in the settings page.
			$login_result = $helpers_firmafy->login();

			if ( false == $login_result ) {
				return;
			}

			$this->export_feed( $entry, $form, $feed );
		}
		/**
		 * Sends data to API
		 *
		 * @param array  $entry Entry data.
		 * @param object $form Form data.
		 * @param array  $feed Feed data.
		 * @return void
		 */
		public function export_feed( $entry, $form, $feed ) {
			global $helpers_firmafy;

			if ( ! empty( $feed['meta']['listFields_first_name'] ) ) {
				$name = $this->get_name( $entry, $feed['meta']['listFields_first_name'] );
			}

			$merge_vars = array();
			$field_maps = $this->get_field_map_fields( $feed, 'listFields' );

			if ( ! empty( $field_maps ) ) {
				// Normal WAY.
				foreach ( $field_maps as $var_key => $field_id ) {
					$field = RGFormsModel::get_field( $form, $field_id );
		
					if ( isset( $field['type'] ) && GFCommon::is_product_field( $field['type'] ) && rgar( $field, 'enablePrice' ) ) {
						$ary          = explode('|', $entry[ $field_id ] );
						$product_name = count($ary) > 0 ? $ary[0] : '';
						$merge_vars[] = array(
							'name' => $var_key,
							'value' => $product_name,
						);
					} elseif ( $field && RGFormsModel::get_input_type( $field ) == 'checkbox' ) {
						$value = '';
						foreach ( $field['inputs'] as $input ) {
							$index   = (string) $input['id'];
							$value_n = apply_filters( 'firmafy_field_value', rgar( $entry, $index ), $form['id'], $field_id, $entry );
							$value .= $value_n;
							if ( $value_n ) {
								$value .= '|';
							}
						}
						$value        = substr( $value, 0, -1 );
						$merge_vars[] = array(
							'name'  => $var_key,
							'value' => $value,
						);
					} elseif ( $field && RGFormsModel::get_input_type( $field ) == 'multiselect' ) {
						$value = apply_filters( 'firmafy_field_value', rgar( $entry, $field_id ), $form['id'], $field_id, $entry );
						$value = str_replace( ',', '|', $value );

						$merge_vars[] = array(
							'name'  => $var_key,
							'value' => $value,
						);
					} elseif ( $field && RGFormsModel::get_input_type( $field ) == 'textarea' ) {
						$value        = apply_filters( 'firmafy_field_value', rgar( $entry, $field_id ), $form['id'], $field_id, $entry );
						$value        = str_replace( array( "\r", "\n" ), ' ', $value );
						$merge_vars[] = array(
							'name'  => $var_key,
							'value' => $value,
						);
					} else {
						$merge_vars[] = array(
							'name'  => $var_key,
							'value' => apply_filters( 'firmafy_field_value', rgar( $entry, $field_id ), $form['id'], $field_id, $entry ),
						);
					}
				}
			}

			$override_custom_fields = apply_filters( 'firmafy_override_blank_custom_fields', false, $entry, $form, $feed );
			if ( ! $override_custom_fields ) {
				$merge_vars = $this->remove_blank_custom_fields( $merge_vars );
			}

			$template        = isset( $feed['meta']['firmafy_template'] ) ? $feed['meta']['firmafy_template'] : '';
			$response_result = $helpers_firmafy->create_entry( $template, $merge_vars );
			$api_status      = isset( $response_result['status'] ) ? $response_result['status'] : '';

			if ( 'error' === $api_status ) {
				firmafy_debug_email_lead( 'Firmafy', 'Error ' . $response_result['message'], $merge_vars );
				$this->add_note( $entry['id'], 'Error ' . $response_result['message'], 'error' );
			} else {
				$this->add_note( $entry['id'], 'Success creating ' . esc_html( 'Firmafy' ) . ' Entry ID:' . $response_result['id'], 'success' );
				firmafy_debug_message( $response_result['id'] );
			}
		}

		/**
		 * Remove blank custom fields
		 *
		 * @param  array $merge_vars Vars to send to API.
		 * @return array
		 */
		private static function remove_blank_custom_fields( $merge_vars ) {
			$i = 0;

			$count = count( $merge_vars );

			for ( $i = 0; $i < $count; $i++ ) {
				if ( rgblank( $merge_vars[ $i ]['value'] ) ) {
					unset( $merge_vars[ $i ] );
				}
			}
			// resort the array because items could have been removed, this will give an error from CRM if the keys are not in numeric sequence.
			sort( $merge_vars );
			return $merge_vars;
		}

		private function get_name( $entry, $field_id ) {

			// If field is simple (one input), simply return full content.
			$name = rgar( $entry, $field_id );
			if ( ! empty( $name ) ) {
				return $name;
			}

			// Complex field (multiple inputs). Join all pieces and create name.
			$prefix = trim( rgar( $entry, $field_id . '.2' ) );
			$first  = trim( rgar( $entry, $field_id . '.3' ) );
			$last   = trim( rgar( $entry, $field_id . '.6' ) );
			$suffix = trim( rgar( $entry, $field_id . '.8' ) );

			$name = $prefix;
			$name .= ! empty( $name ) && ! empty( $first ) ? " $first" : $first;
			$name .= ! empty( $name ) && ! empty( $last ) ? " $last" : $last;
			$name .= ! empty( $name ) && ! empty( $suffix ) ? " $suffix" : $suffix;

			return $name;
		}

	} //from main class
}
