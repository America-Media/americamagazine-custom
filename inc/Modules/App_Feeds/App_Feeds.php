<?php
/**
 * App Feeds.
 *
 * @package AmericaMagazine
 */

namespace AmericaMagazine\Modules\App_Feeds;

use WP_Query;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Class to add a notice to the admin panel
 */
class App_Feeds {

	/**
	 * Initialize the class
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_app_feeds_endpoints' ] );
	}

	/**
	 * Register the REST API endpoints
	 * 
	 * @return void
	 */
	public static function register_app_feeds_endpoints() {
		register_rest_route(
			'america-magazine/v1',
			'/app-all-content/',
			array(
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'handle_app_all_content_request' ],
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handle the app-all-content endpoint
	 * 
	 * @param WP_REST_Request $request The request sent to the endpoint.
	 * 
	 * @return array
	 */
	public static function handle_app_all_content_request( $request ) {

		$post_ids         = self::ids_from_query_param( $request['id'] );
		$topic_ids        = self::ids_from_query_param( $request['topic'] );
		$author_ids       = self::ids_from_query_param( $request['author'] );
		$content_type_ids = self::ids_from_query_param( $request['content_type'] );
		$page             = max( 1, absint( $request['page'] ) );
		$per_page         = 10; // default to 10 and may implement as a real parameter in future
		
		$args = [
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
		];

		if ( ! empty( $post_ids ) ) {
			$args['post__in'] = $post_ids;
		}
		if ( ! empty( $topic_ids ) ) {
			$args['tag__in'] = $topic_ids;
		}
		if ( ! empty( $author_ids ) ) {
			$args['author__in'] = $author_ids;
		}
		if ( ! empty( $content_type_ids ) ) {
			$args['cat__in'] = $content_type_ids;
		}

		$query = new WP_Query( $args );
		$posts = array_map( [ __CLASS__, 'format_post_data' ], $query->posts );

		$response = $posts;
		if ( $request['debug'] ) {
			$response = [
				'id'           => $post_ids,
				'topic'        => $topic_ids,
				'author'       => $author_ids,
				'content_type' => $content_type_ids,
				'total'        => (int) $query->found_posts,
				'total_pages'  => (int) $query->max_num_pages,
				'current_page' => $page,
				'per_page'     => $per_page,
				'posts'        => $posts,
			];
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Return an array of IDs from a query parameter
	 * 
	 * @param string $query_param The query parameter to be processed.
	 * 
	 * @return array
	 */
	public static function ids_from_query_param( $query_param ) {
		if ( ! empty( $query_param ) ) {
			return preg_split( '/[^0-9]/', $query_param );
		}
		return [];
	}

	/**
	 * Format post data as expected by the app
	 * 
	 * @param WP_Post $post The post to be formatted.
	 * 
	 * @return array
	 */
	public static function format_post_data( $post ) {

		$categories = get_the_category( $post->ID );
		$channel_names = [ 'Politics & Society', 'Faith', 'Arts & Culture', 'Magazine', 'Community' ];
		$content_type = $categories[0];
		if ( count( $categories ) > 1 && in_array( $categories[0]->name, $channel_names, true ) ) {
			$content_type = $categories[1];
		}

		return [
			'title'        => get_the_title( $post ),
			'id'           => $post->ID,
			// format_terms_list expects & returns an array, but the app expects a single object for content_type
			'content_type' => self::format_terms_list( [ $content_type ] )[0],
			'topics'       => self::format_terms_list( get_the_tags( $post->ID ) ),
			'created'      => get_the_date( 'D, m/d/Y - G:i', $post ),
			'disable_ads'  => (bool) get_post_meta( $post->ID, 'newspack_ads_suppress_ads', true ),
			'body'         => apply_filters( 'the_content', $post->post_content ),
			// video_embed field not present in WP
			'author_name'  => get_the_author_meta( 'display_name', $post->post_author ),
			'author_id'    => $post->post_author,
			'url'          => get_permalink( $post ),
			'image'        => get_the_post_thumbnail_url( $post ),
			'image_credit' => get_post_meta( $post->ID, 'image_caption', true ),
		];
	}

	/**
	 * Format a terms list to focus on id and name
	 * 
	 * @param array $list Array of WP_Terms to be formatted.
	 * 
	 * @return array
	 */
	public static function format_terms_list( $list ) { 
		if ( $list ) {
			return array_map( 
				function( $item ) {
					return [
						'id'   => $item->term_id,
						'name' => $item->name,
					];
				},
				$list 
			);
		} else {
			return [];
		}
	}
}
