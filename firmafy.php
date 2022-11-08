<?php
/**
 * Plugin Name: Firmafy
 * Plugin URI:  https://firmafy.com
 * Description: Validate legally your forms in WordPress.
 * Version:     1.2.0-beta.1
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

define( 'FIRMAFY_VERSION', '1.2.0-beta.1' );
define( 'FIRMAFY_PLUGIN', __FILE__ );
define( 'FIRMAFY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FIRMAFY_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

add_action( 'plugins_loaded', 'firmafy_plugin_init' );
/**
 * Load localization files
 *
 * @return void
 */
function firmafy_plugin_init() {
	load_plugin_textdomain( 'firmafy', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

/**
 * # Includes
 * ---------------------------------------------------------------------------------------------------- */
require_once FIRMAFY_PLUGIN_PATH . 'includes/class-helpers-firmafy.php';
require_once FIRMAFY_PLUGIN_PATH . 'includes/class-firmafy-admin-settings.php';


// Prevents fatal error is_plugin_active.
if ( ! function_exists( 'is_plugin_active' ) ) {
	include_once ABSPATH . 'wp-admin/includes/plugin.php';
}

if ( ( is_plugin_active( 'gravityforms/gravityforms.php' ) || is_plugin_active( 'gravity-forms/gravityforms.php' ) ) && ! class_exists( 'FIRMAFY_Bootstrap' ) ) {
	add_action( 'gform_loaded', array( 'FIRMAFY_Bootstrap', 'load' ), 5 );
	class FIRMAFY_Bootstrap {

		public static function load() {

			if ( ! method_exists( 'GFForms', 'include_feed_addon_framework' ) ) {
				return;
			}

			require_once FIRMAFY_PLUGIN_PATH . 'includes/forms/class-gravityforms.php';

			GFAddOn::register( 'GFFirmafy' );
		}
	}

	function gf_firmafy() {
		return GFFirmafy::get_instance();
	}
}

// ContactForms7.
if ( is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ) && ! class_exists( 'FORMSCRM_CF7_Settings' ) ) {
	require_once FIRMAFY_PLUGIN_PATH . 'includes/forms/class-contactform7.php';
}

// WooCommerce.
if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
	require_once FIRMAFY_PLUGIN_PATH . 'includes/forms/class-woocommerce.php';
}
