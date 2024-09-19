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

use Dompdf\Dompdf;
use Dompdf\Options;
/**
 * Class Firmafy.
 *
 * Connnector to Firmafy.
 *
 * @since 1.0
 */
class Helpers_Firmafy {

	/**
	 * Available PDF Fonts
	 *
	 * @var array
	 */
	public $available_pdf_fonts;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Add the available_pdf_fonts.
		$this->available_pdf_fonts = array(
			'roboto'       => 'Roboto',
			'courier'      => 'Courier',
			'times'        => 'Times',
			'zapfdingbats' => 'ZapfDingbats',
		);
	}

	/**
	 * POSTS API from Firmafy
	 *
	 * @param array  $credentials Credentials.
	 * @param string $action Action.
	 * @param string $query Query.
	 * @return array
	 */
	public function api_post( $credentials, $action, $query = array() ) {
		$args['timeout'] = 120;
		if ( 'webhook' === $action ) {
			$args['body'] = array(
				'action'  => $action,
				'id_show' => isset( $credentials['id_show'] ) ? $credentials['id_show'] : '',
				'token'   => isset( $credentials['token'] ) ? $credentials['token'] : '',
				'type'    => 1,
				'method'  => 1,
			);
		} else {
			$username = isset( $credentials['username'] ) ? $credentials['username'] : '';
			$password = isset( $credentials['password'] ) ? $credentials['password'] : '';
			if ( ! $username && ! $password ) {
				return array(
					'status' => 'error',
					'data'   => 'No credentials',
				);
			}
			$args['body'] = array(
				'action'   => $action,
				'usuario'  => $username,
				'password' => $password,
			);
		}
		if ( ! empty( $query ) ) {
			$args['body'] = array_merge( $args['body'], $query );
		}
		$result      = wp_remote_post( 'https://app.firmafy.com/ApplicationProgrammingInterface.php', $args );
		$result_body = wp_remote_retrieve_body( $result );
		$body        = json_decode( $result_body, true );

		if ( isset( $body['error'] ) && $body['error'] ) {
			$result = array(
				'status' => 'error',
				'data'   => isset( $body['message'] ) ? $body['message'] : '',
			);
		} else {
			$result = array(
				'status' => 'ok',
				'data'   => isset( $body['data'] ) ? $body['data'] : '',
			);
		}
		return $result;
	}

	/**
	 * Login settings
	 *
	 * @param string $username Username.
	 * @param string $password Password.
	 * @return array
	 */
	public function login( $username = '', $password = '' ) {
		if ( empty( $username ) || empty( $password ) ) {
			$credentials = get_option( 'firmafy_options' );
		} else {
			$credentials = array(
				'username' => $username,
				'password' => $password,
			);
		}

		return $this->api_post( $credentials, 'login' );
	}

	/**
	 * Send Webhook options
	 *
	 * @param array $credentials Credentials.
	 *
	 * @return boolean
	 */
	public function webhook( $credentials ) {
		if ( empty( $token ) ) {
			$result = $this->login();
			$token  = ! empty( $result['data'] ) ? $result['data'] : '';
		}

		$result = $this->api_post(
			$credentials,
			'webhook',
			array(
				'url_webhook' => get_rest_url( null, 'firmafy/v1/webhook' ),
				'type'        => 1,
				'method'      => 2,
			)
		);
		return 'ok' === $result['status'] ? true : false;
	}

	/**
	 * Get Firmafy Templates
	 *
	 * @return array
	 */
	public function get_templates() {
		$templates   = array();
		$args_query  = array(
			'post_type'      => 'firmafy_template',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);
		$posts_array = get_posts( $args_query );
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
	 * @param string $type Type of signers.
	 * @return array
	 */
	public function get_signers( $type = 'form' ) {
		$settings       = get_option( 'firmafy_options' );
		$signers_option = isset( $settings['signers'] ) ? $settings['signers'] : array();
		$signers        = array();

		if ( 'form' === $type ) {
			foreach ( $signers_option as $signer ) {
				if ( ! empty( $signer['nif'] ) && ! empty( $signer['nombre'] ) ) {
					$signers[] = array(
						'name'  => isset( $signer['nif'] ) ? 'firmafy_signer_' . $signer['nif'] : '',
						'label' => isset( $signer['nombre'] ) ? $signer['nombre'] : '',
					);
				}
			}
		} elseif ( 'full' === $type ) {
			$signers = $signers_option;
		}
		return $signers;
	}

	/**
	 * Filter signers from feed meta
	 *
	 * @param array $meta Meta to filter.
	 * @return array
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
	 * @param integer $template_id Template ID.
	 * @return array
	 */
	public function get_variables_template( $template_id ) {
		$fields              = array();
		$template            = get_post( $template_id );
		$required_api_fields = array(
			'nombre',
			'nif',
			'email',
			'telefono',
		);

		preg_match_all( '#\{(.*?)\}#', $template->post_content, $matches );
		if ( ! empty( $matches[1] ) && is_array( $matches[1] ) ) {
			foreach ( $matches[1] as $field ) {
				if ( $this->not_strange_string( $field ) ) {
					$fields[] = $field;
				}
			}
			$fields_to_convert = array_unique( array_merge( $fields, $required_api_fields ) );
			$fields           = array();
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
	 * @param array $field Field to check.
	 * @return boolean
	 */
	private function is_field_required( $field ) {
		if ( 'nombre' === $field || 'nif' === $field || 'telefono' === $field || 'email' === $field ) {
			return true;
		}

		return false;
	}

	/**
	 * Detects strange string
	 *
	 * @param string $string String to check.
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
		if ( false !== strpos( $string, 'salto_pagina' ) ) {
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
	 * @param string  $template_id Template ID.
	 * @param array   $merge_vars Merge Vars.
	 * @param array   $force_signers Second Signers.
	 * @param integer $entry_id Entry ID.
	 * @param boolean $add_header Add Header.
	 *
	 * @return array
	 */
	public function create_entry( $template_id, $merge_vars, $force_signers = array(), $entry_id = null, $add_header = false ) {
		$settings         = get_option( 'firmafy_options' );
		$id_show          = isset( $settings['id_show'] ) ? $settings['id_show'] : '';
		$font             = isset( $settings['pdf_font'] ) ? strtolower( $settings['pdf_font'] ) : 'helvetica';
		$pdf_background   =  isset( $settings['pdf_background'] ) ? $settings['pdf_background'] : '';
		$signer           = array();
		$temp_content_pre = '';

		if ( $add_header ) {
			$temp_content_pre .= '<!-- wp:table -->
			<div class="wp-block-table"><table><tbody><tr><td><strong>NOMBRE Y APELLIDOS</strong></td><td>{nombre}</td></tr><tr><td><strong>D.N.I.</strong></td><td><strong>{nif}</strong></td></tr><tr><td><strong>DIRECCION EMAIL ASIGNADA</strong></td><td><strong>{email}</strong></td></tr><tr><td><strong>TELÉFONO</strong></td><td><strong>{telefono}</strong></td></tr><tr><td><strong>FECHA</strong></td><td>{fecha}</td></tr></tbody></table></div>
			<!-- /wp:table -->';
		}

		if ( isset( $settings['signers'] ) ) {
			$company_signers = $settings['signers'];

			// Check duplicated signers.ƒ
			if ( ! empty( $force_signers ) ) {
				$delete = array_diff( array_column( $company_signers, 'nif' ), $force_signers );

				foreach ( $delete as $key => $value ) {
					if ( isset( $company_signers[ $key ] ) ) {
						unset( $company_signers[ $key ] );
					}
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

		// Replace merge vars for values.
		$secure_mode = isset( $settings['secure_mode'] ) && 'yes' === $settings['secure_mode'] ? true : false;
		// Prevents conflict with web sytles.
		if ( $secure_mode ) {
			$template_content = $temp_content_pre;
		} else {
			$template_content = apply_filters( 'the_content', $temp_content_pre );
		}

		foreach ( $merge_vars as $variable ) {
			if ( isset( $variable['name'] ) ) {
				$value            = is_array( $variable['value'] ) ? implode( ', ', $variable['value'] ) : $variable['value'];
				$template_content = str_replace( '{' . $variable['name'] . '}', $value, $template_content );
				if ( $this->signer_tags( $variable['name'] ) && 'nif' === $variable['name'] ) {
					$signer[ $variable['name'] ] = str_replace( '.', '', $variable['value'] );
				} elseif ( $this->signer_tags( $variable['name'] ) ) {
					$signer[ $variable['name'] ] = $variable['value'];
				}
			}
		}
		$notification                 = isset( $settings['notification'] ) ? (array) $settings['notification'] : $settings['email'];
		$signer['type_notifications'] = implode( ',', $notification );

		// Replace tags.
		$template_content = $this->replace_tags( $template_content, $template_id, $entry_id );

		// Process images.
		$template_content = $this->process_images( $template_content );

		// Generates PDF.
		$filename  = 'firmafy-' . sanitize_title( get_bloginfo( 'name' ) );
		$filename .= '-' . sanitize_title( get_the_title( $template_id ) );
		$filename .= '-' . gmdate( 'Y-m-d-H-i' ) . '.pdf';

		$content  = '<html>';
		$content .= '<head>';
		$content .= '<style>';

		// Prepare Font family tag ready to replace.
		$body_replace_tags = array(
			'font-family: "{{fontFamily}}", sans-serif !important;',
		);
		$content .= 'body {  }';

		// Prepare for the PDF background.
		if ( ! empty( $pdf_background ) ) {
			$body_replace_tags[] = 'background-image: url(' . $pdf_background . '); background-size: cover;  background-position: center; background-repeat: no-repeat;';
		}

		// Append the body CSS.
		$content .= 'body { ' . implode( ' ', $body_replace_tags ) . ' }';

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

		// Gets the custom PDF styles.
		$template_pdf_css_file = FIRMAFY_PLUGIN_PATH . 'assets/pdf.css';
		if ( file_exists( $template_pdf_css_file ) ) {
			$content .= file_get_contents( $template_css_file );
		}

		// Get the Gutenberg styles.
		$content .= $this::get_gutenberg_css();

		// Append the line height to the style (only for p tags).
		$line_height = ! empty( $settings['line_height'] ) ? $settings['line_height'] : '16';

		$content .= 'p.firmafy-lh { line-height: ' . $line_height . 'px; }';
		$content .= '</style>';
		$content .= '</head>';
		$content .= '<body>';
		$content .= $template_content;
		$content .= '</body>';
		$content .= '</html>';

		// Creates PDF.
		try {
			// Define the options.
			$options = new Options();
			$options->set( 'isHtml5ParserEnabled', true ); // Enable HTML5 parser.
			$options->set( 'isRemoteEnabled', true ); // Enable remote file access.
			$options->set( 'isFontSubsettingEnabled', true );

			// Initialize the Dompdf instance.
			$dompdf = new Dompdf( $options );

			// Check if selected font is custom or not. If is custom, we must add the full path.
			if ( $this::font_is_custom( $font ) ) {
				$custom_font = self::get_custom_pdf_font( $font );

				if ( ! empty( $custom_font ) ) {
					foreach( $custom_font as $pdf_font ) {
						$dompdf->getFontMetrics()->registerFont( $pdf_font[0], $pdf_font[1] );
					}
				} else {
					$font = 'helvetica';
				}
			}
			
			// Set the font.
			$content = str_replace( '{{fontFamily}}', ucfirst( $font ), $content );

			// Setup the paper size and orientation.
			$dompdf->setPaper( 'A4', 'portrait' );

			// Load HTML content.
			$dompdf->loadHtml( $content );

			// Render the HTML to PDF.
			$dompdf->render();
			// Local show PDF on navigator.
			$dompdf->stream("dompdf_out.pdf", array("Attachment" => false)); die;
			//$pdf_content = $html2pdf->Output( $filename, 'S' );
		} catch ( Exception $e ) { //phpcs:ignore
			error_log( 'Unexpected Error!<br>Can not load PDF this time! ' . $e->getMessage() );
		}

		$token         = $this->login();
		$final_signers = ! empty( $company_signers ) ? array_merge( array( $signer ), $company_signers ) : array( $signer );

		// Sends to Firmafy.
		$query      = array(
			'id_show'    => $id_show,
			'subject'    => get_the_title( $template_id ),
			'token'      => isset( $token['data'] ) ? $token['data'] : '',
			'signer'     => wp_json_encode( $final_signers ),
			'pdf_name'   => $filename,
			'pdf_base64' => chunk_split( base64_encode( $pdf_content ) ),
		);
		$result_api = $this->api_post( $settings, 'request', $query );

		if ( 'error' === $result_api['status'] ) {
			$result_api['message'] = isset( $result_api['data'] ) ? $result_api['data'] : '';
		} else {
			$result_api['id'] = isset( $result_api['data'] ) ? $result_api['data'] : '';
		}
		return $result_api;
	}

	/**
	 * Signer Tags
	 *
	 * @param string $check Tag to check.
	 * @return boolean
	 */
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
	 * @param string  $content Content to replace.
	 * @param integer $post_id Reference post.
	 * @param integer $entry_id Entry ID.
	 *
	 * @return string
	 */
	private function replace_tags( $content, $post_id, $entry_id = null ) {
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
		$content = str_replace( '{fecha}', gmdate( 'd-m-Y' ), $content );

		if ( ! empty( $entry_id ) ) {
			$content = str_replace( '{referencia}', $entry_id, $content );
		}

		// Replace date text.
		$date_text = sprintf(
			/* translators: %1s: day %2s: month %3s: year */
			esc_html__( '%1s of %2s of %3s', 'textdomain' ),
			gmdate( 'd' ),
			$months[ (int) gmdate( 'm' ) ],
			gmdate( 'Y' )
		);
		$content = str_replace( '{fecha_texto}', $date_text, $content );

		// Replace reference.
		$content = str_replace( '{referencia}', $post_id, $content );

		// Page Break.
		$content = str_replace( '{salto_pagina}', ' <div style="page-break-after:always; clear:both"></div>', $content );

		return $content;
	}

	/**
	 * Process images
	 *
	 * @param string $content Content to process.
	 * @return string
	 */
	public function process_images( $content ) {
		// Utilizar DOMDocument para analizar y modificar el HTML.
		$doc = new DOMDocument();
		@$doc->loadHTML( mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		$images = $doc->getElementsByTagName( 'img' );

		foreach ( $images as $img ) {
			$src    = $img->getAttribute( 'src' );
			$style  = $img->getAttribute( 'style' );
			$width  = $img->getAttribute( 'width' );
			$height = $img->getAttribute( 'height' );

			if ( ! empty( $style ) ) {
				// Extraer width y height del estilo.
				preg_match( '/width:\s*(\d+px)/', $style, $width_match );
				preg_match( '/height:\s*(\d+px|auto)/', $style, $height_match );

				$width  = $width_match[1] ?? null;
				$height = $height_match[1] ?? null;

				if ( ! empty( $width ) && ! empty( $height ) && $height !== 'auto') {
					$img->setAttribute( 'width', intval( $width ) );
					$img->setAttribute( 'height', intval( $height ) );
				} else {
					list( $real_width, $real_height ) = getimagesize( $src );

					if ( ! empty( $width ) && $height === 'auto' ) {
						$real_height = intval( $real_height * $width / $real_width );
						$real_width  = intval( $width );
					} elseif ( $width === 'auto' && ! empty( $height ) ) {
						$real_width  = intval( $real_width * $height / $real_height );
						$real_height = intval( $height );
					}
					$img->setAttribute( 'width', $real_width );
					$img->setAttribute( 'height', $real_height );
				}
			} else {
				// No hay información de estilo, obtener el tamaño real
				list( $real_width, $real_height ) = getimagesize( $src );
				$img->setAttribute( 'width', $real_width );
				$img->setAttribute( 'height', $real_height );
			}

		}

		return $doc->saveHTML();
	}

	/**
	 * Get available PDF fonts
	 *
	 * @return array
	 */
	public function get_available_pdf_fonts() {
		return $this->available_pdf_fonts;
	}

	/**
	 * Add available PDF fonts
	 *
	 * @param string $font Font to add.
	 */
	public function add_available_pdf_fonts( $font ) {
		$this->available_pdf_fonts[] = $font;
	}

	/**
	 * Get custom PDF fonts
	 *
	 * @return string
	 */
	public static function get_custom_pdf_fonts() {
		$fonts = array(
			'roboto' => array(
				array( // Regular.
					array(
						'family' => 'Roboto',
						'style'  => 'normal',
						'weight' => 'normal',
					),
					FIRMAFY_PLUGIN_PATH . 'includes/fonts-pdf/roboto-regular.ttf',
				),
				array( // 500.
					array(
						'family' => 'Roboto',
						'style'  => 'normal',
						'weight' => '500',
					),
					FIRMAFY_PLUGIN_PATH . 'includes/fonts-pdf/roboto-500.ttf',
				),
				array( // 700.
					array(
						'family' => 'Roboto',
						'style'  => 'normal',
						'weight' => '700',
					),
					FIRMAFY_PLUGIN_PATH . 'includes/fonts-pdf/roboto-700.ttf',
				),
			),
		);
		return apply_filters( 'firmafy_custom_font_path', $fonts );
	}

	/**
	 * Get custom PDF font
	 *
	 * @param string $font Font to get.
	 * @return string
	 */
	public static function get_custom_pdf_font( $font ) {
		$fonts = self::get_custom_pdf_fonts();
		return isset( $fonts[ $font ] ) ? $fonts[ $font ] : array();
	}

	/**
	 * Check if font is custom
	 *
	 * @param string $font Font to check.
	 * @return boolean
	 */
	public static function font_is_custom( $font ) {
		$base_fonts = array(
			'courier',
			'helvetica',
			'times',
			'zapfdingbats',
		);

		return in_array( $font, $base_fonts, true ) ? false : true;
	}

	/**
	 * Get Gutenberg CSS
	 *
	 * @return string
	 */
	public static function get_gutenberg_css() {
		$combined = '';
    
		if ( file_exists( ABSPATH . 'wp-includes/css/dist/block-library/style.css' ) ) {
			$combined .= file_get_contents( ABSPATH . 'wp-includes/css/dist/block-library/style.css' );
		}

		if ( file_exists( ABSPATH . 'wp-includes/css/dist/block-library/theme.css' ) ) {
			$combined .= file_get_contents( ABSPATH . 'wp-includes/css/dist/block-library/theme.css' );
		}

		return $combined;
	}


	/**
	 * Get block dynamic CSS
	 *
	 * @return string
	 */
	public static function get_block_dynamic_css() {
		$css_url  = admin_url('load-styles.php?c=1&dir=ltr&load=wp-block-library,wp-block-editor,wp-block-editor-content,wp-editor,wp-components');
		$response = file_get_contents( $css_url );
		return $response;
	}

	/**
	 * Get template content
	 *
	 * @param integer $template_id Template ID.
	 * @return string
	 */
	public static function get_template_content( $template_id ) {
		$_post  = get_post( $template_id );
		$blocks = parse_blocks( $_post->post_content );

		ob_start();
		foreach ( $blocks as $block ) {
			echo render_block( $block );
		}

		return ob_get_clean();
	}

	public static function get_css_defined_vars() {
		// Get the global colors.
		$global_colors = wp_get_global_settings( array( 'color', 'palette', 'theme' ) );
		$css           = '';

		if ( isset( $global_colors['palette'] ) && is_array( $global_colors['palette'] ) ) {
			foreach ( $global_colors['palette'] as $color ) {
				$slug        = isset( $color['slug'] ) ? $color['slug'] : '';
				$color_value = isset( $color['color'] ) ? $color['color'] : '';

				if ( $slug && $color_value ) {
					$css .= "
						.has-{$slug}-color {
								color: {$color_value};
						}
						.has-{$slug}-background-color {
								background-color: {$color_value};
						}
					";
				}
			}
		}

		return $css;
	}

}

$helpers_firmafy = new Helpers_Firmafy();
