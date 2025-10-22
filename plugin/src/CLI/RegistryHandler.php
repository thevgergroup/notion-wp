<?php
/**
 * WP-CLI Registry Handler
 *
 * Handles link registry and link testing operations.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\CLI;

use NotionSync\Router\LinkRegistry;
use NotionSync\Blocks\LinkRewriter;

/**
 * Class RegistryHandler
 *
 * Handles link registry and testing operations.
 *
 * @since 1.0.0
 */
class RegistryHandler {

	/**
	 * Show Notion links found in a WordPress post.
	 *
	 * @param int $post_id WordPress post ID.
	 */
	public static function show_post_links( int $post_id ): void {
		$post = get_post( $post_id );

		if ( ! $post ) {
			\WP_CLI::error( "Post {$post_id} not found." );
		}

		\WP_CLI::log( \WP_CLI::colorize( '%GPost Information:%n' ) );
		\WP_CLI::log( '  ID:      ' . $post->ID );
		\WP_CLI::log( '  Title:   ' . get_the_title( $post ) );
		\WP_CLI::log( '  Type:    ' . $post->post_type );
		\WP_CLI::log( '' );

		// Extract links from post content.
		$content = $post->post_content;
		preg_match_all( '/<a[^>]+href=["\'](.*?)["\']/', $content, $matches );

		$links = $matches[1] ?? array();

		if ( empty( $links ) ) {
			\WP_CLI::log( 'No links found in post content.' );
			return;
		}

		\WP_CLI::log( \WP_CLI::colorize( "%GLinks Found (%d):%n\n", count( $links ) ) );

		$link_registry      = new LinkRegistry();
		$notion_links_found = false;

		foreach ( $links as $url ) {
			// Check if this is a Notion link.
			$result = LinkRewriter::rewrite_url( $url );

			if ( $result['notion_page_id'] ) {
				$notion_links_found = true;

				// Look up in registry.
				$registry_entry = $link_registry->find_by_notion_id( $result['notion_page_id'] );

				\WP_CLI::log( '  ' . \WP_CLI::colorize( '%YNotion Link:%n' ) );
				\WP_CLI::log( '    Original URL:    ' . $url );
				\WP_CLI::log( '    Notion ID:       ' . $result['notion_page_id'] );
				\WP_CLI::log( '    Rewritten URL:   ' . $result['url'] );

				if ( $registry_entry ) {
					\WP_CLI::log( '    Registry Status: ' . \WP_CLI::colorize( '%GRegistered%n' ) );
					\WP_CLI::log( '    Slug:            ' . $registry_entry->slug );
					\WP_CLI::log( '    Sync Status:     ' . $registry_entry->sync_status );
					if ( 'synced' === $registry_entry->sync_status && $registry_entry->wp_post_id ) {
						\WP_CLI::log( '    WP Post ID:      ' . $registry_entry->wp_post_id );
						\WP_CLI::log( '    WP URL:          ' . get_permalink( $registry_entry->wp_post_id ) );
					}
				} else {
					\WP_CLI::log( '    Registry Status: ' . \WP_CLI::colorize( '%RNot Registered%n' ) );
				}
				\WP_CLI::log( '' );
			}
		}

		if ( ! $notion_links_found ) {
			\WP_CLI::log( '  No Notion internal links found in post content.' );
		}
	}

	/**
	 * View link registry entries.
	 *
	 * @param string|null $notion_id   Optional Notion ID filter.
	 * @param string|null $sync_status Optional sync status filter.
	 * @param string      $format      Output format.
	 */
	public static function show_registry( ?string $notion_id, ?string $sync_status, string $format ): void {
		global $wpdb;
		$table_name = $wpdb->prefix . 'notion_links';

		// Build query.
		$where_clauses = array();
		$where_values  = array();

		if ( $notion_id ) {
			$where_clauses[]      = '(notion_id = %s OR notion_id_uuid = %s)';
			$notion_id_normalized = str_replace( '-', '', $notion_id );
			// Format as UUID.
			$notion_id_uuid = substr( $notion_id_normalized, 0, 8 ) . '-' .
								substr( $notion_id_normalized, 8, 4 ) . '-' .
								substr( $notion_id_normalized, 12, 4 ) . '-' .
								substr( $notion_id_normalized, 16, 4 ) . '-' .
								substr( $notion_id_normalized, 20, 12 );
			$where_values[]       = $notion_id_normalized;
			$where_values[]       = $notion_id_uuid;
		}

		if ( $sync_status ) {
			$where_clauses[] = 'sync_status = %s';
			$where_values[]  = $sync_status;
		}

		$where_sql = '';
		if ( ! empty( $where_clauses ) ) {
			$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
		}

		$query = "SELECT * FROM {$table_name} {$where_sql} ORDER BY updated_at DESC";

		if ( ! empty( $where_values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is built with proper placeholders.
			$query = $wpdb->prepare( $query, $where_values );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above or has no parameters.
		$results = $wpdb->get_results( $query );

		if ( empty( $results ) ) {
			\WP_CLI::warning( 'No registry entries found.' );
			return;
		}

		// Format for display.
		$items = array();
		foreach ( $results as $entry ) {
			$items[] = array(
				'Notion ID'  => substr( $entry->notion_id, 0, 12 ) . '...',
				'Title'      => $entry->notion_title,
				'Type'       => $entry->notion_type,
				'Slug'       => $entry->slug,
				'Status'     => $entry->sync_status,
				'WP Post ID' => $entry->wp_post_id ?? 'N/A',
				'Accessed'   => $entry->access_count,
			);
		}

		\WP_CLI\Utils\format_items( $format, $items, array( 'Notion ID', 'Title', 'Type', 'Slug', 'Status', 'WP Post ID', 'Accessed' ) );

		\WP_CLI::log( sprintf( "\nTotal entries: %d", count( $results ) ) );
	}

	/**
	 * Test link rewriting for a URL.
	 *
	 * @param string $url URL to test.
	 */
	public static function test_link( string $url ): void {
		\WP_CLI::log( \WP_CLI::colorize( '%GLink Rewriting Test%n' ) );
		\WP_CLI::log( '  Original URL:    ' . $url );

		$result = LinkRewriter::rewrite_url( $url );

		if ( $result['notion_page_id'] ) {
			\WP_CLI::log( '  Detection:       ' . \WP_CLI::colorize( '%GNotion Internal Link%n' ) );
			\WP_CLI::log( '  Notion ID:       ' . $result['notion_page_id'] );
			\WP_CLI::log( '  Rewritten URL:   ' . $result['url'] );

			// Check registry.
			$registry = new LinkRegistry();
			$entry    = $registry->find_by_notion_id( $result['notion_page_id'] );

			\WP_CLI::log( '' );
			\WP_CLI::log( \WP_CLI::colorize( '%GRegistry Status:%n' ) );

			if ( $entry ) {
				\WP_CLI::log( '  Registered:      ' . \WP_CLI::colorize( '%GYes%n' ) );
				\WP_CLI::log( '  Title:           ' . $entry->notion_title );
				\WP_CLI::log( '  Type:            ' . $entry->notion_type );
				\WP_CLI::log( '  Slug:            ' . $entry->slug );
				\WP_CLI::log( '  Sync Status:     ' . $entry->sync_status );

				if ( 'synced' === $entry->sync_status && $entry->wp_post_id ) {
					\WP_CLI::log( '  WP Post ID:      ' . $entry->wp_post_id );
					\WP_CLI::log( '  WP URL:          ' . get_permalink( $entry->wp_post_id ) );
				}
			} else {
				\WP_CLI::log( '  Registered:      ' . \WP_CLI::colorize( '%RNo%n' ) );
				\WP_CLI::log( '  Note:            Link will be registered on first use' );
			}
		} else {
			\WP_CLI::log( '  Detection:       ' . \WP_CLI::colorize( '%RNot a Notion Internal Link%n' ) );
			\WP_CLI::log( '  Rewritten URL:   ' . $result['url'] . ' (unchanged)' );
		}
	}

	/**
	 * Test routing for a slug.
	 *
	 * @param string $slug Slug to test.
	 */
	public static function test_route( string $slug ): void {
		\WP_CLI::log( "Testing route for slug: {$slug}" );
		\WP_CLI::log( '' );

		$registry = new LinkRegistry();
		$entry    = $registry->find_by_slug( $slug );

		if ( ! $entry ) {
			\WP_CLI::error( 'Slug not found in registry!' );
		}

		\WP_CLI::success( 'Found in registry!' );
		\WP_CLI::log( "  Notion ID:     {$entry->notion_id}" );
		\WP_CLI::log( "  Title:         {$entry->notion_title}" );
		\WP_CLI::log( "  Type:          {$entry->notion_type}" );
		\WP_CLI::log( "  Sync Status:   {$entry->sync_status}" );
		\WP_CLI::log( '  WP Post ID:    ' . ( $entry->wp_post_id ?? 'N/A' ) );

		if ( 'synced' === $entry->sync_status && $entry->wp_post_id ) {
			$permalink = get_permalink( $entry->wp_post_id );
			\WP_CLI::log( "  Permalink:     {$permalink}" );
			\WP_CLI::log( '' );
			\WP_CLI::log( \WP_CLI::colorize( '%GShould redirect to WordPress post%n' ) );
		} else {
			\WP_CLI::log( "  Notion URL:    {$entry->notion_url}" );
			\WP_CLI::log( '' );
			\WP_CLI::log( \WP_CLI::colorize( '%YShould redirect to Notion%n' ) );
		}
	}

	/**
	 * Update internal Notion links in a post.
	 *
	 * @param int $post_id WordPress post ID.
	 */
	public static function update_post_links( int $post_id ): void {
		\WP_CLI::log( "Updating links in post {$post_id}..." );
		\WP_CLI::log( '' );

		$result = \NotionSync\Sync\LinkUpdater::update_post_links( $post_id );

		if ( $result['updated'] ) {
			\WP_CLI::success(
				sprintf(
					'Updated %d link%s in post %d',
					$result['links_rewritten'],
					1 === $result['links_rewritten'] ? '' : 's',
					$post_id
				)
			);
		} else {
			\WP_CLI::log( 'No links needed updating.' );
		}
	}
}
