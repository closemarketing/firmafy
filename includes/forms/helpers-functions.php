<?php
/**
 * Debug functions
 *
 * Functions to debug library CRM
 *
 * @author   closemarketing
 * @category Functions
 * @package  Gravityforms CRM
 * @version  1.0.0
 */

if ( ! function_exists( 'firmafy_debug_message' ) ) {
	/**
	 * Debug message in log
	 *
	 * @param array $message Message.
	 * @return void
	 */
	function firmafy_debug_message( $message ) {
		if ( true === WP_DEBUG ) {
			if ( is_array( $message ) ) {
				$message = print_r( $message, true ); //phpcs:ignore
			}
			error_log( 'Firmafy: ' . esc_html__( 'Message Debug Mode', 'firmafy' ) . ' ' . esc_html( $message ) );
		}
	}
}

if ( ! function_exists( 'firmafy_error_admin_message' ) ) {
	/**
	 * Shows in WordPress error message
	 *
	 * @param string $code Code of error.
	 * @param string $message Message.
	 * @return void
	 */
	function firmafy_error_admin_message( $code, $message ) {
		if ( true === WP_DEBUG ) {
			error_log( 'Firmafy: API ERROR ' . esc_html( $code ) . ': ' . esc_html( $message ) );
		}
	}
}

// * Sends an email to administrator when it not creates the lead
if ( ! function_exists( 'firmafy_debug_email' ) ) {
	/**
	 * Sends error to admin
	 *
	 * @param string $crm   CRM.
	 * @param string $error Error to send.
	 * @param array  $data  Data of error.
	 * @return void
	 */
	function firmafy_debug_email( $crm, $error, $data ) {
		$to      = get_option( 'admin_email' );
		$subject = 'Firmafy - ' . __( 'Error creating the Signature', 'firmafy' );
		$body    = '<p>' . __( 'There was an error creating the Signature', 'firmafy' ) . ' ' . $crm . ':</p><p><strong>' . $error . '</strong></p><p>' . __( 'Signature Data', 'firmafy' ) . ':</p>';
		foreach ( $data as $dataitem ) {
			$value = is_array( $dataitem['value'] ) ? implode( ', ', $dataitem['value'] ) : $dataitem['value'];
			$body .= '<p><strong>' . $dataitem['name'] . ': </strong>' . $value . '</p>';
		}
		$body   .= '</br/><br/>Firmafy';
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		wp_mail( $to, $subject, $body, $headers );
	}
}

if ( ! function_exists( 'firmafy_testserver' ) ) {
	/**
	 * Error message
	 *
	 * @return void
	 */
	function firmafy_testserver() {
		// test curl.
		if ( ! function_exists( 'curl_version' ) && true === WP_DEBUG ) {
			error_log( 'Firmafy: ' . __( 'curl is not Installed in your server. It is needed to work with CRM Libraries.', 'firmafy' ) );
		}
	}
}
