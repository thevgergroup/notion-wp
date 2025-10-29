# Phase 5: Hierarchy & Navigation - Implementation Plan

**Status:** 📋 Ready to Start
**Estimated Duration:** 3-4 weeks
**Complexity:** Large (L) - but simplified from original 5-6 week estimate
**Current Coverage:** 23.26%
**Target Coverage:** 75%+ unit tests

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

### Phase 5.1: Page Hierarchy & Menus (1 week)

**Goal:** Sync nested pages and generate navigation menu

**Tasks:**

1. **Detect Child Pages**
   - Use existing block converter to find `child_page` blocks
   - Build simple parent-child map
   - Add depth limit (max 5 levels, configurable)

2. **Set WordPress Hierarchy**
   - Use `wp_update_post()` with `post_parent`
   - Set `menu_order` from Notion ordering
   - Update existing hierarchy on re-sync

3. **Generate Menu**
   - Use `wp_create_nav_menu()` to create menu
   - Use `wp_update_nav_menu_item()` to add pages
   - Respect hierarchy with `menu_item_parent`
   - Add admin setting for target menu name

**Files to Create:**
```
plugin/src/Hierarchy/
├── HierarchyDetector.php    - Find child pages in Notion
└── MenuBuilder.php           - WordPress menu generation
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
}
```

**Success Criteria:**
- ✅ Child pages appear under parent in WP admin
- ✅ Menu auto-generated with correct nesting
- ✅ Re-sync updates menu (adds new, keeps structure)
- ✅ Works with 3+ levels of nesting

**Testing:**
- Unit tests for hierarchy detection
- Integration tests with nested pages
- Menu generation tests

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
│   └── MenuBuilder.php             - Menu generation
├── Database/
│   ├── ViewParser.php              - Parse view config
│   ├── ViewRenderer.php            - Render views as HTML
│   └── FilterApplicator.php        - Apply filters
└── Router/ (enhanced)
    ├── LinkRegistry.php             - Add batch resolution
    └── LinkRewriter.php             - Content rewriting
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

### Settings: Hierarchy Tab

**Location:** WP Admin → Notion Sync → Settings → Hierarchy

**Settings:**
- Enable hierarchy sync (toggle)
- Max depth (1-10, default 5)
- Auto-generate menu (toggle)
- Menu name (text input, default "Notion Navigation")

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

### Week 1: Hierarchy & Menus
- **Day 1-2:** HierarchyDetector implementation
- **Day 3-4:** MenuBuilder implementation
- **Day 5:** Integration testing, bug fixes

### Week 2: Link Resolution
- **Day 1-2:** Batch resolution in LinkRegistry
- **Day 3:** Two-pass sync implementation
- **Day 4-5:** Admin UI for link status

### Week 3-4: Database Views
- **Week 3:** ViewParser + basic rendering
- **Week 4:** Filter support + ChildDatabaseConverter enhancement

**Total: 3-4 weeks**

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
