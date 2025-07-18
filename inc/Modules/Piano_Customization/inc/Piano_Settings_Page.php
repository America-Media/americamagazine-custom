<?php
/**
 * Generate settings page for Piano plugin
 *
 * @package AmericaMagazine
 */

namespace AmericaMagazine\Modules\Piano_Customization;

/**
 * Class to provide settings page.
 */
class Piano_Settings_Page {
	/**
	 * Initialize the class.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_settings_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'setup_settings_page' ) );
	}

	/**
	 * Registers the functions to create the settings page.
	 */
	public static function register_settings_page() {
		add_options_page(
			__( 'America Piano Settings', 'americamagazine-piano' ),
			__( 'America Piano Settings', 'americamagazine-piano' ),
			'manage_options',
			'americamagazine-piano-settings',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Registers the settings section(s) and field(s)
	 */
	public static function setup_settings_page() {
		add_settings_section(
			'americamagazine-piano-settings',
			__( 'Piano Customization for America magazine', 'americamagazine-piano' ),
			function() {
				// We don't need any top-level description at this point.
			},
			'americamagazine-piano-settings'
		);
		add_settings_field(
			'piano_subscriber_resource_id',
			__( 'Resource ID to recognize subscribers', 'americamagazine-piano' ),
			array( __CLASS__, 'render_subscriber_resource_id_field' ),
			'americamagazine-piano-settings',
			'americamagazine-piano-settings'
		);
		register_setting( 'americamagazine-piano-settings', 'piano_subscriber_resource_id' );
	}

	/**
	 * Prints input field for Piano Subscriber Resource ID setting.
	 */
	public static function render_subscriber_resource_id_field() {
		?>
		<input
			style="width: 600px; height: 40px;"
			name="piano_subscriber_resource_id"
			placeholder="Resource ID (rid) as found in the Piano dashboard"
			id="piano_subscriber_resource_id"
			type="text"
			value="<?php echo esc_attr( get_option( 'piano_subscriber_resource_id' ) ); ?>"
		/>
		<p class="description">
			Piano resource ID (rid) to determine subscription status (usually <samp>unlimited-access</samp>).
		</p>
		<?php
	}

	/**
	 * Generates the markup for the settings page.
	 */
	public static function render_settings_page() {
		?>
		<div class="wrap">
			<h2><?php esc_html_e( 'America Piano Settings', 'americamagazine-piano' ); ?></h2>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'americamagazine-piano-settings' );
				do_settings_sections( 'americamagazine-piano-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}

Piano_Settings_Page::init();
