# Testing Checklist: Menu Meta Box

## Pre-Testing Setup

### Requirements
- [ ] WordPress 6.0+ installed
- [ ] PHP 8.0+ enabled
- [ ] Plugin activated
- [ ] Test user with `manage_options` capability
- [ ] At least one Notion page synced to WordPress

### Test Data Preparation
```sql
-- Verify test data exists
SELECT post_id, meta_value
FROM wp_postmeta
WHERE meta_key = 'notion_page_id'
LIMIT 5;

-- Check menu exists
SELECT * FROM wp_terms WHERE name = 'Notion Navigation';
```

## Visual Testing

### Meta Box Display

**Navigate to:** Appearance → Menus

- [ ] Meta box titled "Notion Menu Sync" appears in right sidebar
- [ ] Meta box is positioned near top (high priority)
- [ ] Last sync time displays (or "Never" if no sync)
- [ ] Synced item count shows "X of Y" format
- [ ] "Sync from Notion Now" button is visible
- [ ] Button has blue background (WordPress primary color)
- [ ] Button has dashicons-update icon
- [ ] Help text displays below button
- [ ] Help text mentions 🔄 icon and override option

**Screenshot Locations:**
- `screenshots/menu-metabox-initial.png`
- `screenshots/menu-metabox-synced.png`

### Menu Item Indicators

**With at least one synced item:**

- [ ] Synced items show 🔄 emoji in title
- [ ] Non-synced items do NOT show 🔄 emoji
- [ ] Expand a synced item
- [ ] "Notion Sync" badge appears (green background)
- [ ] "Notion Page ID" displays with monospace font
- [ ] Page ID is read-only (cannot edit)
- [ ] "Prevent Notion Updates" checkbox appears
- [ ] Checkbox label is clear
- [ ] Help text explains checkbox purpose

**Screenshot Locations:**
- `screenshots/menu-item-expanded.png`
- `screenshots/menu-item-override.png`

## Functional Testing

### Sync Button

**Test: Successful Sync**
1. [ ] Click "Sync from Notion Now" button
2. [ ] Button text changes to "Syncing..."
3. [ ] Button becomes disabled during sync
4. [ ] Dashicon spins (rotation animation)
5. [ ] Success message appears in green notice
6. [ ] Message includes menu name and item count
7. [ ] Message includes link to menu editor
8. [ ] Page reloads after 2 seconds
9. [ ] Updated menu items appear
10. [ ] Last sync time updates to "Just now"

**Test: Sync Failure**
1. [ ] Disconnect from Notion (clear token)
2. [ ] Click "Sync from Notion Now" button
3. [ ] Error message appears in red notice
4. [ ] Error message is user-friendly (not technical)
5. [ ] Button re-enables
6. [ ] Button text returns to "Sync from Notion Now"

**Test: No Pages to Sync**
1. [ ] Delete all synced pages
2. [ ] Click "Sync from Notion Now" button
3. [ ] Error message: "No root pages found..."
4. [ ] Suggests syncing pages from Notion first

### Override Checkbox

**Test: Enable Override**
1. [ ] Expand a synced menu item
2. [ ] Check "Prevent Notion Updates"
3. [ ] Click "Save Menu"
4. [ ] Page reloads
5. [ ] Checkbox remains checked
6. [ ] Verify meta: `get_post_meta( $item_id, '_notion_override', true ) === '1'`

**Test: Disable Override**
1. [ ] Expand a synced menu item with override enabled
2. [ ] Uncheck "Prevent Notion Updates"
3. [ ] Click "Save Menu"
4. [ ] Page reloads
5. [ ] Checkbox remains unchecked
6. [ ] Verify meta: `get_post_meta( $item_id, '_notion_override', true ) === false`

**Test: Override Prevents Updates**
1. [ ] Enable override on a menu item
2. [ ] Manually change item title in WordPress
3. [ ] Click "Sync from Notion Now"
4. [ ] Verify item title did NOT revert to Notion version
5. [ ] Verify other (non-overridden) items DID update

### Item Count Accuracy

**Test: Accurate Counting**
1. [ ] Create a new menu
2. [ ] Add 3 Notion-synced items
3. [ ] Add 2 manual items
4. [ ] Meta box shows "3 of 5"
5. [ ] Sync from Notion
6. [ ] Count updates correctly

## Accessibility Testing

### Keyboard Navigation

**Test: Tab Order**
1. [ ] Tab to "Sync from Notion Now" button
2. [ ] Button receives visible focus outline
3. [ ] Press Enter or Space - sync triggers
4. [ ] Tab through menu items
5. [ ] Tab to "Prevent Notion Updates" checkbox
6. [ ] Checkbox receives visible focus outline
7. [ ] Press Space - checkbox toggles

**Test: Focus Indicators**
- [ ] All focusable elements have 2px blue outline
- [ ] Outline offset is 2px (doesn't overlap element)
- [ ] Focus outline visible on light backgrounds
- [ ] Focus outline visible on dark backgrounds

### Screen Reader Testing

**Tool:** NVDA (Windows) or VoiceOver (Mac)

**Test: Meta Box**
1. [ ] Meta box title announces: "Notion Menu Sync"
2. [ ] Last sync time announces with date/time
3. [ ] Item count announces: "19 of 21"
4. [ ] Sync button announces: "Sync from Notion now button"
5. [ ] Help text is read aloud

**Test: Menu Items**
1. [ ] Synced item announces: "Synced from Notion [title]"
2. [ ] "Notion Sync" label announces
3. [ ] "Notion Page ID" label and value announce
4. [ ] Checkbox announces: "Prevent Notion Updates"
5. [ ] Checkbox help text announces

**Test: Messages**
1. [ ] Success message announces automatically (aria-live="polite")
2. [ ] Error message announces automatically
3. [ ] Message container has role="status"

### Color Contrast

**Tool:** WebAIM Contrast Checker or browser DevTools

**Test: Text Contrast**
- [ ] "Last Synced" label: #1d2327 on #fff → 15.3:1 ✓
- [ ] Time value: #2271b1 on #fff → 4.6:1 ✓
- [ ] Help text: #646970 on #fff → 4.5:1 ✓
- [ ] Button text: #fff on #2271b1 → 4.6:1 ✓
- [ ] Success badge: #00712e on #d5f5e3 → 7.1:1 ✓

**Test: Interactive Elements**
- [ ] Button hover: Sufficient contrast maintained
- [ ] Button focus: Outline visible against all backgrounds
- [ ] Checkbox: Border visible (not relying on color alone)

## Mobile Testing

### Responsive Layout (782px breakpoint)

**Test: Meta Box at 782px**
1. [ ] Resize browser to 782px width
2. [ ] Meta box remains readable
3. [ ] Button is full-width
4. [ ] Button height increases to 44px (touch-friendly)
5. [ ] Text sizes increase appropriately
6. [ ] No horizontal scrolling
7. [ ] All content remains accessible

**Test: Menu Items at 782px**
1. [ ] Synced item indicators still visible
2. [ ] "Prevent Notion Updates" checkbox tappable
3. [ ] All fields remain functional
4. [ ] Layout doesn't break

**Test: Touch Interactions**
1. [ ] Tap "Sync from Notion Now" button - works
2. [ ] Tap checkbox - toggles correctly
3. [ ] No accidental double-taps
4. [ ] Touch targets are 44x44px minimum

## Browser Compatibility

### Chrome/Edge (Chromium)
- [ ] Meta box displays correctly
- [ ] Sync button works
- [ ] AJAX calls succeed
- [ ] Animations smooth
- [ ] No console errors

### Firefox
- [ ] Meta box displays correctly
- [ ] Sync button works
- [ ] AJAX calls succeed
- [ ] Animations smooth
- [ ] No console errors

### Safari (macOS)
- [ ] Meta box displays correctly
- [ ] Sync button works
- [ ] AJAX calls succeed
- [ ] Animations smooth
- [ ] No console errors

### Mobile Safari (iOS)
- [ ] Meta box displays correctly
- [ ] Sync button tappable
- [ ] Touch targets adequate
- [ ] No rendering issues

## Security Testing

### Nonce Verification

**Test: Valid Nonce**
1. [ ] Click "Sync from Notion Now"
2. [ ] Check Network tab - nonce parameter present
3. [ ] Request succeeds (status 200)

**Test: Invalid Nonce**
1. [ ] Modify nonce in browser DevTools
2. [ ] Click "Sync from Notion Now"
3. [ ] Request fails with 403 Forbidden
4. [ ] Error message displayed

**Test: Expired Nonce**
1. [ ] Wait 24+ hours (or modify wp_nonce_tick)
2. [ ] Click "Sync from Notion Now"
3. [ ] Request fails
4. [ ] User prompted to refresh page

### Capability Checks

**Test: Admin User**
1. [ ] Log in as admin
2. [ ] Navigate to Appearance → Menus
3. [ ] Meta box appears
4. [ ] Sync button works

**Test: Editor User**
1. [ ] Log in as editor
2. [ ] Navigate to Appearance → Menus
3. [ ] Meta box may or may not appear (depends on capabilities)
4. [ ] If appears, sync button should check `manage_options`

**Test: No Privileges**
1. [ ] Create user without `manage_options`
2. [ ] Add capability to view menus
3. [ ] Meta box should not appear OR sync should fail

### XSS Protection

**Test: Malicious Page ID**
```php
// Attempt to inject script
update_post_meta( $item_id, '_notion_page_id', '<script>alert("XSS")</script>' );
```
1. [ ] Expand menu item
2. [ ] Page ID displays as text (not executed)
3. [ ] No alert appears
4. [ ] View page source - script tags are escaped

**Test: Malicious Title**
```php
// Attempt to inject via title
wp_update_post([
    'ID' => $item_id,
    'post_title' => '<img src=x onerror=alert(1)>',
]);
```
1. [ ] Menu item displays safely
2. [ ] No alert appears
3. [ ] Image tag is escaped

## Performance Testing

### Load Time

**Test: Initial Page Load**
1. [ ] Clear browser cache
2. [ ] Navigate to Appearance → Menus
3. [ ] Measure time to interactive (TTI)
4. [ ] Target: < 2 seconds
5. [ ] Check Network tab - no unnecessary requests

**Test: Asset Loading**
1. [ ] Check JavaScript loaded: admin-navigation.js
2. [ ] File size: ~3KB (acceptable)
3. [ ] Inline CSS injected (no extra HTTP request)
4. [ ] CSS size: ~3KB (acceptable)

**Test: AJAX Sync Time**
1. [ ] Click "Sync from Notion Now"
2. [ ] Measure request duration
3. [ ] Expected: 500ms - 2000ms (depends on Notion API)
4. [ ] Check for hanging requests

### Database Queries

**Test: Query Efficiency**
1. [ ] Install Query Monitor plugin
2. [ ] Navigate to Appearance → Menus
3. [ ] Check query count
4. [ ] No N+1 query issues
5. [ ] All queries use proper indexes

**Test: Meta Query Performance**
```php
// Should use efficient query
$synced_items = get_posts([
    'post_type' => 'nav_menu_item',
    'meta_key' => '_notion_synced',
    'meta_value' => '1',
    'posts_per_page' => -1,
]);
```
1. [ ] Query executes in < 100ms
2. [ ] Uses index on meta_key

## Integration Testing

### With Other Plugins

**Test: Menu Theme Plugins**
1. [ ] Install Max Mega Menu (or similar)
2. [ ] Verify meta box still appears
3. [ ] Sync button still works
4. [ ] No JavaScript conflicts

**Test: Page Builder Plugins**
1. [ ] Install Elementor/Beaver Builder
2. [ ] Verify menu editor functions
3. [ ] No conflicts with meta box

**Test: Caching Plugins**
1. [ ] Install WP Super Cache
2. [ ] Enable caching
3. [ ] Navigate to Appearance → Menus
4. [ ] Meta box should NOT be cached
5. [ ] AJAX calls work

### Multisite Compatibility

**Test: Network Activated**
1. [ ] Activate plugin network-wide
2. [ ] Switch to subsite
3. [ ] Navigate to Appearance → Menus
4. [ ] Meta box appears
5. [ ] Sync works for subsite

**Test: Per-Site Activation**
1. [ ] Activate plugin on subsite only
2. [ ] Verify meta box appears
3. [ ] Sync works independently

## Error Handling

### Network Errors

**Test: Offline**
1. [ ] Disconnect from internet
2. [ ] Click "Sync from Notion Now"
3. [ ] Error message displays
4. [ ] User-friendly message (not technical)

**Test: Notion API Down**
1. [ ] Mock API failure
2. [ ] Click "Sync from Notion Now"
3. [ ] Appropriate error message
4. [ ] Suggests trying again later

### Data Validation

**Test: Invalid Menu ID**
1. [ ] Navigate to `/wp-admin/nav-menus.php?menu=99999`
2. [ ] Meta box shows "Select or create a menu"
3. [ ] No PHP errors

**Test: Missing Dependencies**
1. [ ] Temporarily remove MenuItemMeta class
2. [ ] Navigate to Appearance → Menus
3. [ ] Graceful error (no fatal error)
4. [ ] Admin notice explains issue

## Regression Testing

### After Code Changes

**Test: Core Functionality**
1. [ ] Re-run all functional tests
2. [ ] Verify no regressions
3. [ ] Check for new console errors

**Test: Accessibility**
1. [ ] Re-run keyboard navigation tests
2. [ ] Verify screen reader compatibility
3. [ ] Check color contrast

**Test: Mobile**
1. [ ] Re-test at 782px breakpoint
2. [ ] Verify touch targets
3. [ ] Check for layout breaks

## Automated Testing (Optional)

### PHPUnit Tests

```php
// Test meta box registration
public function test_meta_box_registers() {
    $menu_item_meta = new MenuItemMeta();
    $menu_meta_box = new MenuMetaBox( $menu_item_meta );
    $menu_meta_box->register();

    $this->assertTrue(
        has_action( 'admin_init', [ $menu_meta_box, 'add_meta_box' ] )
    );
}

// Test sync indicator
public function test_sync_indicator_added_to_synced_items() {
    $item_id = $this->factory->post->create([
        'post_type' => 'nav_menu_item',
    ]);

    update_post_meta( $item_id, '_notion_synced', true );

    $menu_item_meta = new MenuItemMeta();
    $menu_meta_box = new MenuMetaBox( $menu_item_meta );

    $item = get_post( $item_id );
    $title = $menu_meta_box->add_sync_indicator( 'Test Title', $item, [], 0 );

    $this->assertStringContainsString( '🔄', $title );
}
```

### JavaScript Tests (Jest)

```javascript
// Test sync button click
test('sync button disables during sync', async () => {
    const button = document.getElementById('notion-sync-menu-button');
    button.click();

    expect(button.disabled).toBe(true);
    expect(button.textContent).toBe('Syncing...');
});

// Test message display
test('success message displays after sync', async () => {
    const container = document.getElementById('notion-menu-sync-messages');

    showMessage(container, 'success', 'Sync complete');

    const notice = container.querySelector('.notice-success');
    expect(notice).toBeTruthy();
    expect(notice.textContent).toContain('Sync complete');
});
```

## Sign-Off

### Developer Checklist
- [ ] All PHP syntax valid (php -l)
- [ ] WordPress coding standards met (PHPCS)
- [ ] JavaScript linted (ESLint)
- [ ] CSS validated
- [ ] No console errors
- [ ] No PHP warnings
- [ ] Code documented
- [ ] Tests passing

### QA Checklist
- [ ] All functional tests passing
- [ ] All accessibility tests passing
- [ ] All mobile tests passing
- [ ] All browser tests passing
- [ ] All security tests passing
- [ ] No regressions found
- [ ] User experience smooth
- [ ] Performance acceptable

### Product Owner Sign-Off
- [ ] Meets requirements
- [ ] User experience satisfactory
- [ ] Documentation complete
- [ ] Ready for production

---

**Testing Date:** ___________
**Tester Name:** ___________
**Version:** 0.2.0-dev
**Environment:** ___________
**Result:** PASS / FAIL / BLOCKED
