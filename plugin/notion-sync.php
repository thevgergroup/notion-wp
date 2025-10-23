<?php
/**
 * Plugin Name: Notion Sync
 * Plugin URI: https://github.com/thevgergroup/notion-wp
 * Description: Bi-directional synchronization between Notion and WordPress
 * Version: 0.2.0-dev
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: The Verger Group
 * Author URI: https://thevgergroup.com
 * License: GPL-3.0+
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: notion-wp
 * Domain Path: /languages
 *
 * @package NotionSync
 */

namespace NotionSync;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'NOTION_SYNC_VERSION', '0.2.0-dev' );
define( 'NOTION_SYNC_FILE', __FILE__ );
define( 'NOTION_SYNC_PATH', plugin_dir_path( __FILE__ ) );
define( 'NOTION_SYNC_URL', plugin_dir_url( __FILE__ ) );
define( 'NOTION_SYNC_BASENAME', plugin_basename( __FILE__ ) );

// Load composer autoloader for Action Scheduler and other dependencies.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

// Initialize Action Scheduler (WooCommerce library for background processing).
if ( file_exists( __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php' ) ) {
	require_once __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php';
}

// PSR-4 autoloader.
spl_autoload_register(
	function ( $class_name ) {
		$prefix   = 'NotionSync\\';
		$base_dir = __DIR__ . '/src/';

		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class_name, $len ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class_name, $len );
		$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

/**
 * Initialize plugin.
 *
 * @return void
 */
function init() {
	// Register database custom post type.
	$database_cpt = new Database\DatabasePostType();
	$database_cpt->register();

	// Register database frontend template loader.
	$database_template_loader = new Database\DatabaseTemplateLoader();
	$database_template_loader->register();

	// Initialize Link Registry and Router for /notion/{slug} URLs.
	$link_registry = new Router\LinkRegistry();
	$notion_router = new Router\NotionRouter( $link_registry );
	$notion_router->register();

	// Register Notion Link Gutenberg block.
	$notion_link_block = new Blocks\NotionLinkBlock();
	$notion_link_block->register();

	// Register Notion Link shortcode for inline links.
	$notion_link_shortcode = new Blocks\NotionLinkShortcode();
	$notion_link_shortcode->register();

	// Initialize admin interface.
	if ( is_admin() ) {
		$settings_page = new Admin\SettingsPage();
		$settings_page->register();

		$database_view_page = new Admin\DatabaseViewPage();
		$database_view_page->register();

		$sync_logs_page = new Admin\SyncLogsPage();
		$sync_logs_page->register();

		$admin_notices = new Admin\AdminNotices();
		$admin_notices->register();
	}

	// Register REST API endpoints.
	add_action(
		'rest_api_init',
		function () {
			$rest_controller = new API\DatabaseRestController();
			$rest_controller->register_routes();

			$link_rest_controller = new API\LinkRegistryRestController();
			$link_rest_controller->register_routes();
		}
	);

	// Register Action Scheduler hook for batch processing.
	if ( function_exists( 'as_schedule_single_action' ) ) {
		// Configure Action Scheduler for improved reliability.
		Utils\ActionSchedulerConfig::register();

		add_action(
			'notion_sync_process_batch',
			function ( $batch_id, $post_id, $batch_number, $total_batches ) {

				// Get encrypted token.
				$encrypted_token = get_option( 'notion_wp_token' );
				if ( empty( $encrypted_token ) ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging for batch processing.
					error_log( 'Batch processing aborted: No Notion token configured' );
					return;
				}

				// Initialize dependencies.
				$client     = new API\NotionClient( Security\Encryption::decrypt( $encrypted_token ) );
				$fetcher    = new Sync\DatabaseFetcher( $client );
				$repository = new Database\RowRepository();
				$processor  = new Sync\BatchProcessor( $fetcher, $repository );

				// Process the batch.
				$processor->process_batch( $batch_id, $post_id, $batch_number, $total_batches );
			},
			10,
			4
		);

		// Register Media Sync Scheduler hooks.
		Media\MediaSyncScheduler::register_hooks();

		// Register Page Sync Scheduler hooks.
		Sync\PageSyncScheduler::register_hooks();
	}

	// Register WP-CLI commands if WP-CLI is available.
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		\WP_CLI::add_command( 'notion', 'NotionSync\\CLI\\NotionCommand' );
	}

	// Add filter to prepend icon emoji to post titles.
	add_filter( 'the_title', __NAMESPACE__ . '\prepend_notion_icon_to_title', 10, 2 );

	// Plugin loaded hook for extensibility.
	do_action( 'notion_sync_loaded' );
}
add_action( 'init', __NAMESPACE__ . '\init' );

/**
 * Prepend Notion icon emoji to post titles.
 *
 * Adds the Notion page icon (emoji or image) before the post title if available.
 * Only applies to synced posts with a Notion icon.
 *
 * @param string $title   The post title.
 * @param int    $post_id The post ID.
 * @return string Modified title with icon prepended.
 */
function prepend_notion_icon_to_title( string $title, $post_id = null ): string {
	if ( ! $post_id ) {
		return $title;
	}

	$icon_type = get_post_meta( $post_id, '_notion_icon_type', true );
	$icon      = get_post_meta( $post_id, '_notion_icon', true );

	if ( empty( $icon ) ) {
		return $title;
	}

	// Only prepend emoji icons (not image URLs).
	if ( 'emoji' === $icon_type ) {
		return $icon . ' ' . $title;
	}

	return $title;
}

/**
 * Activation hook.
 *
 * @return void
 */
function activate() {
	// Set default options.
	if ( false === get_option( 'notion_wp_token' ) ) {
		add_option( 'notion_wp_token', '' );
	}
	if ( false === get_option( 'notion_wp_workspace_info' ) ) {
		add_option( 'notion_wp_workspace_info', array() );
	}

	// Create custom database tables.
	\NotionSync\Database\Schema::create_tables();
	\NotionSync\Media\MediaRegistry::create_table();

	// Register database CPT before flushing rewrite rules.
	$database_cpt = new \NotionSync\Database\DatabasePostType();
	$database_cpt->register();

	// Register Link Router rewrite rules before flushing.
	$link_registry = new \NotionSync\Router\LinkRegistry();
	$notion_router = new \NotionSync\Router\NotionRouter( $link_registry );
	$notion_router->register_rewrite_rules();

	// Create sync log database table.
	\NotionSync\Database\SyncLogSchema::create_table();

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\activate' );

/**
 * Deactivation hook.
 *
 * @return void
 */
function deactivate() {
	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\deactivate' );

/**
 * Uninstall hook.
 * Note: This is called from uninstall.php, not directly from this file.
 *
 * @return void
 */
function uninstall() {
	// Check if user wants to delete data on uninstall.
	$delete_data = get_option( 'notion_wp_delete_data_on_uninstall', false );

	if ( $delete_data ) {
		// Delete all plugin options.
		delete_option( 'notion_wp_token' );
		delete_option( 'notion_wp_workspace_info' );
		delete_option( 'notion_wp_delete_data_on_uninstall' );

		// Delete cached workspace info.
		delete_transient( 'notion_wp_workspace_info_cache' );
	}
}
