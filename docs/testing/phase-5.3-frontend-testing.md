# Phase 5.3 Frontend Tabulator Integration - Testing Checklist

**Status:** Ready for Testing
**Date Created:** 2025-10-30
**Component:** Database View Gutenberg Block
**Test Environment:** http://localhost:8053

---

## Prerequisites

### Setup Requirements

- [ ] WordPress running at http://localhost:8053
- [ ] notion-wp plugin activated
- [ ] At least one Notion database synced to WordPress
- [ ] Browser DevTools open for debugging
- [ ] Test database has at least 10 entries

### Build Verification

- [ ] Run `npm run build:blocks` successfully
- [ ] Verify `/plugin/blocks/database-view/build/` contains:
  - [ ] `index.js` (editor script)
  - [ ] `index.css` (editor styles)
  - [ ] `frontend.js` (frontend Tabulator integration)
  - [ ] `style-index.css` (frontend styles)
- [ ] Check browser console for any asset loading errors

---

## Block Editor Tests

### Block Insertion

- [ ] Open WordPress post/page editor
- [ ] Click "Add block" (+) button
- [ ] Search for "Notion Database View"
- [ ] Block appears in search results with database icon
- [ ] Click block to insert it
- [ ] Block inserts successfully without errors

### Block Configuration - No Database Selected

- [ ] Newly inserted block shows placeholder
- [ ] Placeholder displays "Select a Notion database to display"
- [ ] Dropdown shows "Select a database..." as first option
- [ ] Dropdown lists all synced Notion databases
- [ ] Selecting a database updates the block preview

### Block Configuration - Settings Panel

**Database Settings Panel:**
- [ ] Select a database from dropdown
- [ ] "View Type" dropdown shows:
  - [ ] "Table" (selectable)
  - [ ] "Board (Coming Soon)" (disabled)
  - [ ] "Gallery (Coming Soon)" (disabled)
  - [ ] "Timeline (Coming Soon)" (disabled)
- [ ] Help text appears: "Choose how to display the database"

**Display Options Panel:**
- [ ] "Show Filters" toggle exists
- [ ] Toggle defaults to ON
- [ ] Help text: "Allow users to filter table columns"
- [ ] "Show Export Buttons" toggle exists
- [ ] Toggle defaults to ON
- [ ] Help text: "Allow users to export data as CSV or JSON"
- [ ] "Page Size" range slider exists
- [ ] Range: 10-200, step: 10, default: 50
- [ ] Help text: "Number of rows to display per page"

### Block Preview in Editor

- [ ] Selected database title displays correctly
- [ ] Block shows preview with database info
- [ ] Preview displays current settings summary:
  - [ ] View Type: table
  - [ ] Page Size: 50 rows
  - [ ] Filters: Enabled/Disabled
  - [ ] Export: Enabled/Disabled
- [ ] Block has proper spacing and borders
- [ ] Database icon displays correctly

### Block Persistence

- [ ] Save post/page
- [ ] Reload editor
- [ ] Block loads with same database selected
- [ ] All settings preserved (filters, export, page size)
- [ ] Change settings and save again
- [ ] Settings persist correctly

### Error Handling in Editor

- [ ] Delete synced database from WordPress
- [ ] Reopen editor with block referencing deleted database
- [ ] Block shows appropriate error message
- [ ] No JavaScript errors in console
- [ ] Block remains editable (can select different database)

---

## Frontend Display Tests

### Single Block Rendering

- [ ] Publish post/page with database view block
- [ ] Visit published page on frontend
- [ ] Block container renders with correct HTML structure
- [ ] Loading indicator appears initially
- [ ] Loading spinner is visible and animated
- [ ] Loading text: "Loading database..."

### Tabulator Initialization

- [ ] Tabulator loads successfully (check DevTools Console)
- [ ] Loading indicator disappears after data loads
- [ ] Table appears with database data
- [ ] All columns render correctly
- [ ] Column headers show proper titles
- [ ] Data displays in rows

### Multiple Blocks on One Page

- [ ] Create page with 2-3 database view blocks
- [ ] Each block references different database
- [ ] Publish and view page
- [ ] All blocks render independently
- [ ] All tables load data correctly
- [ ] No conflicts between blocks
- [ ] Check browser console for errors

### Table Functionality

**Sorting:**
- [ ] Click column header to sort ascending
- [ ] Click again to sort descending
- [ ] Click third time to remove sort
- [ ] Multiple column sorting works (Shift+Click)
- [ ] Date columns sort correctly
- [ ] Number columns sort correctly
- [ ] Text columns sort alphabetically

**Pagination:**
- [ ] Pagination controls appear at bottom
- [ ] Page size selector shows: [25, 50, 100, 200]
- [ ] Change page size, table updates
- [ ] Page number buttons work correctly
- [ ] "Next" and "Previous" buttons work
- [ ] Current page highlighted
- [ ] Total pages calculated correctly

**Filtering (if enabled):**
- [ ] Filter inputs appear in column headers
- [ ] Type in text filter, table filters instantly
- [ ] Filters work on multiple columns simultaneously
- [ ] Clear individual filter works
- [ ] Filtering maintains pagination

**Column Resizing:**
- [ ] Hover over column border, resize cursor appears
- [ ] Drag to resize column
- [ ] Column width persists during interaction
- [ ] All columns resizable

### Action Buttons

**When Filters Enabled:**
- [ ] "Reset Filters" button appears above table
- [ ] Button has proper styling (matches WordPress buttons)
- [ ] Click button clears all active filters
- [ ] Table updates to show all data

**When Export Enabled:**
- [ ] "Export CSV" button appears
- [ ] "Export JSON" button appears
- [ ] Click "Export CSV" downloads .csv file
- [ ] Downloaded CSV contains current table data
- [ ] Click "Export JSON" downloads .json file
- [ ] Downloaded JSON contains current table data
- [ ] Exported files have timestamp in filename

**When Filters/Export Disabled:**
- [ ] No action buttons appear
- [ ] Table still functions normally
- [ ] No console errors about missing buttons

### Data Display Formatting

**Multi-Select Properties:**
- [ ] Multi-select values show as tags
- [ ] Tags have proper styling (colored background)
- [ ] Multiple tags display side-by-side
- [ ] Tags are readable (good contrast)

**Checkbox Properties:**
- [ ] Checkboxes show tick (✓) or cross (✗) icons
- [ ] Centered in column
- [ ] Icons have proper styling

**Date Properties:**
- [ ] Dates format correctly (locale-aware)
- [ ] "Created Time" shows date/time
- [ ] "Last Edited Time" shows date/time
- [ ] Empty dates show blank (not error)

**Title Column:**
- [ ] Title column is visible
- [ ] Frozen on left (doesn't scroll horizontally)
- [ ] Properly formatted

### Responsive Design

**Desktop (1920x1080):**
- [ ] Table displays full width
- [ ] All columns visible
- [ ] Horizontal scrolling if needed
- [ ] Action buttons align properly

**Tablet (768x1024):**
- [ ] Table adapts to smaller width
- [ ] Horizontal scrolling enabled
- [ ] Action buttons stack or shrink
- [ ] Touch interactions work
- [ ] Column resizing works on touch

**Mobile (375x667):**
- [ ] Table container scrolls horizontally
- [ ] Columns collapse to responsive layout
- [ ] Font sizes adjust (13px)
- [ ] Padding reduces (15px container, 8px cells)
- [ ] Action buttons stack vertically
- [ ] Touch scrolling smooth
- [ ] Pinch-to-zoom disabled (if applicable)

---

## Error Handling & Edge Cases

### Invalid Database ID

- [ ] Create block with database ID = 0
- [ ] Frontend shows: "Please select a Notion database"
- [ ] Yellow warning notice displayed
- [ ] No JavaScript errors

### Deleted Database

- [ ] Create block with valid database
- [ ] Delete database from WordPress
- [ ] Visit page on frontend
- [ ] Shows: "The selected database could not be found"
- [ ] Red error notice displayed
- [ ] No JavaScript errors

### Empty Database

- [ ] Sync empty database (0 entries)
- [ ] Block renders table structure
- [ ] Shows placeholder: "No data available"
- [ ] No JavaScript errors
- [ ] Action buttons still visible

### REST API Failure

**Simulate by:**
- [ ] Disconnect from network (DevTools offline mode)
- [ ] Or temporarily deactivate plugin
- [ ] Visit page with database block
- [ ] Error message displays in block
- [ ] Error message is helpful (not cryptic)
- [ ] Console shows detailed error for debugging

### Schema Fetch Failure

- [ ] Modify REST endpoint to return 404
- [ ] Visit page with database block
- [ ] Appropriate error message displays
- [ ] Error includes HTTP status
- [ ] Console logs full error details

### Malformed Data

- [ ] Database with missing properties field
- [ ] Table renders with available data
- [ ] No JavaScript runtime errors
- [ ] Empty cells show blank (not "undefined")

---

## Performance Tests

### Large Dataset

**Test with 500+ row database:**
- [ ] Initial load time < 3 seconds
- [ ] Pagination prevents loading all rows at once
- [ ] Sorting remains responsive
- [ ] Filtering remains responsive
- [ ] No browser lag or freezing

### Multiple Blocks Performance

- [ ] Page with 5 database view blocks
- [ ] All blocks load within 5 seconds
- [ ] Page remains scrollable during load
- [ ] No memory leaks (check DevTools Memory tab)
- [ ] No console warnings about performance

### Network Performance

- [ ] Check Network tab in DevTools
- [ ] Schema fetched once per block
- [ ] Row data fetched with pagination
- [ ] No duplicate requests
- [ ] Proper caching headers (if applicable)

---

## WordPress Theme Compatibility

Test with multiple themes:

### Twenty Twenty-Three (Block Theme)

- [ ] Block displays correctly
- [ ] Tabulator styles don't conflict
- [ ] Fonts inherit properly
- [ ] Colors match theme
- [ ] Spacing consistent

### Twenty Twenty-Two

- [ ] Block displays correctly
- [ ] No styling conflicts
- [ ] Responsive behavior works

### Custom Theme (if available)

- [ ] Block displays correctly
- [ ] Action buttons style consistently
- [ ] No CSS conflicts
- [ ] Z-index issues resolved

---

## Browser Compatibility

### Chrome/Edge (Chromium)

- [ ] All functionality works
- [ ] Tabulator renders correctly
- [ ] No console errors
- [ ] Performance acceptable

### Firefox

- [ ] All functionality works
- [ ] Tabulator renders correctly
- [ ] No console errors
- [ ] Performance acceptable

### Safari (Mac/iOS)

- [ ] All functionality works
- [ ] Tabulator renders correctly
- [ ] Touch events work (iOS)
- [ ] No console errors

---

## Security Tests

### XSS Prevention

- [ ] Database with malicious HTML in properties
- [ ] HTML is escaped (not executed)
- [ ] No script tags execute
- [ ] Tags display as text (escaped)

### Nonce Validation

- [ ] Valid nonce allows API access
- [ ] Expired nonce returns 403
- [ ] Missing nonce returns 403
- [ ] Error messages don't expose internals

### Permission Checks

- [ ] Published database viewable by all users
- [ ] Draft database not viewable by logged-out users
- [ ] Private database respects permissions
- [ ] Block shows appropriate error when unauthorized

---

## Accessibility Tests

### Keyboard Navigation

- [ ] Tab through action buttons
- [ ] Enter key triggers button actions
- [ ] Tab through table cells
- [ ] Arrow keys navigate cells (Tabulator feature)

### Screen Reader

- [ ] Table has proper ARIA labels
- [ ] Column headers announced
- [ ] Data cells announced correctly
- [ ] Action buttons have clear labels

### Color Contrast

- [ ] Text meets WCAG AA standards (4.5:1)
- [ ] Links have sufficient contrast
- [ ] Tags have sufficient contrast
- [ ] Focus indicators visible

---

## Developer Experience Tests

### Build Process

- [ ] `npm run build:blocks` runs without errors
- [ ] Build completes in < 10 seconds
- [ ] All assets generated correctly
- [ ] Minification works (check file sizes)

### Console Output

- [ ] No warnings in browser console
- [ ] No errors in browser console
- [ ] Helpful debugging info available (if WP_DEBUG)

### Code Quality

- [ ] ESLint passes (run `npm run lint:js`)
- [ ] Stylelint passes (run `npm run lint:css`)
- [ ] Prettier formatting consistent
- [ ] No TODO comments left in production

---

## Final Verification

- [ ] All critical tests pass
- [ ] All medium priority tests pass
- [ ] Known issues documented
- [ ] No regressions introduced
- [ ] Performance acceptable
- [ ] Ready for production use

---

## Test Results Summary

**Date Tested:** _______________
**Tester:** _______________
**Environment:** _______________

**Pass Rate:** ___ / ___ tests passed

**Critical Issues Found:** _______________

**Medium Issues Found:** _______________

**Low Issues Found:** _______________

**Notes:**
_______________
_______________
_______________

---

## Known Limitations

Document any known limitations or future enhancements:

1. **View Types:** Only "table" view implemented. Board/Gallery/Timeline deferred to Phase 5.4
2. **Advanced Filters:** Complex Notion filters (formula, relation, rollup) not yet supported
3. **Real-Time Updates:** Data updates require page refresh (webhooks deferred to Phase 6)
4. **Inline Editing:** Table is read-only (editing deferred to Phase 6)

---

## Troubleshooting Guide

### Block Not Appearing in Editor

1. Check if plugin is activated
2. Run `npm run build:blocks`
3. Clear browser cache
4. Check browser console for errors
5. Verify `block.json` exists

### Tabulator Not Loading

1. Check if Tabulator CDN is accessible
2. Verify frontend.js is enqueued
3. Check for JavaScript errors in console
4. Verify block has valid database ID

### Data Not Displaying

1. Check if database is synced
2. Verify REST API endpoint works: `GET /wp-json/notion-sync/v1/databases/{id}/schema`
3. Check browser Network tab for failed requests
4. Verify nonce is valid
5. Check WordPress error logs

### Styling Issues

1. Clear browser cache
2. Check if style-index.css is loaded
3. Inspect CSS conflicts in DevTools
4. Verify Tabulator CSS is loaded
5. Check theme compatibility

---

## Contact & Support

**Developer:** Claude Code
**Documentation:** `/docs/plans/phase-5.3-database-views.md`
**GitHub Issues:** https://github.com/thevgergroup/notion-wp/issues

---

**End of Testing Checklist**
