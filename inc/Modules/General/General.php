<?php
/**
 * General change for America magazine.
 *
 * @package AmericaMagazine
 */

namespace AmericaMagazine\Modules\General;

defined( 'ABSPATH' ) || exit;

/**
 * Class to add a notice to the admin panel
 */
class General {

	/**
	 * Initialize the class
	 *
	 * @return void
	 */
	public static function init() {

		// Disable Yoast monitoring of slug changes for redirect; Redirection plugin takes care of this
		add_filter( 'Yoast\WP\SEO\post_redirect_slug_change', '__return_true' );
		// Also disable notices about Yoast redirects
		add_filter( 'Yoast\WP\SEO\enable_notification_post_trash', '__return_false' );
		add_filter( 'Yoast\WP\SEO\enable_notification_post_slug_change', '__return_false' );

		// This would disable Yoast montioring of taxonomy changes for redirect — 
		// but redirection plugin doesn't do this, so leave it active.
		// add_filter( 'Yoast\WP\SEO\term_redirect_slug_change', '__return_true' );
	}
}
