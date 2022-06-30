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

/**
 * Class Firmafy.
 *
 * Connnector to Firmafy.
 *
 * @since 1.0
 */
class Helpers_Firmafy {
	/**
	 * # Functions
	 * ---------------------------------------------------------------------------------------------------- */

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
				'data'   => 'No API Key',
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
			$args['body'] = $query;
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
				$fields[] = array(
					'name'  => $field,
					'label' => $field,
				);
			}
			return $fields;
		}
	}
}

$helpers_firmafy = new Helpers_Firmafy();
