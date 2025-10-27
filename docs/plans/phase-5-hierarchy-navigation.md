# Phase 5: Hierarchy & Navigation

**Status:** 📋 Ready to Start
**Goal:** Sync page hierarchies, handle embedded database views, and automatically generate WordPress navigation menus.

## Overview

Phase 5 transforms the plugin from a single-page/database sync tool into a complete content management system that preserves Notion's hierarchical structure and relationships in WordPress.

## Success Criteria

### Core Features
- [ ] Child pages in Notion sync as child pages in WordPress
- [ ] Parent-child relationships preserved across syncs
- [ ] WordPress navigation menu auto-generated from Notion structure
- [ ] Internal Notion links convert to WordPress permalinks
- [ ] Menu updates on re-sync (adds new, removes deleted)
- [ ] User can choose which menu to update
- [ ] Works with 3+ levels of nesting

### Embedded Database Views
- [ ] Inline database views render with filtered/sorted entries
- [ ] Linked database views apply view-specific filters
- [ ] Database view display modes (table, list, gallery, board)
- [ ] View pagination and limits respected
- [ ] Filter configurations applied correctly
- [ ] Sort configurations applied correctly
- [ ] Embedded entries link to synced WordPress posts when available

### Enhanced Link Handling
- [ ] Link-to-page blocks resolve to WordPress permalinks
- [ ] Link-to-database blocks resolve to archive pages
- [ ] Broken link detection and warnings
- [ ] External links preserved as-is
- [ ] Email and phone links handled correctly

## Architecture Components

### 1. Hierarchy Detection & Sync

**Component:** `HierarchyManager`
**Location:** `plugin/src/Hierarchy/HierarchyManager.php`

**Responsibilities:**
- Detect parent-child relationships in Notion
- Build complete page tree structure
- Handle recursive page fetching
- Track depth limits (prevent infinite loops)
- Coordinate hierarchical sync operations

**Key Methods:**
```php
class HierarchyManager {
    public function build_page_tree( string $root_page_id, int $max_depth = 5 ): array;
    public function sync_hierarchy( array $page_tree, int $parent_post_id = 0 ): array;
    public function get_child_pages( string $parent_page_id ): array;
    public function detect_circular_references( array $page_tree ): bool;
}
```

**Technical Considerations:**
- Use Notion API's `retrieve block children` to find child_page blocks
- Implement breadth-first traversal to avoid deep recursion
- Cache page relationships to avoid duplicate API calls
- Handle orphaned pages (parent doesn't exist in sync scope)

---

### 2. WordPress Page Hierarchy

**Component:** `WordPressHierarchySync`
**Location:** `plugin/src/Hierarchy/WordPressHierarchySync.php`

**Responsibilities:**
- Set WordPress post parent relationships
- Handle post ordering/menu_order
- Manage page slugs and permalinks
- Clean up orphaned pages on re-sync
- Update page URLs after hierarchy changes

**Key Methods:**
```php
class WordPressHierarchySync {
    public function set_parent( int $post_id, int $parent_post_id ): bool;
    public function set_menu_order( int $post_id, int $order ): bool;
    public function update_hierarchy( array $hierarchy_map ): array;
    public function find_orphaned_pages( array $synced_page_ids ): array;
}
```

**Data Structure:**
```php
// Hierarchy map format
[
    'notion_page_id' => [
        'wp_post_id' => 123,
        'parent_notion_id' => 'abc123',
        'parent_wp_id' => 100,
        'children' => [...],
        'order' => 0,
        'depth' => 1,
    ]
]
```

---

### 3. Menu Generation

**Component:** `MenuGenerator`
**Location:** `plugin/src/Navigation/MenuGenerator.php`

**Responsibilities:**
- Create WordPress navigation menus
- Add pages in hierarchical order
- Handle nested menu items
- Update existing menus on re-sync
- Assign menus to theme locations (optional)

**Key Methods:**
```php
class MenuGenerator {
    public function create_menu( string $menu_name, array $page_tree ): int;
    public function update_menu( int $menu_id, array $page_tree ): bool;
    public function add_pages_to_menu( int $menu_id, array $pages, int $parent_menu_item_id = 0 ): array;
    public function sync_menu_items( int $menu_id, array $current_pages ): void;
}
```

**Features:**
- Smart menu updates (add new pages, remove deleted)
- Preserve custom menu items (non-Notion pages)
- Handle menu item metadata (CSS classes, target, etc.)
- Support multiple menu locations

---

### 4. Link Conversion & Registry

**Component:** Enhanced `LinkRegistry` and `LinkRewriter`
**Location:** `plugin/src/Router/` (already exists, needs enhancement)

**Current State:**
- ✅ Basic Notion ID → WordPress permalink mapping
- ✅ Dynamic link resolution
- ✅ Link registration system

**Enhancements Needed:**
- [ ] Batch link resolution (resolve all links after sync)
- [ ] Link validation (detect broken links)
- [ ] Archive page generation for databases
- [ ] Link preview/fallback content
- [ ] External link detection

**New Methods:**
```php
class LinkRegistry {
    public function resolve_all_pending_links(): array;
    public function find_broken_links(): array;
    public function generate_archive_page( string $database_id ): int;
    public function get_link_preview( string $notion_id ): ?array;
}
```

---

### 5. Embedded Database View Renderer

**Component:** `DatabaseViewRenderer`
**Location:** `plugin/src/Database/DatabaseViewRenderer.php`

**Responsibilities:**
- Fetch database entries with view-specific filters
- Apply sorts and pagination from view config
- Render entries in appropriate display format
- Link entries to synced WordPress posts
- Handle empty states and errors

**Key Methods:**
```php
class DatabaseViewRenderer {
    public function render_view( string $database_id, array $view_config ): string;
    public function fetch_filtered_entries( string $database_id, array $filters, array $sorts, int $page_size ): array;
    public function render_table_view( array $entries, array $properties ): string;
    public function render_list_view( array $entries ): string;
    public function render_gallery_view( array $entries ): string;
    public function render_board_view( array $entries, string $group_by_property ): string;
}
```

**View Configuration Structure:**
```php
[
    'type' => 'table', // table, list, gallery, board
    'filters' => [
        [
            'property' => 'Status',
            'condition' => 'equals',
            'value' => 'Published'
        ]
    ],
    'sorts' => [
        [
            'property' => 'Created',
            'direction' => 'descending'
        ]
    ],
    'page_size' => 10,
    'properties' => ['Name', 'Status', 'Date'], // visible properties
]
```

---

### 6. Database View Parser

**Component:** `DatabaseViewParser`
**Location:** `plugin/src/Database/DatabaseViewParser.php`

**Responsibilities:**
- Extract view configuration from Notion API
- Parse filter expressions
- Parse sort configurations
- Detect view display type
- Extract visible properties

**Key Methods:**
```php
class DatabaseViewParser {
    public function parse_view( array $notion_block ): array;
    public function parse_filters( array $filter_config ): array;
    public function parse_sorts( array $sort_config ): array;
    public function get_view_type( array $notion_block ): string;
}
```

**Notion API Structure (child_database block):**
```json
{
    "type": "child_database",
    "id": "abc123",
    "child_database": {
        "title": "Tasks"
    },
    // Note: View configuration may require separate API call
    // to retrieve_database or query_database with view_id
}
```

---

### 7. Enhanced Block Converters

**Updates to Existing Converters:**

#### `ChildDatabaseConverter` Enhancement
**Location:** `plugin/src/Blocks/Converters/ChildDatabaseConverter.php`

**Current:** Creates simple link to database
**Enhancement:** Render inline database view with filters/sorts

```php
public function convert( array $notion_block ): string {
    $database_id = $notion_block['id'];
    $title = $notion_block['child_database']['title'];

    // Fetch view configuration
    $view_config = $this->get_view_configuration( $database_id );

    // Render database view
    $renderer = new DatabaseViewRenderer();
    return $renderer->render_view( $database_id, $view_config );
}
```

#### `LinkToPageConverter` Enhancement
**Location:** `plugin/src/Blocks/Converters/LinkToPageConverter.php`

**Current:** Creates dynamic notion-link block
**Enhancement:** Resolve to WordPress permalink immediately when available

```php
public function convert( array $notion_block ): string {
    $page_id = $notion_block['link_to_page']['page_id'];

    // Try to resolve immediately
    $permalink = LinkRegistry::resolve( $page_id );

    if ( $permalink ) {
        // Create standard WordPress link
        return $this->create_wordpress_link( $permalink, $title );
    } else {
        // Fallback to dynamic block (current behavior)
        return $this->create_dynamic_link_block( $page_id );
    }
}
```

---

## Database View Display Components

### WordPress Gutenberg Blocks

We'll need custom Gutenberg blocks for rendering database views:

#### 1. Database Table Block
**Block Name:** `notion-sync/database-table`

**Features:**
- Responsive table with horizontal scroll
- Sortable columns (client-side)
- Filterable rows (client-side)
- Property-based column rendering
- Link to full database view

**Attributes:**
```json
{
    "databaseId": "abc123",
    "databaseTitle": "Tasks",
    "entries": [...],
    "visibleProperties": ["Name", "Status", "Date"],
    "showHeader": true,
    "isStriped": true
}
```

#### 2. Database List Block
**Block Name:** `notion-sync/database-list`

**Features:**
- Bulleted or numbered list
- Entry titles as links
- Optional property badges (status, tags)
- Compact view for navigation

**Attributes:**
```json
{
    "databaseId": "abc123",
    "entries": [...],
    "listStyle": "bullets", // bullets, numbers, none
    "showProperties": ["Status"],
    "linkToEntries": true
}
```

#### 3. Database Gallery Block
**Block Name:** `notion-sync/database-gallery`

**Features:**
- Grid layout with images
- Entry cards with title/description
- Responsive columns (2, 3, 4 columns)
- Lightbox for images (optional)

**Attributes:**
```json
{
    "databaseId": "abc123",
    "entries": [...],
    "columns": 3,
    "showTitles": true,
    "imageProperty": "Cover",
    "cardHeight": "medium"
}
```

#### 4. Database Board Block
**Block Name:** `notion-sync/database-board`

**Features:**
- Kanban-style board
- Group by select/multi-select property
- Drag-and-drop (view-only, no updates)
- Collapsible columns

**Attributes:**
```json
{
    "databaseId": "abc123",
    "entries": [...],
    "groupByProperty": "Status",
    "showEmptyGroups": true,
    "cardProperties": ["Assignee", "Due Date"]
}
```

---

## Implementation Phases

### Phase 5.1: Page Hierarchy (1 week)

**Goal:** Sync parent-child page relationships

**Tasks:**
1. Implement `HierarchyManager` for tree building
2. Implement `WordPressHierarchySync` for WP relationships
3. Update page sync to respect hierarchy
4. Add depth limit configuration
5. Handle circular reference detection
6. Test with 3+ level nested pages

**Deliverables:**
- Child pages appear under parent in WordPress
- Page breadcrumbs work correctly
- Hierarchy preserved on re-sync

---

### Phase 5.2: Menu Generation (3-4 days)

**Goal:** Auto-generate WordPress menus from page structure

**Tasks:**
1. Implement `MenuGenerator`
2. Add admin UI for menu selection/creation
3. Implement menu sync logic (add/update/remove)
4. Handle menu item ordering
5. Test with theme menu locations

**Deliverables:**
- Auto-generated navigation menu
- Menu updates on re-sync
- User can assign to theme locations

---

### Phase 5.3: Link Resolution (3-4 days)

**Goal:** Convert all internal Notion links to WordPress permalinks

**Tasks:**
1. Enhance `LinkRegistry` with batch resolution
2. Implement link validation
3. Add broken link detection
4. Update `LinkToPageConverter` for immediate resolution
5. Add post-sync link resolution pass

**Deliverables:**
- Internal links work correctly
- Broken links flagged in admin
- Link preview fallbacks

---

### Phase 5.4: Database View Infrastructure (1 week)

**Goal:** Set up foundation for embedded database views

**Tasks:**
1. Implement `DatabaseViewParser`
2. Implement `DatabaseViewRenderer` (basic)
3. Add database entry fetching with filters
4. Add database entry sorting
5. Test with simple table view

**Deliverables:**
- Can parse view configurations
- Can fetch filtered/sorted entries
- Basic table rendering works

---

### Phase 5.5: Database View Display (1 week)

**Goal:** Render all database view types

**Tasks:**
1. Create `notion-sync/database-table` block
2. Create `notion-sync/database-list` block
3. Create `notion-sync/database-gallery` block
4. Create `notion-sync/database-board` block
5. Add responsive styling
6. Link entries to WordPress posts

**Deliverables:**
- All 4 view types render correctly
- Responsive on mobile
- Entries link to synced posts

---

### Phase 5.6: Enhanced ChildDatabaseConverter (3-4 days)

**Goal:** Upgrade embedded database handling

**Tasks:**
1. Update `ChildDatabaseConverter` to use renderer
2. Fetch view configuration from Notion API
3. Apply filters and sorts
4. Handle pagination
5. Add empty state handling
6. Test with complex views

**Deliverables:**
- Embedded databases render inline
- Filters/sorts applied correctly
- Pagination works

---

## Technical Challenges & Solutions

### Challenge 1: Notion API View Configuration

**Problem:** Notion API doesn't return view configuration (filters, sorts) in child_database block.

**Solution:**
- Make additional API call to `retrieve_database` or `query_database`
- Parse view configuration from database metadata
- Cache view configurations to reduce API calls
- Provide admin UI to override/customize views

### Challenge 2: Performance with Large Databases

**Problem:** Rendering 100+ entry database inline could slow page load.

**Solution:**
- Implement pagination (show first 10-20 entries)
- Add "Load More" button for client-side expansion
- Cache rendered database views (transient cache)
- Provide option to collapse/expand database views
- Use WordPress lazy loading for images

### Challenge 3: Circular Page References

**Problem:** Notion allows circular references (Page A → Page B → Page A).

**Solution:**
- Track visited pages during tree traversal
- Detect circular references and break the loop
- Max depth limit (default 5 levels)
- Log warnings for circular references

### Challenge 4: Menu Item Sync Conflicts

**Problem:** User may manually edit WordPress menu, sync could overwrite.

**Solution:**
- Mark Notion-synced menu items with meta flag
- Only update items marked as synced
- Preserve custom menu items
- Provide "force sync" option to reset menu

### Challenge 5: Link Resolution Timing

**Problem:** Child pages may not be synced when parent is processed.

**Solution:**
- Two-pass sync:
  1. First pass: Sync all pages, store links as placeholders
  2. Second pass: Resolve all links to WordPress permalinks
- Queue link resolution as background job
- Dynamic link blocks auto-update when target synced

---

## Database View Filter Reference

### Notion Filter Types

**Text Filters:**
- `equals`, `does_not_equal`
- `contains`, `does_not_contain`
- `starts_with`, `ends_with`
- `is_empty`, `is_not_empty`

**Number Filters:**
- `equals`, `does_not_equal`
- `greater_than`, `less_than`
- `greater_than_or_equal_to`, `less_than_or_equal_to`
- `is_empty`, `is_not_empty`

**Date Filters:**
- `equals`, `before`, `after`, `on_or_before`, `on_or_after`
- `is_empty`, `is_not_empty`
- Relative: `past_week`, `past_month`, `past_year`, `next_week`, etc.

**Select/Multi-select Filters:**
- `equals`, `does_not_equal`
- `is_empty`, `is_not_empty`

**Checkbox Filters:**
- `equals` (true/false)

**Relation Filters:**
- `contains`, `does_not_contain`
- `is_empty`, `is_not_empty`

### Implementation Strategy

Create filter converter classes:

```php
interface FilterConverter {
    public function convert( array $notion_filter ): array; // WP_Query args
}

class TextFilterConverter implements FilterConverter { ... }
class DateFilterConverter implements FilterConverter { ... }
class SelectFilterConverter implements FilterConverter { ... }
```

---

## Database View Sort Reference

### Notion Sort Types

**Supported:**
- `ascending`, `descending`
- Sort by property
- Multi-level sorts (primary, secondary, tertiary)

**Implementation:**
```php
class SortConverter {
    public function convert_sorts( array $notion_sorts ): array {
        // Convert to WP_Query orderby arguments
        return [
            'orderby' => [...],
            'order' => 'ASC|DESC',
        ];
    }
}
```

---

## Admin UI Enhancements

### Hierarchy Settings Panel

**Location:** WP Admin → Notion Sync → Settings → Hierarchy

**Settings:**
- [ ] Enable hierarchy sync (on/off)
- [ ] Max depth level (1-10, default 5)
- [ ] Circular reference handling (skip/warn/error)
- [ ] Orphaned page handling (keep/delete/draft)
- [ ] Auto-generate menu (on/off)
- [ ] Target menu name (dropdown)
- [ ] Menu location assignment (checkboxes)

### Database View Settings Panel

**Location:** WP Admin → Notion Sync → Settings → Database Views

**Settings:**
- [ ] Render embedded databases (on/off)
- [ ] Default view type (table/list/gallery/board)
- [ ] Max entries per view (10-100, default 20)
- [ ] Enable pagination (on/off)
- [ ] Cache duration (minutes, default 60)
- [ ] Link entries to WordPress posts (on/off)
- [ ] Fallback for non-synced entries (hide/show link to Notion)

### Link Resolution Panel

**Location:** WP Admin → Notion Sync → Tools → Link Resolution

**Features:**
- View all Notion links in registry
- See resolution status (resolved/pending/broken)
- Manually trigger link resolution
- View broken links report
- Export link mapping (CSV)

---

## Testing Strategy

### Unit Tests

**HierarchyManager:**
- Build page tree from flat list
- Detect circular references
- Respect depth limits
- Handle missing parents

**MenuGenerator:**
- Create menu from page tree
- Update existing menu
- Preserve custom items
- Handle nested items

**DatabaseViewParser:**
- Parse filter configurations
- Parse sort configurations
- Detect view types

**DatabaseViewRenderer:**
- Render table view
- Render list view
- Apply filters correctly
- Apply sorts correctly

### Integration Tests

**Hierarchy Sync:**
- Sync 3-level nested pages
- Verify parent-child relationships
- Test circular reference handling
- Test orphaned page cleanup

**Menu Generation:**
- Generate menu from synced pages
- Update menu on re-sync
- Preserve custom menu items

**Database Views:**
- Sync page with embedded database
- Verify filters applied
- Verify sorts applied
- Verify entries linked to posts

### End-to-End Tests

**Scenario 1: Documentation Site**
- Notion workspace with nested docs
- Sync entire hierarchy
- Verify menu structure
- Test internal links

**Scenario 2: Blog with Embedded Databases**
- Blog post with embedded "Related Posts" database
- Sync with filters (same category)
- Verify embedded view renders
- Verify entries link to posts

**Scenario 3: Project Management Site**
- Project pages with embedded task databases
- Kanban view with status grouping
- Verify board rendering
- Test with 50+ tasks

---

## Estimated Complexity: L (Large)

**Total Estimated Time:** 5-6 weeks

**Breakdown:**
- Phase 5.1: Page Hierarchy - 1 week
- Phase 5.2: Menu Generation - 3-4 days
- Phase 5.3: Link Resolution - 3-4 days
- Phase 5.4: Database View Infrastructure - 1 week
- Phase 5.5: Database View Display - 1 week
- Phase 5.6: Enhanced ChildDatabaseConverter - 3-4 days
- Testing & Polish - 1 week

---

## Dependencies

**Required from Previous Phases:**
- Phase 1: Page sync functionality, post meta storage
- Phase 2: Database querying, property mapping, batch processing
- Phase 4: Block converter system, LinkRegistry, notion-sync/notion-link block

**External Dependencies:**
- WordPress menu functions
- WP_Query for filtering/sorting
- Gutenberg block registration
- React for custom blocks (if interactive features needed)

---

## Deliverables Checklist

**Core Infrastructure:**
- [ ] `HierarchyManager` class
- [ ] `WordPressHierarchySync` class
- [ ] `MenuGenerator` class
- [ ] Enhanced `LinkRegistry` with batch resolution
- [ ] `DatabaseViewParser` class
- [ ] `DatabaseViewRenderer` class

**Block Converters:**
- [ ] Enhanced `ChildDatabaseConverter`
- [ ] Enhanced `LinkToPageConverter`

**Gutenberg Blocks:**
- [ ] `notion-sync/database-table`
- [ ] `notion-sync/database-list`
- [ ] `notion-sync/database-gallery`
- [ ] `notion-sync/database-board`

**Admin UI:**
- [ ] Hierarchy settings panel
- [ ] Database view settings panel
- [ ] Link resolution tools panel
- [ ] Menu assignment interface

**Documentation:**
- [ ] Hierarchy sync user guide
- [ ] Menu generation guide
- [ ] Database view customization guide
- [ ] Link resolution troubleshooting

**Tests:**
- [ ] Unit tests for all components
- [ ] Integration tests for sync workflows
- [ ] End-to-end tests for user scenarios

---

## Success Metrics

**Functional:**
- Sync 100+ page hierarchy in under 5 minutes
- Generate navigation menu with 50+ items
- Render embedded database with 100+ entries
- Resolve 500+ internal links correctly
- 99% link resolution accuracy

**Performance:**
- Page hierarchy sync < 30s per 10 pages
- Menu generation < 10s
- Database view rendering < 3s
- Link resolution < 1s per 10 links

**User Experience:**
- Non-technical user can sync nested pages
- Auto-generated menu "just works"
- Embedded databases look good on mobile
- Broken links clearly indicated

---

## Future Enhancements (Post-v1.0)

**Phase 5+ Features:**
- Real-time menu updates via webhooks
- Breadcrumb navigation auto-generation
- Table of contents generation from hierarchy
- Site map XML generation
- Advanced database view customization UI
- Database view shortcodes for use anywhere
- Database entry pagination (AJAX load more)
- Database view search/filter UI (client-side)
- Notion workspace-level sync (multiple root pages)

---

## Risk Mitigation

**Risk 1: API Rate Limits**
- Implement request batching
- Cache API responses
- Provide manual sync controls

**Risk 2: Complex Hierarchies Breaking**
- Extensive testing with real Notion workspaces
- Graceful degradation (flat list fallback)
- Detailed error logging

**Risk 3: Database View Performance**
- Pagination by default
- Caching layer
- Lazy loading for images
- Option to disable embedded views

**Risk 4: User Confusion**
- Clear admin UI with help text
- Video tutorials
- Common scenario documentation
- Progressive disclosure (basic → advanced)

---

## Definition of Done

- [ ] All success criteria met
- [ ] All unit tests passing
- [ ] All integration tests passing
- [ ] End-to-end scenarios tested
- [ ] Admin UI functional and tested
- [ ] No console errors or PHP warnings
- [ ] Documentation complete
- [ ] Performance benchmarks met
- [ ] User testing completed (5+ users)
- [ ] Code review completed
- [ ] Ready for WordPress.org submission
