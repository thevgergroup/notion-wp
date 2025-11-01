# Phase 5: Remaining Work

**Last Updated:** 2025-11-01

## Overview

Phase 5 core infrastructure is **substantially complete** (90%+). This document outlines the remaining work items needed to fully complete Phase 5.

## Completion Status Summary

### ✅ Fully Implemented (6/6 Sub-Phases)

#### Phase 5.1: Page Hierarchy
- ✅ `HierarchyDetector` class with full tree building
- ✅ Parent-child relationship detection from Notion API `parent` property
- ✅ Recursive hierarchy map building
- ✅ Depth limit enforcement (configurable 1-10, default 5)
- ✅ WordPress post parent updating via `notion_sync_page_synced` hook
- ✅ Meta storage (`_notion_parent_page_id`, `notion_page_id`)
- ✅ Circular reference prevention

**Files:** `plugin/src/Hierarchy/HierarchyDetector.php`

#### Phase 5.2: Menu Generation
- ✅ `MenuBuilder` class with recursive menu creation
- ✅ Create/update WordPress menus from hierarchy
- ✅ Preserve manually-added menu items
- ✅ Nested menu item support (3+ levels)
- ✅ `MenuItemMeta` for tracking sync status
- ✅ Override protection for manual edits
- ✅ `NavigationSync` orchestrator

**Files:**
- `plugin/src/Hierarchy/MenuBuilder.php`
- `plugin/src/Navigation/MenuItemMeta.php`
- `plugin/src/Navigation/MenuOverrideHandler.php`
- `plugin/src/Hierarchy/NavigationSync.php`

#### Phase 5.3: Link Resolution
- ✅ `LinkRegistry` with comprehensive Notion ID → WordPress permalink mapping
- ✅ Status tracking (synced, not_synced, outdated, syncing, failed)
- ✅ Batch resolution support
- ✅ Access analytics and tracking
- ✅ Unique slug generation with emoji removal
- ✅ UUID format handling (with/without dashes)
- ✅ Sync timestamp tracking
- ✅ Error tracking

**Files:** `plugin/src/Router/LinkRegistry.php`

#### Phase 5.4: Database View Infrastructure
- ✅ `DatabaseRestController` with REST API endpoints
- ✅ `/databases/{post_id}/rows` endpoint with pagination
- ✅ `RowRepository` for database row access
- ✅ `PropertyFormatter` for data formatting
- ✅ Caching layer (60min schema, 30min rows, 5min admin)
- ✅ Permission callbacks
- ✅ Cache invalidation hooks

**Files:**
- `plugin/src/API/DatabaseRestController.php`
- `plugin/src/Database/RowRepository.php`
- `plugin/src/Database/PropertyFormatter.php`

#### Phase 5.5: Database View Display (Table View)
- ✅ `notion-wp/database-view` Gutenberg block
- ✅ Block edit interface with settings:
  - Database selection dropdown
  - View type selector
  - Show filters toggle
  - Show export toggle
- ✅ Server-side render template (`plugin/blocks/database-view/render.php`)
- ✅ Frontend JavaScript with Tabulator integration
- ✅ Table view fully functional
- ✅ Filter controls UI
- ✅ Export CSV button

**Files:**
- `plugin/blocks/database-view/src/index.js`
- `plugin/blocks/database-view/src/edit.js`
- `plugin/blocks/database-view/src/frontend.js`
- `plugin/blocks/database-view/render.php`

#### Phase 5.6: Enhanced ChildDatabaseConverter
- ✅ Queries Notion `loadCachedPageChunkV2` API to find parent database
- ✅ Extracts `collection_id` from page chunk response
- ✅ Looks up WordPress database post by `notion_collection_id` meta
- ✅ Creates `notion-wp/database-view` block when database found
- ✅ Falls back to `notion-sync/notion-link` block when not found
- ✅ Proper error handling and debug logging
- ✅ LinkRegistry integration for fallback links

**Files:** `plugin/src/Blocks/Converters/ChildDatabaseConverter.php`

---

## 🚧 Remaining Work Items

### 1. Additional Database View Types

**Priority:** Medium
**Effort:** 2-3 weeks
**Affected Files:**
- `plugin/blocks/database-view/src/edit.js` - Remove "Coming Soon" labels
- `plugin/blocks/database-view/src/frontend.js` - Implement renderers
- `plugin/blocks/database-view/render.php` - Add view type templates

**Tasks:**

#### List View
- [ ] Create list view renderer in frontend JavaScript
- [ ] Implement bulleted/numbered list options
- [ ] Add property badge display (status, tags)
- [ ] Entry title as clickable links
- [ ] Compact view styling

#### Gallery View
- [ ] Create gallery view renderer
- [ ] Implement grid layout (2, 3, 4 columns responsive)
- [ ] Entry cards with image/title/description
- [ ] Image property selection
- [ ] Card height options (small, medium, large)
- [ ] Optional lightbox integration

#### Board View (Kanban)
- [ ] Create board view renderer
- [ ] Group by select/multi-select property
- [ ] Column rendering by property values
- [ ] Collapsible columns
- [ ] Card property display (assignee, due date, etc.)
- [ ] View-only (no drag-and-drop updates)

#### Timeline View
- [ ] Create timeline view renderer
- [ ] Date property selection for timeline axis
- [ ] Entry positioning by dates
- [ ] Zoom levels (day, week, month)
- [ ] Entry tooltips with details

#### Calendar View
- [ ] Create calendar view renderer
- [ ] Date property selection
- [ ] Month/week/day views
- [ ] Entry display on calendar dates
- [ ] Multi-day event spanning

**Acceptance Criteria:**
- All view types render correctly in editor and frontend
- View type selection in block settings works
- Responsive design on mobile devices
- Entries link to synced WordPress posts when available
- Empty states handled gracefully

---

### 2. MenuManager CRUD Operations

**Priority:** Low
**Effort:** 1-2 days
**Affected Files:** `plugin/src/Navigation/MenuManager.php`

**Current State:** File exists with TODO stub methods

**Tasks:**
- [ ] Implement `list_menus()` - Return all WordPress menus
- [ ] Implement `get_menu_items($menu_id)` - Get items for specific menu
- [ ] Implement `add_manual_item($menu_id, $item_data)` - Add non-synced items
- [ ] Implement `update_item($item_id, $item_data)` - Update menu item
- [ ] Implement `delete_item($item_id)` - Delete menu item
- [ ] Implement `reorder_items($menu_id, $order)` - Update menu_order

**Acceptance Criteria:**
- All methods return expected data structures
- Manual items integrate with MenuBuilder sync workflow
- Updates don't affect Notion-synced items marked with override flag

---

### 3. User Documentation & README Rework

**Priority:** High
**Effort:** 1 week
**Affected Files:**
- `README.md` - User-focused installation and usage guide
- `docs/development/DEVELOPMENT.md` - New file for developer documentation
- `docs/images/` - Screenshots directory (created)

**Current State:** README is developer-focused (Docker, worktrees, architecture)

**Tasks:**

#### 3.1 Rework README for WordPress Users

**Target Audience:** Non-technical WordPress site owners

**Sections to Include:**
- [ ] Plugin overview (what it does, why use it)
- [ ] Features list with status indicators (✅ Available, ⚠️ Beta, 🚧 Coming Soon)
- [ ] Requirements (WordPress version, PHP version, Notion account)
- [ ] Installation instructions
  - [ ] Install from WordPress.org (future)
  - [ ] Manual installation (download, upload, activate)
  - [ ] Post-installation checklist
- [ ] Getting Started Guide
  - [ ] Creating a Notion Integration
  - [ ] Getting your API token
  - [ ] Connecting to WordPress
  - [ ] Selecting pages/databases to sync
  - [ ] Running your first sync
- [ ] Configuration Guide
  - [ ] Settings overview with screenshots
  - [ ] Notion page selection
  - [ ] Database field mapping
  - [ ] Menu generation setup
  - [ ] Theme integration (how to display menus)
  - [ ] Recommended themes for best compatibility
- [ ] Features Documentation
  - [ ] Page sync (with screenshot)
  - [ ] Database sync (with screenshot)
  - [ ] Hierarchy & navigation (with screenshot)
  - [ ] Database views (table view screenshot)
  - [ ] Media handling
  - [ ] Internal links
- [ ] What's Available vs. What's Coming
  - [ ] Available: All features currently working
  - [ ] Beta: Database view types (list, gallery, board, etc.)
  - [ ] Coming Soon: WordPress → Notion sync
- [ ] Troubleshooting
  - [ ] Common issues and solutions
  - [ ] Where to get help
  - [ ] How to report bugs
- [ ] FAQ
  - [ ] Does this work with my theme?
  - [ ] Can I edit content in WordPress?
  - [ ] How often does it sync?
  - [ ] Is my Notion data secure?
- [ ] Screenshots (moved to docs/images/)
  - [ ] Settings page
  - [ ] Page selection interface
  - [ ] Sync dashboard
  - [ ] Database view in editor
  - [ ] Published page with hierarchy
  - [ ] Navigation menu generated from Notion

**Screenshot Requirements:**
- [ ] Use Playwright to capture screenshots
- [ ] Move all screenshots to `./docs/images/`
- [ ] Name screenshots descriptively (e.g., `settings-connection.png`, `database-table-view.png`)
- [ ] Ensure screenshots show realistic example content
- [ ] Include both editor and frontend views
- [ ] Resize screenshots to appropriate dimensions (1200px max width)

#### 3.2 Create Development Documentation

**New File:** `docs/development/DEVELOPMENT.md`

**Sections to Move from README:**
- [ ] Development setup (Docker, worktrees)
- [ ] Prerequisites (Docker, Node, Composer)
- [ ] Quick start for developers
- [ ] Worktree workflow
- [ ] Project structure (detailed)
- [ ] Common development commands
- [ ] Testing instructions (unit, integration)
- [ ] Debugging guide
- [ ] Code standards (PHPCS, ESLint)
- [ ] Git workflow
- [ ] Contributing guidelines
- [ ] Plugin development guides
  - [ ] Adding block converters
  - [ ] Adding background jobs
  - [ ] Adding REST endpoints
  - [ ] Custom field mappings
- [ ] Architecture references
  - [ ] Link to architecture docs
  - [ ] PSR-4 autoloading
  - [ ] Dependency injection
  - [ ] Repository pattern
- [ ] Troubleshooting for developers
  - [ ] Port conflicts
  - [ ] Database connection errors
  - [ ] Asset build failures

**Additional Development Docs to Create:**
- [ ] `docs/development/ARCHITECTURE.md` - System architecture overview
- [ ] `docs/development/BLOCK_CONVERTERS.md` - Creating custom block converters
- [ ] `docs/development/TESTING.md` - Testing guide (unit, integration, e2e)
- [ ] `docs/development/API.md` - REST API documentation
- [ ] `docs/development/HOOKS.md` - Available WordPress hooks and filters

#### 3.3 Update Existing Documentation

**Files to Update:**
- [ ] `CLAUDE.md` - Add reference to new documentation structure
- [ ] `docs/architecture/project-structure.md` - Update to match new docs structure
- [ ] Add index file: `docs/INDEX.md` - Documentation directory with links to all docs

**Acceptance Criteria:**
- [ ] Non-technical user can install and configure plugin from README alone
- [ ] README focuses on "what" and "how to use", not "how it works internally"
- [ ] All screenshots are clear, professional, and show realistic content
- [ ] Screenshots stored in `./docs/images/` with descriptive names
- [ ] Developer documentation is comprehensive and separate from user docs
- [ ] All code examples in dev docs are tested and working
- [ ] Documentation structure is clear with good navigation
- [ ] Links between docs work correctly

---

### 4. Admin UI Enhancements

**Priority:** Medium
**Effort:** 1 week
**Affected Files:** New admin page templates and settings

#### 4.1 Hierarchy Settings Panel

**Location:** WP Admin → Notion Sync → Settings → Hierarchy

**Settings to Add:**
- [ ] Enable hierarchy sync toggle (on/off)
- [ ] Max depth level slider (1-10, default 5)
- [ ] Circular reference handling (skip/warn/error radio)
- [ ] Orphaned page handling (keep/delete/draft radio)
- [ ] Auto-generate menu toggle (on/off)
- [ ] Target menu name dropdown
- [ ] Menu location assignment checkboxes

#### 4.2 Database View Settings Panel

**Location:** WP Admin → Notion Sync → Settings → Database Views

**Settings to Add:**
- [ ] Render embedded databases toggle (on/off)
- [ ] Default view type dropdown (table/list/gallery/board)
- [ ] Max entries per view number input (10-100, default 20)
- [ ] Enable pagination toggle (on/off)
- [ ] Cache duration input (minutes, default 60)
- [ ] Link entries to WordPress posts toggle (on/off)
- [ ] Fallback for non-synced entries (hide/show link to Notion radio)

#### 4.3 Link Resolution Tools Panel

**Location:** WP Admin → Notion Sync → Tools → Link Resolution

**Features to Add:**
- [ ] Table view of all Notion links in registry
- [ ] Resolution status column (resolved/pending/broken)
- [ ] Filter by status
- [ ] Manually trigger link resolution button
- [ ] Broken links report view
- [ ] Export link mapping CSV button

#### 4.4 Menu Assignment Interface

**Location:** Integrated into sync workflow or settings

**Features to Add:**
- [ ] Post-sync prompt: "Generate menu from synced pages?"
- [ ] Menu name input field
- [ ] Theme location checkboxes
- [ ] Preview hierarchy before menu creation
- [ ] "Update existing menu" vs "Create new menu" radio

**Acceptance Criteria:**
- All settings persist to WordPress options
- Settings apply during sync operations
- UI follows WordPress admin design patterns
- Help text provided for each setting
- Settings validate user input

---

## Testing Requirements

### Unit Tests Needed
- [ ] MenuManager CRUD operations (when implemented)
- [ ] Additional view type renderers
- [ ] Admin settings save/retrieve

### Integration Tests Needed
- [ ] Each database view type with real data
- [ ] Menu generation with hierarchy settings applied
- [ ] Link resolution tools with mixed status links

### End-to-End Tests Needed
- [ ] Sync hierarchy with all view types in content
- [ ] Menu assignment workflow from start to finish
- [ ] Settings changes affecting sync behavior

---

## Documentation Needed

### User Documentation
- [ ] How to use each database view type
- [ ] Hierarchy and menu settings guide
- [ ] Link resolution troubleshooting
- [ ] Menu assignment best practices

### Developer Documentation
- [ ] Database view renderer extension guide
- [ ] Custom view type creation
- [ ] MenuManager API reference
- [ ] Link registry hooks and filters

---

## Optional Future Enhancements

These are **not required** for Phase 5 completion but noted for future consideration:

- Real-time menu updates via webhooks
- Breadcrumb navigation auto-generation
- Table of contents generation from hierarchy
- Site map XML generation
- Database view shortcodes for use outside blocks
- Database entry pagination (AJAX load more)
- Client-side database view search/filter UI
- Notion workspace-level sync (multiple root pages)

---

## Completion Definition

Phase 5 will be considered **100% complete** when:

1. ✅ All 6 sub-phases implemented (DONE)
2. ⚠️ All 5 database view types functional (1/5 complete - table only)
3. ⚠️ MenuManager CRUD operations implemented (0% - all TODO stubs)
4. ⚠️ User documentation & README rework complete (0% - not started)
5. ⚠️ Admin UI panels created and functional (0% - not started)
6. ✅ All success criteria checkboxes marked (7/9 complete - see main-plan.md)
7. ⚠️ Unit tests for new components (partial - existing tests pass)
8. ⚠️ Integration tests for workflows (partial)
9. ⚠️ Screenshots captured and organized (0% - not started)

**Current Phase 5 Completion:** ~80%

**Estimated Time to 100%:** 4-5 weeks
- Database view types: 2-3 weeks
- User documentation & README: 1 week
- MenuManager CRUD: 1-2 days
- Admin UI panels: 1 week
- Screenshots (Playwright): 2-3 days
- Testing & documentation updates: 2-3 days

---

## Recommended Prioritization

**High Priority (Release Blockers):**
1. **User Documentation & README Rework** (1 week) - Essential for release
   - Non-technical users need clear installation/usage instructions
   - Screenshots provide visual guidance
   - Separating dev docs improves clarity for both audiences
2. **Gallery view** (1 week) - Most commonly used for visual content
3. **Screenshots with Playwright** (2-3 days) - Required for README and WordPress.org listing

**Medium Priority (Nice to Have):**
4. List view (3-4 days) - Simple alternative to table
5. Admin settings panels (1 week) - User experience improvement
6. Board view (1 week) - Useful for project management use cases
7. MenuManager CRUD (1-2 days) - Enables manual menu customization

**Low Priority (Future Enhancements):**
8. Timeline view (1 week) - Specialized use case
9. Calendar view (1 week) - Specialized use case
10. Link resolution tools panel (2-3 days) - Debugging aid

---

## Notes

- Core Phase 5 functionality is production-ready
- Table view covers majority of database display use cases
- MenuBuilder works without MenuManager (generates menus, just can't manually edit)
- Additional view types can be added incrementally without breaking existing functionality
- Admin UI enhancements are quality-of-life improvements, not blockers
