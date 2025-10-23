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
 *     # Sync a database with batch processing
 *     $ wp notion sync database-id-here --batch-size=50
 *
 *     # Force re-sync even if already synced
 *     $ wp notion sync 75424b1c35d0476b836cbb0e776f3f7c --force
 *
 *     # View sync logs
 *     $ wp notion logs
 *
 *     # View only errors
 *     $ wp notion logs --severity=error
 *
 *     # View log statistics
 *     $ wp notion logs --stats
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
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @when after_wp_load
	 */
	public function list( $args, $assoc_args ) {
		try {
			$type   = $assoc_args['type'] ?? null;
			$limit  = intval( $assoc_args['limit'] ?? 10 );
			$format = $assoc_args['format'] ?? 'table';

			// Validate limit.
			$limit = max( 1, min( 100, $limit ) );

			// Get Notion client using helper.
			list( $client, $error ) = CommandHelpers::get_notion_client();
			if ( $error ) {
				\WP_CLI::error( $error );
			}

			ListHandler::list_resources( $client, $type, $limit, $format );

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
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @when after_wp_load
	 */
	public function sync( $args, $assoc_args ) {
		$notion_id  = $args[0];
		$force      = isset( $assoc_args['force'] );
		$batch_size = intval( $assoc_args['batch-size'] ?? 20 );

		try {
			// Get Notion client.
			list( $client, $error ) = CommandHelpers::get_notion_client();
			if ( $error ) {
				\WP_CLI::error( $error );
			}

			// Determine if this is a page or database by fetching it.
			\WP_CLI::log( "Detecting resource type for {$notion_id}..." );

			$resource_type = CommandHelpers::detect_resource_type( $client, $notion_id );

			if ( 'page' === $resource_type ) {
				SyncHandler::sync_page( $notion_id, $force );
			} elseif ( 'database' === $resource_type ) {
				SyncHandler::sync_database( $notion_id, $client, $batch_size );
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
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @when after_wp_load
	 */
	public function show( $args, $assoc_args ) {
		$notion_id   = $args[0];
		$show_blocks = isset( $assoc_args['blocks'] );
		$show_raw    = isset( $assoc_args['raw'] );

		try {
			list( $client, $error ) = CommandHelpers::get_notion_client();
			if ( $error ) {
				\WP_CLI::error( $error );
			}

			ShowHandler::show_page( $client, $notion_id, $show_blocks, $show_raw );

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
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @when after_wp_load
	 */
	public function show_database( $args, $assoc_args ) {
		$database_id = $args[0];
		$limit       = intval( $assoc_args['limit'] ?? 10 );
		$format      = $assoc_args['format'] ?? 'table';

		try {
			list( $client, $error ) = CommandHelpers::get_notion_client();
			if ( $error ) {
				\WP_CLI::error( $error );
			}

			ShowHandler::show_database( $client, $database_id, $limit, $format );

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
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @when after_wp_load
	 */
	public function links( $args, $assoc_args ) {
		$post_id = intval( $args[0] );
		RegistryHandler::show_post_links( $post_id );
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
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @when after_wp_load
	 */
	public function registry( $args, $assoc_args ) {
		$notion_id   = $assoc_args['notion-id'] ?? null;
		$sync_status = $assoc_args['sync-status'] ?? null;
		$format      = $assoc_args['format'] ?? 'table';

		RegistryHandler::show_registry( $notion_id, $sync_status, $format );
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
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @when after_wp_load
	 */
	public function test_link( $args, $assoc_args ) {
		$url = $args[0];
		RegistryHandler::test_link( $url );
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
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @when after_wp_load
	 */
	public function test_route( $args, $assoc_args ) {
		$slug = $args[0];
		RegistryHandler::test_route( $slug );
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
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @when after_wp_load
	 */
	public function update_links( $args, $assoc_args ) {
		$post_id = absint( $args[0] );
		RegistryHandler::update_post_links( $post_id );
	}

	/**
	 * View and manage sync logs.
	 *
	 * ## OPTIONS
	 *
	 * [--severity=<severity>]
	 * : Filter by severity level
	 * ---
	 * options:
	 *   - error
	 *   - warning
	 *   - info
	 * ---
	 *
	 * [--category=<category>]
	 * : Filter by category
	 * ---
	 * options:
	 *   - image
	 *   - block
	 *   - api
	 *   - conversion
	 *   - performance
	 * ---
	 *
	 * [--notion-page-id=<id>]
	 * : Filter by Notion page ID
	 *
	 * [--wp-post-id=<id>]
	 * : Filter by WordPress post ID
	 *
	 * [--limit=<limit>]
	 * : Maximum number of logs to display (default: 20)
	 * ---
	 * default: 20
	 * ---
	 *
	 * [--resolve=<log-id>]
	 * : Mark a specific log entry as resolved
	 *
	 * [--resolve-all]
	 * : Mark all unresolved logs as resolved
	 *
	 * [--stats]
	 * : Show log statistics instead of listing logs
	 *
	 * ## EXAMPLES
	 *
	 *     # View all unresolved logs
	 *     wp notion logs
	 *
	 *     # View only errors
	 *     wp notion logs --severity=error
	 *
	 *     # View logs for specific page
	 *     wp notion logs --notion-page-id=75424b1c35d0476b836cbb0e776f3f7c
	 *
	 *     # View image-related warnings
	 *     wp notion logs --severity=warning --category=image
	 *
	 *     # Resolve a specific log
	 *     wp notion logs --resolve=123
	 *
	 *     # Resolve all logs
	 *     wp notion logs --resolve-all
	 *
	 *     # Show statistics
	 *     wp notion logs --stats
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @when after_wp_load
	 */
	public function logs( $args, $assoc_args ) {
		LogsHandler::handle_logs_command( $assoc_args );
	}

	/**
	 * Check Action Scheduler configuration status.
	 *
	 * Displays current Action Scheduler configuration including runner type,
	 * timeout settings, and version information.
	 *
	 * ## EXAMPLES
	 *
	 *     wp notion scheduler-status
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @when after_wp_load
	 */
	public function scheduler_status( $args, $assoc_args ) {
		if ( ! class_exists( 'NotionSync\\Utils\\ActionSchedulerConfig' ) ) {
			\WP_CLI::error( 'ActionSchedulerConfig class not found' );
		}

		$config = \NotionSync\Utils\ActionSchedulerConfig::get_config_status();

		\WP_CLI::log( \WP_CLI::colorize( '%G' . str_repeat( '=', 60 ) . '%n' ) );
		\WP_CLI::log( \WP_CLI::colorize( '%GACTION SCHEDULER STATUS%n' ) );
		\WP_CLI::log( \WP_CLI::colorize( '%G' . str_repeat( '=', 60 ) . '%n' ) );
		\WP_CLI::log( '' );

		\WP_CLI::log( sprintf( 'Version:          %s', $config['action_scheduler_version'] ) );
		\WP_CLI::log( sprintf( 'Timeout Period:   %d seconds (%d minutes)', $config['timeout_period'], $config['timeout_period'] / 60 ) );
		\WP_CLI::log( '' );

		$runner_type = $config['async_runner_enabled'] ? 'Async Request' : 'WP Cron';
		$runner_color = $config['async_runner_enabled'] ? 'y' : 'g';
		\WP_CLI::log( \WP_CLI::colorize( sprintf( '%%BRunner Type:%% %s%s%%n', "%{$runner_color}", $runner_type ) ) );

		if ( ! $config['async_runner_enabled'] ) {
			\WP_CLI::log( \WP_CLI::colorize( '%gWP Cron runner is enabled for improved reliability.%n' ) );
		} else {
			\WP_CLI::log( \WP_CLI::colorize( '%yAsync Request runner may experience timeout issues.%n' ) );
			\WP_CLI::log( \WP_CLI::colorize( '%yConsider forcing WP Cron runner for more reliable background processing.%n' ) );
		}

		\WP_CLI::log( '' );
		\WP_CLI::log( \WP_CLI::colorize( '%G' . str_repeat( '=', 60 ) . '%n' ) );
	}
}

