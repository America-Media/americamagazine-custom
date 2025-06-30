<?php
/**
 * Helper functions
 *
 * @since 0.0.4
 * 
 * @package AmericaMagazine
 */

namespace AmericaMagazine\Modules\Coral_Comments;

/**
 * Prints an admin notice. If the message contains two %s placeholders,
 * the content between them will be wrapped in a link to the plugin settings page
 *
 * @param string $type 'error', 'warning', 'success'.
 * @param string $message Translated message text.
 */
function coral_print_admin_notice( $type = 'error', $message = 'Coral comments error' ) {
	$has_link = ( 2 === substr_count( $message, '%s' ) );

	?>
		<div class="notice notice-<?php echo esc_attr( $type ); ?>">
			<p>
			<?php
			echo ! $has_link ?
				esc_html( $message ) :
				sprintf(
					esc_html( $message ),
					'<a href="' . esc_url( admin_url( 'options-general.php?page=coral-settings' ) ) . '">',
					'</a>'
				);
			?>
			</p>
		</div>
	<?php
}
