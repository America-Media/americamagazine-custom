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
	'storyID' => $story_id,
	'storyURL' => wp_get_canonical_url(),
	'localMode' => $coral_local_mode !== 'server',
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
	<div id="coral_thread"></div>
<?php
