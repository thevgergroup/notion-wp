<?php
/**
 * Check what icon and cover the Notion page has
 */

require_once '/var/www/html/wp-load.php';

echo "=== Checking Notion Page Properties ===\n\n";

// Get Notion page ID from post meta
$notion_page_id = get_post_meta(8, 'notion_page_id', true);

if (empty($notion_page_id)) {
    echo "Error: No Notion page ID found.\n";
    exit(1);
}

echo "Notion Page ID: $notion_page_id\n\n";

// Fetch page properties
$manager = new \NotionSync\Sync\SyncManager();
$encrypted_token = get_option('notion_wp_token');
$token = \NotionSync\Security\Encryption::decrypt($encrypted_token);
$client = new \NotionSync\API\NotionClient($token);
$fetcher = new \NotionSync\Sync\ContentFetcher($client);

$properties = $fetcher->fetch_page_properties($notion_page_id);

echo "Icon:\n";
if (isset($properties['icon']) && $properties['icon']) {
    echo "  Type: " . ($properties['icon']['type'] ?? 'unknown') . "\n";
    if ($properties['icon']['type'] === 'emoji') {
        echo "  Emoji: " . ($properties['icon']['emoji'] ?? 'none') . "\n";
    } elseif ($properties['icon']['type'] === 'external') {
        echo "  URL: " . ($properties['icon']['external']['url'] ?? 'none') . "\n";
    } elseif ($properties['icon']['type'] === 'file') {
        echo "  URL: " . ($properties['icon']['file']['url'] ?? 'none') . "\n";
    }
    echo "  Raw data: " . json_encode($properties['icon'], JSON_PRETTY_PRINT) . "\n";
} else {
    echo "  No icon set\n";
}

echo "\n";

echo "Cover:\n";
if (isset($properties['cover']) && $properties['cover']) {
    echo "  Type: " . ($properties['cover']['type'] ?? 'unknown') . "\n";
    if ($properties['cover']['type'] === 'external') {
        echo "  URL: " . ($properties['cover']['external']['url'] ?? 'none') . "\n";
    } elseif ($properties['cover']['type'] === 'file') {
        echo "  URL: " . ($properties['cover']['file']['url'] ?? 'none') . "\n";
    }
    echo "  Raw data: " . json_encode($properties['cover'], JSON_PRETTY_PRINT) . "\n";
} else {
    echo "  No cover image set\n";
}

echo "\n=== Done ===\n";
