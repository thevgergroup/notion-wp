<?php
/**
 * Test Dynamic Block Rendering
 *
 * This script tests if the notion-sync/notion-image dynamic block's render_callback
 * is being invoked properly.
 *
 * Usage:
 * 1. Place this file in the plugin directory
 * 2. Run via WP-CLI: wp eval-file test-dynamic-block.php
 * 3. Or access via browser (ensure WordPress is loaded)
 *
 * @package NotionSync
 */

// Load WordPress if not already loaded.
if ( ! defined( 'ABSPATH' ) ) {
	require_once __DIR__ . '/../../../wp-load.php';
}

echo "=== Testing Dynamic Block Rendering ===\n\n";

// Test 1: Verify block is registered.
echo "Test 1: Check if block is registered...\n";
$registry = WP_Block_Type_Registry::get_instance();
if ( $registry->is_registered( 'notion-sync/notion-image' ) ) {
	echo "✓ Block 'notion-sync/notion-image' is registered\n";
	$block_type = $registry->get_registered( 'notion-sync/notion-image' );
	echo "  - Has render_callback: " . ( $block_type->render_callback ? 'YES' : 'NO' ) . "\n";
	echo "  - API Version: " . $block_type->api_version . "\n";
	echo "  - Attributes: " . print_r( array_keys( $block_type->attributes ), true ) . "\n";
} else {
	echo "✗ Block 'notion-sync/notion-image' is NOT registered\n";
	echo "  Available blocks: " . implode( ', ', array_keys( $registry->get_all_registered() ) ) . "\n";
}

echo "\n";

// Test 2: Create sample block markup and test parsing.
echo "Test 2: Test block markup parsing...\n";

// Test with empty content (original format).
$empty_content_markup = '<!-- wp:notion-sync/notion-image {"blockId":"test-123","notionUrl":"https://example.com/image.jpg","caption":"Test Caption","altText":"Test Alt"} -->
<!-- /wp:notion-sync/notion-image -->';

echo "Testing empty content format:\n";
$parsed_empty = parse_blocks( $empty_content_markup );
echo "  - Parsed blocks count: " . count( $parsed_empty ) . "\n";
if ( ! empty( $parsed_empty[0] ) ) {
	echo "  - Block name: " . ( $parsed_empty[0]['blockName'] ?? 'NULL' ) . "\n";
	echo "  - Attributes: " . wp_json_encode( $parsed_empty[0]['attrs'] ?? array() ) . "\n";
	echo "  - Inner content: " . wp_json_encode( $parsed_empty[0]['innerHTML'] ?? '' ) . "\n";
}

echo "\n";

// Test with paragraph placeholder (new format).
$placeholder_content_markup = '<!-- wp:notion-sync/notion-image {"blockId":"test-123","notionUrl":"https://example.com/image.jpg","caption":"Test Caption","altText":"Test Alt"} -->
<p></p>
<!-- /wp:notion-sync/notion-image -->';

echo "Testing placeholder content format:\n";
$parsed_placeholder = parse_blocks( $placeholder_content_markup );
echo "  - Parsed blocks count: " . count( $parsed_placeholder ) . "\n";
if ( ! empty( $parsed_placeholder[0] ) ) {
	echo "  - Block name: " . ( $parsed_placeholder[0]['blockName'] ?? 'NULL' ) . "\n";
	echo "  - Attributes: " . wp_json_encode( $parsed_placeholder[0]['attrs'] ?? array() ) . "\n";
	echo "  - Inner content: " . wp_json_encode( $parsed_placeholder[0]['innerHTML'] ?? '' ) . "\n";
}

echo "\n";

// Test 3: Test render_block function directly.
echo "Test 3: Test render_block output...\n";

echo "Rendering empty content block:\n";
$rendered_empty = render_block( $parsed_empty[0] );
echo "  - Rendered length: " . strlen( $rendered_empty ) . "\n";
echo "  - Rendered output:\n" . $rendered_empty . "\n";

echo "\n";

echo "Rendering placeholder content block:\n";
$rendered_placeholder = render_block( $parsed_placeholder[0] );
echo "  - Rendered length: " . strlen( $rendered_placeholder ) . "\n";
echo "  - Rendered output:\n" . $rendered_placeholder . "\n";

echo "\n";

// Test 4: Test do_blocks on full content.
echo "Test 4: Test do_blocks on full content...\n";

echo "Processing empty content markup through do_blocks:\n";
$processed_empty = do_blocks( $empty_content_markup );
echo "  - Output length: " . strlen( $processed_empty ) . "\n";
echo "  - Output:\n" . $processed_empty . "\n";

echo "\n";

echo "Processing placeholder content markup through do_blocks:\n";
$processed_placeholder = do_blocks( $placeholder_content_markup );
echo "  - Output length: " . strlen( $processed_placeholder ) . "\n";
echo "  - Output:\n" . $processed_placeholder . "\n";

echo "\n=== Test Complete ===\n";
echo "Check error_log for render_block debug messages\n";
