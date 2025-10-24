<?php
/**
 * Re-sync test for post 8 to test image fix
 */

require_once '/var/www/html/wp-load.php';

echo "=== Re-sync Test for Image Fix ===\n\n";

// Check post meta
echo "Step 1: Checking post 8 meta...\n";
$meta = get_post_meta(8);
$notion_keys = array_filter(array_keys($meta), function($key) {
    return strpos($key, 'notion') !== false || strpos($key, 'sync') !== false;
});

if (!empty($notion_keys)) {
    foreach ($notion_keys as $key) {
        echo "  $key = " . print_r($meta[$key][0], true) . "\n";
    }
} else {
    echo "  No Notion meta found. Checking all meta...\n";
    foreach ($meta as $key => $value) {
        if (substr($key, 0, 1) !== '_') continue;
        echo "  $key\n";
    }
}

echo "\n";

// Get the Notion page ID - try different meta key variations
$notion_page_id = get_post_meta(8, '_notion_page_id', true);
if (empty($notion_page_id)) {
    $notion_page_id = get_post_meta(8, 'notion_page_id', true);
}

if (empty($notion_page_id)) {
    echo "✗ Error: No Notion page ID found for post 8\n";
    echo "Cannot re-sync without Notion page ID.\n";
    echo "\nPlease sync manually from: http://phase3.localtest.me/wp-admin/admin.php?page=notion-wp-settings\n";
    exit(1);
}

echo "Step 2: Found Notion page ID: $notion_page_id\n\n";
echo "Step 3: Re-syncing with the fix applied...\n";

try {
    $manager = new \NotionSync\Sync\SyncManager();
    $result = $manager->sync_page($notion_page_id);

    if ($result['success']) {
        echo "✓ Sync successful!\n";
        echo "  Post ID: {$result['post_id']}\n\n";

        // Check if image appears in content
        echo "Step 4: Checking for image in content...\n";
        $post = get_post($result['post_id']);
        $content = $post->post_content;

        if (strpos($content, 'Image conversion failed') !== false) {
            echo "✗ Image conversion still failing!\n";
            echo "  Error message still present in content.\n";
        } else if (strpos($content, 'wp:image') !== false) {
            echo "✓ Image block found in content!\n";
            echo "  Fix appears to be working!\n";
        } else {
            echo "? No image blocks found in content.\n";
            echo "  (Image may not be in this page, or might be external URL)\n";
        }
    } else {
        echo "✗ Sync failed: " . ($result['error'] ?? 'Unknown error') . "\n";
    }
} catch (Exception $e) {
    echo "✗ Exception: " . $e->getMessage() . "\n";
    echo "  Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";
