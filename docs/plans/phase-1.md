# Phase 1: MVP Core - Single Page Sync

**Status:** Ready to Start
**Duration:** 1-2 weeks
**Complexity:** M (Medium)
**Version Target:** v0.2-dev

## 🎯 Goal

Import a single Notion page to WordPress as a post with basic text formatting. **This phase proves the core sync functionality works end-to-end.**

**User Story:** "As a WordPress admin, I can select a Notion page from a dropdown, click 'Sync Now', and see it appear as a WordPress post with all basic formatting preserved."

## ✅ Success Criteria (Gatekeeping)

**DO NOT PROCEED to Phase 2 until ALL criteria are met:**

- [ ] User can select a Notion page from dropdown in admin UI
- [ ] Clicking "Sync Now" fetches page content from Notion API
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

### Stream 4: Admin UI Updates (User Interface)
**Worktree:** `phase-1-mvp`
**Duration:** 2-3 days
**Files Created:** Extend existing SettingsPage

**What Users See:**
- Dropdown showing available Notion pages
- "Sync to WordPress" button next to each page
- Loading spinner during sync
- Success message with link to WordPress post
- Error messages if sync fails

**Technical Implementation:**

**Update:** `plugin/src/Admin/SettingsPage.php`
- Add page selector dropdown (use existing `list_pages()` data)
- Add "Sync" button with AJAX handler
- Show sync progress indicator
- Display success/error messages

**Update:** `plugin/templates/admin/settings.php`
```php
<!-- After workspace info section -->
<h3><?php esc_html_e( 'Sync Notion Pages', 'notion-wp' ); ?></h3>
<table class="wp-list-table widefat fixed striped">
    <thead>
        <tr>
            <th><?php esc_html_e( 'Page Title', 'notion-wp' ); ?></th>
            <th><?php esc_html_e( 'Last Synced', 'notion-wp' ); ?></th>
            <th><?php esc_html_e( 'Actions', 'notion-wp' ); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ( $pages as $page ) : ?>
        <tr>
            <td><?php echo esc_html( $page['title'] ); ?></td>
            <td><?php echo esc_html( $page['last_synced'] ?? 'Never' ); ?></td>
            <td>
                <button class="button sync-page-btn" data-page-id="<?php echo esc_attr( $page['id'] ); ?>">
                    <?php esc_html_e( 'Sync Now', 'notion-wp' ); ?>
                </button>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
```

**Update:** `plugin/assets/src/js/admin.js`
```javascript
// AJAX handler for sync button
document.querySelectorAll('.sync-page-btn').forEach(button => {
    button.addEventListener('click', function() {
        const pageId = this.dataset.pageId;
        syncPage(pageId);
    });
});

async function syncPage(pageId) {
    // Show loading spinner
    // Call WordPress AJAX endpoint
    // Handle success/error
    // Update UI
}
```

**Add AJAX Handler:** `plugin/src/Admin/SettingsPage.php`
```php
public function register(): void {
    // ... existing code ...
    add_action( 'wp_ajax_notion_sync_page', array( $this, 'ajax_sync_page' ) );
}

public function ajax_sync_page(): void {
    check_ajax_referer( 'notion_sync_ajax' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    $page_id = sanitize_text_field( $_POST['page_id'] ?? '' );

    try {
        $sync_manager = new SyncManager();
        $post_id = $sync_manager->sync_page( $page_id );

        wp_send_json_success([
            'post_id' => $post_id,
            'edit_url' => get_edit_post_link( $post_id, 'raw' ),
            'view_url' => get_permalink( $post_id ),
        ]);
    } catch ( Exception $e ) {
        wp_send_json_error( $e->getMessage() );
    }
}
```

**Tasks:**
1. Add page list table to settings template
2. Add "Sync Now" button for each page
3. Implement AJAX sync handler
4. Add loading spinner during sync
5. Show success message with link to post
6. Handle and display error messages

**Definition of Done:**
- [ ] Can select any Notion page from list
- [ ] Click "Sync Now" triggers AJAX request
- [ ] Loading spinner shows during sync
- [ ] Success message shows link to WordPress post
- [ ] Error messages are helpful
- [ ] UI is responsive on mobile

---

## 📦 Deliverables

### Visible to Users (What They Can Do)
- ✅ Navigate to **WP Admin > Notion Sync**
- ✅ See list of accessible Notion pages
- ✅ Click "Sync Now" button for any page
- ✅ Watch progress indicator during sync
- ✅ See success message: "Page synced! [Edit Post] [View Post]"
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
- ✅ Updated `plugin/src/Admin/SettingsPage.php` with AJAX handler
- ✅ Updated `plugin/templates/admin/settings.php` with sync UI
- ✅ Updated `plugin/assets/src/js/admin.js` with sync logic
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
