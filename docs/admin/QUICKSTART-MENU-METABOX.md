# Quick Start: Menu Meta Box

## Installation

The menu meta box is automatically registered when the plugin is activated. No additional configuration needed.

## User Workflow

### 1. Navigate to Menu Editor

```
WordPress Admin → Appearance → Menus
```

### 2. View Sync Status

Look for **"Notion Menu Sync"** meta box in the right sidebar:

```
┌─────────────────────────────────────┐
│ 🔄 Notion Menu Sync                 │
├─────────────────────────────────────┤
│ Last Synced: 2 minutes ago          │
│ Synced Items: 19 of 21              │
│                                      │
│ [🔄 Sync from Notion Now]           │
│                                      │
│ ℹ️ Notion-synced items show 🔄 icon │
└─────────────────────────────────────┘
```

### 3. Sync Menu

Click **"Sync from Notion Now"** button:

1. Button shows "Syncing..." with spinning icon
2. Success message appears
3. Page automatically reloads
4. Updated menu items appear

### 4. Identify Synced Items

Notion-synced items show **🔄** icon next to their title in the menu editor.

### 5. Prevent Automatic Updates (Optional)

To preserve manual changes to a synced item:

1. Expand the menu item
2. Scroll to **"Notion Sync"** section
3. Check **"Prevent Notion Updates"**
4. Click **"Save Menu"**

This item will no longer be updated during Notion sync.

## Developer Integration

### Check if Item is Synced

```php
use NotionWP\Navigation\MenuItemMeta;

$menu_item_meta = new MenuItemMeta();

if ( $menu_item_meta->is_notion_synced( $item_id ) ) {
    // Item is synced from Notion
    $notion_page_id = $menu_item_meta->get_notion_page_id( $item_id );
}
```

### Check Override Status

```php
if ( $menu_item_meta->has_override( $item_id ) ) {
    // User wants to preserve manual changes
    // Skip this item during sync
}
```

### Trigger Programmatic Sync

```php
// Option 1: Direct call
$hierarchy_detector = new \NotionWP\Hierarchy\HierarchyDetector();
$menu_item_meta = new \NotionWP\Navigation\MenuItemMeta();
$menu_builder = new \NotionWP\Hierarchy\MenuBuilder( $menu_item_meta, $hierarchy_detector );

$menu_name = 'Notion Navigation';
$root_pages = [ 'page-id-1', 'page-id-2' ];

$hierarchy_map = [];
foreach ( $root_pages as $root_page_id ) {
    $hierarchy_map = array_merge(
        $hierarchy_map,
        $hierarchy_detector->build_hierarchy_map( $root_page_id )
    );
}

$menu_id = $menu_builder->create_or_update_menu( $menu_name, $hierarchy_map );

// Option 2: Via AJAX endpoint
wp_remote_post( admin_url( 'admin-ajax.php' ), [
    'body' => [
        'action' => 'notion_sync_menu_now',
        'nonce'  => wp_create_nonce( 'notion_sync_menu_now' ),
    ],
]);
```

## Customization

### Change Meta Box Position

```php
// In your theme or plugin
add_filter( 'add_meta_boxes_nav-menus', function() {
    remove_meta_box( 'notion-menu-sync', 'nav-menus', 'side' );

    add_meta_box(
        'notion-menu-sync',
        __( 'Notion Menu Sync', 'notion-wp' ),
        [ $menu_meta_box, 'render_meta_box' ],
        'nav-menus',
        'normal',  // Change from 'side' to 'normal'
        'high'
    );
}, 20 );
```

### Add Custom Fields to Synced Items

```php
add_action( 'wp_nav_menu_item_custom_fields', function( $item_id, $item, $depth, $args ) {
    $menu_item_meta = new \NotionWP\Navigation\MenuItemMeta();

    if ( ! $menu_item_meta->is_notion_synced( $item_id ) ) {
        return;
    }

    // Add your custom fields here
    ?>
    <p class="description description-wide">
        <label for="my-custom-field-<?php echo esc_attr( $item_id ); ?>">
            <?php esc_html_e( 'My Custom Field', 'my-plugin' ); ?>
        </label>
        <input type="text"
               id="my-custom-field-<?php echo esc_attr( $item_id ); ?>"
               name="menu-item-custom[<?php echo esc_attr( $item_id ); ?>]"
               value="<?php echo esc_attr( get_post_meta( $item_id, '_my_custom_field', true ) ); ?>">
    </p>
    <?php
}, 11, 4 );
```

### Customize Sync Button Label

```php
add_filter( 'notion_menu_sync_button_text', function( $text ) {
    return __( 'Update from Notion', 'my-plugin' );
});

// Usage in MenuMetaBox::render_meta_box():
$button_text = apply_filters( 'notion_menu_sync_button_text',
    __( 'Sync from Notion Now', 'notion-wp' )
);
```

### Hook Into Sync Events

```php
// Before sync
add_action( 'notion_menu_sync_before', function( $menu_id, $hierarchy_map ) {
    // Log sync start
    error_log( "Starting sync for menu {$menu_id}" );
}, 10, 2 );

// After sync
add_action( 'notion_menu_sync_after', function( $menu_id, $item_count ) {
    // Send notification, update cache, etc.
    do_action( 'my_plugin_menu_updated', $menu_id, $item_count );
}, 10, 2 );
```

## Styling Customization

### Override Meta Box Styles

```php
// In your theme's functions.php or plugin
add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( 'nav-menus.php' !== $hook ) {
        return;
    }

    $custom_css = '
        .notion-menu-sync-meta-box {
            border: 2px solid #0073aa;
            background: #f0f8ff;
        }

        #notion-sync-menu-button {
            background: #0073aa;
            border-color: #005177;
        }
    ';

    wp_add_inline_style( 'wp-admin', $custom_css );
}, 20 );
```

### Change Sync Icon

```php
add_filter( 'notion_menu_sync_icon', function( $icon ) {
    return '↻'; // Use different Unicode character
});

// Or use Dashicons
add_filter( 'notion_menu_sync_icon_class', function( $class ) {
    return 'dashicons-update-alt';
});
```

## Common Issues

### Issue: Meta Box Not Showing

**Solution:**
1. Check Screen Options (top right) - ensure meta box is enabled
2. Verify user has `manage_options` capability
3. Check for JavaScript errors in console

### Issue: Sync Button Does Nothing

**Solution:**
1. Check browser console for errors
2. Verify AJAX URL is correct: `console.log(window.ajaxurl)`
3. Check nonce is valid
4. Verify NavigationAjaxHandler is registered

### Issue: Items Not Showing 🔄 Icon

**Solution:**
1. Verify items are marked as synced: `get_post_meta( $item_id, '_notion_synced', true )`
2. Check filter is registered: `has_filter( 'nav_menu_item_title' )`
3. Clear WordPress cache

### Issue: Override Checkbox Not Saving

**Solution:**
1. Verify item is Notion-synced
2. Check `wp_update_nav_menu_item` action is firing
3. Verify meta key: `get_post_meta( $item_id, '_notion_override', true )`

## Testing

### Manual Testing Checklist

```
[ ] Meta box appears on menu editor
[ ] Last sync time displays correctly
[ ] Item count is accurate
[ ] Sync button works
[ ] Success message shows
[ ] Page reloads after sync
[ ] Synced items show 🔄 icon
[ ] Override checkbox saves
[ ] Override prevents updates
[ ] Keyboard navigation works
[ ] Screen reader announces elements
[ ] Works on mobile (782px)
```

### Automated Testing

```php
// PHPUnit test example
public function test_meta_box_registers() {
    $menu_item_meta = new MenuItemMeta();
    $menu_meta_box = new MenuMetaBox( $menu_item_meta );
    $menu_meta_box->register();

    $this->assertTrue(
        has_action( 'admin_init', [ $menu_meta_box, 'add_meta_box' ] )
    );
}

public function test_sync_indicator_added() {
    $item_id = $this->factory->post->create([
        'post_type' => 'nav_menu_item',
    ]);

    update_post_meta( $item_id, '_notion_synced', true );

    $menu_item_meta = new MenuItemMeta();
    $this->assertTrue( $menu_item_meta->is_notion_synced( $item_id ) );
}
```

## Support

### Documentation
- Full documentation: `docs/admin/menu-meta-box.md`
- API reference: See `MenuMetaBox.php` docblocks

### Debugging

Enable WordPress debug mode:

```php
// wp-config.php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

Check logs at `wp-content/debug.log`

### Getting Help

1. Check documentation
2. Review troubleshooting section
3. Search existing GitHub issues
4. Open new issue with:
   - WordPress version
   - PHP version
   - Plugin version
   - Steps to reproduce
   - Error messages/logs
