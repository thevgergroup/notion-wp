# ContentFetcher Testing Guide

## Overview

The ContentFetcher class (`plugin/src/Sync/ContentFetcher.php`) fetches pages and blocks from the Notion API with automatic pagination handling. This guide covers how to test it.

## Prerequisites

- WordPress installation with Notion Sync plugin active
- Valid Notion API token configured in plugin settings
- At least one Notion page shared with your integration

## Testing Methods

### Method 1: WordPress Admin UI (Recommended)

1. Navigate to WordPress Admin > Notion Sync Settings
2. Verify connection shows "Connected to [Workspace Name]"
3. Use the "Test Connection" button to verify API access

### Method 2: Command Line Test Script

Run the provided test script:

```bash
php test-content-fetcher.php
```

This will test all ContentFetcher methods:

- `fetch_pages_list()` - List accessible pages
- `fetch_page_properties()` - Get page metadata
- `fetch_page_blocks()` - Fetch all blocks with pagination
- Error handling with invalid page IDs

### Method 3: WordPress Plugin Integration

Add this code to your theme's `functions.php` temporarily:

```php
add_action('init', function() {
    if (!isset($_GET['test_fetcher'])) {
        return;
    }

    $token = get_option('notion_sync_api_token');
    $client = new \NotionSync\API\NotionClient($token);
    $fetcher = new \NotionSync\Sync\ContentFetcher($client);

    // Test fetch pages list
    $pages = $fetcher->fetch_pages_list(5);
    echo '<h2>Pages Found: ' . count($pages) . '</h2>';

    if (!empty($pages)) {
        $page_id = $pages[0]['id'];

        // Test fetch properties
        $props = $fetcher->fetch_page_properties($page_id);
        echo '<h3>Page Properties:</h3>';
        echo '<pre>' . print_r($props, true) . '</pre>';

        // Test fetch blocks
        $blocks = $fetcher->fetch_page_blocks($page_id);
        echo '<h3>Blocks Found: ' . count($blocks) . '</h3>';
        echo '<pre>' . print_r(array_slice($blocks, 0, 3), true) . '</pre>';
    }

    die();
});
```

Then visit: `https://your-site.test/?test_fetcher`

## Test Cases

### Test Case 1: Fetch Pages List

**Expected Behavior:**

- Returns array of page objects
- Each page has: id, title, last_edited_time, created_time, url
- Respects limit parameter (max 100)
- Returns empty array on error (not exception)

**Test:**

```php
$pages = $fetcher->fetch_pages_list(10);
assert(is_array($pages));
assert(count($pages) <= 10);
```

### Test Case 2: Fetch Page Properties

**Expected Behavior:**

- Returns page metadata for valid page ID
- Includes: id, title, created_time, last_edited_time, url, properties
- Handles both dashed and non-dashed page IDs
- Returns empty array for invalid page ID
- Logs errors to PHP error log

**Test:**

```php
$props = $fetcher->fetch_page_properties('valid-page-id');
assert(!empty($props));
assert(isset($props['id']));
assert(isset($props['title']));

$invalid = $fetcher->fetch_page_properties('invalid-id');
assert(empty($invalid));
```

### Test Case 3: Fetch Page Blocks (< 100 blocks)

**Expected Behavior:**

- Returns all blocks from page
- Blocks in Notion's native JSON format
- Single API request for pages with <100 blocks
- Returns empty array if page has no content

**Test:**

```php
$blocks = $fetcher->fetch_page_blocks('page-with-few-blocks');
assert(is_array($blocks));
assert(count($blocks) < 100);
foreach ($blocks as $block) {
    assert(isset($block['type']));
    assert(isset($block['id']));
}
```

### Test Case 4: Fetch Page Blocks (> 100 blocks) - Pagination

**Expected Behavior:**

- Makes multiple API requests automatically
- Combines all blocks into single array
- Respects safety limit (5000 blocks max)
- Logs warning if safety limit reached

**Test:**

```php
// Use a Notion page with 150+ blocks
$blocks = $fetcher->fetch_page_blocks('large-page-id');
assert(count($blocks) > 100);
assert(count($blocks) <= 5000);
```

### Test Case 5: Error Handling

**Expected Behavior:**

- Invalid page ID returns empty array
- Network errors return empty array
- Errors logged to error_log
- No exceptions thrown to calling code

**Test:**

```php
$result = $fetcher->fetch_page_blocks('definitely-not-a-valid-id');
assert(is_array($result));
assert(empty($result));
// Check error_log for message
```

## Pagination Test Setup

To test pagination, create a Notion page with 100+ blocks:

1. Create new page in Notion
2. Add 150+ paragraphs (use duplicate block feature)
3. Share page with your integration
4. Get page ID from URL: `https://notion.so/Page-Title-<PAGE_ID>`
5. Run: `$fetcher->fetch_page_blocks('PAGE_ID')`

Expected: All 150+ blocks returned in single array.

## Block Structure Validation

Verify returned blocks match Notion API format:

```php
$blocks = $fetcher->fetch_page_blocks($page_id);
$first_block = $blocks[0];

// Common block properties
assert(isset($first_block['object'])); // Should be "block"
assert(isset($first_block['id']));
assert(isset($first_block['type'])); // paragraph, heading_1, etc.
assert(isset($first_block['created_time']));
assert(isset($first_block['last_edited_time']));
assert(isset($first_block['has_children']));

// Type-specific content
assert(isset($first_block[$first_block['type']]));
```

## Common Block Types to Test

Create a test page with these block types:

- `paragraph` - Basic text
- `heading_1`, `heading_2`, `heading_3` - Headings
- `bulleted_list_item` - Bullet lists
- `numbered_list_item` - Numbered lists
- `to_do` - Checkboxes
- `image` - Images with captions
- `code` - Code blocks
- `quote` - Quotes
- `callout` - Callouts with icons
- `toggle` - Toggle blocks
- `divider` - Horizontal rules
- `table` - Tables

## Performance Testing

Test with pages of varying sizes:

| Page Size                    | Expected Behavior           |
| ---------------------------- | --------------------------- |
| Empty (0 blocks)             | Returns `[]` immediately    |
| Small (1-50 blocks)          | Single API call, <1 second  |
| Medium (51-100 blocks)       | Single API call, <2 seconds |
| Large (101-500 blocks)       | 2-5 API calls, <5 seconds   |
| Very Large (501-1000 blocks) | 6-10 API calls, <10 seconds |

## Error Log Monitoring

Enable WordPress debugging in `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Monitor `/wp-content/debug.log` for:

- `NotionSync: fetch_pages_list returned non-array response`
- `NotionSync: fetch_page_properties called with empty page_id`
- `NotionSync: Failed to fetch page properties for [ID]`
- `NotionSync: Error fetching blocks batch for page [ID]`
- `NotionSync: Reached maximum batch limit (50) for page [ID]`

## Integration with Stream 3 (SyncManager)

Stream 3 will use ContentFetcher like this:

```php
// Initialize
$client = new NotionClient($token);
$fetcher = new ContentFetcher($client);

// Get pages to sync
$pages = $fetcher->fetch_pages_list(100);

foreach ($pages as $page_summary) {
    // Get full page data
    $page = $fetcher->fetch_page_properties($page_summary['id']);

    // Get all content blocks
    $blocks = $fetcher->fetch_page_blocks($page_summary['id']);

    // Pass to BlockConverter (Stream 2)
    // Pass to SyncManager (Stream 3)
}
```

## Known Limitations

1. **Maximum blocks per page**: 5,000 (50 batches × 100 blocks)
    - Safety limit to prevent infinite loops
    - Logs warning if limit reached
    - Typical Notion pages have <1,000 blocks

2. **Nested blocks**: Not automatically fetched
    - `has_children` flag indicates nested content
    - Stream 2 (BlockConverter) may need recursive fetching

3. **API rate limits**: ~50 requests/second
    - ContentFetcher does not implement throttling
    - Stream 3 (SyncManager) should handle rate limiting

4. **Media URLs**: Time-limited S3 URLs
    - Image/file URLs expire after ~1 hour
    - Download immediately in Stream 4 (MediaImporter)

## Success Criteria

- ✅ All 3 public methods implemented
- ✅ Pagination works for 100+ blocks
- ✅ Error handling returns empty arrays (no exceptions)
- ✅ Can fetch real pages from connected workspace
- ✅ File under 300 lines (currently ~260 lines)
- ✅ PHPDoc comments on all methods
- ✅ Follows WordPress coding standards
- ✅ Works with existing NotionClient from Phase 0

## Next Steps for Stream 3

Stream 3 (SyncManager) can now:

1. Use `fetch_pages_list()` to discover pages
2. Use `fetch_page_properties()` to get metadata
3. Use `fetch_page_blocks()` to get content
4. Focus on sync logic, scheduling, and conflict resolution

The ContentFetcher provides a stable interface that won't change during Stream 3 development.
