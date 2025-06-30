<?php
/**
 * Adjust WordPress comments settings display in WP Admin while Coral is active
 *
 * @package AmericaMagazine
 */

namespace AmericaMagazine\Modules\Coral_Comments;

/**
 * Class to provide default settings.
 */
class Coral_Default_Comments_Settings {
	/**
	 * Initialize the class.
	 */
	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'options_discussion_notice' ) );
		add_action(
			'admin_menu',
			function() {
				remove_menu_page( 'edit-comments.php' );
			} 
		);
	}

	/**
	 * Print admin notice in Discussion settings page so people don't get confused
	 *
	 * @since 0.0.2
	 */
	public static function options_discussion_notice() {
		$screen = get_current_screen();
		if ( 'options-discussion' === $screen->base ) {
			coral_print_admin_notice(
				'success',
				esc_html( 'Coral is activated, so comments are controlled in %sCoral plugin settings%s.' )
			);
		}
	}
}

Coral_Default_Comments_Settings::init();
