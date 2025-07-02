<?php
/**
 * America magazine custom Coral comments implementation for Newspack.
 * 
 * Derived from Coral Talk WordPress plugin (https://github.com/coralproject/talk-wp-plugin),
 * under Apache 2.0 license.
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

use Firebase\JWT\JWT;
use Firebase\JWT\Key as JWT_Key;
use Firebase\JWT\SignatureInvalidException;

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
			 * scripts on pages that do not actually have comments or have comments open.
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

			/**
			 * Since users login with Piano, we need to supply an endpoint that, given a Piano user's JWT,
			 * can supply and sign a JWT that will be used to authorize a user with the Coral commenting system
			 */
			add_action( 'rest_api_init', [ __CLASS__, 'register_coral_token_endpoint' ] );

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

	/**
	 * Register a REST API endpoint to return and sign JWT for Coral
	 * 
	 * @return void
	 */
	public static function register_coral_token_endpoint() {
		register_rest_route(
			'america-magazine',
			'/coral-token/',
			array(
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'handle_coral_token_request' ],
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handle a Coral token request by validating the JWT and signing a new one
	 * 
	 * @param array $request The request sent to the endpoint.
	 * 
	 * @return array
	 */
	public static function handle_coral_token_request( $request ) {

		$piano_key = new JWT_Key( base64_decode( get_option( 'coral_piano_jwt_validation_key' ) ), 'HS256' );
		$coral_key = get_option( 'coral_jwt_signing_key' );

		$piano_token = (string) $request['piano-jwt'];
		
		try {
			$piano_token_data = (array) JWT::decode( $piano_token, $piano_key );
		} catch ( SignatureInvalidException $e ) {
			// TODO return REST API error response
			$piano_token_data = null;
		}

		$coral_role = 'COMMENTER';
		$coral_badges = [];

		$piano_user = self::get_piano_user( $piano_token_data['sub'] );
		$commenting_roles_field = array_find( 
			$piano_user->custom_fields, 
			function ( $item ) {
				// WP coding standards don't allow camelCase, but that is how Piano returns the object
				$field_name = 'fieldName';
				return $item->$field_name === 'commentingRoles';
			}
		);
		if ( ! empty( $commenting_roles_field ) ) {
			$commenting_roles = json_decode( $commenting_roles_field->value );
			if ( ! empty( $commenting_roles ) ) {
				if ( in_array( 'Admin', $commenting_roles ) ) {
					$coral_role = 'ADMIN';
				} elseif ( in_array( 'Moderator', $commenting_roles ) ) {
					$coral_role = 'MODERATOR';
				} elseif ( in_array( 'Staff', $commenting_roles ) ) {
					$coral_role = 'STAFF';
				}
			}
		}

		$subscriber_mode = get_option( 'coral_subscriber_mode' );
		$rid_for_subscribers = get_option( 'coral_subscriber_resource_id' );

		if ( ( 'badge-subscribers' === $subscriber_mode || 'subscribers-only' === $subscriber_mode ) && ! empty( $rid_for_subscribers ) ) {

			$subscriber_access = self::get_resource_access( $piano_user->uid, $rid_for_subscribers );
		
			if ( 'badge-subscribers' === $subscriber_mode && 'COMMENTER' === $coral_role && ! empty( $subscriber_access ) ) {
				$coral_badges[] = 'subscriber';
			} elseif ( 'subscribers-only' === $subscriber_mode && empty( $subscriber_access ) ) {
				// If comment access is restricted to subscribers and user does not have subscriber access, do not return a token
				return rest_ensure_response( null );
			}
		}

		$coral_token_data = [
			'jti'  => wp_generate_uuid4(),
			'exp'  => $piano_token_data['exp'],
			'iat'  => time(),
			'user' => [
				'id'       => $piano_user->uid,
				'email'    => $piano_user->email,
				'username' => $piano_user->first_name . ' ' . $piano_user->last_name,
				'role'     => $coral_role,
				'badges'   => $coral_badges,
			],
		];

		$coral_token = JWT::encode( $coral_token_data, $coral_key, 'HS256' );
		
		return rest_ensure_response( $coral_token );
	}

	/**
	 * Make a Piano API call with a given path and params
	 * 
	 * @param string $path The path for the API method.
	 * @param array  $params An array of params to pass in query string to the API.
	 *
	 * @return object
	 */
	public static function make_piano_api_call( $path, $params ) {
		// We need the Piano plugin classes in order to be able to call the API easily
		require_once WP_PLUGIN_DIR . '/piano/autoload.php';

		$piano = \WP_Piano::piano();
		$base_url = $piano->settings()->get_endpoint() . '/api/v3';
		$base_params = [
			'aid'       => $piano->settings( \Piano\Settings::AID ),
			'api_token' => $piano->settings( \Piano\Settings::API_TOKEN ),
		];

		$request = wp_remote_request(
			$base_url . $path . '?' . http_build_query( array_merge( $base_params, $params ) ),
			[ 'method' => 'GET' ]
		);

		$response = wp_remote_retrieve_body( $request );

		// TODO handle reposible response errors

		$json = json_decode( $response );

		return $json;
	}
	
	/**
	 * Get a Piano user given a user ID by calling the Piano API
	 * 
	 * @param string $uid The Piano user ID.
	 * 
	 * @return object|null 
	 */
	public static function get_piano_user( $uid ) {
		$api_response = self::make_piano_api_call( '/publisher/user/get', [ 'uid' => $uid ] );
		if ( 0 !== $api_response->code ) {
			return null;
		} else {
			return $api_response->user;
		}
	}

	/**
	 * Get a Piano user's access to a resource by calling the Piano API
	 * 
	 * @param string $uid The Piano user ID.
	 * @param string $rid The Piano resource ID.
	 * 
	 * @return object|null
	 */
	public static function get_resource_access( $uid, $rid ) {
		$api_response = self::make_piano_api_call(
			'/publisher/user/access/check',
			[
				'uid' => $uid,
				'rid' => $rid,
			] 
		);
		if ( 0 !== $api_response->code || ! property_exists( $api_response, 'access' ) ) {
			return null;
		} else {
			return $api_response->access;
		}
	}
}
