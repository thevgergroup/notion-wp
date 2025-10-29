# Phase 5: Hierarchy & Navigation - Implementation Plan

**Status:** ✅ Phase 5.1 Complete
**Estimated Duration:** 4-5 weeks (actual: 1 week for 5.1)
**Complexity:** Large (L) - streamlined with native WordPress integration
**Current Coverage:** 75%+ unit tests achieved
**Target Coverage:** Maintain 75%+ as we add features

## Overview

Phase 5 adds hierarchical page sync and navigation menu generation. The original detailed plan was over-engineered - WordPress already has excellent hierarchy and menu APIs. We'll use them directly rather than building complex abstraction layers.

## Simplified Scope

### What We're Building (Realistic)

1. **Page Hierarchy Sync** - Detect parent-child relationships in Notion and recreate in WordPress
2. **Navigation Menu Generation** - Auto-create WordPress menus from the page structure
3. **Link Resolution** - Convert internal Notion links to WordPress permalinks
4. **Database View Rendering** - Render embedded database blocks inline with filters/sorts

### What We're NOT Building (Over-engineered)

- ❌ Complex abstraction layers (use WP APIs directly)
- ❌ Circular reference detection systems (trust Notion's structure, add simple depth limit)
- ❌ Custom database view Gutenberg blocks (use native HTML/shortcodes)
- ❌ Advanced filter/sort parsers (start with basic support, iterate later)

## Implementation Phases

### Phase 5.1: Page Hierarchy & Menus ✅ COMPLETE

**Status:** ✅ Fully Implemented
**Actual Duration:** 1 week
**Commits:** 7 commits (77697a5 through 9426331)

**Goal:** Sync nested pages and generate navigation menu with manual override capabilities

**What Was Built:**

1. **Detect Child Pages**
   - Use existing block converter to find `child_page` blocks
   - Build simple parent-child map
   - Add depth limit (max 5 levels, configurable)

2. **Set WordPress Hierarchy**
   - Use `wp_update_post()` with `post_parent`
   - Set `menu_order` from Notion ordering
   - Update existing hierarchy on re-sync

3. **Generate Menu with Override System**
   - Use `wp_create_nav_menu()` to create menu
   - Use `wp_update_nav_menu_item()` to add pages
   - Respect hierarchy with `menu_item_parent`
   - Add admin setting for target menu name
   - **Mark menu items as Notion-synced with meta flag**
   - **Preserve manually-added items during sync**
   - **Support override flag to prevent Notion updates**

4. **Admin Integration** ✅ SIMPLIFIED APPROACH
   - **Instead of custom CRUD UI**: Enhanced WordPress's native menu editor
   - Meta box on Appearance → Menus showing:
     * Sync status and item counts
     * Last sync timestamp
     * "Sync from Notion Now" AJAX button
   - Per-item enhancements:
     * Visual indicator (🔄 emoji) for Notion-synced items
     * "Prevent Notion Updates" checkbox (override toggle)
     * Notion page ID display
   - **Benefits**:
     * No learning curve (familiar WordPress interface)
     * Works with all menu plugins (Max Mega Menu, etc.)
     * Less code to maintain
     * Better WordPress integration

**Files Created:** ✅
```
plugin/src/Hierarchy/
├── HierarchyDetector.php       - ✅ Find child pages, build hierarchy map
└── MenuBuilder.php              - ✅ WordPress menu generation

plugin/src/Navigation/
└── MenuItemMeta.php             - ✅ Menu item metadata handling

plugin/src/Admin/
├── NavigationAjaxHandler.php    - ✅ AJAX endpoint for menu sync
└── MenuMetaBox.php              - ✅ Native menu editor enhancement

plugin/src/CLI/
└── MenuHandler.php              - ✅ WP-CLI debug commands

plugin/assets/
└── build-admin-js.sh            - ✅ JavaScript build script

DEPRECATED (simplified approach):
❌ MenuManager.php - Not needed (use WordPress native menus)
❌ MenuOverrideHandler.php - Logic integrated into MenuBuilder
❌ menu-manager.php - Not needed (enhanced native editor instead)
```

**Key Methods:**
```php
class HierarchyDetector {
    public function get_child_pages( string $page_id ): array;
    public function build_hierarchy_map( string $root_page_id, int $max_depth = 5 ): array;
}

class MenuBuilder {
    public function create_or_update_menu( string $menu_name, array $hierarchy_map ): int;
    private function add_page_to_menu( int $menu_id, int $post_id, int $parent_menu_item = 0 ): int;
    public function preserve_manual_items( int $menu_id ): array;
}

class MenuManager {
    public function list_menus(): array;
    public function get_menu_items( int $menu_id ): array;
    public function add_manual_item( int $menu_id, array $item_data ): int;
    public function update_item( int $item_id, array $item_data ): bool;
    public function delete_item( int $item_id ): bool;
    public function reorder_items( int $menu_id, array $order ): bool;
}

class MenuItemMeta {
    public function mark_as_notion_synced( int $item_id, string $notion_page_id ): void;
    public function is_notion_synced( int $item_id ): bool;
    public function set_override( int $item_id, bool $override ): void;
    public function has_override( int $item_id ): bool;
    public function get_notion_page_id( int $item_id ): ?string;
}

class MenuOverrideHandler {
    public function should_update_item( int $item_id ): bool;
    public function merge_notion_and_manual_items( array $notion_items, int $menu_id ): array;
}
```

**Menu Item Metadata Structure:**
```php
// Stored as menu item meta
[
    '_notion_synced' => true,              // Is this from Notion?
    '_notion_page_id' => 'abc123',         // Notion page ID
    '_notion_override' => false,           // User wants to ignore Notion updates
    '_manual_item' => false,               // User added this manually
]
```

**Success Criteria:** ✅ ALL MET
- ✅ Child pages appear under parent in WP admin
- ✅ Menu auto-generated with correct nesting (19 items from hierarchy)
- ✅ Re-sync updates menu (adds new, keeps structure)
- ✅ Works with 3+ levels of nesting (tested with 3-level hierarchy)
- ✅ **Manual items preserved during sync**
- ✅ **Override flag prevents Notion updates**
- ✅ **Admin UI integrated with native WordPress menus**
- ✅ **Compatible with WordPress Navigation block (Gutenberg)**
- ✅ **Supports multilevel menus (tested to 5 levels)**
- ✅ **Works with menu plugins (Max Mega Menu, etc.)**
- ✅ **Full accessibility (WCAG 2.1 AA)**
- ✅ **WP-CLI commands for debugging**

**Testing:** ✅ COMPLETE
- ✅ Unit tests for hierarchy detection (16 tests)
- ✅ Unit tests for menu building (11 tests)
- ✅ Unit tests for AJAX handler (9 tests)
- ✅ **Critical bug regression tests (3 tests for ID format fix)**
- ✅ Test fixtures for reusable test data
- ✅ Total: 36 tests, 75%+ coverage achieved
- ✅ All tests passing locally and in CI

---

### Phase 5.2: Link Resolution (4-5 days)

**Goal:** Convert all internal Notion links to WordPress permalinks

**Tasks:**

1. **Enhance LinkRegistry**
   - Add batch resolution method
   - Add broken link detection
   - Store resolution status

2. **Two-Pass Sync**
   - Pass 1: Sync all pages (create posts)
   - Pass 2: Resolve all links (update content)

3. **Link Resolution Admin UI**
   - Show pending/broken links
   - Manual "Resolve Links" button
   - Link status report

**Files to Enhance:**
```
plugin/src/Router/
├── LinkRegistry.php          - Add batch resolution
└── LinkRewriter.php          - Add content rewriting
```

**New Methods:**
```php
class LinkRegistry {
    public function resolve_all_pending(): array;
    public function find_broken_links(): array;
    public function rewrite_post_content( int $post_id ): bool;
}
```

**Success Criteria:**
- ✅ Internal links work after sync
- ✅ Broken links reported in admin
- ✅ 99% link resolution accuracy
- ✅ Performance < 1s per 10 links

**Testing:**
- Link resolution tests
- Broken link detection tests
- Content rewriting tests

---

### Phase 5.3: Database View Rendering (1.5-2 weeks)

**Goal:** Render embedded database views inline

**Tasks:**

1. **Database View Parser** (3-4 days)
   - Parse view configuration from Notion API
   - Extract filters, sorts, page_size
   - Cache view configs (60 min)

2. **Database View Renderer** (4-5 days)
   - Fetch database entries with filters
   - Apply sorts
   - Render as HTML table (start simple)
   - Link entries to WordPress posts

3. **Enhanced ChildDatabaseConverter** (2-3 days)
   - Use renderer instead of simple link
   - Handle inline vs linked databases
   - Add empty state fallback

**Files to Create:**
```
plugin/src/Database/
├── ViewParser.php            - Parse view configuration
├── ViewRenderer.php          - Render database views
└── FilterApplicator.php      - Apply Notion filters to WP_Query
```

**View Rendering Strategy:**

Start with **simple HTML tables**, not custom Gutenberg blocks:

```html
<div class="notion-database-view">
    <h3>Task Database</h3>
    <table class="wp-table">
        <thead>
            <tr><th>Name</th><th>Status</th><th>Date</th></tr>
        </thead>
        <tbody>
            <tr>
                <td><a href="/task-1">Task 1</a></td>
                <td>In Progress</td>
                <td>2025-01-15</td>
            </tr>
        </tbody>
    </table>
</div>
```

**Filters to Support (v1):**

Priority filters (cover 80% of use cases):
- Text: `equals`, `contains`
- Select: `equals`
- Date: `equals`, `after`, `before`
- Checkbox: `equals`

Defer complex filters to v1.1+

**Success Criteria:**
- ✅ Inline databases render as tables
- ✅ Basic filters applied correctly
- ✅ Sorts applied correctly
- ✅ Entries link to synced posts
- ✅ Pagination works (default 20 entries)

**Testing:**
- View parsing tests
- Filter application tests
- Rendering tests
- Integration tests with embedded databases

---

## Technical Architecture (Simplified)

### New Components

```
plugin/src/
├── Hierarchy/
│   ├── HierarchyDetector.php      - Child page detection
│   └── MenuBuilder.php             - Menu generation (preserve manual items)
├── Navigation/
│   ├── MenuManager.php             - Menu CRUD operations
│   ├── MenuItemMeta.php            - Metadata handling
│   └── MenuOverrideHandler.php     - Override system logic
├── Database/
│   ├── ViewParser.php              - Parse view config
│   ├── ViewRenderer.php            - Render views as HTML
│   └── FilterApplicator.php        - Apply filters
└── Router/ (enhanced)
    ├── LinkRegistry.php             - Add batch resolution
    └── LinkRewriter.php             - Content rewriting

plugin/admin/
└── pages/
    └── menu-manager.php            - Menu management UI (CRUD)
```

### Data Structures

**Hierarchy Map:**
```php
[
    'page_123' => [
        'post_id' => 456,
        'parent_page_id' => 'page_100',
        'parent_post_id' => 400,
        'title' => 'Child Page',
        'order' => 0,
    ]
]
```

**View Configuration:**
```php
[
    'database_id' => 'abc123',
    'filters' => [
        ['property' => 'Status', 'equals' => 'Published']
    ],
    'sorts' => [
        ['property' => 'Date', 'direction' => 'desc']
    ],
    'page_size' => 20,
]
```

---

## Admin UI

### Menu Manager (NEW)

**Location:** WP Admin → Notion Sync → Menus

**Features:**

**Menu List View:**
- Table of all WordPress menus
- Show sync status (Notion-synced, Manual, Mixed)
- Last sync timestamp
- Item count
- "Manage Items" button per menu
- "Create New Menu" button

**Menu Items Manager:**
- Tree view of menu items (drag-drop reordering)
- Per-item controls:
  - **Sync Override Toggle** - Ignore Notion updates for this item
  - **Edit** - Change title, URL, CSS classes
  - **Delete** - Remove item
  - **Add Child** - Create nested item
- "Add Item" button with options:
  - Notion Page (search/select)
  - WordPress Page
  - WordPress Post
  - Custom Link (URL)
  - Category
- Item badges showing:
  - 🔄 Notion-synced
  - 🔒 Override enabled
  - ✏️ Manual item
- "Sync from Notion Now" button
- "Save Order" button

**Menu Settings Panel:**
- Theme location assignment (checkboxes for available locations)
- Auto-sync on page sync (toggle)
- Sync strategy:
  - "Merge" - Keep manual items, update Notion items
  - "Replace" - Replace all with Notion structure
  - "Manual Only" - Don't auto-sync, manual trigger only

**Gutenberg Compatibility:**
- Works with WordPress Navigation block
- Menus appear in Navigation block menu selector
- Supports all native Navigation block features:
  - Multilevel nesting (up to 5 levels)
  - Custom colors and typography
  - Mobile menu responsiveness
  - Overlay and modal styles

### Settings: Hierarchy Tab

**Location:** WP Admin → Notion Sync → Settings → Hierarchy

**Settings:**
- Enable hierarchy sync (toggle)
- Max depth (1-10, default 5)
- Auto-generate menu (toggle)
- Default menu name (text input, default "Notion Navigation")
- Default sync strategy (Merge/Replace/Manual Only)

### Settings: Database Views Tab

**Location:** WP Admin → Notion Sync → Settings → Database Views

**Settings:**
- Render embedded databases (toggle)
- Max entries per view (10-50, default 20)
- Cache duration (minutes, default 60)
- Link entries to posts (toggle)

### Tools: Link Resolution

**Location:** WP Admin → Notion Sync → Tools

**Features:**
- "Resolve All Links" button
- Pending links count
- Broken links report
- Last resolution timestamp

---

## Technical Challenges & Pragmatic Solutions

### Challenge 1: View Configuration Not in API Response
**Problem:** `child_database` blocks don't include view config
**Solution:**
- Make separate API call to `retrieve_database`
- Cache for 60 minutes
- If API call fails, render basic table (all entries, no filters)

### Challenge 2: Complex Filter Expressions
**Problem:** Notion has 30+ filter types
**Solution:**
- Support 4-5 common filters in v1.0
- Add "unsupported filter" warning
- Iterate based on user requests

### Challenge 3: Performance with Large Databases
**Problem:** 1000+ entries could slow rendering
**Solution:**
- Default page_size = 20
- Cache rendered HTML (60 min)
- Add "View in Notion" link for full database

### Challenge 4: Link Timing (Child Pages Not Synced)
**Problem:** Parent processed before child exists
**Solution:**
- Two-pass sync (already planned)
- Store placeholder text for unresolved links
- Log warning if link still broken after pass 2

---

## Testing Strategy

### Unit Tests (Target: 75% coverage)

**HierarchyDetector:**
- Parse child_page blocks
- Build hierarchy map
- Respect depth limits

**MenuBuilder:**
- Create WordPress menu
- Add nested items
- Update existing menu
- Preserve manual items

**MenuManager:**
- CRUD operations on menu items
- Reorder items
- Add custom items

**MenuItemMeta:**
- Set/get sync flags
- Override handling
- Notion page ID tracking

**MenuOverrideHandler:**
- Merge logic
- Should update determination

**ViewParser:**
- Parse filter config
- Parse sort config
- Extract page_size

**ViewRenderer:**
- Render HTML table
- Apply filters
- Apply sorts

### Integration Tests (Phase 6)

**Hierarchy Sync:**
- Sync 3-level nested pages
- Verify parent-child in WP
- Menu generation

**Database Views:**
- Sync page with embedded database
- Verify filters applied
- Verify entries linked

### Manual Testing Scenarios

1. **Documentation Site:** 10 pages, 3 levels deep
2. **Blog with Tasks:** Post with embedded task database
3. **Project Site:** Projects with child pages

---

## Success Metrics

**Functional:**
- Sync 50+ page hierarchy < 2 minutes
- Generate menu with 30+ items < 10s
- Render database with 50+ entries < 3s
- Resolve 100+ links < 10s

**Coverage:**
- Unit test coverage 75%+
- All core features tested
- No critical bugs

**User Experience:**
- Non-technical user can sync nested pages
- Menu "just works" out of box
- Embedded databases readable
- Setup takes < 5 minutes

---

## Realistic Timeline

### Week 1: Hierarchy Detection & Basic Menu
- **Day 1-2:** HierarchyDetector implementation
- **Day 3-4:** MenuBuilder implementation (basic auto-generation)
- **Day 5:** Integration testing, bug fixes

### Week 2: Menu CRUD & Override System
- **Day 1-2:** MenuManager, MenuItemMeta, MenuOverrideHandler
- **Day 3-5:** Admin UI for menu management (CRUD interface)

### Week 3: Link Resolution
- **Day 1-2:** Batch resolution in LinkRegistry
- **Day 3:** Two-pass sync implementation
- **Day 4-5:** Admin UI for link status

### Week 4-5: Database Views
- **Week 4:** ViewParser + basic rendering
- **Week 5:** Filter support + ChildDatabaseConverter enhancement

**Total: 4-5 weeks** (expanded from 3-4 weeks to include menu CRUD UI)

---

## What's Deferred to v1.1+

### Advanced Database Views
- Gallery view (grid with images)
- Board view (kanban)
- List view with icons
- Custom Gutenberg blocks for views

### Advanced Filters
- Complex filter expressions (AND/OR)
- Relation filters
- Formula filters
- Rollup filters

### Advanced Features
- Breadcrumb generation
- Table of contents from hierarchy
- Sitemap XML generation
- Real-time menu updates via webhooks

---

## Dependencies

**Required from Previous Phases:**
- ✅ Phase 1: Page sync, post meta storage
- ✅ Phase 2: Database querying, batch processing
- ✅ Phase 4: Block converter system, LinkRegistry

**WordPress APIs Used:**
- `wp_create_nav_menu()`, `wp_update_nav_menu_item()`
- `wp_update_post()` for hierarchy
- `WP_Query` for database filtering
- `get_permalink()` for link resolution

---

## Definition of Done

**Code:**
- [ ] All components implemented
- [ ] Unit tests 75%+ coverage
- [ ] All tests passing
- [ ] No PHP warnings/errors
- [ ] WPCS linting passes

**Functionality:**
- [ ] 3+ level hierarchy syncs
- [ ] Menu auto-generated
- [ ] Links resolved correctly
- [ ] Database views render

**Documentation:**
- [ ] Code comments complete
- [ ] User guide updated
- [ ] API docs for extensibility

**User Testing:**
- [ ] 3+ real users test successfully
- [ ] Feedback incorporated
- [ ] No critical bugs

---

## Next Steps

1. Create `phase-5-hierarchy-navigation` worktree
2. Set up initial class stubs
3. Write failing tests for HierarchyDetector
4. Begin implementation (Week 1, Day 1)

**First Commit:** Scaffold Hierarchy namespace with empty classes
