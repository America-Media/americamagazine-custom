<?php
/**
 * Plugin Name: America Magazine custom plugin
 * Plugin URI: https://github.com/America-Media/americamagazine-custom/
 * Description: Custom features for americamagazine.org on Newspack
 * Version: 1.0.0
 * Author: America Media 
 * Author URI: https://www.americamagazine.org
 * Text Domain: america-magazine
 * Requires Plugins: piano
 *
 * For more information on WordPress plugin headers, see the following page:
 * https://developer.wordpress.org/plugins/plugin-basics/header-requirements/
 *
 * @package AmericaMagazine
 */

// Ensure that everything is namespaced.
namespace AmericaMagazine;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// This will load composer's autoload.
// Keep in mind that you need to run `composer dump-autoload` after adding new classes.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
} else {
	// Add admin notice in case the plugin has not been built.
	add_action(
		'admin_notices',
		function() {
			?>
			<div class="notice notice-error">
				<p><?php esc_html_e( 'Publisher Name plugin was not properly built.', 'america-magazine' ); ?></p>
			</div>
			<?php
		}
	);
	return;
}

// Initialize the plugin.

Module_Loader::init();
