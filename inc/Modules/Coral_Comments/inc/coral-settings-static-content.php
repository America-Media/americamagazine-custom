<?php
/**
 * Prints static content for Coral Settings page.
 * 
 * @package AmericaMagazine
 */

namespace AmericaMagazine\Modules\Coral_Comments;

?>
<p>
	<?php
	printf(
		esc_html( 'Coral is an open-source commenting platform from Vox Media. Find out more about Coral %shere%s.' ),
		'<a href="https://coralproject.net" target="_blank">',
		'</a>'
	);
	?>
</p>
<p>
	<?php
	printf(
		esc_html( 'You can find out how to install and manage Coral %shere%s.' ),
		'<a href="https://docs.coralproject.net/" target="_blank">',
		'</a>'
	);
	?>
</p>

<h2><?php esc_html_e( 'Coral Settings', 'coral-project' ); ?></h2>
<p>
	<?php
	printf(
		esc_html( 'You are using the America magazine customization of the Coral WordPress Plugin. View the code, documentation, and latest releases %shere%s.' ),
		'<a href="https://github.com/America-Media/americamagazine-custom" target="_blank">',
		'</a>'
	);
	?>
</p>
