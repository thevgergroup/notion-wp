<?php
/**
 * Plugin Name: Notion Sync
 * Plugin URI: https://github.com/thevgergroup/notion-wp
 * Description: Bi-directional synchronization between Notion and WordPress
 * Version: 1.0.3
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: The Verger Group
 * Author URI: https://thevgergroup.com
 * License: GPL-3.0+
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: notion-sync
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
define( 'NOTION_SYNC_VERSION', '1.0.3' );
define( 'NOTION_SYNC_FILE', __FILE__ );
define( 'NOTION_SYNC_PATH', plugin_dir_path( __FILE__ ) );
define( 'NOTION_SYNC_URL', plugin_dir_url( __FILE__ ) );
define( 'NOTION_SYNC_BASENAME', plugin_basename( __FILE__ ) );

// Load composer autoloader for Action Scheduler and other dependencies.
// Note: vendor directory is inside the plugin directory for Docker mount compatibility.
if ( file_exists( NOTION_SYNC_PATH . 'vendor/autoload.php' ) ) {
	require_once NOTION_SYNC_PATH . 'vendor/autoload.php';
}

// Initialize Action Scheduler (WooCommerce library for background processing).
// This must be loaded before WordPress init to ensure proper initialization.
if ( file_exists( NOTION_SYNC_PATH . 'vendor/woocommerce/action-scheduler/action-scheduler.php' ) ) {
	require_once NOTION_SYNC_PATH . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
}

// PSR-4 autoloader for NotionSync namespace.
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

// PSR-4 autoloader for NotionWP namespace (Phase 5+ features).
spl_autoload_register(
	function ( $class_name ) {
		$prefix   = 'NotionWP\\';
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
 * Load plugin text domain for internationalization.
 *
 * Note: Since WordPress 4.6+, translations for plugins hosted on WordPress.org
 * are automatically loaded by WordPress. The load_plugin_textdomain() call
 * is no longer necessary for WordPress.org plugins.
 *
 * @see https://make.wordpress.org/core/2016/07/06/i18n-improvements-in-4-6/
 *
 * @return void
 */
function load_textdomain() {
	// WordPress.org automatically loads translations for plugins since WP 4.6+.
	// Keeping this function for potential future use or non-WordPress.org installations.
	// load_plugin_textdomain(
	//  'notion-sync',
	//  false,
	//  dirname( plugin_basename( __FILE__ ) ) . '/languages'
	// );
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\load_textdomain' );

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

	// Register Notion Image dynamic block (Phase 4).
	$notion_image_block = new Blocks\NotionImageBlock();
	$notion_image_block->register();

	// Register Notion Link shortcode for inline links.
	$notion_link_shortcode = new Blocks\NotionLinkShortcode();
	$notion_link_shortcode->register();

	// Register Database View Gutenberg block (Phase 5.3).
	$database_view_block = new \NotionWP\Blocks\DatabaseViewBlock( __FILE__ );
	$database_view_block->init();

	// Register block patterns for Notion content (Phase 6).
	$block_patterns = new Blocks\Patterns();
	$block_patterns->register();

	// Initialize hierarchy detection (Phase 5).
	$hierarchy_detector = new \NotionWP\Hierarchy\HierarchyDetector();
	$hierarchy_detector->init();

	// Initialize menu building (Phase 5).
	$menu_item_meta = new \NotionWP\Navigation\MenuItemMeta();
	$menu_builder   = new \NotionWP\Hierarchy\MenuBuilder( $menu_item_meta, $hierarchy_detector );
	$navigation_sync = new \NotionWP\Hierarchy\NavigationSync( $menu_builder, $hierarchy_detector );
	$navigation_sync->init();

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

		// Initialize menu meta box (Phase 5) - enhances WordPress native menu editor.
		$menu_meta_box = new \NotionWP\Admin\MenuMetaBox( $menu_item_meta );
		$menu_meta_box->register();

		// Initialize navigation AJAX handler (Phase 5).
		$navigation_ajax = new \NotionWP\Admin\NavigationAjaxHandler();
		$navigation_ajax->register();
	}

	// Register REST API endpoints.
	add_action(
		'rest_api_init',
		function () {
			$rest_controller = new API\DatabaseRestController();
			$rest_controller->register_routes();

			$link_rest_controller = new API\LinkRegistryRestController();
			$link_rest_controller->register_routes();

			$sync_status_controller = new API\SyncStatusRestController();
			$sync_status_controller->register_routes();
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

		// Register Image Download Handler (Phase 4).
		// Downloads images in background and registers in MediaRegistry.
		// No post content updates needed - dynamic block checks registry at render time.
		Media\ImageDownloadHandler::register_hooks();
	}

	// Register WP-CLI commands if WP-CLI is available.
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		\WP_CLI::add_command( 'notion', 'NotionSync\\CLI\\NotionCommand' );
	}

	// Add filter to prepend icon emoji to post titles.
	add_filter( 'the_title', __NAMESPACE__ . '\prepend_notion_icon_to_title', 10, 2 );

	// Enqueue frontend CSS for advanced blocks.
	add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\enqueue_frontend_assets' );

	// Enqueue block editor assets (JavaScript for block registration).
	add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\enqueue_block_editor_assets' );

	// Plugin loaded hook for extensibility.
	do_action( 'notion_sync_loaded' );
}
add_action( 'init', __NAMESPACE__ . '\init' );

/**
 * Enqueue frontend CSS for advanced Notion blocks.
 *
 * Loads styling for callout and toggle blocks converted from Notion.
 *
 * @return void
 */
function enqueue_frontend_assets(): void {
	// Enqueue callout block styles.
	wp_enqueue_style(
		'notion-sync-callout-blocks',
		NOTION_SYNC_URL . 'assets/css/callout-blocks.css',
		array(),
		NOTION_SYNC_VERSION,
		'all'
	);

	// Enqueue toggle block styles.
	wp_enqueue_style(
		'notion-sync-toggle-blocks',
		NOTION_SYNC_URL . 'assets/css/toggle-blocks.css',
		array(),
		NOTION_SYNC_VERSION,
		'all'
	);

	// Enqueue navigation pattern styles.
	wp_enqueue_style(
		'notion-sync-navigation-patterns',
		NOTION_SYNC_URL . 'assets/css/navigation-patterns.css',
		array(),
		NOTION_SYNC_VERSION,
		'all'
	);

	// Enqueue navigation pattern scripts.
	wp_enqueue_script(
		'notion-sync-navigation-patterns',
		NOTION_SYNC_URL . 'assets/js/navigation-patterns.js',
		array(),
		NOTION_SYNC_VERSION,
		true
	);
}

/**
 * Enqueue block editor JavaScript assets.
 *
 * Loads JavaScript files needed for block registration in the Gutenberg editor.
 * This includes client-side registration for dynamic blocks that are rendered server-side.
 *
 * @return void
 */
function enqueue_block_editor_assets(): void {
	// Get file path for cache busting with filemtime.
	$script_path = NOTION_SYNC_PATH . 'assets/js/blocks/notion-image-block.js';
	$script_url  = NOTION_SYNC_URL . 'assets/js/blocks/notion-image-block.js';

	// Only enqueue if file exists.
	if ( ! file_exists( $script_path ) ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging for missing assets.
		error_log( 'Notion Sync: Block editor script not found at ' . $script_path );
		return;
	}

	// Enqueue Notion Image block editor script.
	wp_enqueue_script(
		'notion-sync-image-block',
		$script_url,
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-i18n' ),
		filemtime( $script_path ),
		false
	);

	// Set script translations if available.
	if ( function_exists( 'wp_set_script_translations' ) ) {
		wp_set_script_translations(
			'notion-sync-image-block',
			'notion-sync',
			NOTION_SYNC_PATH . 'languages'
		);
	}
}

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
