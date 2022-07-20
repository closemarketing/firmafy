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
		$body   = json_decode( $result_body, true );

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
	public function login() {
		$settings = get_option( 'firmafy_options' );
		$username = isset( $settings['username'] ) ? $settings['username'] : '';
		$password = isset( $settings['password'] ) ? $settings['password'] : '';

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
	 * Get Firmafy Templates
	 *
	 * @return array
	 */
	public function get_variables_template( $template_id ) {
		$fields   = array();
		$template = get_post( $template_id );
		preg_match_all( '#\{(.*?)\}#', $template->post_content, $matches);
		if ( ! empty( $matches[1] ) && is_array( $matches[1] ) ) {
			foreach ( $matches[1] as $field ) {
				if ( $this->not_strange_string( $field ) ) {
					$fields[] = array(
						'name'  => $field,
						'label' => $field,
					);
				}
			}
			return $fields;
		}
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
	public function create_entry( $template_id, $merge_vars ) {
		$settings = get_option( 'firmafy_options' );
		$username = isset( $settings['username'] ) ? $settings['username'] : '';
		$password = isset( $settings['password'] ) ? $settings['password'] : '';
		$font     = isset( $settings['font'] ) ? $settings['font'] : 'helvetica';
		$signer   = array();

		// Replace merge vars for values
		$template_content = apply_filters( 'the_content', get_the_content( '', false, $template_id ) );

		foreach ( $merge_vars as $variable ) {
			if ( ! empty( $variable['name'] ) ) {
				$template_content = str_replace( '{' . $variable['name'] . '}', $variable['value'], $template_content );
				if ( $this->signer_tags( $variable['name'] ) ) {
					$signer[ $variable['name'] ] = $variable['value'];
				}
			}
		}
		$signer['type_notifications'] = 'email';

		$template_content = $this->replace_tags( $template_content );

		// Generates PDF
		$filename   = 'firmafy-' . sanitize_title( get_bloginfo( 'name' ) ) . '-' . date( 'Y-m-d-H-i' ) . '.pdf';
		$upload_dir = wp_upload_dir();
		$dirname    = $upload_dir['basedir'] . '/firmafy/';
		if ( ! file_exists( $dirname ) ) {
			wp_mkdir_p( $dirname );
		}

		$pdf_url       = $upload_dir['baseurl'] . '/firmafy/' . $filename;
		$filename_path = $dirname . $filename;

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
			$html2pdf->Output( $filename_path, 'F' );
		} catch ( Html2PdfException $e ) { //phpcs:ignore
			//error
			$formatter = new ExceptionFormatter( $e ); //phpcs:ignore
			error_log( 'Unexpected Error!<br>Can not load PDF this time! ' . $formatter->getHtmlMessage() );
		}

		// Sends to Firmafy
		$query = array(
			'id_show' => 'id_show',
			'signer'  => array(
				$signer,
			),
			'pdf'     => file_get_contents( $filename_path ),
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
	 * @param string $content
	 * @return string
	 */
	private function replace_tags( $content ) {

		// Replace for known tags.
		$content = str_replace( '<figure', '<div', $content );
		$content = str_replace( '</figure', '</div', $content );

		// Replace date.
		$content = str_replace( '{fecha}', date( 'd-m-Y' ), $content );

		return $content;
	}
}

$helpers_firmafy = new Helpers_Firmafy();
