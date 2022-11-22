<?php
/**
 * Connection Library Firmafy
 *
 * @package    WordPress
 * @author     David Perez <david@closemarketing.es>
 * @copyright  2020 Closemarketing
 * @version    1.0
 */

defined( 'ABSPATH' ) || exit;

require FIRMAFY_PLUGIN_PATH . '/vendor/autoload.php';

use Spipu\Html2Pdf\Html2Pdf;

/**
 * Class Firmafy.
 *
 * Connnector to Firmafy.
 *
 * @since 1.0
 */
class Helpers_Firmafy {
	/**
	 * POSTS API from Firmafy
	 *
	 * @param string $apikey API Key.
	 * @param string $module Module.
	 * @param string $query Query.
	 * @return array
	 */
	public function api_post( $username, $password, $action, $query = array() ) {
		if ( ! $username && ! $password ) {
			return array(
				'status' => 'error',
				'data'   => 'No credentials',
			);
		}
		$args     = array(
			'timeout' => 120,
			'body'    => array(
				'action'   => $action,
				'usuario'  => $username,
				'password' => $password,
			)
		);
		if ( ! empty( $query ) ) {
			$args['body'] = array_merge( $args['body'], $query );
		}
		$result      = wp_remote_post( 'https://app.firmafy.com/ApplicationProgrammingInterface.php', $args );
		$result_body = wp_remote_retrieve_body( $result );
		$body        = json_decode( $result_body, true );

		if ( isset( $body['error'] ) && $body['error'] ) {
			return array(
				'status' => 'error',
				'data'   => isset( $body['error_message'] ) ? $body['error_message'] : '',
			);
		} else {
			return array(
				'status' => 'ok',
				'data'   => isset( $body['data'] ) ? $body['data'] : '',
			);
		}
	}

	/**
	 * Login settings
	 *
	 * @param array $settings
	 * @return void
	 */
	public function login( $username = '', $password = '' ) {
		if ( empty( $username ) || empty( $password ) ) {
			$settings = get_option( 'firmafy_options' );
			$username = isset( $settings['username'] ) ? $settings['username'] : '';
			$password = isset( $settings['password'] ) ? $settings['password'] : '';
		}

		return $this->api_post( $username, $password, 'login' );
	}

	/**
	 * Get Firmafy Templates
	 *
	 * @return array
	 */
	public function get_templates() {
		$templates  = array();
		$args_query = array(
			'post_type'      => 'firmafy_template',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);
		$posts_array   = get_posts( $args_query );
		foreach ( $posts_array as $post_id ) {
			$templates[] = array(
				'value' => $post_id,
				'label' => get_the_title( $post_id ),
			);
		}
		return $templates;
	}

	/**
	 * Get signers from Company.
	 *
	 * @return array
	 */
	public function get_signers() {
		$settings       = get_option( 'firmafy_options' );
		$signers_option = isset( $settings['signers'] ) ? $settings['signers'] : array();
		$signers        = array();

		foreach ( $signers_option as $signer ) {
			if ( ! empty( $signer['nif'] ) && ! empty( $signer['nombre'] ) ) {
				$signers[] = array(
					'name'  => isset( $signer['nif'] ) ? 'firmafy_signer_' . $signer['nif'] : '',
					'label' => isset( $signer['nombre'] ) ? $signer['nombre'] : '',
				);
			}
		}
		return $signers;
	}

	/**
	 * Filter signers from feed meta
	 *
	 * @param array $meta
	 * @return void
	 */
	public function filter_signers( $meta ) {
		$signers = array();
		foreach ( $meta as $key => $value ) {
			if ( str_contains( $key, 'firmafy_signer_' ) && 1 === (int) $value ) {
				$signers[] = str_replace( 'firmafy_signer_', '', $key );
			}
		}
		return $signers;
	}


	/**
	 * Get Firmafy Templates
	 *
	 * @return array
	 */
	public function get_variables_template( $template_id ) {
		$fields   = array();
		$template = get_post( $template_id );
		$required_api_fields = array(
			'nombre',
			'nif',
			'email',
			'telefono',
		);

		preg_match_all( '#\{(.*?)\}#', $template->post_content, $matches);
		if ( ! empty( $matches[1] ) && is_array( $matches[1] ) ) {
			foreach ( $matches[1] as $field ) {
				if ( $this->not_strange_string( $field ) ) {
					$fields[] = $field;
				}
			}
			$fields_to_convert = array_unique( array_merge( $fields, $required_api_fields ) );
			$fields   = array();
			foreach ( $fields_to_convert as $field ) {
				$fields[] = array(
					'name'     => $field,
					'label'    => $field,
					'required' => $this->is_field_required( $field ),
				);
			}
			return $fields;
		}
	}

	/**
	 * Shows is field is required
	 *
	 * @param array $field
	 * @return boolean
	 */
	private function is_field_required( $field ) {
		if ( 'nombre' === $field || 'nif' === $field || 'telefono' === $field || 'email' === $field ) {
			return true;
		}

		return false;
	}

	/**
	 * detects strange string
	 *
	 * @param [type] $string
	 * @return boolean
	 */
	private function not_strange_string( $string ) {
		if ( false !== strpos( $string, '"' ) ) {
			return false;
		}
		if ( false !== strpos( $string, 'fecha' ) ) {
			return false;
		}
		if ( false !== strpos( $string, 'fecha_texto' ) ) {
			return false;
		}
		if ( false !== strpos( $string, 'referencia' ) ) {
			return false;
		}
		if ( false !== strpos( $string, ':' ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Creates and generates PDF for signature
	 *
	 * @param string $template
	 * @return void
	 */
	public function create_entry( $template_id, $merge_vars, $signers = array(), $add_header = false ) {
		$settings         = get_option( 'firmafy_options' );
		$username         = isset( $settings['username'] ) ? $settings['username'] : '';
		$password         = isset( $settings['password'] ) ? $settings['password'] : '';
		$id_show          = isset( $settings['id_show'] ) ? $settings['id_show'] : '';
		$font             = isset( $settings['font'] ) ? $settings['font'] : 'helvetica';
		$signer           = array();
		$temp_content_pre = '';

		if ( $add_header ) {
			$temp_content_pre .= '<!-- wp:table -->
			<div class="wp-block-table"><table><tbody><tr><td><strong>NOMBRE Y APELLIDOS</strong></td><td>{nombre}</td></tr><tr><td><strong>D.N.I.</strong></td><td><strong>{nif}</strong></td></tr><tr><td><strong>DIRECCION EMAIL ASIGNADA</strong></td><td><strong>{email}</strong></td></tr><tr><td><strong>TELÉFONO</strong></td><td><strong>{telefono}</strong></td></tr><tr><td><strong>FECHA</strong></td><td>{fecha}</td></tr></tbody></table></div>
			<!-- /wp:table -->';
		}

		if ( ! empty( $signers ) && isset( $settings['signers'] ) ) {
			$company_signers = $settings['signers'];
			$delete = array_diff( array_column( $company_signers, 'nif' ), $signers );
			
			foreach ( $delete as $key => $value ) {
				if ( isset( $company_signers[ $key ] ) ) {
					unset( $company_signers[ $key ] );
				}
			}
			// Remove company field empty.
			$index = 0;
			foreach ( $company_signers as $signer_item ) {
				foreach ( $signer_item as $key => $value ) {
					if ( empty( $value ) ) {
						unset( $company_signers[ $index ][ $key ] );
					}
				}
				$index++;
			}
		}

		$temp_content_pre .= get_the_content( '', false, $template_id );

		// Replace merge vars for values
		$secure_mode = isset( $settings['secure_mode'] ) && 'yes' === $settings['secure_mode'] ? true : false;
		// Prevents conflict with web sytles.
		if ( $secure_mode ) {
			$template_content = $temp_content_pre;
		} else {
			$template_content = apply_filters( 'the_content', $temp_content_pre );
		}

		foreach ( $merge_vars as $variable ) {
			if ( ! empty( $variable['name'] ) ) {
				$template_content = str_replace( '{' . $variable['name'] . '}', $variable['value'], $template_content );
				if ( $this->signer_tags( $variable['name'] ) && 'nif' === $variable['name'] ) {
					$signer[ $variable['name'] ] = str_replace( '.', '', $variable['value'] );
				} elseif ( $this->signer_tags( $variable['name'] ) ) {
					$signer[ $variable['name'] ] = $variable['value'];
				}
			}
		}
		$notification = isset( $settings['notification'] ) ? (array) $settings['notification'] : ['email'];
		$signer['type_notifications'] = implode( ',', $notification );

		$template_content = $this->replace_tags( $template_content, $template_id );

		// Generates PDF
		$filename   = 'firmafy-' . sanitize_title( get_bloginfo( 'name' ) ) . '-' . date( 'Y-m-d-H-i' ) . '.pdf';

		$content = '<page style="margin-top:10mm;" backcolor="#fff">';
		$content .= '<style>';
		// Gets Template Style.
		$template_css_file = get_template_directory() . '/style.css';
		if ( file_exists( $template_css_file ) ) {
			$content .= file_get_contents( $template_css_file );
		}
		// Gets Template Child Style.
		$template_css_file = get_stylesheet_directory() . '/style.css';
		if ( file_exists( $template_css_file ) ) {
			$content .= file_get_contents( $template_css_file );
		}

		$content .= '</style>';
		$content .= $template_content;
		$content .= '</page>';

		// Creates PDF;
		$lang = isset( explode( '_', get_locale() )[0] ) ? explode( '_', get_locale() )[0] : 'en';
		try {
			$html2pdf  = new Html2Pdf(
				'P',
				'A4',
				$lang,
				true,
				'UTF-8',
				array( 10, 10, 10, 10 ) // in mm.
			);
			$html2pdf->addFont( $font );
			$html2pdf->setDefaultFont( $font );
			$html2pdf->setTestTdInOnePage( false );
			$html2pdf->writeHTML( $content );
			$pdf_content = $html2pdf->Output( $filename, 'S' );
		} catch ( Html2PdfException $e ) { //phpcs:ignore
			//error
			$formatter = new ExceptionFormatter( $e ); //phpcs:ignore
			error_log( 'Unexpected Error!<br>Can not load PDF this time! ' . $formatter->getHtmlMessage() );
		}

		$token         = $this->login();
		$final_signers = ! empty( $company_signers ) ? array_merge( array( $signer ), $company_signers ) : array( $signer );
		// Sends to Firmafy
		$query = array(
			'id_show'    => $id_show,
			'subject'    => get_the_title( $template_id ),
			'token'      => isset( $token['data'] ) ? $token['data'] : '',
			'signer'     => wp_json_encode( $final_signers ),
			'pdf_name'   => $filename,
			'pdf_base64' => chunk_split( base64_encode( $pdf_content ) ),

		);
		return $this->api_post( $username, $password, 'request', $query );
	}

	private function signer_tags( $check ) {
		$signer_tags = array(
			'nombre',
			'nif',
			'cargo',
			'email',
			'telefono',
		);

		if ( false !== array_search( $check, $signer_tags, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Replaces variables in document with allowed tags and core variables
	 *
	 * @param string $content  Content to replace
	 * @param integer $post_id Reference post
	 * @return string
	 */
	private function replace_tags( $content, $post_id ) {
		$months = array(
			1  => __( 'January', 'firmafy' ),
			2  => __( 'February', 'firmafy' ),
			3  => __( 'March', 'firmafy' ),
			4  => __( 'April', 'firmafy' ),
			5  => __( 'May', 'firmafy' ),
			6  => __( 'June', 'firmafy' ),
			7  => __( 'July', 'firmafy' ),
			8  => __( 'August', 'firmafy' ),
			9  => __( 'September', 'firmafy' ),
			10 => __( 'October', 'firmafy' ),
			11 => __( 'November', 'firmafy' ),
			12 => __( 'December', 'firmafy' ),
		);

		// Replace for known tags.
		$content = str_replace( '<figure', '<div', $content );
		$content = str_replace( '</figure', '</div', $content );

		$content = str_replace( '<section', '<div', $content );
		$content = str_replace( '</section', '</div', $content );

		// Replace date.
		$content = str_replace( '{fecha}', date( 'd-m-Y' ), $content );

		// Replace date text.
		$date_text = sprintf(
			/* translators: 1: day 2: month 3: year */
			esc_html__( '%1s of %2s of %3s', 'textdomain' ),
			date( 'd' ),
			$months[ (int) date('m') ],
			date( 'Y' )
		);
		$content = str_replace( '{fecha_texto}', $date_text, $content );

		// Replace reference.
		$content = str_replace( '{referencia}', $post_id, $content );

		return $content;
	}
}

$helpers_firmafy = new Helpers_Firmafy();
