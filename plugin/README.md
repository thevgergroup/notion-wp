# Notion Sync for WordPress

Sync your Notion content to WordPress seamlessly. Manage your content in Notion and publish it to WordPress automatically.

## What Does This Plugin Do?

Notion Sync connects your Notion workspace to your WordPress site, allowing you to:

- Write and organize content in Notion
- Automatically publish to WordPress
- Keep your WordPress site in sync with Notion updates
- Maintain page hierarchies and navigation
- Handle images and media automatically

This plugin is currently in **Phase 0 (Proof of Concept)**. Right now, you can connect your Notion account and verify the plugin can access your workspace. Content syncing features are coming in future phases.

## Requirements

- WordPress 6.0 or higher
- PHP 8.0 or higher
- A Notion account
- Notion Internal Integration (free - instructions below)

## Dependencies

This plugin includes **Action Scheduler 3.7.4** as a bundled library for reliable background processing.

**Note**: If you have other plugins that include Action Scheduler (like WooCommerce), WordPress will automatically load the newest version available across all plugins. No separate Action Scheduler plugin installation is required.

Action Scheduler is bundled as a library (recommended best practice) rather than required as a separate plugin. This ensures:

- Your plugin works standalone without external dependencies
- Better user experience (one-click installation)
- Automatic version resolution (newest version loads across all plugins)
- Full compatibility with WordPress.org plugin directory standards

## Installation

### Step 1: Install the Plugin

**Option A: Manual Installation**

1. Download the plugin ZIP file
2. Go to **WordPress Admin > Plugins > Add New**
3. Click **Upload Plugin**
4. Choose the ZIP file and click **Install Now**
5. Click **Activate Plugin**

**Option B: Upload via FTP**

1. Extract the ZIP file
2. Upload the `notion-sync` folder to `/wp-content/plugins/`
3. Go to **WordPress Admin > Plugins**
4. Find "Notion Sync" and click **Activate**

### Step 2: Create a Notion Integration

Before you can connect, you need to create a Notion integration:

1. Go to https://www.notion.com/my-integrations
2. Click **+ New integration**
3. Give it a name (e.g., "WordPress Sync")
4. Select the workspace you want to connect
5. Leave the default settings (Internal Integration)
6. Click **Submit**
7. Copy the **Internal Integration Token** (starts with `secret_`)

**Important:** Keep this token secret! Don't share it publicly or commit it to version control.

### Step 3: Share Pages with Your Integration

Your integration can only access pages you explicitly share with it:

1. Open the Notion page or database you want to sync
2. Click **Share** in the top right corner
3. Click **Invite**
4. Search for your integration name (e.g., "WordPress Sync")
5. Select it and click **Invite**

Repeat this for every page or database you want to sync to WordPress.

### Step 4: Connect in WordPress

1. Go to **WordPress Admin > Notion Sync** in your WordPress dashboard
2. Paste your Notion token in the **API Token** field
3. Click **Connect to Notion**
4. You should see a success message with your workspace name

That's it! You're connected.

## How to Use

### Phase 0 Features (Current)

The plugin is currently in its first development phase. You can:

- Connect your Notion account using an API token
- Verify the connection is working
- See your Notion workspace name
- View a list of accessible pages
- Disconnect and reconnect

**Content syncing features are not yet available.** These will be added in Phase 1.

### Coming in Future Phases

- Sync Notion pages to WordPress posts
- Convert Notion blocks to WordPress Gutenberg blocks
- Import images and media
- Map Notion properties to WordPress fields
- Generate navigation menus from Notion page structure
- Scheduled automatic syncing
- Real-time webhook updates

## Troubleshooting

### "Invalid token" error

**Problem:** You see an error saying "Invalid token" or "Unauthorized"

**Solutions:**

1. Double-check you copied the entire token (starts with `secret_`)
2. Make sure there are no extra spaces before or after the token
3. Verify the token is from an Internal Integration (not a public integration)
4. Check that the integration hasn't been deleted in Notion

### "No pages found" or empty page list

**Problem:** Connection succeeds but you don't see any pages

**Solutions:**

1. Make sure you've shared at least one page with your integration
2. In Notion, go to the page, click Share, and verify your integration is listed
3. Wait a few seconds after sharing and refresh the WordPress page
4. Try sharing a different page to test

### Network or connection errors

**Problem:** You see "Network error" or "Could not connect to Notion"

**Solutions:**

1. Check your internet connection
2. Verify your WordPress site can make outbound HTTPS requests
3. Check if your hosting provider blocks external API calls
4. Try again in a few minutes (Notion API might be temporarily down)

### Token field is blank after saving

**Problem:** After entering your token and saving, the field appears empty

**This is normal!** For security reasons, we don't display your saved token. If you see your workspace name and pages list, your token is saved correctly.

## Frequently Asked Questions

### Do I need a paid Notion plan?

No! This plugin works with free Notion accounts. You only need to create a free Internal Integration.

Note: Real-time webhook updates (coming in a future phase) require a Notion paid plan, but scheduled polling works with free accounts.

### Will this work with my existing WordPress content?

Yes! This plugin creates new content from Notion but doesn't modify or delete existing WordPress posts.

### Can I sync multiple Notion workspaces?

Currently, you can connect one Notion workspace per WordPress site. Multi-workspace support may be added in the future.

### Does this plugin work with Gutenberg (block editor)?

Yes! The plugin is designed to convert Notion blocks to WordPress Gutenberg blocks for the best editing experience.

### What happens if I disconnect?

Disconnecting removes your Notion token from WordPress. Your WordPress content remains unchanged. You can reconnect anytime with the same or different token.

### Is my Notion token secure?

Yes! Your token is stored securely in the WordPress database and is never displayed in the admin interface or sent to any third parties. Only your WordPress site uses it to communicate directly with the Notion API.

## Screenshots

_Screenshots will be added soon showing:_

1. Settings page with connection form
2. Successful connection with workspace info
3. List of accessible Notion pages
4. Error handling examples

## Privacy & Data

- This plugin communicates directly between your WordPress site and Notion's API
- No data is sent to third-party services
- Your Notion token is stored only in your WordPress database
- The plugin only accesses pages you explicitly share with the integration

## Support

### Documentation

- [Getting Started Guide](/docs/getting-started.md) - Detailed setup instructions
- [Development Guide](/CONTRIBUTING.md) - For developers who want to contribute

### Issues

If you encounter bugs or have feature requests, please report them in our issue tracker.

### Community

Join our community for help and discussions.

## Development Status

This plugin is under active development following a phased approach:

- **Phase 0 (Current):** Authentication and connection verification
- **Phase 1:** Basic Notion to WordPress sync
- **Phase 2:** Advanced block conversion and field mapping
- **Phase 3:** Media handling and navigation
- **Phase 4:** Bi-directional sync and real-time updates

Each phase goes through rigorous testing before moving to the next.

## Credits

Developed by The VGER Group as a comprehensive solution for Notion-WordPress integration.

## License

This plugin is licensed under GPL-2.0-or-later, the same license as WordPress itself.

## Version

Current version: 0.1-dev (Phase 0)
