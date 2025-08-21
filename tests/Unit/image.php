<?php
/**
 * TEST
 * 
 * http://firmafy-plugin.local/wp-content/plugins/firmafy/test/image.php
 *
 * @package    WordPress
 * @author     David Perez <david@close.technology>
 * @copyright  2023 Closemarketing
 * @version    1.0
 */

require_once '../../../../wp-load.php';

echo 'Testing images...<br>';

define( 'WPAT_PLUGINPATH', dirname( __DIR__ ) . '/' );

require_once '../includes/class-helpers-firmafy.php';
echo 'Antes...<br>';
echo '<pre>$content';
print_r(htmlentities($content));
echo '</pre>';
$content = file_get_contents( WPAT_PLUGINPATH . 'test/examples/image.html' );

global $helpers_firmafy;
$content = $helpers_firmafy->process_images( $content );

echo 'Despues...<br>';
echo '<pre>$content';
print_r(htmlentities($content));
echo '</pre>';