<?php
/**
 * Test REST API Registration
 *
 * Run this from the plugin directory to test if REST API routes are registered.
 */

// Load WordPress
require_once __DIR__ . '/../../../wp-load.php';

// Get REST server
$rest_server = rest_get_server();

// Get all registered routes
$routes = $rest_server->get_routes();

echo "Looking for notion-sync REST routes...\n\n";

$found = false;
foreach ( $routes as $route => $handlers ) {
	if ( strpos( $route, 'notion-sync' ) !== false ) {
		$found = true;
		echo "Found: $route\n";
		foreach ( $handlers as $handler ) {
			$methods = implode( ', ', $handler['methods'] );
			echo "  Methods: $methods\n";

			if ( isset( $handler['callback'] ) && is_array( $handler['callback'] ) ) {
				$class = is_object( $handler['callback'][0] ) ? get_class( $handler['callback'][0] ) : $handler['callback'][0];
				$method = $handler['callback'][1];
				echo "  Callback: {$class}::{$method}()\n";
			}
		}
		echo "\n";
	}
}

if ( ! $found ) {
	echo "❌ No notion-sync routes found!\n\n";
	echo "Checking if SyncStatusRestController class exists...\n";

	if ( class_exists( 'NotionSync\\API\\SyncStatusRestController' ) ) {
		echo "✅ SyncStatusRestController class exists\n";

		// Try to instantiate it
		try {
			$controller = new \NotionSync\API\SyncStatusRestController();
			echo "✅ Controller instantiated successfully\n";

			// Try to register routes
			$controller->register_routes();
			echo "✅ register_routes() called successfully\n";

			// Check again
			$routes = rest_get_server()->get_routes();
			foreach ( $routes as $route => $handlers ) {
				if ( strpos( $route, 'notion-sync' ) !== false ) {
					echo "✅ Route registered: $route\n";
				}
			}
		} catch ( \Exception $e ) {
			echo "❌ Error: " . $e->getMessage() . "\n";
		}
	} else {
		echo "❌ SyncStatusRestController class not found\n";
	}
} else {
	echo "✅ Found " . count( array_filter( array_keys( $routes ), function( $r ) {
		return strpos( $r, 'notion-sync' ) !== false;
	} ) ) . " notion-sync routes\n";
}
