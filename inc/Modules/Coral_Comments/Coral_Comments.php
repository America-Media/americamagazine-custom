<?php
/**
 * America magazine custom Coral comments implementation for Newspack.
 * 
 * Derived from Coral Talk WordPress plugin (https://github.com/coralproject/talk-wp-plugin),
 * under Apach 2.0 license.
 * Updated to reflect:
 * - Coral Talk -> Coral name change
 * - No need for pre-v5 implementation
 * - Newspack custom plugin conventions (module-based and static class functions where possible)
 * - America magazine customization for storyID handling & Piano ID-based JWT authentication
 *
 * @package AmericaMagazine
 */

namespace AmericaMagazine\Modules\Coral_Comments;

defined( 'ABSPATH' ) || exit;

define( 'CORAL_MODULE_DIR', __DIR__ );

/**
 * Class to implement Coral Comments functionality
 */
class Coral_Comments {

	/**
	 * Initialize the class
	 */
	public static function init() {
		require_once CORAL_MODULE_DIR . '/inc/helper-functions.php';
		require_once CORAL_MODULE_DIR . '/inc/class-coral-settings-page.php';
		require_once CORAL_MODULE_DIR . '/inc/class-coral-default-comments-settings.php';

		$coral_base_url = get_option( 'coral_base_url' );
		
		// Existence of coral_base_url option triggers activating Coral replacement of native WordPress comments
		if ( ! empty( $coral_base_url ) ) {
			add_filter(
				'comments_template',
				function( $default_template_path ) {
					return ( CORAL_MODULE_DIR . '/inc/comments-template.php' );
				},
				99 
			);

			/**
			 * We register scripts here to set up the plugin, but we will not enqueue
			 * them until they are needed in the comments template to avoid extra
			 * scripts on pages that do not actually have comments.
			 */ 
			wp_register_script(
				'coral-embed-script',
				$coral_base_url . '/assets/js/embed.js',
				array(),
				null,
				array( 'strategy' => 'defer' )
			);
			wp_register_script(
				'coral-count-script',
				$coral_base_url . '/assets/js/count.js',
				array(),
				null,
				array( 'strategy' => 'defer' )
			);
			wp_register_script( 
				'americamagazine-coral',
				plugin_dir_url( __FILE__ ) . 'js/americamagazine-coral.js',
				array( 'coral-embed-script' ),
				null,
				array( 'strategy' => 'defer' )
			);

		} else {
			add_action(
				'admin_notices',
				function() {
					coral_print_admin_notice(
						'warning',
						esc_html( 'The Base URL is required in %sCoral plugin settings%s for Coral to run' )
					);
				}    
			);
		}
	}
}
