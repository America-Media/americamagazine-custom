<?php
/**
 * App Feeds.
 *
 * @package AmericaMagazine
 */

namespace AmericaMagazine\Modules\App_Feeds;

use WP_Query;
use WP_User_Query;
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
		// App All Content
		register_rest_route(
			'america-magazine/v1',
			'/app-all-content/',
			array(
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'handle_app_all_content_request' ],
				'permission_callback' => '__return_true',
			)
		);

		// App Authors Feed
		register_rest_route(
			'america-magazine/v1',
			'/app-authors-feed/',
			array(
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'handle_app_authors_feed_request' ],
				'permission_callback' => '__return_true',
			)
		);

		// App Topics Feed
		register_rest_route(
			'america-magazine/v1',
			'/app-topics-feed/',
			array(
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'handle_app_topics_feed_request' ],
				'permission_callback' => '__return_true',
			)
		);

		// App Search
		register_rest_route(
			'america-magazine/v1',
			'/app-search',
			array(
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'handle_app_search_request' ],
				'args'                => [
					'search' => [
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
				'permission_callback' => '__return_true',
			)
		);

		// App Author Search
		register_rest_route(
			'america-magazine/v1',
			'/app-search-authors',
			array(
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'handle_app_search_authors_request' ],
				'args'                => [
					'author' => [
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
				'permission_callback' => '__return_true',
			)
		);

		// App Author Search Literal (stub to return an empty array for backwards compatibility with Drupal)
		register_rest_route(
			'america-magazine/v1',
			'/app-search-authors-literal',
			array(
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'handle_app_search_authors_literal_request' ],
				'permission_callback' => '__return_true',
			)
		);

		// Word app feed for lectionary days, with date as a path parameter
		register_rest_route(
			'america-magazine/v1',
			'/word-app/(?P<date>\d{4}-\d{2}-\d{2})',
			array(
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'handle_word_app_request' ],
				'permission_callback' => '__return_true',
			)
		);

		// America Today app "homepage"
		register_rest_route( 
			'america-magazine/v1',
			'/app-america-today',
			array(
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'handle_america_today_request' ],
				'permission_callback' => '__return_true',
			)
		);

		// America Today app "homepage"
		register_rest_route( 
			'america-magazine/v1',
			'/app-reels',
			array(
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'handle_reels_request' ],
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
		$channel_ids      = self::ids_from_query_param( $request['channel'] );
		$author_ids       = self::ids_from_query_param( $request['author'] );
		$content_type_ids = self::ids_from_query_param( $request['content_type'] );
		// App expects 0-based paging; WP_Query starts paging at 1
		$page             = max( 1, absint( $request['page'] ) + 1 ); 
		$per_page         = 10; // default to 10 and may implement as a real parameter in future
		
		// App had hard-coded request for topic=1130 for the Vatican feed, needs to be 446 in WordPress
		if ( 1 === count( $topic_ids ) && 1130 === intval( $topic_ids[0] ) ) {
			$topic_ids = [ 446 ];
		}
		
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
			// Translate author IDs to terms in the author taxonomy to handle co-authors
			$author_term_names = array_map( 
				function( $user_id ) {
					return get_userdata( $user_id )->user_login;
				},
				$author_ids
			);
			$args['tax_query'] = [
				[
					'taxonomy' => 'author',
					'field'    => 'name',
					'terms'    => $author_term_names,
				],
			];
		}
		// In Drupal, content_type meant section; both section and channel are categories in WordPress
		if ( ! empty( $content_type_ids ) || ! empty( $channel_ids ) ) {
			$args['category__in'] = array_merge( $content_type_ids, $channel_ids );
		}

		$query = new WP_Query( $args );
		$posts = array_map( [ __CLASS__, 'format_post_data' ], $query->posts );

		$response = $posts;
		if ( $request['debug'] ) {
			$response = [
				'id'            => $post_ids,
				'topic'         => $topic_ids,
				'author'        => $author_ids,
				'content_type'  => $content_type_ids,
				'wp_query_args' => $args,
				'total'         => (int) $query->found_posts,
				'total_pages'   => (int) $query->max_num_pages,
				'current_page'  => $page,
				'per_page'      => $per_page,
				'posts'         => $posts,
			];
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Handle the app-authors-feed endpoint
	 * 
	 * @param WP_REST_Request $request The request sent to the endpoint.
	 * 
	 * @return array
	 */
	public static function handle_app_authors_feed_request( $request ) {

		$args = [
			'number'              => 10,
			'has_published_posts' => true,
			'fields'              => [ 'id', 'user_nicename', 'display_name' ],
			'orderby'             => [ 'post_count' => 'DESC' ],
		];

		$author_ids = self::ids_from_query_param( $request['id'] );
		// App expects 0-based paging; WP_Query starts paging at 1
		$page_requested = $request['page'] + 1;

		if ( ! empty( $author_ids ) ) {
			$args['include'] = $author_ids;
		}
		if ( ! empty( $page_requested ) ) {
			$args['paged'] = $page_requested;
		}

		$query = new WP_User_Query( $args );
		$users = array_map( [ __CLASS__, 'format_user_data_extended' ], $query->results );

		return rest_ensure_response( $users );
	}

	/**
	 * Handle the app-all-content endpoint
	 * 
	 * @param WP_REST_Request $request The request sent to the endpoint.
	 * 
	 * @return array
	 */
	public static function handle_app_topics_feed_request( $request ) {
		return rest_ensure_response( self::format_terms_list( get_tags() ) );
	}

	/**
	 * Format search results data as expected by the app
	 *
	 * @param array $request The request sent to the endpoint.
	 *
	 * @return array
	 */
	public static function handle_app_search_request( $request ) {
		$search_query = $request->get_param( 'search' );

		$query = new WP_Query(
			[
				's'              => $search_query,
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'fields'         => 'ids', // Only return IDs
				'posts_per_page' => 10,
			] 
		);

		if ( empty( $query->posts ) ) {
			return rest_ensure_response( [] );
		}

		$results = array_map(
			function ( $id ) {
				return [ 'id' => $id ];
			},
			$query->posts 
		);

		return rest_ensure_response( $results );
	}

	/**
	 * Format author search results data as expected by the app
	 *
	 * @param array $request The request sent to the endpoint.
	 *
	 * @return array
	 */
	public static function handle_app_search_authors_request( $request ) {
		$author_query = $request->get_param( 'author' );

		$query = new WP_User_Query(
			[
				'number'              => 50,
				'has_published_posts' => true,
				'fields'              => [ 'id', 'user_nicename', 'display_name' ],
				'orderby'             => [ 'post_count' => 'DESC' ],
				'meta_query'          => [
					'relation' => 'OR',
					[
						'key'     => 'nickname',
						'value'   => $author_query,
						'compare' => 'LIKE',
					],
					[
						'key'     => 'first_name',
						'value'   => $author_query,
						'compare' => 'LIKE',
					],
					[
						'key'     => 'last_name',
						'value'   => $author_query,
						'compare' => 'LIKE',
					],
				],
			] 
		);

		$users = array_map(
			[ __CLASS__, 'format_user_data' ], 
			array_filter(
				$query->results,
				function( $i ) {
					// We only want users that have real-world display names, indicated by a space somewhere in them
					return strpos( $i->display_name, ' ' );
				} 
			) 
		);

		return rest_ensure_response( array_values( $users ) );
	}

	/**
	 * Stub for the search authors literal endpoint for backwards compatibility to drupal
	 *
	 * @param array $request The request sent to the endpoint.
	 *
	 * @return array
	 */
	public static function handle_app_search_authors_literal_request( $request ) {
		return rest_ensure_response( [] );
	}

	/**
	 * Handle request for a specfic lectionary day for Word app functionality
	 *
	 * @param array $request The request sent to the endpoint.
	 *
	 * @return array
	 */
	public static function handle_word_app_request( $request ) {
		$lectionary_date_query = new WP_Query(
			[
				'post_type'   => 'lectionary_date',
				'post_status' => 'publish',
				'meta_query'  => [
					// Due to Drupal migration, the calendar date field may or may not have hyphens
					// so we query for both versions
					'relation' => 'OR',
					[
						'key'   => 'calendar_date',
						'value' => $request['date'],
					],
					[
						'key'   => 'calendar_date',
						'value' => str_replace( '-', '', $request['date'] ),
					],
				],
			] 
		);

		$response = [];

		foreach ( $lectionary_date_query->posts as $post ) {
			$response[] = [
				'title'                                 => 
					$post->post_title,
				'field_calendar_date'                   => 
					$request['date'],
				'field_word_app_related_content_export' => 
					self::get_lectionary_date_related_content( $post->ID ),
			];
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Return content of related posts for a lectionary date request
	 *
	 * @param int $post_id The post ID for which to return related content.
	 *
	 * @return array
	 */
	public static function get_lectionary_date_related_content( $post_id ) {
		$related_content_ids = get_field( 'word_app_related_content', $post_id );
		// Due to Drupal migration, the word_app_related_content meta can be either a string, int, or array
		// We need an array of ints, so we turn it into that.
		if ( is_array( $related_content_ids ) ) {
			$related_content_ids = array_unique( $related_content_ids );
		} else {
			$related_content_ids = [ $related_content_ids ];
		}
		$related_content_ids = array_map( 'intval', $related_content_ids );

		$related_content_query = new WP_Query(
			[
				'post__in' => $related_content_ids,
			] 
		);

		$related_content = array_map(
			function( $related_post ) {
				return [
					'id'        => $related_post->ID,
					'title'     => $related_post->post_title,
					'url'       => get_permalink( $related_post ),
					'body'      => $related_post->post_content,
					'by_author' => array_map( 
						function( $author_data ) {
							return [
								'id'      => $author_data->ID,
								'title'   => $author_data->display_name,
								'uid'     => null,
								'created' => gmdate( 'D, d/m/Y - H:i', strtotime( $author_data->user_registered ) ),                         
							];
						},
						wp_list_pluck( get_coauthors( $related_post->ID ), 'data' ) 
					),
					'image'     => get_the_post_thumbnail_url( $related_post ),
					'section'   => self::format_terms_list( get_the_category( $related_post->ID ) )[0],
					'uid'       => null,
					'created'   => gmdate( 'D, d/m/Y - H:i', get_post_timestamp( $related_post ) ),
				];
			},
			$related_content_query->posts
		);

		return $related_content;
	} 

	/**
	 * Handle request for America Today
	 *
	 * @param array $request The request sent to the endpoint.
	 *
	 * @return array
	 */
	public static function handle_america_today_request( $request ) {
		if ( ! function_exists( 'get_field' ) ) {
			return new WP_Error( 'scf_missing', 'SCF not active', [ 'status' => 500 ] );
		}

		// Old Drupal format for top article had ID as a string
		$top_article = strval( get_field( 'top_story', 'options' )->ID );

		// Old Drupal format for secondary articles had an array of IDs as strings
		$secondary_articles = array_map( 
			'strval',
			self::ids_from_scf_options_field(
				'secondary_articles',
				'secondary_article'
			) 
		);

		// Old Drupal format for tertiary articles had an array of integer IDs
		$tertiary_articles = self::ids_from_scf_options_field( 
			'tertiary_articles',
			'tertiary_article'
		);

		// Old Drupal format for curated topics & authors had IDs as a comma separated string
		$curated_topics = implode(
			', ',
			self::ids_from_scf_options_field(
				'curated_topics',
				'topic'
			)
		);
		$curated_authors = implode(
			', ',
			self::ids_from_scf_options_field(
				'curated_authors',
				'author'
			)
		);

		$response = [
			'top_article'        => $top_article,
			'secondary_articles' => $secondary_articles,
			'tertiary_articles'  => $tertiary_articles,
			'curated_topics'     => $curated_topics,
			'curated_authors'    => $curated_authors,
			'donation_headline'  => get_field( 'donation_headline', 'options' ),
			'donation_message'   => get_field( 'donation_message', 'options' ),
			'categories'         => array_map( 
				function( $cat ) {
					return [
						'app_image'     => $cat['app_image'],
						'app_label'     => $cat['app_label'],
						'taxonomy'      => 'Channel' === $cat['taxonomy_label'] ? 'channel' : 'topics',
						// Old Drupal format had taxonomy_term with ID as string
						'taxonomy_term' => strval( 'Channel' === $cat['taxonomy_label'] ? $cat['taxonomy_term_category'] : $cat['taxonomy_term_tags'] ),
					];
				},
				get_field( 'app_category', 'options' )
			),
		];

		// Old Drupal format had the whole response wrapped in an array
		return rest_ensure_response( [ $response ] );
	}

	/**
	 * Handle request for reels
	 *
	 * @param array $request The request sent to the endpoint.
	 *
	 * @return array
	 */
	public static function handle_reels_request( $request ) {
		$reels_query = new WP_Query(
			[
				'post_type'      => 'app-reels-feature',
				'post_status'    => 'publish',
				'posts_per_page' => 10,
			]
		);

		$response = array_map(
			function( $reel ) {
				return [
					'title'          => get_the_title( $reel ),
					'blurb'          => get_the_excerpt( $reel ),
					'content_link'   => [
						'url'  => get_field( 'content_url', $reel ),
						'text' => get_field( 'link_text', $reel ),
					],
					'vertical_image' => get_the_post_thumbnail_url( $reel ),
					'vertical_video' => get_field( 'vertical_video', $reel ),
				];
			},
			$reels_query->posts
		);

		return rest_ensure_response( $response );
	}

	/**
	 * Return an array of IDs from a SCF options field
	 * 
	 * @param string $field_name The name of the options field.
	 * @param string $field_key The key of the ID field within the returned field data.
	 * 
	 * @return array
	 */
	public static function ids_from_scf_options_field( $field_name, $field_key ) {
		$ids = wp_list_pluck(
			get_field( $field_name, 'options' ),
			$field_key
		);

		// Filter the array to remove any empty values (what array_filter does without a callback)
		return array_filter( $ids );
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
			if ( is_array( $query_param ) ) {
				// If the param is an array, split the individual elements and flatten the resulting array
				return array_merge( ...array_map( [ __CLASS__, 'ids_from_query_param' ], $query_param ) );
			} else {
				return preg_split( '/[^0-9]/', $query_param );
			}       
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
		$authors = wp_list_pluck( get_coauthors( $post->ID ), 'data' );

		return [
			'title'        => get_the_title( $post ),
			'id'           => strval( $post->ID ), // Old Drupal produced this ID as a string
			// format_terms_list expects & returns an array, but the app expects a single object for content_type
			'content_type' => self::format_terms_list( [ $content_type ] )[0],
			'topics'       => self::format_terms_list( get_the_tags( $post->ID ) ),
			'created'      => get_the_date( 'D, m/d/Y - G:i', $post ),
			'disable_ads'  => (bool) get_post_meta( $post->ID, 'newspack_ads_suppress_ads', true ),
			'body'         => apply_filters( 'the_content', $post->post_content ),
			// video_embed field not present in WP
			'author_name'  => wp_list_pluck( $authors, 'display_name' ),
			'author_id'    => implode( ', ', wp_list_pluck( $authors, 'ID' ) ),
			'url'          => get_permalink( $post ),
			'image'        => get_the_post_thumbnail_url( $post ),
			'image_credit' => wp_filter_nohtml_kses( get_post_meta( $post->ID, 'image_caption', true ) ),
		];
	}

	/**
	 * Format user data as expected by the app
	 * 
	 * @param WP_User $user The user to be formatted.
	 * @param bool    $extended True to add bio, image, and url; false for just name and id.
	 * 
	 * @return array
	 */
	public static function format_user_data_extended( $user, $extended = true ) {
		
		$formatted_user = [
			'name' => $user->display_name,
			'id'   => $user->ID,
		];

		if ( $extended ) {
			$formatted_user['bio']   = get_user_meta( $user->ID, 'description' )[0];
			$formatted_user['image'] = get_avatar_url( $user->ID );
			$formatted_user['url']   = get_site_url() . '/author/' . $user->user_nicename;
		}
		
		return $formatted_user;
	}

	/**
	 * Format user data as expected by the app with just name and ID
	 * 
	 * @param WP_User $user The user to be formatted.
	 * 
	 * @return array
	 */
	public static function format_user_data( $user ) {
		return self::format_user_data_extended( $user, false );
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
