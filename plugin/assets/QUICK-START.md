# Quick Start - Admin UI Integration

**Goal:** Get the Notion Sync settings page styled and functional in 5 minutes.

## Step 1: Copy Enqueue Code (2 minutes)

Open your `SettingsPage.php` and add this method:

```php
/**
 * Enqueue admin assets on settings page only
 *
 * @param string $hook_suffix The current admin page hook suffix
 */
public function enqueue_assets( string $hook_suffix ): void {
    // Only load on our settings page
    if ( 'toplevel_page_notion-sync' !== $hook_suffix ) {
        return;
    }

    $plugin_url = plugin_dir_url( NOTION_SYNC_FILE );
    $version = NOTION_SYNC_VERSION;

    // Enqueue CSS (plain CSS, no build needed)
    wp_enqueue_style(
        'notion-sync-admin',
        $plugin_url . 'assets/src/css/admin.css',
        array(),
        $version,
        'all'
    );

    // Enqueue JavaScript (vanilla JS)
    wp_enqueue_script(
        'notion-sync-admin',
        $plugin_url . 'assets/src/js/admin.js',
        array(),
        $version,
        true
    );
}
```

Then register the hook in your `register()` method:

```php
public function register(): void {
    add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
    add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
}
```

## Step 2: Use HTML Patterns (2 minutes)

Open `plugin/templates/admin/settings-sample.php` and copy the relevant sections:

### For Disconnected State (Connection Form):
```php
<div class="wrap notion-sync-settings">
    <div class="notion-sync-header">
        <h1><?php esc_html_e( 'Notion Sync Settings', 'notion-wp' ); ?></h1>
    </div>

    <div class="notion-sync-connection-form">
        <form id="notion-sync-connection-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'notion_sync_connect', 'notion_sync_nonce' ); ?>
            <input type="hidden" name="action" value="notion_sync_connect">

            <div class="form-field">
                <label for="notion_token">
                    <?php esc_html_e( 'Notion Integration Token', 'notion-wp' ); ?>
                </label>
                <input
                    type="password"
                    id="notion_token"
                    name="notion_token"
                    class="token-input"
                    required
                >
            </div>

            <div class="form-actions">
                <button type="submit" class="button button-primary" disabled>
                    <?php esc_html_e( 'Connect to Notion', 'notion-wp' ); ?>
                </button>
            </div>
        </form>
    </div>
</div>
```

### For Connected State (Workspace Info):
```php
<div class="wrap notion-sync-settings">
    <div class="notion-sync-workspace-info">
        <span class="success-icon"></span>
        <h2 class="workspace-name">
            <?php echo esc_html( $workspace_name ); ?>
        </h2>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'notion_sync_disconnect', 'notion_sync_nonce' ); ?>
            <input type="hidden" name="action" value="notion_sync_disconnect">
            <button type="submit" id="notion-sync-disconnect" class="button disconnect-button">
                <?php esc_html_e( 'Disconnect', 'notion-wp' ); ?>
            </button>
        </form>
    </div>

    <div class="notion-sync-pages">
        <h2><?php esc_html_e( 'Available Pages', 'notion-wp' ); ?></h2>
        <div class="pages-list">
            <?php foreach ( $pages as $page ) : ?>
                <div class="page-item">
                    <span class="page-icon"><?php echo esc_html( $page['icon'] ); ?></span>
                    <div class="page-info">
                        <div class="page-title"><?php echo esc_html( $page['title'] ); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
```

## Step 3: Test (1 minute)

1. Navigate to **WP Admin → Notion Sync**
2. Check browser console for errors (should be none)
3. Try entering a token (button should enable)
4. Check on mobile (resize browser)

## That's It!

Your settings page now has:
- ✅ Professional WordPress-native styling
- ✅ Loading states during form submission
- ✅ Token validation
- ✅ Mobile responsive design
- ✅ Keyboard accessible
- ✅ WCAG 2.1 AA compliant

## Important Classes to Remember

### Required WordPress Classes:
- `.wrap` - Always wrap your admin page
- `.button-primary` - For primary actions
- `.notice`, `.notice-success`, `.notice-error` - For admin notices

### Custom Classes (from our CSS):
- `.notion-sync-settings` - Main container
- `.notion-sync-connection-form` - Connection form wrapper
- `.token-input` - Monospace token input
- `.notion-sync-workspace-info` - Connected state box
- `.notion-sync-pages` - Pages list container

## Troubleshooting

**CSS not loading?**
- Check `NOTION_SYNC_FILE` constant points to main plugin file
- Verify hook suffix: `toplevel_page_notion-sync` (debug with var_dump)
- Check browser Network tab for 404s

**JavaScript not working?**
- Check browser console for errors
- Verify script is enqueued (view page source)
- Ensure form has correct ID: `notion-sync-connection-form`
- Ensure button is inside form

**Styles look wrong?**
- Make sure you have `.wrap` class on outer div
- Check WordPress version (5.9+ recommended)
- Try clearing browser cache

## Next Steps

Once basic styling works:

1. **Add admin notices** for success/error feedback
2. **Implement API connection** in form handler
3. **Display workspace info** after successful connection
4. **List pages** from Notion API
5. **Test accessibility** with keyboard navigation

## Need More Help?

See detailed documentation:
- `/plugin/assets/README-ADMIN-UI.md` - Full implementation guide
- `/plugin/templates/admin/settings-sample.php` - Complete HTML examples
- `/plugin/assets/ENQUEUE-SNIPPET.php` - Advanced enqueue options

---

**Time to implement:** ~5 minutes
**Lines of code to write:** ~50 (mostly copy-paste)
**Dependencies:** None (vanilla CSS & JS)
