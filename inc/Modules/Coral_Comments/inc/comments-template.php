<?php
/**
 * Comments template replacement.
 * 
 * This will replace the default comments.php when calling comments_template() and populate the data
 * needed for the Coral embed to be properly invokved.
 *
 * @package AmericaMagazine
 * 
 * @since 0.0.3 Added support for coral container class and removed id
 * @since 1.0.0 Added support for custom CSS url, custom fonts CSS url, 
 * disable default fonts, custom scroll container, canonical story url, and story mode. Also
 * updated support for container class name.
 */

namespace AmericaMagazine\Modules\Coral_Comments;

$coral_url = esc_url( get_option( 'coral_base_url' ) );

if ( empty( $coral_url ) || is_attachment() ) :
	exit();
endif;

$story_id = 'wp-' . $post->ID;
$old_node_id = get_post_meta( $post->ID, '_fgd2wp_old_node_id', true );
if ( ! empty( $old_node_id ) ) {
	$story_id = $old_node_id;
}

$coral_local_mode = get_option( 'coral_local_mode' );

$america_coral_settings = [
	'coralRootURL' => $coral_url,
	'storyID'      => $story_id,
	'storyURL'     => wp_get_canonical_url(),
	'localMode'    => $coral_local_mode !== 'server',
];

wp_enqueue_script( 'coral-embed-script' );
wp_enqueue_script( 'coral-count-script' );
wp_enqueue_script( 'americamagazine-coral' );
wp_add_inline_script(
	'americamagazine-coral',
	'const AmericaCoralSettings = ' . json_encode( $america_coral_settings ),
	'before'
);

?>
<button id="coral-comments-toggle" class="comments-toggle">
	<div class="coral-comments-show-hide"><?php echo wp_kses( newspack_get_icon_svg( 'chevron_right', 24 ), newspack_sanitize_svgs() ); ?></div>
	<span class="coral-comments-show-hide">Show</span>
	<div class="coral-comments-show-hide" hidden><?php echo wp_kses( newspack_get_icon_svg( 'chevron_left', 24 ), newspack_sanitize_svgs() ); ?></div>
	<span class="coral-comments-show-hide" hidden>Hide</span>&nbsp;Comments (
	<span class="coral-count" data-coral-id="<?php echo esc_attr( $story_id ); ?>" data-coral-url="<?php echo esc_url( wp_get_canonical_url() ); ?>" data-coral-notext="true"></span>)
</button>
<div id="coral-thread" class="coral-comments-show-hide" hidden></div>

<?php
