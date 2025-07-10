<?php
/**
 * Piano Customization.
 * 
 * Customizes the Piano Javascript environment beyond the default Piano plugin.
 *
 * @package AmericaMagazine
 */

namespace AmericaMagazine\Modules\Piano_Customization;

defined( 'ABSPATH' ) || exit;

/**
 * Class to add Piano customizations for America magazine on Newspack
 */
class Piano_Customization {

	/**
	 * Initialize the class
	 *
	 * @return void
	 */
	public static function init() {
		/**
		 * Since the Piano plugin does not register/enqueue a script but simply outputs it in a wp_footer 
		 * action, we unfortunately need to mimic this, but with priority ahead of the default 10, in order
		 * to make sure our script can load its Piano commands to the tp object before Piano Composer starts 
		 */
		add_action( 'wp_footer', [ __CLASS__, 'piano_custom_tags_in_footer' ], 9 );

		/**
		 * Add styles & script for UX with Piano ID accounts (styles to head, script to footer with Piano script)
		 */
		add_action( 'wp_head', [ __CLASS__, 'piano_id_account_styles' ] );
		add_action( 'wp_footer', [ __CLASS__, 'piano_id_account_scripts' ] );
	}

	/**
	 * Builds custom tags and pushes them to Piano Composer
	 *
	 * @return void
	 */
	public static function piano_custom_tags_in_footer() {

		/* Tag metadata only makes sense for Piano in single pages or posts */
		if ( is_singular( [ 'post', 'page' ] ) ) {
			$item = get_post();
			$tags = get_the_tags();
			$categories = get_the_terms( $item->ID, 'category' );
			$channel_names = [ 'politics-society', 'faith', 'arts-culture', 'magazine', 'community' ];

			$tags_for_tp_push = [ 
				'wp-id:' . $item->ID,   // the post ID to distinguish an individual piece of content
				'platform:newspack',     // so that Piano Composer can be targeted to run only in Newspack
			];

			/* Node ID for backwards compatibility to Drupal */
			$nid = get_post_meta( $item->ID, '_fgd2wp_old_node_id', true );
			if ( ! empty( $nid ) ) {
				$tags_for_tp_push[] = 'nid:' . $nid;
			}

			/* Content-type for backwards compatibility to Drupal */
			if ( get_post_type() === 'post ' ) {
				$tags_for_tp_push[] = 'content-type:article';
			} elseif ( get_post_type() === 'page' ) {
				$tags_for_tp_push[] = 'content-type:page';
			}

			if ( ! empty( $tags ) && ! is_wp_error( $tags ) ) {
				$tag_names = wp_list_pluck( $tags, 'name' );
				foreach ( $tag_names as $name ) {
					$clean_name = self::_clean_tag_name( $name );
					$tags_for_tp_push[] = 'tag:' . $clean_name;
					/* Topic for backwards compatibility to Drupal */
					$tags_for_tp_push[] = 'topic:' . $clean_name;
				}
			}

			if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
				$category_names = wp_list_pluck( $categories, 'name' );
				foreach ( $category_names as $name ) {
					$clean_name = self::_clean_tag_name( $name );
					$tags_for_tp_push[] = 'category:' . $clean_name;
					/* Section for backwards compatibility to Drupal */
					$tags_for_tp_push[] = 'section:' . $clean_name;
					/* Special cases for channel for backwards compatibility to Drupal */
					if ( in_array( $clean_name, $channel_names, true ) ) {
						$tags_for_tp_push[] = 'channel:' . $clean_name;
					}
				}
			}

			?>
<script type="text/javascript">
(function() {
	tp = window["tp"] || [];
	americaCustomTags = <?php echo json_encode( $tags_for_tp_push ); ?>;

	/**
	 * Since the Piano plugin script does not check for other setTags commands,
	 * and Piano Composer will only honor the last-issued setTags command,
	 * we need to override the push method on the Javascript array to intercept
	 * the newer setTags command and append its tags instead.
	 */
	Object.defineProperty(tp, "push", {
		value: function(args) {
			setTagsTest = function(a) {
				return Array.isArray(a) && a.length === 2 && a[0] === "setTags" && Array.isArray(a[1]);
			};
			if (setTagsTest(args)) {
				existingSetTags = this.find(setTagsTest);
				if (existingSetTags) { 
					// Append new tags to array of tags in existing set tags command
					Array.prototype.push.apply(existingSetTags[1],args[1]);
					return this.length;
				}
			}
			return Array.prototype.push.call(this,args);
		}
	});

	tp.push(["setTags", americaCustomTags]);
})();
</script>
			<?php

		}
	}

	/**
	 * Helper to generate a Piano-safe tag name from a source string.
	 *
	 * @param string $name String to be cleaned.
	 *
	 * @return string
	 *   Piano-safe tag name cleaned-up of any special chars
	 *   (hypens and colons allowed) and lowercased.
	 */
	public static function _clean_tag_name( string $name ) {
		return strtolower(
			preg_replace(
				array(
					'/[^a-zA-Z0-9:]+/',
					'/-+/',
					'/^-+/',
					'/-+$/',
				),
				array( '-', '-', '', '' ),
				$name
			)
		);
	}

	/**
	 * Add styles to support Piano ID accounts
	 * 
	 * @return void
	 */
	public static function piano_id_account_styles() {
		?>
		<style type="text/css">
			.wp_piano_id_button.hide, 
			.wp_piano_id_account_button.hide, 
			.wp_piano_id_logged_in.hide {
				display: none!important;
			}
		</style>
		<?php
	}

	/**
	 * Add styles to support Piano ID accounts
	 * 
	 * @return void
	 */
	public static function piano_id_account_scripts() {
		?>
		<script type="text/javascript">
			(function() {
				/** 
				 * Since the stock Piano plugin does not make an account item visible on login,
				 * we need to watch for the addition of the "hide" class on the login button.
				 * But we only need to watch on one of however many login buttons might be available.
				 */
				const pianoIdLoginButton = document.getElementsByClassName("wp_piano_id_button")[0];
				const unhideLoggedInItemsWatcher = new MutationObserver((mutations) => {
					mutations.forEach( (mutation) => {
						if ("attributes" === mutation.type && "class" === mutation.attributeName 
							&& mutation.target.classList.contains("hide")
						) {
							const loggedInElements = document.getElementsByClassName("wp_piano_id_logged_in");
							for (const e of loggedInElements) {
								e.classList.remove("hide");
							}
						}
					});	
				});
				if (pianoIdLoginButton) {
					unhideLoggedInItemsWatcher.observe(pianoIdLoginButton, { attributes: true, attributeFilter: ["class"] });
				}

				// Wire the logout link to a Piano ID action
				const pianoIdLogoutButtons = document.getElementsByClassName("wp_piano_id_logout");
				for (const e of pianoIdLogoutButtons) {
					e.addEventListener("click", (() => {
							tp.pianoId.logout();

							// Clear local storage of America custom items
							Object.keys(localStorage)
								.filter((k) => k.startsWith("america-"))
								.forEach((k) => localStorage.removeItem(k));
							
							// After logout, return to homepage
							location.href = "/";
						}
					));
				}
			})();
		</script>
		<?php
	}
}
