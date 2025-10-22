<?php
/**
 * WP-CLI Commands for Notion Sync
 *
 * Provides command-line interface for managing Notion synchronization.
 * All commands reuse existing plugin functionality from NotionClient, SyncManager,
 * BatchProcessor, and other core classes.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\CLI;

use NotionSync\API\NotionClient;
use NotionSync\Sync\ContentFetcher;
use NotionSync\Sync\DatabaseFetcher;
use NotionSync\Sync\SyncManager;
use NotionSync\Sync\BatchProcessor;
use NotionSync\Database\RowRepository;
use NotionSync\Router\LinkRegistry;
use NotionSync\Blocks\LinkRewriter;
use NotionSync\Security\Encryption;

/**
 * Manage Notion synchronization via WP-CLI.
 *
 * ## EXAMPLES
 *
 *     # List all accessible Notion resources
 *     $ wp notion list
 *
 *     # List only pages
 *     $ wp notion list --type=page
 *
 *     # List databases
 *     $ wp notion list --type=database
 *
 *     # Sync a specific page
 *     $ wp notion sync 75424b1c35d0476b836cbb0e776f3f7c
 *
 *     # Force re-sync even if already synced
 *     $ wp notion sync 75424b1c35d0476b836cbb0e776f3f7c --force
 *
 *     # Show page details
 *     $ wp notion show 75424b1c35d0476b836cbb0e776f3f7c
 *
 *     # Show database with sample rows
 *     $ wp notion show-database abc123def456 --limit=5
 *
 *     # Check links in a WordPress post
 *     $ wp notion links 123
 *
 *     # View link registry entries
 *     $ wp notion registry
 *
 *     # Test link rewriting
 *     $ wp notion test-link "/75424b1c35d0476b836cbb0e776f3f7c"
 *
 * @when after_wp_load
 */
class NotionCommand {

	/**
	 * List accessible Notion pages and databases.
	 *
	 * ## OPTIONS
	 *
	 * [--type=<type>]
	 * : Filter by type: page or database
	 * ---
	 * options:
	 *   - page
	 *   - database
	 * ---
	 *
	 * [--limit=<limit>]
	 * : Maximum number of items to display (default: 10, max: 100)
	 * ---
	 * default: 10
	 * ---
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp notion list
	 *     wp notion list --type=page --limit=20
	 *     wp notion list --type=database --format=json
	 *
	 * @when after_wp_load
	 */
	public function list( $args, $assoc_args ) {
		try {
			$type   = $assoc_args['type'] ?? null;
			$limit  = intval( $assoc_args['limit'] ?? 10 );
			$format = $assoc_args['format'] ?? 'table';

			// Validate limit.
			$limit = max( 1, min( 100, $limit ) );

			// Get Notion client using existing helper.
			list( $client, $error ) = $this->get_notion_client();
			if ( $error ) {
				\WP_CLI::error( $error );
			}

			$items = array();

			// Fetch pages if requested (or no type specified).
			if ( ! $type || 'page' === $type ) {
				\WP_CLI::log( 'Fetching pages from Notion...' );
				$fetcher = new ContentFetcher( $client );
				$pages   = $fetcher->fetch_pages_list( $limit );

				// Cache for parent page titles to avoid redundant API calls.
				$parent_cache = array();

				foreach ( $pages as $page ) {
					$parent_display = $this->resolve_parent_title( $page, $fetcher, $parent_cache );

					$items[] = array(
						'Type'        => 'Page',
						'ID'          => $page['id'] ?? '',
						'Title'       => $page['title'] ?? 'Untitled',
						'Last Edited' => $this->format_timestamp( $page['last_edited_time'] ?? '' ),
						'Parent'      => $parent_display,
					);
				}
			}

			// Fetch databases if requested (or no type specified).
			if ( ! $type || 'database' === $type ) {
				\WP_CLI::log( 'Fetching databases from Notion...' );
				$db_fetcher = new DatabaseFetcher( $client );
				$databases  = $db_fetcher->get_databases();

				// Limit databases if type is specifically 'database'.
				if ( 'database' === $type ) {
					$databases = array_slice( $databases, 0, $limit );
				}

				foreach ( $databases as $database ) {
					$items[] = array(
						'Type'        => 'Database',
						'ID'          => $database['id'] ?? '',
						'Title'       => $database['title'] ?? 'Untitled',
						'Last Edited' => $this->format_timestamp( $database['last_edited_time'] ?? '' ),
						'Parent'      => 'N/A',
					);
				}
			}

			if ( empty( $items ) ) {
				\WP_CLI::warning( 'No items found. Make sure your integration has access to Notion pages/databases.' );
				return;
			}

			\WP_CLI\Utils\format_items( $format, $items, array( 'Type', 'ID', 'Title', 'Last Edited', 'Parent' ) );

		} catch ( \Exception $e ) {
			\WP_CLI::error( 'Failed to list Notion resources: ' . $e->getMessage() );
		}
	}

	/**
	 * Sync a Notion page or database to WordPress.
	 *
	 * ## OPTIONS
	 *
	 * <notion-id>
	 * : Notion page or database ID (with or without dashes)
	 *
	 * [--force]
	 * : Force re-sync even if already synced
	 *
	 * [--batch-size=<size>]
	 * : For databases: number of entries per batch (default: 20)
	 * ---
	 * default: 20
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp notion sync 75424b1c35d0476b836cbb0e776f3f7c
	 *     wp notion sync abc-123-def-456 --force
	 *     wp notion sync database-id-here --batch-size=50
	 *
	 * @when after_wp_load
	 */
	public function sync( $args, $assoc_args ) {
		$notion_id  = $args[0];
		$force      = isset( $assoc_args['force'] );
		$batch_size = intval( $assoc_args['batch-size'] ?? 20 );

		try {
			// Get Notion client.
			list( $client, $error ) = $this->get_notion_client();
			if ( $error ) {
				\WP_CLI::error( $error );
			}

			// Determine if this is a page or database by fetching it.
			\WP_CLI::log( "Detecting resource type for {$notion_id}..." );

			$resource_type = $this->detect_resource_type( $client, $notion_id );

			if ( 'page' === $resource_type ) {
				$this->sync_page( $notion_id, $force );
			} elseif ( 'database' === $resource_type ) {
				$this->sync_database( $notion_id, $client, $batch_size );
			} else {
				\WP_CLI::error( 'Unable to determine resource type. Please check the Notion ID and integration access.' );
			}

		} catch ( \Exception $e ) {
			\WP_CLI::error( 'Sync failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Show details for a Notion page.
	 *
	 * ## OPTIONS
	 *
	 * <notion-id>
	 * : Notion page ID (with or without dashes)
	 *
	 * [--blocks]
	 * : Also display block structure
	 *
	 * [--raw]
	 * : Output raw JSON data for blocks
	 *
	 * ## EXAMPLES
	 *
	 *     wp notion show 75424b1c35d0476b836cbb0e776f3f7c
	 *     wp notion show 75424b1c35d0476b836cbb0e776f3f7c --blocks
	 *     wp notion show 75424b1c35d0476b836cbb0e776f3f7c --raw
	 *
	 * @when after_wp_load
	 */
	public function show( $args, $assoc_args ) {
		$notion_id = $args[0];
		$show_blocks = isset( $assoc_args['blocks'] );
		$show_raw = isset( $assoc_args['raw'] );

		try {
			list( $client, $error ) = $this->get_notion_client();
			if ( $error ) {
				\WP_CLI::error( $error );
			}

			$fetcher = new ContentFetcher( $client );

			\WP_CLI::log( "Fetching page {$notion_id}..." );

			// Fetch page properties.
			$properties = $fetcher->fetch_page_properties( $notion_id );

			if ( empty( $properties ) ) {
				\WP_CLI::error( 'Page not found or integration lacks access.' );
			}

			// Display page properties.
			\WP_CLI::log( \WP_CLI::colorize( '%GPage Properties:%n' ) );
			\WP_CLI::log( '  ID:            ' . ( $properties['id'] ?? 'N/A' ) );
			\WP_CLI::log( '  Title:         ' . ( $properties['title'] ?? 'Untitled' ) );
			\WP_CLI::log( '  Created:       ' . $this->format_timestamp( $properties['created_time'] ?? '' ) );
			\WP_CLI::log( '  Last Edited:   ' . $this->format_timestamp( $properties['last_edited_time'] ?? '' ) );
			\WP_CLI::log( '  URL:           ' . ( $properties['url'] ?? 'N/A' ) );

			// Check sync status.
			$sync_manager = new SyncManager();
			$sync_status  = $sync_manager->get_sync_status( $notion_id );

			\WP_CLI::log( '' );
			\WP_CLI::log( \WP_CLI::colorize( '%GSync Status:%n' ) );
			if ( $sync_status['is_synced'] ) {
				\WP_CLI::log( '  Synced:        ' . \WP_CLI::colorize( '%GYes%n' ) );
				\WP_CLI::log( '  WP Post ID:    ' . $sync_status['post_id'] );
				\WP_CLI::log( '  Last Synced:   ' . $this->format_timestamp( $sync_status['last_synced'] ) );
				\WP_CLI::log( '  WP URL:        ' . get_permalink( $sync_status['post_id'] ) );
			} else {
				\WP_CLI::log( '  Synced:        ' . \WP_CLI::colorize( '%RNo%n' ) );
			}

			// Show blocks if requested.
			if ( $show_blocks || $show_raw ) {
				\WP_CLI::log( '' );
				$blocks = $fetcher->fetch_page_blocks( $notion_id );

				if ( empty( $blocks ) ) {
					\WP_CLI::log( '  (No blocks found)' );
				} else {
					if ( $show_raw ) {
						// Output raw JSON for debugging.
						\WP_CLI::log( \WP_CLI::colorize( '%GRaw Block JSON:%n' ) );
						\WP_CLI::log( json_encode( $blocks, JSON_PRETTY_PRINT ) );
					} else {
						\WP_CLI::log( \WP_CLI::colorize( '%GPage Blocks:%n' ) );
						\WP_CLI::log( '  Total blocks: ' . count( $blocks ) );
						foreach ( $blocks as $index => $block ) {
							$type = $block['type'] ?? 'unknown';
							\WP_CLI::log( sprintf( '  [%d] %s', $index + 1, $type ) );
						}
					}
				}
			}

		} catch ( \Exception $e ) {
			\WP_CLI::error( 'Failed to fetch page: ' . $e->getMessage() );
		}
	}

	/**
	 * Show details for a Notion database.
	 *
	 * ## OPTIONS
	 *
	 * <notion-id>
	 * : Notion database ID (with or without dashes)
	 *
	 * [--limit=<limit>]
	 * : Number of sample rows to display (default: 10)
	 * ---
	 * default: 10
	 * ---
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp notion show-database abc123def456
	 *     wp notion show-database abc123def456 --limit=5 --format=json
	 *
	 * @when after_wp_load
	 */
	public function show_database( $args, $assoc_args ) {
		$database_id = $args[0];
		$limit       = intval( $assoc_args['limit'] ?? 10 );
		$format      = $assoc_args['format'] ?? 'table';

		try {
			list( $client, $error ) = $this->get_notion_client();
			if ( $error ) {
				\WP_CLI::error( $error );
			}

			$fetcher = new DatabaseFetcher( $client );

			\WP_CLI::log( "Fetching database {$database_id}..." );

			// Get database schema.
			$schema = $fetcher->get_database_schema( $database_id );

			\WP_CLI::log( \WP_CLI::colorize( '%GDatabase Information:%n' ) );
			\WP_CLI::log( '  ID:            ' . ( $schema['id'] ?? 'N/A' ) );
			\WP_CLI::log( '  Title:         ' . ( $schema['title'] ?? 'Untitled' ) );
			\WP_CLI::log( '  Last Edited:   ' . $this->format_timestamp( $schema['last_edited_time'] ?? '' ) );
			\WP_CLI::log( '' );

			// Display properties/columns.
			\WP_CLI::log( \WP_CLI::colorize( '%GProperties (Columns):%n' ) );
			if ( isset( $schema['properties'] ) && is_array( $schema['properties'] ) ) {
				foreach ( $schema['properties'] as $name => $prop ) {
					$type = $prop['type'] ?? 'unknown';
					\WP_CLI::log( sprintf( '  - %s (%s)', $name, $type ) );
				}
			}

			// Fetch sample rows.
			\WP_CLI::log( '' );
			\WP_CLI::log( \WP_CLI::colorize( "%GSample Rows (showing first {$limit}):%n" ) );

			$entries = $fetcher->query_database( $database_id );

			if ( empty( $entries ) ) {
				\WP_CLI::warning( 'No entries found in database.' );
				return;
			}

			// Limit entries for display.
			$sample_entries = array_slice( $entries, 0, $limit );

			// Normalize and prepare for display.
			$rows = array();
			foreach ( $sample_entries as $entry ) {
				$normalized = $fetcher->normalize_entry( $entry );
				$rows[] = array(
					'ID'          => substr( $normalized['id'], 0, 8 ) . '...',
					'Created'     => $this->format_timestamp( $normalized['created_time'] ),
					'Properties'  => count( $normalized['properties'] ),
				);
			}

			\WP_CLI\Utils\format_items( $format, $rows, array( 'ID', 'Created', 'Properties' ) );

			\WP_CLI::log( sprintf( "\nTotal entries in database: %d", count( $entries ) ) );

		} catch ( \Exception $e ) {
			\WP_CLI::error( 'Failed to fetch database: ' . $e->getMessage() );
		}
	}

	/**
	 * Show Notion links found in a WordPress post.
	 *
	 * ## OPTIONS
	 *
	 * <post-id>
	 * : WordPress post ID
	 *
	 * ## EXAMPLES
	 *
	 *     wp notion links 123
	 *
	 * @when after_wp_load
	 */
	public function links( $args, $assoc_args ) {
		$post_id = intval( $args[0] );

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

		$link_registry = new LinkRegistry();
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
	 * ## OPTIONS
	 *
	 * [--notion-id=<id>]
	 * : Filter by specific Notion ID
	 *
	 * [--sync-status=<status>]
	 * : Filter by sync status: synced or not_synced
	 * ---
	 * options:
	 *   - synced
	 *   - not_synced
	 * ---
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp notion registry
	 *     wp notion registry --notion-id=abc123
	 *     wp notion registry --sync-status=synced --format=json
	 *
	 * @when after_wp_load
	 */
	public function registry( $args, $assoc_args ) {
		$notion_id   = $assoc_args['notion-id'] ?? null;
		$sync_status = $assoc_args['sync-status'] ?? null;
		$format      = $assoc_args['format'] ?? 'table';

		global $wpdb;
		$table_name = $wpdb->prefix . 'notion_links';

		// Build query.
		$where_clauses = array();
		$where_values  = array();

		if ( $notion_id ) {
			$where_clauses[] = '(notion_id = %s OR notion_id_uuid = %s)';
			$notion_id_normalized = str_replace( '-', '', $notion_id );
			// Format as UUID.
			$notion_id_uuid = substr( $notion_id_normalized, 0, 8 ) . '-' .
							  substr( $notion_id_normalized, 8, 4 ) . '-' .
							  substr( $notion_id_normalized, 12, 4 ) . '-' .
							  substr( $notion_id_normalized, 16, 4 ) . '-' .
							  substr( $notion_id_normalized, 20, 12 );
			$where_values[] = $notion_id_normalized;
			$where_values[] = $notion_id_uuid;
		}

		if ( $sync_status ) {
			$where_clauses[] = 'sync_status = %s';
			$where_values[] = $sync_status;
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
				'Notion ID'   => substr( $entry->notion_id, 0, 12 ) . '...',
				'Title'       => $entry->notion_title,
				'Type'        => $entry->notion_type,
				'Slug'        => $entry->slug,
				'Status'      => $entry->sync_status,
				'WP Post ID'  => $entry->wp_post_id ?? 'N/A',
				'Accessed'    => $entry->access_count,
			);
		}

		\WP_CLI\Utils\format_items( $format, $items, array( 'Notion ID', 'Title', 'Type', 'Slug', 'Status', 'WP Post ID', 'Accessed' ) );

		\WP_CLI::log( sprintf( "\nTotal entries: %d", count( $results ) ) );
	}

	/**
	 * Test link rewriting for a URL.
	 *
	 * ## OPTIONS
	 *
	 * <url>
	 * : URL to test (Notion internal link format)
	 *
	 * ## EXAMPLES
	 *
	 *     wp notion test-link "/75424b1c35d0476b836cbb0e776f3f7c"
	 *     wp notion test-link "https://notion.so/abc123def456"
	 *
	 * @when after_wp_load
	 */
	public function test_link( $args, $assoc_args ) {
		$url = $args[0];

		\WP_CLI::log( \WP_CLI::colorize( '%GLink Rewriting Test%n' ) );
		\WP_CLI::log( '  Original URL:    ' . $url );

		$result = LinkRewriter::rewrite_url( $url );

		if ( $result['notion_page_id'] ) {
			\WP_CLI::log( '  Detection:       ' . \WP_CLI::colorize( '%GNotion Internal Link%n' ) );
			\WP_CLI::log( '  Notion ID:       ' . $result['notion_page_id'] );
			\WP_CLI::log( '  Rewritten URL:   ' . $result['url'] );

			// Check registry.
			$registry = new LinkRegistry();
			$entry = $registry->find_by_notion_id( $result['notion_page_id'] );

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

	// ===== PRIVATE HELPER METHODS =====

	/**
	 * Sync a single page.
	 *
	 * @param string $notion_id Notion page ID.
	 * @param bool   $force     Whether to force re-sync.
	 */
	private function sync_page( string $notion_id, bool $force ) {
		$sync_manager = new SyncManager();

		// Check if already synced.
		if ( ! $force ) {
			$status = $sync_manager->get_sync_status( $notion_id );
			if ( $status['is_synced'] ) {
				\WP_CLI::log( sprintf(
					'Page already synced to post ID %d (last synced: %s)',
					$status['post_id'],
					$this->format_timestamp( $status['last_synced'] )
				) );
				\WP_CLI::log( 'Use --force to re-sync.' );
				return;
			}
		}

		\WP_CLI::log( "Syncing page {$notion_id}..." );

		$result = $sync_manager->sync_page( $notion_id );

		if ( $result['success'] ) {
			\WP_CLI::success( sprintf(
				'Page synced successfully! WordPress post ID: %d',
				$result['post_id']
			) );
			\WP_CLI::log( 'View post: ' . get_permalink( $result['post_id'] ) );
		} else {
			\WP_CLI::error( 'Sync failed: ' . $result['error'] );
		}
	}

	/**
	 * Sync a database.
	 *
	 * @param string       $database_id Notion database ID.
	 * @param NotionClient $client      Notion client instance.
	 * @param int          $batch_size  Entries per batch.
	 */
	private function sync_database( string $database_id, NotionClient $client, int $batch_size ) {
		\WP_CLI::log( "Syncing database {$database_id}..." );

		$fetcher    = new DatabaseFetcher( $client );
		$repository = new RowRepository();
		$processor  = new BatchProcessor( $fetcher, $repository );

		try {
			$batch_id = $processor->queue_database_sync( $database_id );

			\WP_CLI::success( sprintf( 'Database sync queued! Batch ID: %s', $batch_id ) );
			\WP_CLI::log( 'Use "wp notion batch-status ' . $batch_id . '" to check progress.' );
			\WP_CLI::log( '' );
			\WP_CLI::log( 'Background processing will handle the sync via Action Scheduler.' );

		} catch ( \Exception $e ) {
			\WP_CLI::error( 'Failed to queue database sync: ' . $e->getMessage() );
		}
	}

	/**
	 * Detect if a Notion ID is a page or database.
	 *
	 * @param NotionClient $client     Notion client instance.
	 * @param string       $notion_id  Notion ID.
	 * @return string 'page', 'database', or 'unknown'.
	 */
	private function detect_resource_type( NotionClient $client, string $notion_id ): string {
		// Try fetching as a page first.
		$page_response = $client->get_page( $notion_id );

		if ( isset( $page_response['object'] ) ) {
			if ( 'page' === $page_response['object'] ) {
				// Check if it's a database page (parent is database).
				if ( isset( $page_response['parent']['type'] ) && 'database_id' === $page_response['parent']['type'] ) {
					return 'page'; // It's a database entry (which is still a page).
				}
				return 'page';
			} elseif ( 'database' === $page_response['object'] ) {
				return 'database';
			}
		}

		// Try as database.
		$db_response = $client->request( 'GET', '/databases/' . $notion_id );

		if ( isset( $db_response['object'] ) && 'database' === $db_response['object'] ) {
			return 'database';
		}

		return 'unknown';
	}

	/**
	 * Resolve parent page title for display.
	 *
	 * For pages with a parent page, fetches and displays the parent's title.
	 * Uses caching to avoid redundant API calls.
	 *
	 * @param array            $page         Page data from Notion.
	 * @param ContentFetcher   $fetcher      Content fetcher instance for API calls.
	 * @param array            $parent_cache Reference to parent title cache array.
	 * @return string Parent display string (title, "workspace", or "N/A").
	 */
	private function resolve_parent_title( array $page, ContentFetcher $fetcher, array &$parent_cache ): string {
		$parent_type = $page['parent_type'] ?? 'unknown';

		// Handle non-page parents.
		if ( 'workspace' === $parent_type ) {
			return 'workspace';
		}

		if ( 'database_id' === $parent_type ) {
			return 'database';
		}

		// For page_id parents, fetch the parent page title.
		if ( 'page_id' === $parent_type ) {
			// Parent info should be in the raw page data that ContentFetcher has.
			// We need to get the parent page ID and fetch its title.
			// The parent structure is: {"type": "page_id", "page_id": "uuid"}.

			// Since we only have the formatted data here, we need to make an API call.
			// First check if we have this in our cache.
			$page_id = $page['id'] ?? '';

			// We need to get the parent page ID from the raw Notion data.
			// The easiest way is to fetch the page details which includes parent info.
			try {
				$page_details = $fetcher->fetch_page_properties( $page_id );

				if ( isset( $page_details['parent']['page_id'] ) ) {
					$parent_id = $page_details['parent']['page_id'];

					// Check cache first.
					if ( isset( $parent_cache[ $parent_id ] ) ) {
						return $parent_cache[ $parent_id ];
					}

					// Fetch parent page title.
					$parent_details = $fetcher->fetch_page_properties( $parent_id );
					$parent_title   = $parent_details['title'] ?? 'Untitled';

					// Cache it.
					$parent_cache[ $parent_id ] = $parent_title;

					return $parent_title;
				}
			} catch ( \Exception $e ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
				error_log( 'Failed to resolve parent title: ' . $e->getMessage() );
				return 'page_id';
			}
		}

		return $parent_type;
	}

	/**
	 * Get authenticated Notion client.
	 *
	 * @return array [NotionClient|null, string|null] Client and error message.
	 */
	private function get_notion_client(): array {
		$encrypted_token = get_option( 'notion_wp_token' );

		if ( empty( $encrypted_token ) ) {
			return array( null, 'Notion API token not configured. Please configure it in Settings > Notion Sync.' );
		}

		if ( ! Encryption::is_available() ) {
			return array( null, 'Encryption is not available. OpenSSL extension is required.' );
		}

		$token = Encryption::decrypt( $encrypted_token );

		if ( empty( $token ) ) {
			return array( null, 'Failed to decrypt Notion API token.' );
		}

		$client = new NotionClient( $token );

		// Test connection.
		if ( ! $client->test_connection() ) {
			return array( null, 'Failed to connect to Notion API. Please check your token.' );
		}

		return array( $client, null );
	}

	/**
	 * Test routing for a slug.
	 *
	 * ## OPTIONS
	 *
	 * <slug>
	 * : The slug to test (e.g., understanding-ai-fundamentals-1)
	 *
	 * ## EXAMPLES
	 *
	 *     wp notion test_route understanding-ai-fundamentals-1
	 *
	 * @when after_wp_load
	 */
	public function test_route( $args, $assoc_args ) {
		$slug = $args[0];

		\WP_CLI::log( "Testing route for slug: {$slug}" );
		\WP_CLI::log( '' );

		$registry = new LinkRegistry();
		$entry    = $registry->find_by_slug( $slug );

		if ( ! $entry ) {
			\WP_CLI::error( "Slug not found in registry!" );
		}

		\WP_CLI::success( "Found in registry!" );
		\WP_CLI::log( "  Notion ID:     {$entry->notion_id}" );
		\WP_CLI::log( "  Title:         {$entry->notion_title}" );
		\WP_CLI::log( "  Type:          {$entry->notion_type}" );
		\WP_CLI::log( "  Sync Status:   {$entry->sync_status}" );
		\WP_CLI::log( "  WP Post ID:    " . ( $entry->wp_post_id ?? 'N/A' ) );

		if ( 'synced' === $entry->sync_status && $entry->wp_post_id ) {
			$permalink = get_permalink( $entry->wp_post_id );
			\WP_CLI::log( "  Permalink:     {$permalink}" );
			\WP_CLI::log( '' );
			\WP_CLI::log( \WP_CLI::colorize( "%GShould redirect to WordPress post%n" ) );
		} else {
			\WP_CLI::log( "  Notion URL:    {$entry->notion_url}" );
			\WP_CLI::log( '' );
			\WP_CLI::log( \WP_CLI::colorize( "%YShould redirect to Notion%n" ) );
		}
	}

	/**
	 * Update internal Notion links in a post
	 *
	 * Rewrites Notion internal links to use current slugs from the link registry.
	 * Useful after syncing to update links that were created with placeholder slugs.
	 *
	 * ## OPTIONS
	 *
	 * <post_id>
	 * : WordPress post ID to update links in
	 *
	 * ## EXAMPLES
	 *
	 *     wp notion update_links 20
	 *
	 * @when after_wp_load
	 */
	public function update_links( $args, $assoc_args ) {
		$post_id = absint( $args[0] );

		\WP_CLI::log( "Updating links in post {$post_id}..." );
		\WP_CLI::log( '' );

		$result = \NotionSync\Sync\LinkUpdater::update_post_links( $post_id );

		if ( $result['updated'] ) {
			\WP_CLI::success( sprintf(
				'Updated %d link%s in post %d',
				$result['links_rewritten'],
				$result['links_rewritten'] === 1 ? '' : 's',
				$post_id
			) );
		} else {
			\WP_CLI::log( 'No links needed updating.' );
		}
	}

	/**
	 * Format timestamp for display.
	 *
	 * @param string $timestamp ISO 8601 timestamp or MySQL timestamp.
	 * @return string Formatted date/time.
	 */
	private function format_timestamp( string $timestamp ): string {
		if ( empty( $timestamp ) ) {
			return 'N/A';
		}

		$date = strtotime( $timestamp );
		if ( ! $date ) {
			return $timestamp;
		}

		return gmdate( 'Y-m-d H:i:s', $date );
	}
}
