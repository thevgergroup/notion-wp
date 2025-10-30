# Phase 5.3: Database View Rendering - Implementation Plan

**Status:** 🚧 Planning
**Estimated Duration:** 1.5-2 weeks
**Complexity:** Medium (M)
**Dependencies:** Phase 5.1 (Hierarchy & Menus) ✅ Complete

## Overview

Phase 5.3 adds support for rendering embedded Notion database views as **interactive, client-side data tables** within WordPress content. Currently, `ChildDatabaseConverter` creates links back to Notion. This phase transforms those links into fully-rendered, filterable, sortable database views powered by Tabulator.js.

**Architecture Decision:** We're building this with custom Gutenberg blocks + REST API + client-side rendering (Tabulator.js) because:
- ✅ Interactive filtering/sorting requires JavaScript
- ✅ REST API properly implements WordPress security model
- ✅ Gutenberg blocks provide clean editor experience
- ✅ Tabulator handles complex table features (sorting, filtering, pagination, responsive)
- ✅ Separates concerns: backend (data/security) vs frontend (presentation/interaction)

## Goals

1. **REST API for Database Views** - Secure endpoint with filtering, sorting, pagination
2. **Custom Gutenberg Block** - `notion-wp/database-view` block for embedded views
3. **Tabulator Integration** - Interactive table with client-side features
4. **View Type Support** - Design for table/board/gallery/timeline (implement table first)
5. **WordPress Security** - Respect post visibility, user capabilities, authentication

## What We're Building

### Core Features

1. **REST API Endpoint** (`/wp-json/notion-wp/v1/database-view/{id}`)
   - Query database entries with filters and sorts
   - Implement WordPress security model (post visibility, capabilities)
   - Support pagination (page, per_page, offset)
   - Return normalized JSON for frontend consumption
   - Cache responses aggressively

2. **Custom Gutenberg Block** (`notion-wp/database-view`)
   - Block attributes: `databaseId`, `viewType`, `filters`, `sorts`, `columns`
   - Server-side rendering: Load Tabulator assets
   - Client-side: Initialize Tabulator with database data
   - Block controls: Edit filters, sorts, columns, view type
   - Preview mode and published mode

3. **Tabulator Integration**
   - Interactive table component
   - Client-side filtering and sorting
   - Pagination with remote data loading
   - Responsive design (mobile-friendly)
   - Column formatting (property type-specific)
   - Link to WordPress posts for synced entries

4. **View Type System** (Architecture)
   - Design extensible view type system
   - Implement `TableView` (Tabulator)
   - Defer `BoardView`, `GalleryView`, `TimelineView` to future phases
   - Each view type has renderer component

5. **Property Formatters**
   - Format title (link to post)
   - Format select/multi-select (badges)
   - Format dates (locale-aware)
   - Format checkbox (icons)
   - Format URL/email/phone (links)

### What We're NOT Building (Deferred)

- ❌ Board view (Kanban) - Phase 5.4
- ❌ Gallery view - Phase 5.4
- ❌ Timeline view - Phase 5.4
- ❌ Calendar view - Phase 5.4
- ❌ Formula/Relation/Rollup filters - Phase 5.5
- ❌ Real-time updates (webhooks) - Phase 6
- ❌ Inline editing - Phase 6

## Architecture

### System Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                     WordPress Admin (Gutenberg)                  │
│  ┌────────────────────────────────────────────────────────┐    │
│  │  notion-wp/database-view Block                         │    │
│  │  - Block attributes (databaseId, filters, sorts)       │    │
│  │  - Block controls (InspectorControls)                  │    │
│  │  - Server-side render (Tabulator container)            │    │
│  └────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────┘
                              ↓ saves block markup
┌─────────────────────────────────────────────────────────────────┐
│                     WordPress Frontend                           │
│  ┌────────────────────────────────────────────────────────┐    │
│  │  <div class="notion-database-view">                     │    │
│  │    <div id="tabulator-container"></div>                │    │
│  │  </div>                                                 │    │
│  │  <script>                                               │    │
│  │    Tabulator.init({                                     │    │
│  │      ajax: '/wp-json/notion-wp/v1/database-view/123'  │    │
│  │    });                                                  │    │
│  │  </script>                                              │    │
│  └────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────┘
                              ↓ AJAX request
┌─────────────────────────────────────────────────────────────────┐
│                        REST API                                  │
│  /wp-json/notion-wp/v1/database-view/{id}                      │
│  ┌────────────────────────────────────────────────────────┐    │
│  │  DatabaseViewController                                 │    │
│  │  1. Check permissions (WP security)                     │    │
│  │  2. Get database config (filters, sorts)               │    │
│  │  3. Query Notion API via DatabaseFetcher               │    │
│  │  4. Format data via PropertyFormatter                  │    │
│  │  5. Cache response (Transients)                        │    │
│  │  6. Return JSON                                         │    │
│  └────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────┘
                              ↓ queries Notion
┌─────────────────────────────────────────────────────────────────┐
│                        Notion API                                │
│  POST /v1/databases/{id}/query                                  │
│  - filters, sorts, pagination                                   │
└─────────────────────────────────────────────────────────────────┘
```

### New Components

```
plugin/src/API/
└── DatabaseViewController.php    - REST endpoint controller

plugin/src/Database/
├── ViewConfig.php                - View configuration model
├── ViewTypeRegistry.php          - Register view types (table, board, etc)
├── ViewTypes/
│   ├── ViewTypeInterface.php     - Interface for view types
│   ├── TableView.php             - Table view implementation
│   └── [BoardView.php]           - Future: Kanban board view
└── PropertyFormatter.php         - Format Notion properties to JSON

plugin/blocks/
└── database-view/
    ├── block.json                - Block configuration
    ├── edit.js                   - Block editor (React)
    ├── save.js                   - Block save (minimal, uses dynamic render)
    ├── render.php                - Server-side render callback
    ├── view.js                   - Frontend Tabulator initialization
    └── style.scss                - Block styles

plugin/assets/vendor/
└── tabulator/                    - Tabulator.js library

tests/unit/API/
└── DatabaseViewControllerTest.php

tests/unit/Database/
├── ViewConfigTest.php
├── PropertyFormatterTest.php
└── ViewTypes/
    └── TableViewTest.php
```

### Data Flow

#### Block Editor Flow
```
User inserts block → Edit component loads
  → Fetches database schema (REST API)
  → Shows controls (filters, sorts, columns)
  → Preview updates on changes
  → Saves block attributes to post content
```

#### Frontend Render Flow
```
Page loads → render.php generates container HTML
  → Enqueues Tabulator assets
  → view.js initializes Tabulator
  → Tabulator makes AJAX to REST endpoint
  → DatabaseViewController:
      1. Validates request
      2. Checks WordPress permissions
      3. Gets cached data OR queries Notion
      4. Formats data via PropertyFormatter
      5. Returns JSON response
  → Tabulator renders data table
  → User interacts (sort/filter/paginate)
  → Tabulator makes new AJAX requests with params
```

## Implementation Plan

### Phase 5.3.1: REST API Foundation (3-4 days)

**Goal:** Build secure REST API endpoint for database queries

#### Tasks

1. **Create DatabaseViewController**
   - Register REST route: `/wp-json/notion-wp/v1/database-view/{id}`
   - Methods: `GET` for queries
   - Query params: `filters`, `sorts`, `page`, `per_page`
   - Permission callback (check post visibility, user capabilities)
   - Response caching via WordPress Transients (30 min TTL)

2. **Implement WordPress Security**
   - Check if database is public or requires authentication
   - Verify `read` capability for the associated post
   - Respect post status (published vs draft vs private)
   - Nonce verification for authenticated requests
   - Rate limiting (basic, via Transients)

3. **Create ViewConfig Model**
   - Parse and validate view configuration
   - Store: `databaseId`, `viewType`, `filters`, `sorts`, `columns`, `pageSize`
   - Validate filter syntax (Notion API format)
   - Validate sort syntax (Notion API format)
   - Default configurations

4. **Integrate DatabaseFetcher**
   - Use existing `DatabaseFetcher::query_database()`
   - Pass filters and sorts from request
   - Handle pagination (Notion cursor-based)
   - Error handling and retries

#### Files Created
- `plugin/src/API/DatabaseViewController.php`
- `plugin/src/Database/ViewConfig.php`
- `tests/unit/API/DatabaseViewControllerTest.php`
- `tests/unit/Database/ViewConfigTest.php`

#### Success Criteria
- ✅ REST endpoint registered and accessible
- ✅ Security checks pass (authentication, authorization)
- ✅ Filters and sorts applied correctly
- ✅ Pagination works (50 entries per page)
- ✅ Response cached appropriately
- ✅ Unit tests pass (12+ tests)
- ✅ Integration tests pass (3+ tests)

---

### Phase 5.3.2: Property Formatting & View Types (3-4 days)

**Goal:** Format Notion properties and design view type system

#### Tasks

1. **Create PropertyFormatter class**
   - `format_title( array $title_property ): array` - Return { text, url }
   - `format_select( array $select_property ): array` - Return { name, color }
   - `format_multi_select( array $multi_select ): array` - Return array of { name, color }
   - `format_date( array $date_property ): array` - Return { start, end, formatted }
   - `format_checkbox( array $checkbox ): bool`
   - `format_url( array $url ): string`
   - `format_email( array $email ): string`
   - `format_phone( array $phone ): string`
   - `format_number( array $number ): string`
   - `format_rich_text( array $rich_text ): string`
   - Return JSON-serializable data structures

2. **Create ViewTypeRegistry**
   - Register view types (table, board, gallery, timeline, calendar)
   - Get available view types
   - Get view type renderer class
   - Extensible for plugins to register custom view types

3. **Create ViewTypeInterface**
   - `get_name(): string` - e.g., "table"
   - `get_label(): string` - e.g., "Table View"
   - `supports_features(): array` - e.g., ['filtering', 'sorting', 'pagination']
   - `get_required_assets(): array` - CSS/JS assets needed

4. **Create TableView (first implementation)**
   - Implements ViewTypeInterface
   - Returns Tabulator configuration
   - Column definitions based on Notion properties
   - Formatters for each property type
   - Default table settings (pagination, sorting, filtering)

#### Files Created
- `plugin/src/Database/PropertyFormatter.php`
- `plugin/src/Database/ViewTypeRegistry.php`
- `plugin/src/Database/ViewTypes/ViewTypeInterface.php`
- `plugin/src/Database/ViewTypes/TableView.php`
- `tests/unit/Database/PropertyFormatterTest.php`
- `tests/unit/Database/ViewTypeRegistryTest.php`
- `tests/unit/Database/ViewTypes/TableViewTest.php`

#### Success Criteria
- ✅ All Notion property types formatted correctly
- ✅ Output is JSON-serializable
- ✅ ViewTypeRegistry manages view types
- ✅ TableView returns valid Tabulator config
- ✅ Post links use LinkRegistry correctly
- ✅ Unit tests pass (20+ tests)

---

### Phase 5.3.3: Gutenberg Block Development (4-5 days)

**Goal:** Create `notion-wp/database-view` Gutenberg block

#### Tasks

1. **Block Configuration** (`block.json`)
   - Block name: `notion-wp/database-view`
   - Category: `notion-wp`
   - Attributes: `databaseId`, `viewType`, `filters`, `sorts`, `columns`, `pageSize`
   - Supports: `align`, `className`
   - Dynamic rendering: `true`
   - Editor/Frontend scripts and styles

2. **Block Editor (edit.js)**
   - React component with InspectorControls
   - Database selector (autocomplete from synced databases)
   - View type selector (table only for now, UI for future types)
   - Filter builder UI (add/remove/edit filters)
   - Sort builder UI (add/remove/edit sorts)
   - Column selector (show/hide columns)
   - Live preview using ServerSideRender

3. **Block Save (save.js)**
   - Minimal save (just attributes)
   - Use dynamic render callback for actual output
   - No static HTML to avoid stale data

4. **Server-Side Render (render.php)**
   - Render `<div>` container with data attributes
   - Enqueue Tabulator assets
   - Enqueue view.js (frontend initialization)
   - Pass block attributes to JavaScript via `wp_localize_script`

5. **Frontend Script (view.js)**
   - Initialize Tabulator on page load
   - Read configuration from data attributes
   - Make AJAX request to REST endpoint
   - Handle Tabulator events (sorting, filtering, pagination)
   - Error handling and loading states

6. **Block Styles (style.scss)**
   - Container styles
   - Loading spinner
   - Error message styles
   - Responsive design
   - Tabulator customizations

#### Files Created
- `plugin/blocks/database-view/block.json`
- `plugin/blocks/database-view/edit.js`
- `plugin/blocks/database-view/save.js`
- `plugin/blocks/database-view/render.php`
- `plugin/blocks/database-view/view.js`
- `plugin/blocks/database-view/style.scss`
- `plugin/blocks/database-view/editor.scss`

#### Success Criteria
- ✅ Block appears in inserter
- ✅ Block editor shows controls
- ✅ Preview updates on changes
- ✅ Block saves attributes correctly
- ✅ Frontend renders Tabulator
- ✅ Interactive features work (sort, filter, paginate)
- ✅ Styles responsive and theme-compatible

---

### Phase 5.3.4: Tabulator Integration & Polish (2-3 days)

**Goal:** Integrate Tabulator.js and polish the experience

#### Tasks

1. **Add Tabulator.js**
   - Download Tabulator dist files to `plugin/assets/vendor/tabulator/`
   - Register Tabulator CSS/JS with WordPress
   - Version: 6.3+ (latest stable)
   - License: MIT (compatible)

2. **Configure Tabulator for Notion Data**
   - Define column formatters for each Notion property type
   - Title column with link formatter
   - Select column with badge formatter
   - Date column with date formatter
   - Checkbox column with icon formatter
   - Configure pagination (remote, 50 rows per page)
   - Configure sorting (remote, multi-column)
   - Configure filtering (header filters)

3. **Enhance ChildDatabaseConverter**
   - Detect `child_database` blocks during sync
   - Auto-insert `notion-wp/database-view` block
   - Pass database ID from Notion block
   - Preserve existing behavior as fallback
   - Log conversion for debugging

4. **Error Handling & Loading States**
   - Show loading spinner during AJAX
   - Display friendly error messages
   - Retry logic for failed requests
   - Fallback to "View in Notion" link on persistent errors
   - Log errors to browser console

#### Files Modified
- `plugin/src/Blocks/Converters/ChildDatabaseConverter.php`
- `plugin/blocks/database-view/view.js` (Tabulator config)

#### Success Criteria
- ✅ Tabulator loads correctly
- ✅ Tables render with Notion data
- ✅ Sorting works (client and server)
- ✅ Filtering works (client and server)
- ✅ Pagination works (50 rows/page)
- ✅ Loading states smooth
- ✅ Error messages helpful

---

### Phase 5.3.5: Testing & Documentation (2 days)

**Goal:** Comprehensive testing and documentation

#### Tasks

1. **Unit Tests** (40+ tests)
   - DatabaseViewController (15 tests)
   - PropertyFormatter (15 tests)
   - ViewConfig (5 tests)
   - TableView (5 tests)
   - ViewTypeRegistry (3 tests)

2. **Integration Tests** (8+ tests)
   - Full flow: Notion API → REST API → Frontend
   - Test with real Notion database
   - Test filtering combinations
   - Test sorting combinations
   - Test pagination
   - Test error scenarios
   - Test security (unauthorized access)
   - Test caching behavior

3. **End-to-End Tests** (3+ tests)
   - Insert block in editor
   - Configure filters/sorts
   - Verify frontend rendering
   - Verify interactive features

4. **Documentation**
   - User guide: Using database view blocks
   - Developer guide: Adding custom view types
   - REST API reference
   - Troubleshooting guide
   - Update Phase 5 plan with completion notes

5. **Performance Testing**
   - Benchmark REST API response times
   - Test with 500+ entry databases
   - Measure cache hit rates
   - Profile frontend rendering
   - Identify optimization opportunities

#### Success Criteria
- ✅ All unit tests pass (40+ tests)
- ✅ All integration tests pass (8+ tests)
- ✅ All E2E tests pass (3+ tests)
- ✅ Code coverage > 75%
- ✅ Documentation complete
- ✅ Performance targets met

---

## HTML Output Structure

### Gutenberg Block Markup (Server-Side Render)

```html
<!-- wp:notion-wp/database-view {
    "databaseId":"abc123def456",
    "viewType":"table",
    "filters":[{"property":"Status","select":{"equals":"Published"}}],
    "sorts":[{"property":"Date","direction":"descending"}],
    "pageSize":50
} -->
<div class="wp-block-notion-wp-database-view"
     data-database-id="abc123def456"
     data-view-type="table"
     data-filters='[{"property":"Status","select":{"equals":"Published"}}]'
     data-sorts='[{"property":"Date","direction":"descending"}]'
     data-page-size="50">

    <div id="notion-database-abc123def456" class="notion-database-container">
        <div class="notion-database-loading">
            <span class="spinner"></span>
            <span>Loading database...</span>
        </div>
    </div>
</div>
<!-- /wp:notion-wp/database-view -->
```

### Frontend JavaScript Initialization

```javascript
// view.js initializes Tabulator
document.addEventListener('DOMContentLoaded', function() {
    const containers = document.querySelectorAll('.wp-block-notion-wp-database-view');

    containers.forEach(container => {
        const databaseId = container.dataset.databaseId;
        const viewType = container.dataset.viewType;
        const filters = JSON.parse(container.dataset.filters || '[]');
        const sorts = JSON.parse(container.dataset.sorts || '[]');
        const pageSize = parseInt(container.dataset.pageSize || '50');

        new Tabulator(`#notion-database-${databaseId}`, {
            ajaxURL: `/wp-json/notion-wp/v1/database-view/${databaseId}`,
            ajaxParams: { filters, sorts },
            pagination: 'remote',
            paginationSize: pageSize,
            layout: 'fitColumns',
            responsiveLayout: 'collapse',
            // ... column definitions from view type
        });
    });
});
```

### REST API JSON Response

```json
{
    "data": [
        {
            "id": "page-123",
            "properties": {
                "title": {
                    "text": "Implement Database Views",
                    "url": "/tasks/implement-database-views"
                },
                "status": {
                    "name": "In Progress",
                    "color": "blue"
                },
                "date": {
                    "start": "2025-11-15",
                    "formatted": "Nov 15, 2025"
                }
            }
        }
    ],
    "pagination": {
        "page": 1,
        "per_page": 50,
        "total": 150,
        "total_pages": 3,
        "has_more": true
    },
    "cache": {
        "hit": false,
        "ttl": 1800,
        "generated_at": "2025-11-01T10:30:00Z"
    }
}
```

### Error State (Fallback)

```html
<div class="wp-block-notion-wp-database-view notion-error">
    <div class="notion-database-error">
        <p class="error-message">
            <strong>⚠️ Unable to load database</strong><br>
            The database could not be loaded. <a href="https://notion.so/abc123" target="_blank">View in Notion →</a>
        </p>
    </div>
</div>
```

## Supported Filters (v1)

### Priority Filters (80% use cases)

| Property Type | Supported Filters |
|--------------|-------------------|
| Text | `equals`, `does_not_equal`, `contains`, `does_not_contain` |
| Select | `equals`, `does_not_equal` |
| Multi-select | `contains`, `does_not_contain` |
| Date | `equals`, `before`, `after`, `on_or_before`, `on_or_after` |
| Checkbox | `equals` |

### Deferred to v1.1+

- Formula filters
- Relation filters
- Rollup filters
- Created/Edited time filters
- Created/Edited by filters
- Complex AND/OR logic (support simple AND only in v1)

## Property Formatting

### Property Display Formats

| Property Type | Display Format | Example |
|--------------|----------------|---------|
| Title | Link (if synced) or text | `<a href="/page">Title</a>` |
| Text | Plain text | `"This is text"` |
| Number | Formatted number | `1,234.56` |
| Select | Colored badge | `<span class="notion-select" style="color: blue">Status</span>` |
| Multi-select | Multiple badges | `<span>Tag1</span> <span>Tag2</span>` |
| Date | Formatted date | `<time datetime="2025-11-15">Nov 15, 2025</time>` |
| Checkbox | Icon | `✓` or `☐` |
| URL | Link | `<a href="url" target="_blank">url</a>` |
| Email | Link | `<a href="mailto:email">email</a>` |
| Phone | Link | `<a href="tel:phone">phone</a>` |

## Caching Strategy

### Cache Keys

```php
// View configuration cache
'notion_view_config_' . md5( $database_id )  // 60 min TTL

// Rendered view cache
'notion_view_render_' . md5( $database_id . serialize( $config ) )  // 30 min TTL
```

### Cache Invalidation

- Manual: "Clear Cache" button in admin
- Automatic: On database sync completion
- TTL-based: 60 min for configs, 30 min for rendered output

## Performance Targets

| Metric | Target | Measurement |
|--------|--------|-------------|
| View Config Parsing | < 100ms | Unit test timing |
| Database Query (50 entries) | < 500ms | Integration test timing |
| HTML Rendering | < 200ms | Unit test timing |
| Total (cache miss) | < 1s | End-to-end test |
| Total (cache hit) | < 50ms | End-to-end test |

## Testing Strategy

### Unit Tests (40+ tests)

1. **ViewParser Tests** (15 tests)
   - Parse valid configurations
   - Handle missing/invalid data
   - Extract filters correctly
   - Extract sorts correctly
   - Cache integration

2. **ViewRenderer Tests** (20 tests)
   - Render simple tables
   - Apply filters
   - Apply sorts
   - Format properties
   - Handle empty results
   - Handle errors
   - Generate links

3. **PropertyFormatter Tests** (10 tests)
   - Format each property type
   - Handle null/empty values
   - Escape HTML correctly

### Integration Tests (5+ tests)

1. Full conversion flow (Notion block → HTML)
2. Filtered database rendering
3. Sorted database rendering
4. Empty database handling
5. Error fallback to Notion link

## Success Criteria

### Functionality
- ✅ Embedded databases render as HTML tables
- ✅ Filters applied correctly (text, select, date, checkbox)
- ✅ Sorts applied correctly (single and multiple)
- ✅ Database entries link to WordPress posts when synced
- ✅ Unsynced entries link to Notion
- ✅ Empty states displayed gracefully
- ✅ Errors degrade to Notion links

### Performance
- ✅ View rendering < 2s (cache miss)
- ✅ View rendering < 50ms (cache hit)
- ✅ Cache hit rate > 80% for repeated views
- ✅ Memory usage reasonable (< 50MB per view)

### Quality
- ✅ 40+ unit tests passing
- ✅ 5+ integration tests passing
- ✅ Code coverage > 75%
- ✅ No PHP errors or warnings
- ✅ WordPress coding standards compliant
- ✅ Accessible markup (WCAG 2.1 AA)

### Documentation
- ✅ Implementation documented
- ✅ Supported filters documented
- ✅ Troubleshooting guide created
- ✅ Code comments comprehensive

## Rollout Plan

### Phase 5.3.1 (Days 1-4)
- Create ViewParser and ViewCache
- Write unit tests
- Commit: "feat(database): add view parser and caching"

### Phase 5.3.2 (Days 5-9)
- Create ViewRenderer and PropertyFormatter
- Write unit tests
- Commit: "feat(database): add view renderer"

### Phase 5.3.3 (Days 10-12)
- Enhance ChildDatabaseConverter
- Write integration tests
- Commit: "feat(database): integrate view rendering into block converter"

### Phase 5.3.4 (Days 13-14)
- Complete testing
- Update documentation
- Commit: "docs(database): add view rendering documentation"
- Open PR: "Phase 5.3: Database View Rendering"

## Risk Assessment

### Medium Risks

1. **Performance with Large Databases**
   - Risk: Slow rendering for 500+ entry databases
   - Mitigation: Aggressive caching, pagination, query optimization

2. **Complex Filters**
   - Risk: Notion filter syntax is complex
   - Mitigation: Start with simple filters, iterate based on usage

3. **Property Type Variations**
   - Risk: Notion has many property types with edge cases
   - Mitigation: Comprehensive unit tests, graceful degradation

### Low Risks

1. **Cache Invalidation**
   - Risk: Stale data shown to users
   - Mitigation: Reasonable TTLs, manual cache clear option

2. **HTML Styling**
   - Risk: Tables don't match theme styles
   - Mitigation: Use WordPress table classes, minimal custom CSS

## Future Enhancements (Post-MVP)

### Phase 5.3.1+ (Future)
- Interactive filtering UI
- Live database updates (webhooks)
- Alternative view types (board, gallery, calendar)
- Advanced filters (formula, relation, rollup)
- Export to CSV
- Inline editing capabilities
- Custom property renderers (extensibility API)

## Dependencies

### External
- Notion API (database query endpoint)
- WordPress Transients API (caching)
- LinkRegistry (post linking)
- DatabaseFetcher (query execution)

### Internal
- Phase 5.1 ✅ (hierarchy and menu building)
- ChildDatabaseConverter (current implementation)
- Block conversion system

## References

- [Notion API - Query Database](https://developers.notion.com/reference/post-database-query)
- [Notion API - Filter Objects](https://developers.notion.com/reference/post-database-query-filter)
- [Notion API - Sort Objects](https://developers.notion.com/reference/post-database-query-sort)
- [WordPress Transients API](https://developer.wordpress.org/apis/transients/)
- Phase 5 Main Plan: `docs/plans/phase-5-plan.md`
