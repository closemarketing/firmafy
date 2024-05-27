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
class Firmafy_CF7_Settings {

	/**
	 * Construct of class
	 */
	public function __construct() {
		add_filter( 'wpcf7_editor_panels', array( $this, 'show_cm_metabox' ) );
		add_action( 'wpcf7_after_save', array( $this, 'firmafy_save_options' ) );
		add_action( 'wpcf7_before_send_mail', array( $this, 'firmafy_process_entry' ) );
	}

	/**
	 * Shows metabox in form
	 *
	 * @param array $panels Panels actived in CF7.
	 * @return array
	 */
	public function show_cm_metabox( $panels ) {
		$new_page = array(
			'firmafy-extension' => array(
				'title'    => 'Firmafy',
				'callback' => array( $this, 'settings_add_firmafy' ),
			),
		);
		$panels = array_merge( $panels, $new_page );
		return $panels;
	}

	/**
	 * Adds CRM options in Contact Form 7
	 *
	 * @param obj $args Arguments.
	 * @return void
	 */
	public function settings_add_firmafy( $args ) {
		global $helpers_firmafy;

		$cf7_firmafy_defaults = array();
		$cf7_firmafy          = get_option( 'cf7_firmafy_' . $args->id(), $cf7_firmafy_defaults );
		?>
		<div class="metabox-holder">
			<div class="cme-main-fields">
				<p>
					<label for="wpcf7-firmafy-firmafy_template"><?php esc_html_e( 'Template:', 'firmafy' ); ?></label><br />
					<select name="wpcf7-firmafy[firmafy_template]" class="medium" onchange="jQuery(this).parents('form').submit();" id="firmafy_template">
						<?php
						echo '<option value="" ';
						if ( empty( $template['value'] ) ) {
							selected( $cf7_firmafy['firmafy_template'], '' );
						}
						echo '>' . __( 'Select template', 'firmafy' ) . '</option>';
						foreach ( $helpers_firmafy->get_templates() as $template ) {
							echo '<option value="' . esc_html( $template['value'] ) . '" ';
							if ( isset( $template['value'] ) ) {
								selected( $cf7_firmafy['firmafy_template'], $template['value'] );
							}
							echo '>' . esc_html( $template['label'] ) . '</option>';
						}
						?>
					</select>
				</p>
			</div>
			<div class="cme-main-fields">
				<p>
					<label for="wpcf7-firmafy-firmafy_signers"><?php esc_html_e( 'Self signers:', 'firmafy' ); ?></label><br />
					<?php
					$signers = $helpers_firmafy->get_signers();
					foreach ( $signers as $signer ) {
						echo '<p><input type="checkbox"';
						echo 'name="wpcf7-firmafy[' . esc_html( $signer['name'] ) . ']"';
						echo ' value="1"';
						if ( isset( $cf7_firmafy[ $signer['name'] ] ) && $cf7_firmafy[ $signer['name'] ] ) {
							echo ' checked';
						}
						echo '/>' . esc_html( $signer['label'] ) . '</p>';
					}?>
				</p>
			</div>

		<?php
		if ( isset( $cf7_firmafy['firmafy_template'] ) && $cf7_firmafy['firmafy_template'] ) {
			$firmafy_fields = $helpers_firmafy->get_variables_template( $cf7_firmafy['firmafy_template'] );
			?>
			<table class="cf7-map-table" cellspacing="0" cellpadding="0">
				<tbody>
					<tr class="cf7-map-row">
						<th class="cf7-map-column cf7-map-column-heading cf7-map-column-key"><?php esc_html_e( 'Field Template', 'firmafy' ); ?></th>
						<th class="cf7-map-column cf7-map-column-heading cf7-map-column-value"><?php esc_html_e( 'Form Field', 'firmafy' ); ?></th>
					</tr>
						<?php
						foreach ( $firmafy_fields as $firmafy_field ) {
							?>
							<tr class="cf7-map-row">
									<td class="cf7-map-column cf7-map-column-key">
										<label for="wpcf7-firmafy-field-<?php echo esc_html( $firmafy_field['name'] ); ?>"><?php echo esc_html( $firmafy_field['label'] ); ?><?php if ( $firmafy_field['required'] ) { echo ' <span class="required">*</span>'; } ?></label>
									</td>
									<td class="cf7-map-column cf7-map-column-value">
										<input type="text" id="wpcf7-firmafy-field-<?php echo esc_html( $firmafy_field['name'] ); ?>" name="wpcf7-firmafy[firmafy_field-<?php echo esc_html( $firmafy_field['name'] ); ?>]" class="wide" size="70" placeholder="<?php esc_html_e( 'Name of your field', 'firmafy' ); ?>" value="<?php echo ( isset( $cf7_firmafy[ 'firmafy_field-' . $firmafy_field['name'] ] ) ) ? esc_attr( $cf7_firmafy[ 'firmafy_field-' . $firmafy_field['name'] ] ) : ''; ?>" <?php if ( $firmafy_field['required'] ) { echo ' required'; } ?>/>
									</td>
							</tr>
						<?php } ?>
				</tbody>
			</table>
		<?php } ?>
	</div>
		<?php
	}

	/**
	 * Save options CRM.
	 *
	 * @param obj $args Arguments CF7.
	 * @return void
	 */
	public function firmafy_save_options( $args ) {

		if ( isset( $_POST['wpcf7-firmafy'] ) && is_array( $_POST['wpcf7-firmafy'] ) ) {
			$settings_firmafy_san = array();
			foreach ( array_filter( $_POST['wpcf7-firmafy'] ) as $key => $value ) {
				$settings_firmafy_san[ $key ] = sanitize_text_field( $value );
			}
			update_option( 'cf7_firmafy_' . $args->id, $settings_firmafy_san );
		}
	}

	/**
	 * Process the entry.
	 *
	 * @param obj $obj CF7 Object.
	 * @return void
	 */
	public function firmafy_process_entry( $obj ) {
		global $helpers_firmafy;
		$cf7_firmafy = get_option( 'cf7_firmafy_' . $obj->id() );
		$submission  = WPCF7_Submission::get_instance();

		if ( $cf7_firmafy ) {
			$merge_vars      = $this->get_merge_vars( $cf7_firmafy, $submission->get_posted_data() );
			$signers         = $helpers_firmafy->filter_signers( $cf7_firmafy );
			$response_result = $helpers_firmafy->create_entry( $cf7_firmafy['firmafy_template'], $merge_vars, $signers );

			if ( 'error' === $response_result['status'] ) {
				firmafy_debug_email( $cf7_firmafy['fc_firmafy_type'], 'Error ' . $response_result['message'], $merge_vars );
			} else {
				error_log( $response_result['id'] );
			}
		}
	}

	/**
	 * Extract merge variables
	 *
	 * @param array $cf7_firmafy Array settings from CRM.
	 * @param array $submitted_data Submitted data.
	 * @return array
	 */
	private function get_merge_vars( $cf7_firmafy, $submitted_data ) {
		$merge_vars = array();
		foreach ( $cf7_firmafy as $key => $value ) {
			if ( false !== strpos( $key, 'firmafy_field' ) ) {
				$firmafy_key      = str_replace( 'firmafy_field-', '', $key );
				$merge_vars[] = array(
					'name'  => $firmafy_key,
					'value' => isset( $submitted_data[ $value ] ) ? $submitted_data[ $value ] : '',
				);
			}
		}

		return $merge_vars;
	}
}

new Firmafy_CF7_Settings();
