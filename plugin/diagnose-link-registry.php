<?php
/**
 * Diagnostic script for Link Registry routing issues
 *
 * Run with: make wp ARGS="eval-file /var/www/html/wp-content/plugins/notion-sync/diagnose-link-registry.php"
 */

// WordPress is already loaded when run via WP-CLI eval-file

global $wpdb;

echo "=== LINK REGISTRY DIAGNOSTIC REPORT ===\n\n";

// 1. Query all link registry entries
echo "1. ALL LINK REGISTRY ENTRIES:\n";
echo str_repeat('-', 80) . "\n";
$links = $wpdb->get_results(
    "SELECT id, notion_id, notion_title, notion_type, wp_post_id, wp_post_type, slug, sync_status
     FROM {$wpdb->prefix}notion_links
     ORDER BY updated_at DESC",
    ARRAY_A
);

if (empty($links)) {
    echo "No entries found in wp_notion_links table!\n";
} else {
    foreach ($links as $link) {
        printf(
            "ID: %d | Notion ID: %s | Title: %s\nType: %s | WP Post ID: %s | WP Post Type: %s\nSlug: %s | Sync Status: %s\n\n",
            $link['id'],
            $link['notion_id'],
            substr($link['notion_title'], 0, 50),
            $link['notion_type'],
            $link['wp_post_id'] ?: 'NULL',
            $link['wp_post_type'] ?: 'NULL',
            $link['slug'] ?: 'NULL',
            $link['sync_status']
        );
    }
}

// 2. Check post 14 specifically
echo "\n2. POST 14 DETAILS:\n";
echo str_repeat('-', 80) . "\n";
$post_14 = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}posts WHERE ID = 14", ARRAY_A);
if ($post_14) {
    printf(
        "ID: %d | Title: %s\nSlug: %s | Type: %s\nStatus: %s | Date: %s\n",
        $post_14['ID'],
        $post_14['post_title'],
        $post_14['post_name'],
        $post_14['post_type'],
        $post_14['post_status'],
        $post_14['post_date']
    );
} else {
    echo "Post 14 not found!\n";
}

// 3. Check if post 14 has a registry entry
echo "\n3. POST 14 REGISTRY ENTRY:\n";
echo str_repeat('-', 80) . "\n";
$post_14_link = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}notion_links WHERE wp_post_id = %d",
        14
    ),
    ARRAY_A
);
if ($post_14_link) {
    print_r($post_14_link);
} else {
    echo "Post 14 has NO entry in wp_notion_links table! ❌\n";
}

// 4. Check the database link entry
echo "\n4. DATABASE LINK ENTRY (4349fe02a27240808b8c73ecbe2d962e):\n";
echo str_repeat('-', 80) . "\n";
$db_link = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}notion_links WHERE notion_id = %s",
        '4349fe02a27240808b8c73ecbe2d962e'
    ),
    ARRAY_A
);
if ($db_link) {
    print_r($db_link);
    echo "\nExpected wp_post_type: notion_database\n";
    echo "Actual wp_post_type: " . ($db_link['wp_post_type'] ?: 'NULL') . "\n";
} else {
    echo "Database link entry not found! ❌\n";
}

// 5. Check recent synced posts
echo "\n5. RECENT SYNCED POSTS:\n";
echo str_repeat('-', 80) . "\n";
$recent_posts = $wpdb->get_results(
    "SELECT ID, post_title, post_type, post_date
     FROM {$wpdb->prefix}posts
     WHERE post_type = 'post'
     ORDER BY post_date DESC
     LIMIT 10",
    ARRAY_A
);
foreach ($recent_posts as $post) {
    printf("ID: %d | %s | %s\n", $post['ID'], $post['post_title'], $post['post_date']);
}

// 6. Check Notion page IDs in post meta
echo "\n6. NOTION PAGE IDs IN POST META:\n";
echo str_repeat('-', 80) . "\n";
$post_meta = $wpdb->get_results(
    "SELECT post_id, meta_key, meta_value
     FROM {$wpdb->prefix}postmeta
     WHERE meta_key = 'notion_page_id'
     AND post_id IN (
         SELECT ID FROM {$wpdb->prefix}posts
         WHERE post_type = 'post'
         ORDER BY post_date DESC
         LIMIT 10
     )",
    ARRAY_A
);

if (empty($post_meta)) {
    echo "No notion_page_id meta found for recent posts! ❌\n";
} else {
    foreach ($post_meta as $meta) {
        printf("Post ID: %d | Notion ID: %s\n", $meta['post_id'], $meta['meta_value']);

        // Check if this notion_page_id exists in link registry
        $registry_entry = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, sync_status FROM {$wpdb->prefix}notion_links WHERE notion_id = %s",
                $meta['meta_value']
            ),
            ARRAY_A
        );

        if ($registry_entry) {
            printf("  ✓ Has registry entry (ID: %d, Status: %s)\n", $registry_entry['id'], $registry_entry['sync_status']);
        } else {
            printf("  ❌ NO registry entry for this Notion ID!\n");
        }
    }
}

// 7. Check for orphaned registry entries (notion_id exists but wp_post_id is NULL for synced posts)
echo "\n7. ORPHANED REGISTRY ENTRIES:\n";
echo str_repeat('-', 80) . "\n";
$orphaned = $wpdb->get_results(
    "SELECT notion_id, notion_title, notion_type, sync_status
     FROM {$wpdb->prefix}notion_links
     WHERE wp_post_id IS NULL
     AND notion_type = 'page'
     AND sync_status = 'synced'",
    ARRAY_A
);

if (empty($orphaned)) {
    echo "No orphaned entries found.\n";
} else {
    echo "Found " . count($orphaned) . " synced pages with NULL wp_post_id! ❌\n";
    foreach ($orphaned as $entry) {
        printf("Notion ID: %s | Title: %s | Status: %s\n",
            $entry['notion_id'],
            substr($entry['notion_title'], 0, 50),
            $entry['sync_status']
        );
    }
}

// 8. Root cause analysis
echo "\n=== ROOT CAUSE ANALYSIS ===\n";
echo str_repeat('=', 80) . "\n";

$issues = [];

// Check if synced posts are being registered
$synced_posts_count = $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->prefix}postmeta WHERE meta_key = 'notion_page_id'"
);
$registry_count = $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->prefix}notion_links WHERE notion_type = 'page'"
);

echo "Posts with notion_page_id meta: $synced_posts_count\n";
echo "Page entries in link registry: $registry_count\n";

if ($synced_posts_count > $registry_count) {
    $issues[] = "CRITICAL: Synced posts are not being registered in wp_notion_links!";
}

// Check database entry
if (!$db_link) {
    $issues[] = "CRITICAL: Database link entry missing from registry!";
} elseif ($db_link['wp_post_type'] !== 'notion_database') {
    $issues[] = "CRITICAL: Database link has wrong wp_post_type: " . $db_link['wp_post_type'];
}

// Check post 14
if ($post_14 && !$post_14_link) {
    $issues[] = "CRITICAL: Post 14 exists but has no registry entry!";

    // Check if post 14 has notion_page_id meta
    $post_14_notion_id = get_post_meta(14, 'notion_page_id', true);
    if ($post_14_notion_id) {
        echo "Post 14 has notion_page_id meta: $post_14_notion_id\n";
        $issues[] = "Post 14 was synced but SyncManager did not call LinkRegistry::register()";
    }
}

if (empty($issues)) {
    echo "\n✓ No critical issues detected.\n";
} else {
    echo "\n🚨 ISSUES FOUND:\n";
    foreach ($issues as $i => $issue) {
        echo ($i + 1) . ". " . $issue . "\n";
    }
}

echo "\n=== RECOMMENDED FIXES ===\n";
echo str_repeat('=', 80) . "\n";
echo "1. Verify SyncManager::sync_page() calls LinkRegistry::register() after creating post\n";
echo "2. Verify LinkRegistry::register() updates wp_post_id and sync_status correctly\n";
echo "3. Add error logging to LinkRegistry::register() to catch failures\n";
echo "4. Re-register database entry with correct wp_post_type\n";
echo "5. Run a one-time script to backfill missing registry entries for synced posts\n";

echo "\n=== END DIAGNOSTIC REPORT ===\n";
