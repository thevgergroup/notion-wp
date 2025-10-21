<?php
/**
 * Plugin Name: Notion Sync
 * Plugin URI: https://github.com/thevgergroup/notion-wp
 * Description: Bi-directional synchronization between Notion and WordPress
 * Version: 0.1.0-dev
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
define( 'NOTION_SYNC_VERSION', '0.1.0-dev' );
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

	// Initialize admin interface.
	if ( is_admin() ) {
		$settings_page = new Admin\SettingsPage();
		$settings_page->register();

		$admin_notices = new Admin\AdminNotices();
		$admin_notices->register();
	}

	// Register Action Scheduler hook for batch processing.
	if ( function_exists( 'as_schedule_single_action' ) ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
		error_log( 'NotionSync: Registering Action Scheduler hook for notion_sync_process_batch' );

		add_action(
			'notion_sync_process_batch',
			function ( $batch_id, $post_id, $entries, $batch_number, $total_batches ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
				error_log( sprintf(
					'NotionSync: Processing batch %d/%d for batch_id=%s, post_id=%d, entries=%d',
					$batch_number,
					$total_batches,
					$batch_id,
					$post_id,
					count( $entries )
				) );

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
				$processor->process_batch( $batch_id, $post_id, $entries, $batch_number, $total_batches );

				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
				error_log( sprintf( 'NotionSync: Completed processing batch %d/%d', $batch_number, $total_batches ) );
			},
			10,
			5
		);
	} else {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
		error_log( 'NotionSync: WARNING - Action Scheduler not available, hook not registered' );
	}

	// Plugin loaded hook for extensibility.
	do_action( 'notion_sync_loaded' );
}
add_action( 'init', __NAMESPACE__ . '\init' );

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

	// Register database CPT before flushing rewrite rules.
	$database_cpt = new \NotionSync\Database\DatabasePostType();
	$database_cpt->register();

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
