<?php
/**
 * Generate settings page for Coral plugin
 *
 * @package AmericaMagazine
 */

namespace AmericaMagazine\Modules\Coral_Comments;

/**
 * Class to provide settings page.
 */
class Coral_Settings_Page {
	/**
	 * Initialize the class.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_settings_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'setup_settings_page' ) );
	}

	/**
	 * Registers the functions to create the settings page.
	 *
	 * @since 0.0.1
	 */
	public static function register_settings_page() {
		add_options_page(
			__( 'Coral Settings', 'coral-project' ),
			__( 'Coral Settings', 'coral-project' ),
			'manage_options',
			'coral-settings',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Registers the settings section(s) and field(s)
	 *
	 * @since 0.0.1
	 * @since 0.0.3 Added new container classes setting
	 */
	public static function setup_settings_page() {
		add_settings_section(
			'about-coral',
			__( 'About Coral', 'coral-project' ),
			function() {
				require_once CORAL_MODULE_DIR . '/inc/coral-settings-static-content.php';
			},
			'coral-settings'
		);

		add_settings_field(
			'coral_base_url',
			__( 'Coral Base URL', 'coral-project' ),
			array( __CLASS__, 'render_base_url_field' ),
			'coral-settings',
			'about-coral'
		);
		register_setting(
			'coral-settings',
			'coral_base_url',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_url' ),
			) 
		);

		add_settings_field(
			'coral_jwt_signing_key',
			__( 'JWT Signing Key', 'coral-project' ),
			array( __CLASS__, 'render_jwt_signing_key_field' ),
			'coral-settings',
			'about-coral'
		);
		register_setting( 'coral-settings', 'coral_jwt_signing_key' );

		add_settings_field(
			'coral_local_mode',
			__( 'Local or Server', 'coral-project' ),
			array( __CLASS__, 'render_local_mode_field' ),
			'coral-settings',
			'about-coral'
		);
		register_setting( 'coral-settings', 'coral_local_mode' );
	}

	/**
	 * Sanitizes base URL input, removing trailing slash
	 *
	 * @param String $url Input to sanitize.
	 * @return String Sanitized and untrailingslashed URL.
	 * @since 0.0.6
	 */
	public static function sanitize_url( $url ) {
		return esc_url( untrailingslashit( $url ) );
	}

	/**
	 * Prints input field for base URL setting.
	 *
	 * @since 0.0.1
	 */
	public static function render_base_url_field() {
		?>
		<input
			style="width: 600px; height: 40px;"
			name="coral_base_url"
			placeholder="https://my-site.coral.coralproject.net"
			id="coral_base_url"
			type="url"
			value="<?php echo esc_url( get_option( 'coral_base_url' ) ); ?>"
		/>
		<p class="description">
			<span style="font-weight: bold;">* Required.</span> 
			The root url, starting with https://, of the installed or hosted Coral application.<br/>
			Populating this setting will activate Coral comments to replace WP native comments site-wide.
		</p>
		<?php
	}

	/**
	 * Prints input field for JWT Signing Key setting.
	 *
	 * @since 0.0.1
	 */
	public static function render_jwt_signing_key_field() {
		?>
		<input
			style="width: 600px; height: 40px;"
			name="coral_jwt_signing_key"
			placeholder="Obtain key from link in description below after setting Coral Base URL"
			id="coral_jwt_signing_key"
			type="text"
			value="<?php echo esc_attr( get_option( 'coral_jwt_signing_key' ) ); ?>"
		/>
		<p class="description">Key to sign JWTs for Coral authentication, found in 
			<a href="<?php echo esc_url( get_option( 'coral_base_url' ) ); ?>/admin/configure/auth" target="_blank">Coral Authentication Settings</a>.
		</p>
		<?php
	}

	/**
	 * Prints input field for local mode setting.
	 *
	 * @since 1.0.0
	 */
	public static function render_local_mode_field() {
		$development_mode = esc_attr( get_option( 'coral_local_mode' ) )
		?>
		<select
				style="width: 600px; height: 40px;"
				name="coral_local_mode"
				placeholder=""
				id="coral_local_mode"
				type="select"
		>
		<option value="local"
				<?php 
				if ( $development_mode === 'local' ) {
					echo 'selected="selected"'; 
				}
				?>
			>
				Local
			</option>
			<option value="server"
				<?php 
				if ( $development_mode === 'server' ) {
					echo 'selected="selected"'; 
				}
				?>
			>
				Server
			</option>
	</select>
		<p class="description">
			If using WordPress in a local development environment, set to Local.<br/>
			This will help prevent Coral from storing invalid local environment URLs.
		</p>
		<?php
	}

	/**
	 * Generates the markup for the settings page.
	 *
	 * @since 0.0.1
	 */
	public static function render_settings_page() {
		?>
		<div class="wrap">
			<h2><?php esc_html_e( 'Coral Settings', 'coral-project' ); ?></h2>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'coral-settings' );
				do_settings_sections( 'coral-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}

Coral_Settings_Page::init();
