# Stream 4 Integration Guide - SyncManager Usage

**For**: Stream 4 Agent (Admin UI Developer)
**Date**: 2025-10-20
**Stream 3 Status**: ✅ COMPLETE

---

## Quick Start

### Initialize SyncManager

```php
use NotionSync\Sync\SyncManager;

// Simple initialization (production)
$manager = new SyncManager();

// Dependency injection (testing)
$manager = new SyncManager( $mock_fetcher, $mock_converter );
```

---

## Core Operations

### 1. Sync a Single Page

```php
$result = $manager->sync_page( $notion_page_id );

// Result structure
array(
    'success' => true|false,
    'post_id' => 42|null,
    'error'   => null|'Error message'
)
```

**Success Example**:

```php
if ( $result['success'] ) {
    $post_id = $result['post_id'];
    echo "Post created/updated: #{$post_id}";
    echo '<a href="' . get_edit_post_link( $post_id ) . '">Edit Post</a>';
}
```

**Error Example**:

```php
if ( ! $result['success'] ) {
    echo '<div class="notice notice-error">';
    echo esc_html( $result['error'] );
    echo '</div>';
}
```

### 2. Check Sync Status

```php
$status = $manager->get_sync_status( $notion_page_id );

// Status structure
array(
    'is_synced'   => true|false,
    'post_id'     => 42|null,
    'last_synced' => '2025-10-20 10:00:00'|null
)
```

**Display Example**:

```php
if ( $status['is_synced'] ) {
    printf(
        'Synced to <a href="%s">Post #%d</a> (%s ago)',
        get_edit_post_link( $status['post_id'] ),
        $status['post_id'],
        human_time_diff( strtotime( $status['last_synced'] ) )
    );
} else {
    echo '<button class="sync-button">Sync Now</button>';
}
```

---

## Integration Patterns

### Pattern 1: Admin Page Sync Handler

```php
add_action( 'admin_post_notion_sync_single', function() {
    // Security checks
    check_admin_referer( 'sync_notion_page' );

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_die( 'Unauthorized' );
    }

    // Get page ID from POST
    $page_id = sanitize_text_field( $_POST['notion_page_id'] );

    // Sync
    $manager = new SyncManager();
    $result = $manager->sync_page( $page_id );

    // Redirect with message
    $redirect_url = admin_url( 'admin.php?page=notion-sync' );

    if ( $result['success'] ) {
        $redirect_url = add_query_arg( array(
            'message' => 'synced',
            'post_id' => $result['post_id']
        ), $redirect_url );
    } else {
        $redirect_url = add_query_arg( array(
            'message' => 'error',
            'error_msg' => urlencode( $result['error'] )
        ), $redirect_url );
    }

    wp_redirect( $redirect_url );
    exit;
});
```

### Pattern 2: AJAX Sync Endpoint

```php
add_action( 'wp_ajax_notion_sync_page', function() {
    // Security
    check_ajax_referer( 'notion_sync_nonce' );

    // Get page ID
    $page_id = sanitize_text_field( $_POST['page_id'] );

    // Sync
    $manager = new SyncManager();
    $result = $manager->sync_page( $page_id );

    // Send JSON response
    if ( $result['success'] ) {
        wp_send_json_success( array(
            'post_id' => $result['post_id'],
            'edit_url' => get_edit_post_link( $result['post_id'] ),
            'message' => 'Page synced successfully!'
        ) );
    } else {
        wp_send_json_error( array(
            'message' => $result['error']
        ) );
    }
});
```

**JavaScript Handler**:

```javascript
jQuery('.sync-button').on('click', function () {
	const pageId = jQuery(this).data('page-id');

	jQuery
		.post(ajaxurl, {
			action: 'notion_sync_page',
			page_id: pageId,
			_ajax_nonce: notionSync.nonce,
		})
		.done(function (response) {
			if (response.success) {
				alert('Synced! Post ID: ' + response.data.post_id);
				window.location.href = response.data.edit_url;
			}
		})
		.fail(function (xhr) {
			alert('Error: ' + xhr.responseJSON.data.message);
		});
});
```

### Pattern 3: Bulk Sync

```php
add_action( 'wp_ajax_notion_bulk_sync', function() {
    check_ajax_referer( 'notion_bulk_sync' );

    $page_ids = json_decode( stripslashes( $_POST['page_ids'] ), true );
    $manager = new SyncManager();

    $results = array();

    foreach ( $page_ids as $page_id ) {
        $result = $manager->sync_page( $page_id );

        $results[] = array(
            'page_id' => $page_id,
            'success' => $result['success'],
            'post_id' => $result['post_id'],
            'error'   => $result['error']
        );
    }

    $success_count = count( array_filter( $results, fn($r) => $r['success'] ) );

    wp_send_json_success( array(
        'results' => $results,
        'summary' => sprintf(
            '%d succeeded, %d failed',
            $success_count,
            count( $results ) - $success_count
        )
    ) );
});
```

### Pattern 4: List Table Integration

```php
class Notion_Pages_List_Table extends WP_List_Table {

    private $sync_manager;

    public function __construct() {
        parent::__construct();
        $this->sync_manager = new SyncManager();
    }

    public function column_sync_status( $item ) {
        $status = $this->sync_manager->get_sync_status( $item['id'] );

        if ( $status['is_synced'] ) {
            return sprintf(
                '<span class="dashicons dashicons-yes-alt"></span> Post #%d<br><small>%s ago</small>',
                $status['post_id'],
                human_time_diff( strtotime( $status['last_synced'] ) )
            );
        }

        return '<span class="dashicons dashicons-marker"></span> Not synced';
    }

    public function column_actions( $item ) {
        $status = $this->sync_manager->get_sync_status( $item['id'] );

        if ( $status['is_synced'] ) {
            return sprintf(
                '<a href="%s" class="button button-small">Re-sync</a> ' .
                '<a href="%s" class="button button-small">Edit Post</a>',
                $this->get_sync_url( $item['id'] ),
                get_edit_post_link( $status['post_id'] )
            );
        }

        return sprintf(
            '<a href="%s" class="button button-primary button-small">Sync Now</a>',
            $this->get_sync_url( $item['id'] )
        );
    }

    private function get_sync_url( $page_id ) {
        return wp_nonce_url(
            admin_url( 'admin-post.php?action=notion_sync_single&notion_page_id=' . urlencode( $page_id ) ),
            'sync_notion_page'
        );
    }
}
```

---

## Error Handling

### Error Categories

All errors are returned in `$result['error']` with descriptive messages.

**1. Validation Errors**:

- "Notion page ID cannot be empty."
- "Notion page ID contains invalid characters..."
- "Notion page ID exceeds maximum length..."

**2. API Errors**:

- "Failed to fetch page properties from Notion. The page may not exist..."
- "Failed to fetch page blocks from Notion API."

**3. Conversion Errors**:

- "Block conversion failed: [specific error]"

**4. WordPress Errors**:

- "WordPress post creation failed: [WP error message]"

### User-Friendly Error Display

```php
function display_sync_error( $error_message ) {
    $friendly_messages = array(
        'cannot be empty' => array(
            'title' => 'Missing Page ID',
            'message' => 'Please provide a valid Notion page ID.',
            'action' => 'Go back and enter a page ID.'
        ),
        'invalid characters' => array(
            'title' => 'Invalid Format',
            'message' => 'The page ID contains invalid characters.',
            'action' => 'Use only letters, numbers, and hyphens.'
        ),
        'Failed to fetch page properties' => array(
            'title' => 'Connection Error',
            'message' => 'Could not connect to Notion.',
            'action' => 'Check your API token in Settings > Notion Sync.'
        ),
        'Block conversion failed' => array(
            'title' => 'Conversion Error',
            'message' => 'Some content blocks could not be converted.',
            'action' => 'The page may contain unsupported block types.'
        ),
        'WordPress post creation failed' => array(
            'title' => 'Database Error',
            'message' => 'Failed to save the post to WordPress.',
            'action' => 'Check your database connection.'
        ),
    );

    foreach ( $friendly_messages as $pattern => $friendly ) {
        if ( strpos( $error_message, $pattern ) !== false ) {
            printf(
                '<div class="notice notice-error"><h3>%s</h3><p>%s</p><p><em>%s</em></p></div>',
                esc_html( $friendly['title'] ),
                esc_html( $friendly['message'] ),
                esc_html( $friendly['action'] )
            );
            return;
        }
    }

    // Fallback for unknown errors
    printf(
        '<div class="notice notice-error"><p>%s</p></div>',
        esc_html( $error_message )
    );
}
```

---

## Post Meta Reference

### Stored Fields

Each synced post has three meta fields:

| Meta Key             | Type   | Example Value              | Purpose                       |
| -------------------- | ------ | -------------------------- | ----------------------------- |
| `notion_page_id`     | string | `abc123def456`             | Mapping & duplicate detection |
| `notion_last_synced` | string | `2025-10-20 10:00:00`      | Sync history tracking         |
| `notion_last_edited` | string | `2025-10-20T10:00:00.000Z` | Future conflict detection     |

### Querying Synced Posts

```php
// Find all synced posts
$synced_posts = get_posts( array(
    'post_type' => 'post',
    'posts_per_page' => -1,
    'meta_key' => 'notion_page_id',
    'fields' => 'ids'
) );

// Find post by Notion page ID
$post_id = get_posts( array(
    'post_type' => 'post',
    'posts_per_page' => 1,
    'meta_query' => array(
        array(
            'key' => 'notion_page_id',
            'value' => $notion_page_id,
            'compare' => '='
        )
    ),
    'fields' => 'ids'
) )[0] ?? null;
```

---

## Testing Tips

### Unit Testing with Mocks

```php
use NotionSync\Sync\SyncManager;
use NotionSync\Sync\ContentFetcher;
use NotionSync\Blocks\BlockConverter;

class MyAdminUITest extends WP_UnitTestCase {

    public function test_sync_button_action() {
        // Mock dependencies
        $mock_fetcher = $this->createMock( ContentFetcher::class );
        $mock_converter = $this->createMock( BlockConverter::class );

        // Configure mocks
        $mock_fetcher->method( 'fetch_page_properties' )
            ->willReturn( array( 'id' => '123', 'title' => 'Test' ) );

        // Inject mocks
        $manager = new SyncManager( $mock_fetcher, $mock_converter );

        // Test your UI code that uses $manager
        $result = $manager->sync_page( '123' );

        $this->assertTrue( $result['success'] );
    }
}
```

### Integration Testing

```php
// Test with real SyncManager (requires WordPress environment)
public function test_real_sync() {
    // Setup: Create ContentFetcher with real Notion client
    $token = get_option( 'notion_wp_token' );
    $client = new NotionClient( Encryption::decrypt( $token ) );
    $fetcher = new ContentFetcher( $client );

    // Use real BlockConverter
    $converter = new BlockConverter();

    // Create SyncManager
    $manager = new SyncManager( $fetcher, $converter );

    // Test sync with real Notion page
    $result = $manager->sync_page( 'your-test-page-id' );

    $this->assertTrue( $result['success'] );
    $this->assertNotNull( $result['post_id'] );
}
```

---

## Performance Notes

### Expected Performance

- **Single sync**: 2-5 seconds (depends on page size)
- **10 pages**: ~20-50 seconds
- **100 pages**: ~200-500 seconds (consider background processing)

### Timeout Prevention

```php
// For large sync operations
set_time_limit( 300 ); // 5 minutes
ini_set( 'max_execution_time', 300 );

// OR use chunks with progress tracking
$chunks = array_chunk( $page_ids, 10 );
foreach ( $chunks as $chunk ) {
    foreach ( $chunk as $page_id ) {
        $manager->sync_page( $page_id );
    }
    // Update progress, allow user to cancel, etc.
}
```

---

## WordPress Admin UI Patterns

### Admin Notice Display

```php
add_action( 'admin_notices', function() {
    if ( isset( $_GET['message'] ) && 'synced' === $_GET['message'] ) {
        $post_id = intval( $_GET['post_id'] ?? 0 );
        printf(
            '<div class="notice notice-success is-dismissible"><p>Page synced successfully! <a href="%s">Edit Post #%d</a></p></div>',
            esc_url( get_edit_post_link( $post_id ) ),
            $post_id
        );
    }

    if ( isset( $_GET['message'] ) && 'error' === $_GET['message'] ) {
        printf(
            '<div class="notice notice-error is-dismissible"><p>%s</p></div>',
            esc_html( urldecode( $_GET['error_msg'] ?? 'Unknown error' ) )
        );
    }
});
```

### Progress Indicator (JavaScript)

```javascript
jQuery('.bulk-sync-button').on('click', function () {
	const pageIds = getSelectedPageIds(); // Your implementation
	let completed = 0;

	jQuery('#sync-progress').show();

	pageIds.forEach(function (pageId) {
		jQuery
			.post(ajaxurl, {
				action: 'notion_sync_page',
				page_id: pageId,
				_ajax_nonce: notionSync.nonce,
			})
			.always(function () {
				completed++;
				const percent = (completed / pageIds.length) * 100;
				jQuery('#progress-bar').css('width', percent + '%');
				jQuery('#progress-text').text(
					completed + ' / ' + pageIds.length
				);

				if (completed === pageIds.length) {
					location.reload(); // Refresh to show updated status
				}
			});
	});
});
```

---

## Common Gotchas

### 1. Page ID Format

**Problem**: Page IDs from Notion API may have dashes.

**Solution**: SyncManager handles both formats (with/without dashes).

```php
// Both work:
$manager->sync_page( 'abc-123-def-456' );
$manager->sync_page( 'abc123def456' );
```

### 2. Draft Status

**Problem**: All synced posts are drafts.

**Solution**: This is intentional for MVP. Change status after sync if needed:

```php
$result = $manager->sync_page( $page_id );
if ( $result['success'] ) {
    wp_update_post( array(
        'ID' => $result['post_id'],
        'post_status' => 'publish'
    ) );
}
```

### 3. Duplicate Detection

**Problem**: Second sync should update, not create new post.

**Solution**: This is automatic. SyncManager queries post meta before creating.

**Verification**:

```php
// First sync
$result1 = $manager->sync_page( 'abc123' );
$post_id_1 = $result1['post_id']; // e.g., 42

// Second sync (same page)
$result2 = $manager->sync_page( 'abc123' );
$post_id_2 = $result2['post_id']; // Still 42, not 43
```

---

## Quick Reference

### Method Signatures

```php
// Sync a page
sync_page( string $notion_page_id ): array

// Check status
get_sync_status( string $notion_page_id ): array
```

### Return Structures

```php
// sync_page() returns:
array(
    'success' => bool,
    'post_id' => int|null,
    'error'   => string|null
)

// get_sync_status() returns:
array(
    'is_synced'   => bool,
    'post_id'     => int|null,
    'last_synced' => string|null // MySQL datetime
)
```

---

## Need Help?

**Documentation**: See `STREAM-3-SYNC-MANAGER-COMPLETE.md` for full implementation details.

**Code Location**: `/plugin/src/Sync/SyncManager.php`

**Tests**: `/tests/unit/Sync/SyncManagerTest.php` (contains usage examples)

**Support**: Contact wordpress-plugin-engineer (Stream 3 agent)

---

**Last Updated**: 2025-10-20
**For Stream**: 4 - Admin UI
**Stream 3 Status**: ✅ COMPLETE AND READY
