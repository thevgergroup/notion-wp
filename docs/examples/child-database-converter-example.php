<?php
/**
 * Example: ChildDatabaseConverter Usage
 *
 * This example demonstrates how the updated ChildDatabaseConverter automatically
 * creates database-view blocks when a Notion database has been synced to WordPress.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Examples;

use NotionSync\Blocks\Converters\ChildDatabaseConverter;
use NotionSync\Database\DatabasePostType;

/**
 * Example 1: Database is synced to WordPress
 *
 * When a Notion database has been synced and exists as a notion_database post,
 * the converter creates an interactive notion-wp/database-view block.
 */
function example_synced_database() {
	echo "=== Example 1: Synced Database ===\n\n";

	// Simulated Notion block data for a child_database
	$notion_block = array(
		'object'         => 'block',
		'id'             => '2654dac9-b96e-808a-b3b7-ffb185d4fd92',
		'type'           => 'child_database',
		'child_database' => array(
			'title' => 'My Project Tasks',
		),
		'created_time'   => '2024-01-15T10:00:00.000Z',
		'last_edited_time' => '2024-01-15T10:00:00.000Z',
	);

	// Assume this database has been synced and exists as WordPress post ID 6
	// (In real usage, this would be looked up via DatabasePostType::find_by_notion_id)

	$converter = new ChildDatabaseConverter();

	// Convert the block
	$gutenberg_block = $converter->convert( $notion_block );

	echo "Input: Notion child_database block\n";
	echo "Database ID: {$notion_block['id']}\n";
	echo "Title: {$notion_block['child_database']['title']}\n\n";

	echo "Output: Gutenberg block\n";
	echo $gutenberg_block;

	echo "\nExpected output:\n";
	echo "<!-- wp:notion-wp/database-view {\"databaseId\":6,\"viewType\":\"table\",\"showFilters\":true,\"showExport\":true} /-->\n\n";

	echo "Result: Interactive Tabulator table will be rendered on the frontend\n";
	echo "with filtering, sorting, and CSV export capabilities.\n\n";
}

/**
 * Example 2: Database is NOT synced to WordPress
 *
 * When a Notion database has not been synced yet, the converter creates
 * a notion-sync/notion-link block that links to Notion.
 */
function example_unsynced_database() {
	echo "=== Example 2: Unsynced Database ===\n\n";

	// Simulated Notion block data
	$notion_block = array(
		'object'         => 'block',
		'id'             => '3a45b6c7-d890-1234-5678-abcdef123456',
		'type'           => 'child_database',
		'child_database' => array(
			'title' => 'External Database',
		),
		'created_time'   => '2024-01-15T10:00:00.000Z',
		'last_edited_time' => '2024-01-15T10:00:00.000Z',
	);

	// This database has NOT been synced
	// DatabasePostType::find_by_notion_id() returns null

	$converter = new ChildDatabaseConverter();
	$gutenberg_block = $converter->convert( $notion_block );

	echo "Input: Notion child_database block\n";
	echo "Database ID: {$notion_block['id']}\n";
	echo "Title: {$notion_block['child_database']['title']}\n\n";

	echo "Output: Gutenberg block\n";
	echo $gutenberg_block;

	echo "\nExpected output:\n";
	echo "<!-- wp:notion-sync/notion-link {\"notionId\":\"3a45b6c7d890123456789abcdef123456\",\"showIcon\":true,\"openInNewTab\":true} /-->\n\n";

	echo "Result: A link to the database in Notion will be rendered.\n";
	echo "The link text and URL will be fetched from LinkRegistry at render time.\n\n";
}

/**
 * Example 3: Converting a page with multiple databases
 *
 * Shows how the converter handles a page containing both synced and unsynced databases.
 */
function example_mixed_databases() {
	echo "=== Example 3: Page with Multiple Databases ===\n\n";

	$notion_blocks = array(
		// Synced database
		array(
			'id'             => '2654dac9-b96e-808a-b3b7-ffb185d4fd92',
			'type'           => 'child_database',
			'child_database' => array( 'title' => 'Synced Tasks' ),
		),
		// Unsynced database
		array(
			'id'             => '9876fedc-ba98-7654-3210-fedcba987654',
			'type'           => 'child_database',
			'child_database' => array( 'title' => 'External Contacts' ),
		),
		// Another synced database
		array(
			'id'             => '1111aaaa-2222-bbbb-3333-ccccddddeeee',
			'type'           => 'child_database',
			'child_database' => array( 'title' => 'Synced Inventory' ),
		),
	);

	$converter = new ChildDatabaseConverter();

	echo "Converting page with 3 databases...\n\n";

	foreach ( $notion_blocks as $index => $block ) {
		$result = $converter->convert( $block );

		echo "Database " . ( $index + 1 ) . ": {$block['child_database']['title']}\n";

		// Check which type of block was created
		if ( strpos( $result, 'notion-wp/database-view' ) !== false ) {
			echo "  → Created: database-view block (synced)\n";
			preg_match( '/\"databaseId\":(\d+)/', $result, $matches );
			if ( isset( $matches[1] ) ) {
				echo "  → WordPress Post ID: {$matches[1]}\n";
			}
		} elseif ( strpos( $result, 'notion-sync/notion-link' ) !== false ) {
			echo "  → Created: notion-link block (not synced)\n";
			preg_match( '/\"notionId\":\"([^\"]+)\"/', $result, $matches );
			if ( isset( $matches[1] ) ) {
				echo "  → Links to Notion ID: {$matches[1]}\n";
			}
		}
		echo "\n";
	}

	echo "Benefits:\n";
	echo "- Synced databases show interactive tables directly in WordPress\n";
	echo "- Unsynced databases show links that can be clicked to view in Notion\n";
	echo "- No manual configuration needed - converter detects sync status automatically\n\n";
}

/**
 * Example 4: Testing the conversion logic
 */
function example_test_conversion() {
	echo "=== Example 4: Testing Conversion Logic ===\n\n";

	// Test data
	$test_cases = array(
		array(
			'name'   => 'Valid synced database',
			'block'  => array(
				'id'             => '2654dac9-b96e-808a-b3b7-ffb185d4fd92',
				'type'           => 'child_database',
				'child_database' => array( 'title' => 'Test DB' ),
			),
			'expect' => 'notion-wp/database-view',
		),
		array(
			'name'   => 'Missing database ID',
			'block'  => array(
				'type'           => 'child_database',
				'child_database' => array( 'title' => 'Test DB' ),
			),
			'expect' => 'wp:paragraph',
		),
		array(
			'name'   => 'Missing title',
			'block'  => array(
				'id'             => 'test-id',
				'type'           => 'child_database',
				'child_database' => array(),
			),
			'expect' => 'notion-sync/notion-link',
		),
	);

	$converter = new ChildDatabaseConverter();

	foreach ( $test_cases as $test ) {
		echo "Test: {$test['name']}\n";

		$result = $converter->convert( $test['block'] );

		if ( strpos( $result, $test['expect'] ) !== false ) {
			echo "  ✓ PASS - Contains expected block type: {$test['expect']}\n";
		} else {
			echo "  ✗ FAIL - Expected {$test['expect']}, got:\n";
			echo "    " . trim( $result ) . "\n";
		}
		echo "\n";
	}
}

// Run examples if this file is executed directly
if ( ! defined( 'WPINC' ) ) {
	echo "Note: These examples are for demonstration purposes.\n";
	echo "In a real WordPress environment, the converter would be called\n";
	echo "during the sync process by the BlockConverter registry.\n\n";

	// Uncomment to run examples:
	// example_synced_database();
	// example_unsynced_database();
	// example_mixed_databases();
	// example_test_conversion();
}
