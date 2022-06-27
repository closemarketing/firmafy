<?php
/**
 * Plugin Name: Firmafy
 * Plugin URI:  https://firmafy.com
 * Description: Validate legally your forms in WordPress.
 * Version:     1.0
 * Author:      Closetechnology
 * Author URI:  https://close.technology
 * Text Domain: firmafy
 * Domain Path: /languages
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 *
 * @package     WordPress
 * @author      Closetechnology
 * @copyright   2021 Closetechnology
 * @license     GPL-2.0+
 *
 * @wordpress-plugin
 *
 * Prefix:      fcrm
 */

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

define( 'FIRMAFY_VERSION', '1.0' );
define( 'FIRMAFY_PLUGIN', __FILE__ );
define( 'FIRMAFY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FIRMAFY_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

if ( ! defined( 'FORMSCRM_VERSION' ) ) {
	define( 'FORMSCRM_VERSION', '3.7.0' );
	define( 'FORMSCRM_PLUGIN', __FILE__ );
	define( 'FORMSCRM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
	define( 'FORMSCRM_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}

add_action( 'plugins_loaded', 'firmafy_plugin_init' );
/**
 * Load localization files
 *
 * @return void
 */
function firmafy_plugin_init() {
	load_plugin_textdomain( 'firmafy', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

add_filter(
	'formscrm_load_options',
	function() {
		return false;
	}
);

add_filter(
	'formscrm_choices',
	function( $choices ) {
		$choices[] = array(
			'label' => 'Firmafy',
			'value' => 'firmafy',
		);

		return $choices;
	}
);

add_filter(
	'formscrm_dependency_password',
	function( $choices ) {

		$choices[] = 'firmafy';

		return $choices;
	}
);

add_filter(
	'formscrm_crmlib_path',
	function( $choices ) {
		$choices['firmafy'] = FIRMAFY_PLUGIN_PATH . 'includes/crm-library/class-crmlib-firmafy.php';

		return $choices;
	}
);


/**
 * # Includes
 * ---------------------------------------------------------------------------------------------------- */
require_once FIRMAFY_PLUGIN_PATH . 'includes/class-api-firmafy.php';
require_once FIRMAFY_PLUGIN_PATH . 'includes/formscrm-library/loader.php';
require_once FIRMAFY_PLUGIN_PATH . 'includes/class-firmafy-admin-settings.php';
