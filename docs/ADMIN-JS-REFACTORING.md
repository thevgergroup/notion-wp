# Admin.js Refactoring Plan

**Current:** 793 lines
**Target:** < 500 lines (split into 4 modules)
**Approach:** ES6 modules with clear separation of concerns

## Module Structure

### 1. `modules/admin-connection.js` (~130 lines)

**Purpose:** Handle authentication and connection management

**Functions:**

- `initConnectionForm()` - Main initialization
- `handleConnectionForm()` - Form submission handling
- `validateTokenFormat()` - Input validation
- `isValidTokenFormat()` - Token format checker
- `handleDisconnectButton()` - Disconnect flow

**Dependencies:** Imports `showLoadingState`, `showInlineError`, `clearInlineError` from admin-ui.js

### 2. `modules/admin-sync.js` (~350 lines)

**Purpose:** Sync operations, bulk actions, table updates

**Functions:**

- `initSyncFunctionality()` - Main initialization
- `handleSyncNow()` - Individual page sync via AJAX
- `handleBulkActions()` - Bulk sync form handling
- `updateStatusBadge()` - Status badge updates
- `updateWpPostColumn()` - Post link column updates
- `updateLastSyncedColumn()` - Timestamp updates
- `updateRowActions()` - Row action link updates
- `handleCopyNotionId()` - Copy ID functionality

**Dependencies:** Imports `showAdminNotice`, `showCopyFeedback` from admin-ui.js

### 3. `modules/admin-ui.js` (~200 lines)

**Purpose:** UI utilities, notices, keyboard navigation

**Functions:**

- `showLoadingState()` - Button loading states
- `showInlineError()` - Inline error display
- `clearInlineError()` - Error clearing
- `enhanceKeyboardNavigation()` - Accessibility
- `closeAllModals()` - Modal management
- `handleDismissibleNotices()` - Auto-dismiss notices
- `initAdminNotices()` - Notice initialization
- `initCopyButtons()` - Copy button setup
- `showCopySuccess()` - Copy feedback
- `fallbackCopy()` - Clipboard fallback
- `showAdminNotice()` - Dynamic notice creation
- `escapeHtml()` - XSS prevention

**Exports:** All functions (used by other modules)

### 4. `admin.js` (~80 lines)

**Purpose:** Main coordinator and initialization

**Functions:**

- `init()` - Main initialization
- DOM ready event handling
- Module coordination

**Dependencies:** Imports from all 3 modules

## Benefits

1. **Maintainability:** Each module has a single, clear purpose
2. **Testability:** Modules can be tested in isolation
3. **Reusability:** UI utilities can be used by other scripts
4. **File Size:** Meets 500-line requirement (largest module: 350 lines)
5. **Modern:** Uses ES6 modules (standard JavaScript)

## Build Process Note

The refactored modules use ES6 imports/exports. The build process (likely webpack or similar based on the `src/js` structure) will bundle these into a single file for production.

## Migration Steps

1. Create `modules/` directory
2. Create individual module files
3. Update `admin.js` to import and coordinate
4. Test in browser to ensure functionality preserved
5. Update build configuration if needed
