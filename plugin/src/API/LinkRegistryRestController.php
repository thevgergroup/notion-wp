<?php
/**
 * Link Registry REST API Controller
 *
 * Provides REST endpoints for fetching link registry data.
 * Used by the Gutenberg block editor to show current link titles/slugs.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\API;

use NotionSync\Router\LinkRegistry;
use WP_REST_Controller;
use WP_REST_Server;
use WP_Error;

/**
 * Class LinkRegistryRestController
 *
 * REST API endpoints for link registry queries.
 *
 * @since 1.0.0
 */
class LinkRegistryRestController extends WP_REST_Controller {

	/**
	 * Namespace for REST routes
	 *
	 * @var string
	 */
	protected $namespace = 'notion-sync/v1';

	/**
	 * Resource base
	 *
	 * @var string
	 */
	protected $rest_base = 'links';

	/**
	 * Link registry instance.
	 *
	 * @var LinkRegistry
	 */
	private LinkRegistry $registry;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->registry = new LinkRegistry();
	}

	/**
	 * Register REST routes.
	 *
	 * @since 1.0.0
	 */
	public function register_routes(): void {
		// GET /wp-json/notion-sync/v1/links/{notion_id}
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<notion_id>[a-f0-9-]{32,36})',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_link' ),
					'permission_callback' => array( $this, 'get_link_permissions_check' ),
					'args'                => array(
						'notion_id' => array(
							'description' => 'Notion page/database ID (32 or 36 chars with dashes)',
							'type'        => 'string',
							'required'    => true,
							'pattern'     => '^[a-f0-9-]{32,36}$',
						),
					),
				),
			)
		);
	}

	/**
	 * Get a single link by Notion ID.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return array|WP_Error Link data or error.
	 */
	public function get_link( $request ) {
		$notion_id = $request->get_param( 'notion_id' );

		// Find in registry.
		$entry = $this->registry->find_by_notion_id( $notion_id );

		if ( ! $entry ) {
			return new WP_Error(
				'notion_link_not_found',
				'Link not found in registry',
				array( 'status' => 404 )
			);
		}

		// Build response.
		return $this->prepare_link_for_response( $entry );
	}

	/**
	 * Check permissions for getting a link.
	 *
	 * Currently allows anyone to read (for frontend rendering).
	 * Could be restricted to edit_posts capability if needed.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool True if user can view link.
	 */
	public function get_link_permissions_check( $request ): bool {
		// Allow anyone to read link data.
		// This is safe because links are public on frontend anyway.
		return true;
	}

	/**
	 * Prepare link data for REST response.
	 *
	 * @since 1.0.0
	 *
	 * @param object $entry Registry entry from database.
	 * @return array Formatted link data.
	 */
	private function prepare_link_for_response( object $entry ): array {
		// Determine URL.
		if ( 'synced' === $entry->sync_status && $entry->wp_post_id ) {
			$url = get_permalink( $entry->wp_post_id );
		} else {
			$url = home_url( '/notion/' . $entry->slug );
		}

		return array(
			'notion_id'    => $entry->notion_id,
			'title'        => $entry->notion_title,
			'type'         => $entry->notion_type,
			'slug'         => $entry->slug,
			'url'          => $url,
			'sync_status'  => $entry->sync_status,
			'wp_post_id'   => $entry->wp_post_id,
			'wp_post_type' => $entry->wp_post_type ?? null,
		);
	}
}
