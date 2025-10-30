# Menu Meta Box - WordPress Admin Integration

## Overview

The `MenuMetaBox` class enhances WordPress's native menu editor (Appearance → Menus) with Notion sync information and controls. This implementation follows WordPress admin UI best practices and accessibility standards.

## File Location

```
plugin/src/Admin/MenuMetaBox.php
```

## Features

### 1. Meta Box Display

**Location:** Right sidebar of menu editor
**Position:** High priority (appears near top)

**Displays:**
- Last sync timestamp (human-readable, e.g., "2 minutes ago")
- Count of Notion-synced items vs total items (e.g., "19 of 21")
- "Sync from Notion Now" button
- Help text explaining Notion-synced items

### 2. Menu Item Enhancements

For items synced from Notion:

**Visual Indicator:**
- 🔄 emoji icon prepended to item title in editor
- Only visible in admin, not on frontend

**Custom Fields:**
- "Notion Sync" badge (green, indicates synced status)
- "Notion Page ID" display (read-only, monospace font)
- "Prevent Notion Updates" checkbox (toggle override protection)

### 3. AJAX Sync Integration

**Endpoint:** `notion_sync_menu_now`
**Handler:** `NavigationAjaxHandler::ajax_sync_menu_now()`

**Features:**
- Real-time sync without page reload
- Loading state with spinning icon
- Success/error message display
- Automatic page refresh on success (after 2 seconds)

## WordPress Integration

### Hooks Used

```php
// Meta box registration
add_action( 'admin_init', array( $this, 'add_meta_box' ) );

// Menu item custom fields
add_action( 'wp_nav_menu_item_custom_fields', array( $this, 'add_item_fields' ), 10, 4 );

// Save menu item settings
add_action( 'wp_update_nav_menu_item', array( $this, 'save_item_override' ), 10, 2 );

// Asset enqueuing
add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

// Title filter for sync indicator
add_filter( 'nav_menu_item_title', array( $this, 'add_sync_indicator' ), 10, 4 );
```

### Screen Detection

Assets only load on `nav-menus.php` screen:

```php
if ( 'nav-menus.php' !== $hook ) {
    return;
}
```

## Design Principles

### WordPress Admin Standards

**Color Scheme:**
- Primary: `#2271b1` (WordPress blue)
- Success: `#00a32a` (WordPress green)
- Text: `#1d2327` (dark gray)
- Secondary text: `#646970` (medium gray)
- Borders: `#c3c4c7` (light gray)

**Typography:**
- Sans-serif system fonts (matches WordPress)
- Font sizes: 13px (body), 12px (descriptions)
- Line height: 1.5 (readable)

**Spacing:**
- 12-16px margins between sections
- 8-12px padding in containers
- Consistent gap sizes (4px, 8px, 12px, 16px)

### Accessibility (WCAG 2.1 AA)

**Keyboard Navigation:**
- All interactive elements focusable via Tab
- Visible focus indicators (2px blue outline)
- Logical tab order

**Screen Readers:**
- Proper ARIA labels on buttons
- `aria-describedby` for checkboxes
- `role="status"` and `aria-live="polite"` for messages
- Semantic HTML elements

**Color Contrast:**
- Text meets 4.5:1 ratio minimum
- Interactive elements distinguishable
- Not relying on color alone for information

**Focus States:**
```css
#notion-sync-menu-button:focus {
    outline: 2px solid #2271b1;
    outline-offset: 2px;
    box-shadow: none;
}
```

### Mobile Responsiveness

**Breakpoint:** 782px (WordPress admin standard)

**Mobile Adjustments:**
- Full-width buttons
- Larger touch targets (44px minimum height)
- Increased font sizes (14px → 16px for inputs)
- Adjusted spacing for smaller screens

## CSS Architecture

### Inline Styles

Styles are injected inline via `wp_add_inline_style()` to avoid requiring a separate CSS file. This simplifies distribution and ensures styles always load with the admin interface.

**Advantages:**
- No additional HTTP requests
- Guaranteed to load when needed
- No cache issues
- Single file distribution

**Organization:**
```css
/* Meta Box Container */
.notion-menu-sync-meta-box { ... }

/* Statistics Display */
.notion-sync-stats { ... }

/* Action Buttons */
.notion-sync-actions { ... }

/* Menu Item Fields */
.field-notion-sync { ... }

/* Animations */
@keyframes rotation { ... }

/* Mobile Responsive */
@media screen and (max-width: 782px) { ... }
```

## JavaScript Architecture

### Bundle Creation

**Source:** `assets/src/js/modules/admin-navigation.js`
**Built:** `assets/dist/js/admin-navigation.js`
**Build Script:** `assets/build-admin-js.sh`

**To rebuild:**
```bash
cd plugin/assets
./build-admin-js.sh
```

### IIFE Pattern

JavaScript wrapped in immediately-invoked function expression (IIFE) to avoid global namespace pollution:

```javascript
(function() {
    'use strict';
    // Code here
})();
```

### Functions

**`initNavigationSync()`**
- Finds sync button
- Attaches click handler
- Called on DOM ready

**`handleMenuSync(event)`**
- Disables button, shows loading state
- Makes AJAX call to `notion_sync_menu_now`
- Displays success/error messages
- Updates UI state
- Reloads page on success

**`showMessage(container, type, message)`**
- Creates WordPress-style admin notice
- Accepts: success, error, warning, info
- Inserts into message container

**`updateLastSyncTime()`**
- Updates "Last Synced" timestamp
- Sets to "Just now" after sync

## Security

### Nonce Verification

**Meta Box Sync Button:**
```php
$nonce = wp_create_nonce( 'notion_sync_menu_now' );
```

**AJAX Handler:**
```php
check_ajax_referer( 'notion_sync_menu_now', 'nonce' );
```

**Capability Check:**
```php
if ( ! current_user_can( 'manage_options' ) ) {
    wp_send_json_error( ... );
}
```

### Data Escaping

**Output Escaping:**
```php
esc_html__()      // Translatable strings
esc_attr()        // HTML attributes
esc_url()         // URLs
wp_kses()         // Allowed HTML
```

**Input Sanitization:**
```php
// Menu ID
$menu_id = isset( $_REQUEST['menu'] ) ? (int) $_REQUEST['menu'] : 0;

// Checkbox value
$override_value = isset( $_POST['menu-item-notion-override'][ $item_id ] )
    ? true
    : false;
```

## Usage Example

### Plugin Initialization

```php
// In notion-sync.php
if ( is_admin() ) {
    $menu_item_meta = new \NotionWP\Navigation\MenuItemMeta();
    $menu_meta_box = new \NotionWP\Admin\MenuMetaBox( $menu_item_meta );
    $menu_meta_box->register();
}
```

### Checking Sync Status

```php
// Check if menu item is synced
$is_synced = $menu_item_meta->is_notion_synced( $item_id );

// Get Notion page ID
$page_id = $menu_item_meta->get_notion_page_id( $item_id );

// Check if user overrode updates
$has_override = $menu_item_meta->has_override( $item_id );
```

## Testing Checklist

### Functional Testing

- [ ] Meta box appears on Appearance → Menus
- [ ] Last sync time displays correctly
- [ ] Synced item count is accurate
- [ ] Sync button triggers AJAX call
- [ ] Success message displays after sync
- [ ] Page reloads after successful sync
- [ ] Error messages show on failure
- [ ] Notion items show 🔄 indicator
- [ ] Notion Page ID displays correctly
- [ ] Override checkbox saves properly
- [ ] Override prevents sync updates

### Accessibility Testing

**Keyboard Navigation:**
- [ ] Tab through all interactive elements
- [ ] Space/Enter activates buttons
- [ ] Focus indicators visible
- [ ] Logical tab order

**Screen Reader Testing:**
- [ ] Button labels announce correctly
- [ ] Success/error messages announce
- [ ] Checkbox descriptions read properly
- [ ] Time elements have proper datetime attributes

**Visual Testing:**
- [ ] Text contrast meets WCAG AA
- [ ] Focus outlines visible
- [ ] Works with high contrast mode
- [ ] Readable at 200% zoom

### Mobile Testing

- [ ] Layout works at 782px breakpoint
- [ ] Touch targets are 44px minimum
- [ ] Buttons are tappable
- [ ] Text is readable
- [ ] No horizontal scrolling

### Cross-Browser Testing

- [ ] Chrome/Edge (Chromium)
- [ ] Firefox
- [ ] Safari (macOS/iOS)

### Integration Testing

- [ ] Works with other menu editor plugins
- [ ] Doesn't conflict with theme customizations
- [ ] Works in WordPress multisite
- [ ] Compatible with WordPress 6.0+

## Troubleshooting

### Meta Box Not Appearing

**Check:**
1. Plugin is activated
2. User has `manage_options` capability
3. On `nav-menus.php` screen
4. JavaScript console for errors

**Debug:**
```php
add_action( 'admin_notices', function() {
    $screen = get_current_screen();
    echo '<div class="notice notice-info"><p>Screen ID: ' . esc_html( $screen->id ) . '</p></div>';
});
```

### Sync Button Not Working

**Check:**
1. JavaScript file loaded (Network tab)
2. `window.ajaxurl` is defined
3. Nonce is valid
4. Console errors

**Debug:**
```javascript
console.log('Sync button:', document.getElementById('notion-sync-menu-button'));
console.log('AJAX URL:', window.ajaxurl);
```

### Styles Not Applied

**Check:**
1. `wp_add_inline_style()` called on correct hook
2. Screen detection working
3. CSS selector specificity

**Debug:**
```php
add_action( 'admin_enqueue_scripts', function( $hook ) {
    error_log( 'Hook suffix: ' . $hook );
}, 1 );
```

### Override Not Saving

**Check:**
1. Item is Notion-synced
2. Nonce verification passing
3. Post meta updating

**Debug:**
```php
add_action( 'wp_update_nav_menu_item', function( $menu_id, $item_id ) {
    error_log( sprintf(
        'Saving menu item %d in menu %d',
        $item_id,
        $menu_id
    ));
}, 5, 2 );
```

## Performance Considerations

### Asset Loading

**Conditional Loading:**
- Only loads on `nav-menus.php`
- No unnecessary HTTP requests
- Inline CSS (no external file)
- Minified JavaScript (via build script)

### AJAX Efficiency

**Optimizations:**
- Single AJAX call per sync
- Proper error handling
- Loading states prevent duplicate requests
- Response caching via WordPress transients

### Database Queries

**Optimized Queries:**
- Batch fetch menu items (`wp_get_nav_menu_items()`)
- Efficient meta queries
- Minimal database writes

## Future Enhancements

### Potential Features

1. **Batch Operations**
   - Select multiple items to override at once
   - Bulk enable/disable Notion sync

2. **Sync History**
   - Show last 5 sync operations
   - Display changes made during sync

3. **Visual Diff**
   - Preview changes before sync
   - Highlight new/modified/deleted items

4. **Settings Integration**
   - Configure auto-sync interval
   - Choose which Notion properties to sync
   - Set default override behavior

5. **Advanced Filtering**
   - Show only Notion-synced items
   - Filter by sync status
   - Search by Notion page ID

## Related Files

- `MenuItemMeta.php` - Menu item metadata management
- `MenuBuilder.php` - Menu creation from Notion hierarchy
- `NavigationAjaxHandler.php` - AJAX endpoint handler
- `admin-navigation.js` - Frontend JavaScript
- `build-admin-js.sh` - JavaScript build script

## References

- [WordPress Menu Editor](https://developer.wordpress.org/reference/functions/wp_nav_menu/)
- [Admin Meta Boxes](https://developer.wordpress.org/reference/functions/add_meta_box/)
- [AJAX in Plugins](https://developer.wordpress.org/plugins/javascript/ajax/)
- [WCAG 2.1 Guidelines](https://www.w3.org/WAI/WCAG21/quickref/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
