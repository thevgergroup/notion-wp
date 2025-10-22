<?php
/**
 * Notion URL Router
 *
 * Handles /notion/{slug} requests intelligently - serving WordPress content
 * when available, redirecting to Notion when not synced.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Router;

/**
 * Class NotionRouter
 *
 * Registers WordPress rewrite rules for /notion/{slug} URLs and handles
 * routing logic based on link registry entries.
 *
 * @since 1.0.0
 */
class NotionRouter {

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
	 *
	 * @param LinkRegistry $registry Link registry instance.
	 */
	public function __construct( LinkRegistry $registry ) {
		$this->registry = $registry;
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @since 1.0.0
	 */
	public function register(): void {
		// Register rewrite rules immediately since this is called during init.
		$this->register_rewrite_rules();

		// Register hooks for routing and query vars.
		add_action( 'wp', array( $this, 'route_request' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
	}

	/**
	 * Register rewrite rules for /notion/{slug} URLs.
	 *
	 * Creates a custom rewrite rule that maps /notion/{slug} to a query var
	 * that can be checked in route_request().
	 *
	 * @since 1.0.0
	 */
	public function register_rewrite_rules(): void {
		add_rewrite_rule(
			'^notion/([^/]+)/?$',
			'index.php?notion_link=$matches[1]',
			'top'
		);

		add_rewrite_tag( '%notion_link%', '([^&]+)' );
	}

	/**
	 * Add custom query vars.
	 *
	 * Registers the 'notion_link' query var so WordPress recognizes it.
	 *
	 * @since 1.0.0
	 *
	 * @param array $vars Existing query vars.
	 * @return array Modified query vars.
	 */
	public function add_query_vars( array $vars ): array {
		$vars[] = 'notion_link';
		return $vars;
	}

	/**
	 * Handle /notion/{slug} requests.
	 *
	 * Checks if the current request is for a /notion/{slug} URL and handles
	 * routing based on the link registry entry.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP $wp WordPress environment object.
	 */
	public function route_request( \WP $wp ): void {
		if ( ! isset( $wp->query_vars['notion_link'] ) ) {
			return;
		}

		$slug = sanitize_title( $wp->query_vars['notion_link'] );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
		error_log( sprintf( '[NotionRouter] Routing request for slug: %s', $slug ) );

		// Look up in registry.
		$link_entry = $this->registry->find_by_slug( $slug );

		if ( ! $link_entry ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
			error_log( sprintf( '[NotionRouter] Slug not found in registry: %s', $slug ) );
			// Slug not found - 404.
			status_header( 404 );
			include get_404_template();
			exit;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, Generic.Files.LineLength.MaxExceeded -- Debug logging.
		error_log( sprintf( '[NotionRouter] Found entry - sync_status: %s, wp_post_id: %s', $link_entry->sync_status, $link_entry->wp_post_id ?? 'null' ) );

		// Handle based on sync status.
		if ( 'synced' === $link_entry->sync_status && $link_entry->wp_post_id ) {
			// Synced to WordPress - serve WordPress content.
			$this->serve_wordpress_content( $link_entry );
		} else {
			// Not synced - redirect to Notion.
			$this->redirect_to_notion( $link_entry );
		}
	}

	/**
	 * Serve WordPress content.
	 *
	 * Redirects to the appropriate WordPress URL based on resource type.
	 * - Pages/posts/databases: Redirect to permalink
	 * - If logged in and database: optionally could redirect to admin viewer
	 *
	 * @since 1.0.0
	 *
	 * @param object $link_entry Link registry entry.
	 */
	private function serve_wordpress_content( object $link_entry ): void {
		// Track access.
		$this->registry->increment_access_count( $link_entry->id );

		// For all synced content (pages, posts, databases), redirect to permalink.
		$permalink = get_permalink( $link_entry->wp_post_id );

		if ( $permalink ) {
			wp_safe_redirect( $permalink, 302 );
		} else {
			// Post deleted or unavailable - fall back to Notion.
			$this->redirect_to_notion( $link_entry );
		}

		exit;
	}

	/**
	 * Redirect to Notion.
	 *
	 * Redirects to the original Notion URL for resources that haven't been
	 * synced to WordPress yet.
	 *
	 * @since 1.0.0
	 *
	 * @param object $link_entry Link registry entry.
	 */
	private function redirect_to_notion( object $link_entry ): void {
		// Track access.
		$this->registry->increment_access_count( $link_entry->id );

		// Redirect to Notion URL.
		// Use wp_redirect() instead of wp_safe_redirect() because notion.so is external.
		$notion_url = 'https://notion.so/' . $link_entry->notion_id;
		wp_redirect( $notion_url, 302 );
		exit;
	}
}
