<?php
/**
 * PHPStan Bootstrap File
 *
 * This file is loaded by PHPStan to provide WordPress function definitions
 * and prevent false positives for undefined functions.
 *
 * @package Notion_WP
 */

// Load WordPress stubs if available
if ( file_exists( __DIR__ . '/../vendor/php-stubs/wordpress-stubs/wordpress-stubs.php' ) ) {
	require_once __DIR__ . '/../vendor/php-stubs/wordpress-stubs/wordpress-stubs.php';
}

// Load WP-CLI stubs if available
if ( file_exists( __DIR__ . '/../vendor/php-stubs/wp-cli-stubs/wp-cli-stubs.php' ) ) {
	require_once __DIR__ . '/../vendor/php-stubs/wp-cli-stubs/wp-cli-stubs.php';
}

// Define plugin constants if not already defined
if ( ! defined( 'NOTION_WP_VERSION' ) ) {
	define( 'NOTION_WP_VERSION', '0.1.0' );
}

if ( ! defined( 'NOTION_WP_PLUGIN_FILE' ) ) {
	define( 'NOTION_WP_PLUGIN_FILE', __DIR__ . '/../plugin/notion-wp.php' );
}

if ( ! defined( 'NOTION_WP_PLUGIN_DIR' ) ) {
	define( 'NOTION_WP_PLUGIN_DIR', dirname( NOTION_WP_PLUGIN_FILE ) );
}

if ( ! defined( 'NOTION_WP_PLUGIN_URL' ) ) {
	define( 'NOTION_WP_PLUGIN_URL', 'http://localhost/wp-content/plugins/notion-wp/' );
}

// Common WordPress constants
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/var/www/html/' );
}

if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
}

if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
	define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );
}

if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', true );
}
