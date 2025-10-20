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

// PSR-4 autoloader.
spl_autoload_register(
	function ( $class ) {
		$prefix   = 'NotionSync\\';
		$base_dir = __DIR__ . '/src/';

		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class, $len );
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
	// Load text domain for translations.
	load_plugin_textdomain( 'notion-wp', false, dirname( NOTION_SYNC_BASENAME ) . '/languages' );

	// Initialize admin interface.
	if ( is_admin() ) {
		$settings_page = new Admin\SettingsPage();
		$settings_page->register();

		$admin_notices = new Admin\AdminNotices();
		$admin_notices->register();
	}

	// Plugin loaded hook for extensibility.
	do_action( 'notion_sync_loaded' );
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\init' );

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
