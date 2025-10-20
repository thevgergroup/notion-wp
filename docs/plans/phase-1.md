# Phase 1: MVP Core - Single Page Sync

**Status:** Ready to Start
**Duration:** 1-2 weeks
**Complexity:** M (Medium)
**Version Target:** v0.2-dev

## 🎯 Goal

Import Notion pages to WordPress as posts with basic text formatting. **This phase proves the core sync functionality works end-to-end.**

**User Story:** "As a WordPress admin, I can see all my Notion pages in a table with sync status, select one or more pages, click 'Sync', and see them appear as WordPress posts with all basic formatting preserved."

## ✅ Success Criteria (Gatekeeping)

**DO NOT PROCEED to Phase 2 until ALL criteria are met:**

- [ ] User sees Notion pages in a WordPress-style list table
- [ ] Table shows metadata: page title, last synced, WordPress post link, sync status
- [ ] User can select individual pages or use "Select All"
- [ ] Clicking "Sync Selected" syncs one or more pages
- [ ] Clicking individual "Sync" action syncs single page
- [ ] Sync fetches page content from Notion API
- [ ] Page title appears as WordPress post title
- [ ] Basic blocks convert correctly:
  - [ ] Paragraphs with text formatting (bold, italic, code)
  - [ ] Headings (H1, H2, H3)
  - [ ] Bulleted lists
  - [ ] Numbered lists
  - [ ] Links (internal and external)
- [ ] Post is editable in WordPress Gutenberg editor
- [ ] Re-syncing updates existing post (no duplicates created)
- [ ] Success message shows link to created/updated post
- [ ] All linting passes (WPCS, ESLint, PHPStan level 5)
- [ ] Zero PHP warnings or JavaScript console errors
- [ ] **Can be demoed to a non-developer in under 5 minutes**

## 📋 Dependencies

**Required from Phase 0 (COMPLETED ✅):**
- ✅ Working Notion API authentication
- ✅ Admin settings page foundation
- ✅ Development environment with Docker
- ✅ Linting infrastructure
- ✅ NotionClient API wrapper

## 🔀 Parallel Work Streams

### Stream 1: Content Fetcher (Backend Core)
**Worktree:** `phase-1-mvp` (already created)
**Duration:** 2-3 days
**Files Created:** 1-2 files, all <300 lines

**What This Builds:**
- Fetch page content from Notion API
- Retrieve all blocks with pagination support
- Handle API errors gracefully

**Technical Implementation:**

**File 1:** `plugin/src/Sync/ContentFetcher.php` (<300 lines)
```php
<?php
namespace NotionSync\Sync;

use NotionSync\API\NotionClient;

class ContentFetcher {
    private NotionClient $client;

    public function fetch_page_blocks( string $page_id ): array {
        // GET /blocks/{block_id}/children
        // Handle pagination (100 blocks per request)
        // Return array of block objects
    }

    public function fetch_page_properties( string $page_id ): array {
        // GET /pages/{page_id}
        // Extract title, created time, last edited time
        // Return page metadata
    }
}
```

**Tasks:**
1. Create ContentFetcher class with Notion API integration
2. Implement `fetch_page_blocks()` with pagination
3. Implement `fetch_page_properties()` for metadata
4. Add error handling and retry logic
5. Write unit tests for API responses

**Definition of Done:**
- [ ] Can fetch all blocks from a Notion page (even 100+ blocks)
- [ ] Handles API errors gracefully (network issues, rate limits)
- [ ] Returns structured data ready for conversion
- [ ] Unit tests pass

---

### Stream 2: Block Converter (Core Logic)
**Worktree:** `phase-1-mvp`
**Duration:** 4-5 days
**Files Created:** 4-5 files, all <300 lines

**What This Builds:**
- Convert Notion block format → WordPress Gutenberg blocks
- Support for basic text formatting
- Extensible architecture for future block types

**Technical Implementation:**

**File 1:** `plugin/src/Blocks/BlockConverter.php` (<250 lines)
```php
<?php
namespace NotionSync\Blocks;

class BlockConverter {
    private array $converters = [];

    public function register_converter( string $type, BlockConverterInterface $converter ): void {
        $this->converters[ $type ] = $converter;
    }

    public function convert_blocks( array $notion_blocks ): string {
        // Loop through blocks
        // Call appropriate converter
        // Return serialized Gutenberg blocks
    }
}
```

**File 2:** `plugin/src/Blocks/BlockConverterInterface.php` (<100 lines)
```php
<?php
namespace NotionSync\Blocks;

interface BlockConverterInterface {
    public function supports( array $notion_block ): bool;
    public function convert( array $notion_block ): string;
}
```

**File 3:** `plugin/src/Blocks/Converters/ParagraphConverter.php` (<200 lines)
```php
<?php
namespace NotionSync\Blocks\Converters;

class ParagraphConverter implements BlockConverterInterface {
    public function convert( array $notion_block ): string {
        // Convert to <!-- wp:paragraph -->
        // Handle rich text formatting (bold, italic, code, links)
        // Return Gutenberg block HTML
    }
}
```

**File 4:** `plugin/src/Blocks/Converters/HeadingConverter.php` (<150 lines)
**File 5:** `plugin/src/Blocks/Converters/ListConverter.php` (<200 lines)

**Tasks:**
1. Create BlockConverter orchestrator
2. Implement ParagraphConverter with rich text support
3. Implement HeadingConverter (H1, H2, H3)
4. Implement ListConverter (bulleted and numbered)
5. Add support for links
6. Write comprehensive unit tests for each converter

**Definition of Done:**
- [ ] Paragraphs convert with bold, italic, code, strikethrough
- [ ] Headings (H1-H3) convert correctly
- [ ] Bulleted and numbered lists work
- [ ] Links (internal and external) preserved
- [ ] Output is valid Gutenberg block HTML
- [ ] All converters have unit tests

---

### Stream 3: Sync Manager (Orchestration)
**Worktree:** `phase-1-mvp`
**Duration:** 3-4 days
**Files Created:** 2 files, all <350 lines

**What This Builds:**
- Coordinates fetch → convert → create/update workflow
- Stores mapping between Notion pages and WordPress posts
- Prevents duplicates on re-sync

**Technical Implementation:**

**File 1:** `plugin/src/Sync/SyncManager.php` (<350 lines)
```php
<?php
namespace NotionSync\Sync;

class SyncManager {
    private ContentFetcher $fetcher;
    private BlockConverter $converter;

    public function sync_page( string $page_id ): int {
        // 1. Fetch page data from Notion
        // 2. Check if WordPress post exists (via post meta)
        // 3. Convert blocks to Gutenberg
        // 4. Create or update WordPress post
        // 5. Store notion_page_id in post meta
        // 6. Return post ID
    }

    private function get_post_by_notion_id( string $notion_id ): ?int {
        // Query posts by meta_key = 'notion_page_id'
    }

    private function create_or_update_post( array $data, ?int $post_id ): int {
        // wp_insert_post() or wp_update_post()
    }
}
```

**File 2:** `plugin/src/Sync/SyncLogger.php` (<200 lines)
```php
<?php
namespace NotionSync\Sync;

class SyncLogger {
    public function log_sync_start( string $page_id ): void;
    public function log_sync_success( string $page_id, int $post_id ): void;
    public function log_sync_error( string $page_id, string $error ): void;
}
```

**Tasks:**
1. Create SyncManager orchestrator
2. Implement sync workflow (fetch → convert → save)
3. Add duplicate detection via post meta
4. Implement create and update logic
5. Add comprehensive logging
6. Write integration tests

**Definition of Done:**
- [ ] Can sync a Notion page to WordPress post
- [ ] Creates new post on first sync
- [ ] Updates existing post on re-sync (no duplicates)
- [ ] Stores notion_page_id in post meta
- [ ] Logs all operations for debugging
- [ ] Integration tests pass

---

### Stream 4: Admin UI with List Table & Bulk Actions
**Worktree:** `phase-1-mvp`
**Duration:** 3-4 days
**Files Created:** 2 new files + extend existing SettingsPage

**What Users See:**
- WordPress-style list table showing all Notion pages
- Columns: Checkbox, Page Title, Last Synced, WordPress Post, Status, Actions
- Bulk actions dropdown: "Sync Selected"
- Individual "Sync" row action for each page
- Real-time status updates during sync
- Success messages with links to created/updated posts

**Technical Implementation:**

**File 1:** `plugin/src/Admin/PagesListTable.php` (<400 lines)
```php
<?php
namespace NotionSync\Admin;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class PagesListTable extends \WP_List_Table {
    public function __construct() {
        parent::__construct([
            'singular' => 'notion-page',
            'plural'   => 'notion-pages',
            'ajax'     => true,
        ]);
    }

    public function get_columns(): array {
        return [
            'cb'          => '<input type="checkbox" />',
            'title'       => __( 'Page Title', 'notion-wp' ),
            'last_synced' => __( 'Last Synced', 'notion-wp' ),
            'wp_post'     => __( 'WordPress Post', 'notion-wp' ),
            'status'      => __( 'Status', 'notion-wp' ),
        ];
    }

    public function get_bulk_actions(): array {
        return [
            'sync' => __( 'Sync Selected', 'notion-wp' ),
        ];
    }

    public function column_cb( $item ): string {
        return sprintf(
            '<input type="checkbox" name="notion_pages[]" value="%s" />',
            esc_attr( $item['id'] )
        );
    }

    public function column_title( $item ): string {
        $actions = [
            'sync' => sprintf(
                '<a href="#" class="sync-page" data-page-id="%s">%s</a>',
                esc_attr( $item['id'] ),
                esc_html__( 'Sync', 'notion-wp' )
            ),
        ];

        if ( ! empty( $item['wp_post_id'] ) ) {
            $actions['edit'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url( get_edit_post_link( $item['wp_post_id'] ) ),
                esc_html__( 'Edit Post', 'notion-wp' )
            );
            $actions['view'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url( get_permalink( $item['wp_post_id'] ) ),
                esc_html__( 'View Post', 'notion-wp' )
            );
        }

        return sprintf(
            '<strong>%s</strong> %s',
            esc_html( $item['title'] ),
            $this->row_actions( $actions )
        );
    }

    public function column_wp_post( $item ): string {
        if ( empty( $item['wp_post_id'] ) ) {
            return '<span class="dashicons dashicons-minus"></span> ' .
                   esc_html__( 'Not synced', 'notion-wp' );
        }

        return sprintf(
            '<a href="%s">#%d</a>',
            esc_url( get_edit_post_link( $item['wp_post_id'] ) ),
            $item['wp_post_id']
        );
    }

    public function column_status( $item ): string {
        $status = $item['sync_status'] ?? 'never';

        $statuses = [
            'synced'   => '<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> ' .
                         __( 'Synced', 'notion-wp' ),
            'modified' => '<span class="dashicons dashicons-info" style="color: #ffb900;"></span> ' .
                         __( 'Modified', 'notion-wp' ),
            'never'    => '<span class="dashicons dashicons-marker" style="color: #999;"></span> ' .
                         __( 'Never synced', 'notion-wp' ),
            'error'    => '<span class="dashicons dashicons-warning" style="color: #dc3232;"></span> ' .
                         __( 'Error', 'notion-wp' ),
        ];

        return $statuses[ $status ] ?? $statuses['never'];
    }

    public function prepare_items(): void {
        // Get Notion pages from API or cache
        // Check WordPress post meta for existing syncs
        // Set $this->items with enriched data
    }
}
```

**Update:** `plugin/src/Admin/SettingsPage.php`
- Create PagesListTable instance
- Handle bulk action form submission
- Add AJAX handlers for single and bulk sync

**Update:** `plugin/templates/admin/settings.php`
```php
<!-- After workspace info section -->
<h3><?php esc_html_e( 'Sync Notion Pages', 'notion-wp' ); ?></h3>

<form method="post" action="">
    <?php wp_nonce_field( 'notion_sync_bulk_action', 'notion_sync_bulk_nonce' ); ?>
    <?php $pages_table->display(); ?>
</form>
```

**Update:** `plugin/assets/src/js/admin.js`
```javascript
// Handle bulk sync
document.querySelector('form').addEventListener('submit', function(e) {
    const bulkAction = document.querySelector('[name="action"]').value;
    if (bulkAction === 'sync') {
        e.preventDefault();
        const selectedPages = Array.from(
            document.querySelectorAll('input[name="notion_pages[]"]:checked')
        ).map(cb => cb.value);

        if (selectedPages.length === 0) {
            alert('Please select at least one page to sync.');
            return;
        }

        syncMultiplePages(selectedPages);
    }
});

// Handle individual sync
document.querySelectorAll('.sync-page').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        const pageId = this.dataset.pageId;
        syncSinglePage(pageId);
    });
});

async function syncSinglePage(pageId) {
    updateRowStatus(pageId, 'syncing');

    try {
        const response = await fetch(ajaxurl, {
            method: 'POST',
            body: new FormData(...)
        });

        const data = await response.json();

        if (data.success) {
            updateRowStatus(pageId, 'success', data.data);
        } else {
            updateRowStatus(pageId, 'error', data.data);
        }
    } catch (error) {
        updateRowStatus(pageId, 'error', error.message);
    }
}

async function syncMultiplePages(pageIds) {
    // Show progress bar
    // Sync pages one at a time (or in parallel batches)
    // Update UI as each completes
    // Show final summary
}

function updateRowStatus(pageId, status, data) {
    const row = document.querySelector(`tr[data-page-id="${pageId}"]`);
    // Update status column with spinner/success/error icon
    // Update WordPress post column if post created
    // Update last synced timestamp
}
```

**Add AJAX Handlers:** `plugin/src/Admin/SettingsPage.php`
```php
public function register(): void {
    // ... existing code ...
    add_action( 'wp_ajax_notion_sync_single', array( $this, 'ajax_sync_single' ) );
    add_action( 'wp_ajax_notion_sync_bulk', array( $this, 'ajax_sync_bulk' ) );
}

public function ajax_sync_single(): void {
    check_ajax_referer( 'notion_sync_ajax' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'Unauthorized', 'notion-wp' ) );
    }

    $page_id = sanitize_text_field( $_POST['page_id'] ?? '' );

    try {
        $sync_manager = new SyncManager();
        $post_id = $sync_manager->sync_page( $page_id );

        // Update last synced timestamp
        update_option( "notion_page_{$page_id}_last_synced", current_time( 'mysql' ) );

        wp_send_json_success([
            'post_id'   => $post_id,
            'edit_url'  => get_edit_post_link( $post_id, 'raw' ),
            'view_url'  => get_permalink( $post_id ),
            'synced_at' => current_time( 'mysql' ),
        ]);
    } catch ( Exception $e ) {
        wp_send_json_error( $e->getMessage() );
    }
}

public function ajax_sync_bulk(): void {
    check_ajax_referer( 'notion_sync_ajax' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'Unauthorized', 'notion-wp' ) );
    }

    $page_ids = $_POST['page_ids'] ?? [];
    $page_ids = array_map( 'sanitize_text_field', $page_ids );

    $results = [
        'success' => [],
        'errors'  => [],
    ];

    $sync_manager = new SyncManager();

    foreach ( $page_ids as $page_id ) {
        try {
            $post_id = $sync_manager->sync_page( $page_id );
            $results['success'][] = [
                'page_id' => $page_id,
                'post_id' => $post_id,
            ];
        } catch ( Exception $e ) {
            $results['errors'][] = [
                'page_id' => $page_id,
                'error'   => $e->getMessage(),
            ];
        }
    }

    wp_send_json_success( $results );
}
```

**Tasks:**
1. Create PagesListTable extending WP_List_Table
2. Add columns for all metadata (title, last synced, WP post, status)
3. Implement bulk actions dropdown
4. Add individual row actions (Sync, Edit Post, View Post)
5. Implement single page AJAX sync
6. Implement bulk page AJAX sync
7. Add real-time status updates in table
8. Show progress indicator during bulk sync
9. Display success/error summaries

**Definition of Done:**
- [ ] Pages display in WordPress-style list table
- [ ] Table shows: title, last synced, WordPress post link, sync status
- [ ] Can check multiple pages and use "Sync Selected" bulk action
- [ ] Can click individual "Sync" row action
- [ ] Status column updates in real-time during sync
- [ ] Success shows links to WordPress posts
- [ ] Bulk sync shows progress (e.g., "Syncing 3 of 10...")
- [ ] Error messages are specific and helpful
- [ ] Table is responsive on mobile
- [ ] Follows WordPress admin design patterns

---

## 📦 Deliverables

### Visible to Users (What They Can Do)
- ✅ Navigate to **WP Admin > Notion Sync**
- ✅ See WordPress-style list table with all Notion pages
- ✅ View metadata: page title, last synced, WordPress post link, sync status
- ✅ Select multiple pages using checkboxes
- ✅ Use "Sync Selected" bulk action to sync multiple pages
- ✅ Click individual "Sync" row action for single page
- ✅ Watch real-time status updates during sync
- ✅ See links to WordPress posts (Edit/View)
- ✅ Open WordPress post and see content formatted correctly
- ✅ Make edits in Gutenberg editor
- ✅ Re-sync updates post without creating duplicate

### Technical (What We Built)
- ✅ `plugin/src/Sync/ContentFetcher.php` - Notion API integration
- ✅ `plugin/src/Sync/SyncManager.php` - Sync orchestration
- ✅ `plugin/src/Sync/SyncLogger.php` - Operation logging
- ✅ `plugin/src/Blocks/BlockConverter.php` - Converter orchestrator
- ✅ `plugin/src/Blocks/BlockConverterInterface.php` - Converter interface
- ✅ `plugin/src/Blocks/Converters/ParagraphConverter.php`
- ✅ `plugin/src/Blocks/Converters/HeadingConverter.php`
- ✅ `plugin/src/Blocks/Converters/ListConverter.php`
- ✅ `plugin/src/Admin/PagesListTable.php` - WP_List_Table for pages
- ✅ Updated `plugin/src/Admin/SettingsPage.php` with single & bulk AJAX handlers
- ✅ Updated `plugin/templates/admin/settings.php` with list table UI
- ✅ Updated `plugin/assets/src/js/admin.js` with single & bulk sync logic
- ✅ Unit tests for all converters
- ✅ Integration tests for full sync workflow

### Not Built (Deferred to Later Phases)
- ❌ Database sync (Phase 2)
- ❌ Images (Phase 3)
- ❌ Advanced blocks (callouts, toggles, code) (Phase 4)
- ❌ Page hierarchy and menus (Phase 5)
- ❌ Bi-directional sync (Phase 5+)
- ❌ Webhooks (Phase 5+)

---

## 🚀 Daily Workflow

### Week 1: Core Infrastructure

**Day 1-2: Content Fetcher**
- Create ContentFetcher class
- Implement Notion API block fetching
- Add pagination support
- Write unit tests
- **Demo:** Can fetch blocks from Notion page

**Day 3-4: Block Converters Foundation**
- Create BlockConverter orchestrator
- Implement ParagraphConverter
- Add rich text formatting support
- Write unit tests
- **Demo:** Can convert Notion paragraph to Gutenberg block

**Day 5: More Block Converters**
- Implement HeadingConverter
- Implement ListConverter (bulleted/numbered)
- Write unit tests
- **Demo:** All basic blocks convert correctly

### Week 2: Integration & Polish

**Day 6-7: Sync Manager**
- Create SyncManager orchestration
- Implement duplicate detection
- Add create/update post logic
- Store post meta mappings
- **Demo:** Can sync page end-to-end

**Day 8-9: Admin UI**
- Add sync UI to settings page
- Implement AJAX handler
- Add loading states
- Show success/error messages
- **Demo:** Full user workflow works

**Day 10: Testing & Polish**
- Run through all gatekeeping criteria
- Fix any bugs found
- Improve error messages
- Test on mobile
- **Demo:** Ready for gatekeeping review

---

## ✋ Gatekeeping Review

Before proceeding to Phase 2, schedule a **5-minute demo** with someone who:
- Is NOT a developer
- Has access to a Notion workspace
- Can provide honest feedback

**Demo Script:**
1. Show Notion page with content (30 seconds)
2. Navigate to WP Admin > Notion Sync (15 seconds)
3. Click "Sync Now" button (30 seconds)
4. Show WordPress post with formatted content (2 minutes)
5. Make edit in Gutenberg, show it works (2 minutes)

**Pass Criteria:**
- They understood what happened
- Sync completed successfully
- Post looks correct (no broken formatting)
- They could repeat it without help
- No confusion or errors

**If demo fails:**
- Document what went wrong
- Fix specific issues
- Schedule another demo
- **DO NOT** proceed to Phase 2

---

## 🔍 Testing Checklist

### Functional Testing
- [ ] Can fetch blocks from Notion page with <10 blocks
- [ ] Can fetch blocks from Notion page with 100+ blocks (pagination)
- [ ] Paragraphs convert with all formatting (bold, italic, code, links)
- [ ] Headings (H1, H2, H3) convert correctly
- [ ] Bulleted lists convert correctly
- [ ] Numbered lists convert correctly
- [ ] Internal Notion links work
- [ ] External links work
- [ ] First sync creates new WordPress post
- [ ] Re-sync updates existing post (no duplicate)
- [ ] Post title matches Notion page title
- [ ] Post is editable in Gutenberg editor
- [ ] Success message shows correct post link

### Error Handling
- [ ] Network errors handled gracefully
- [ ] Invalid page ID shows helpful error
- [ ] API rate limits handled (retry logic)
- [ ] Empty Notion page handles correctly
- [ ] Unsupported block types show warning (graceful degradation)

### Security Testing
- [ ] AJAX handler has nonce verification
- [ ] Capability checks on sync action
- [ ] Page ID sanitized before use
- [ ] Gutenberg output is safe (no XSS)

### Code Quality
- [ ] All files under 500 lines
- [ ] `composer lint` passes
- [ ] `npm run lint` passes
- [ ] PHPStan level 5 passes
- [ ] No console errors or warnings
- [ ] No PHP notices or warnings

### UI/UX Testing
- [ ] Works in Chrome
- [ ] Works in Firefox
- [ ] Works in Safari
- [ ] Works on mobile (responsive)
- [ ] Loading spinner shows during sync
- [ ] Success message is clear
- [ ] Error messages are helpful

---

## 📊 Success Metrics

**Time Metrics:**
- First sync should complete in <10 seconds
- Re-sync should complete in <5 seconds
- UI should feel responsive (<300ms feedback)

**Quality Metrics:**
- Zero linting errors
- 100% of basic blocks convert correctly
- Zero duplicate posts created
- 100% of formatting preserved (bold, italic, links)

**User Metrics:**
- 5/5 test users can sync successfully
- Zero confusing error messages
- Post looks identical to Notion page (basic formatting)

---

## 🚧 Risks & Mitigation

| Risk | Impact | Mitigation |
|------|--------|------------|
| Notion API changes block structure | High | Version API calls (Notion-Version header), test with multiple pages |
| Gutenberg block format incompatibility | High | Use official Gutenberg parser, validate output |
| Large pages timeout during sync | Medium | Implement background processing for Phase 2 |
| Rich text formatting edge cases | Medium | Comprehensive test suite with real Notion pages |
| Post meta conflicts with other plugins | Low | Use unique meta key prefix `notion_sync_*` |

---

## 📝 Phase 1 Completion Checklist

### Code Complete
- [ ] All 4 work streams merged to `phase-1-mvp` branch
- [ ] All files under 500 lines
- [ ] Zero linting errors
- [ ] All TODO comments resolved or converted to issues

### Testing Complete
- [ ] All functional tests pass
- [ ] All security checks pass
- [ ] Tested with 5+ different Notion pages
- [ ] Tested on 3+ devices (desktop, phone, tablet)

### Documentation Complete
- [ ] README.md updated with sync instructions
- [ ] API documentation for new classes
- [ ] Inline code comments
- [ ] Troubleshooting guide updated

### Demo Complete
- [ ] 5-minute demo successful with non-developer
- [ ] No confusion during demo
- [ ] Post formatting looks correct
- [ ] Ready to show stakeholders

### Ready for Phase 2
- [ ] All gatekeeping criteria met
- [ ] No critical bugs
- [ ] No security issues
- [ ] Team confident to proceed

---

## ⏭️ Next Phase Preview

**Phase 2: Database Sync** will build on this foundation:
- Use ContentFetcher to query Notion databases
- Batch sync multiple pages
- Map database properties to WordPress fields
- **Requires:** Working single page sync from Phase 1

**Do not start Phase 2 until this checklist is 100% complete.**

---

**Document Version:** 1.0
**Last Updated:** 2025-10-20
**Status:** Ready for Development
