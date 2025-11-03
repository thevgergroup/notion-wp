=== Notion Sync ===
Contributors: thevgergroup
Tags: notion, sync, content, database, blocks, menu, gutenberg, import, collaboration
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPL-3.0+
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Sync Notion pages and databases to WordPress with automatic navigation menus and embedded database views.

== Description ==

**Write in Notion. Publish to WordPress.**

Notion Sync brings your Notion content to WordPress with one click. Perfect for teams who love Notion's collaborative writing experience but need WordPress for their public-facing website.

= Features =

* **One-Click Page Sync** - Import Notion pages as WordPress posts with rich content
* **Automatic Menus** - Generate WordPress navigation menus from your Notion page hierarchy
* **Embedded Database Tables** - Display Notion databases as interactive, filterable tables
* **Rich Content Support** - Images, tables, code blocks, callouts, toggles, and more
* **Background Processing** - Handle large imports without timeouts
* **Parent-Child Hierarchies** - Maintain nested page structures from Notion
* **Internal Link Resolution** - Notion page links automatically convert to WordPress permalinks
* **Media Library Integration** - Images download automatically to WordPress

= Supported Notion Blocks =

* Text blocks (paragraphs, headings, lists)
* Rich text formatting (bold, italic, links, code)
* Images and files
* Tables
* Code blocks with syntax highlighting
* Callouts with icons
* Toggles (expandable sections)
* Quotes
* Dividers
* Embeds (YouTube, Twitter, etc.)
* Columns
* Child pages and databases
* Link-to-page blocks

= Coming Soon =

* Board, gallery, timeline, and calendar database views
* WordPress → Notion bi-directional sync
* Scheduled automatic syncs
* Real-time webhook sync

= Use Cases =

* **Content Teams**: Write and review in Notion, publish to WordPress
* **Documentation Sites**: Maintain docs in Notion, display on WordPress
* **Knowledge Bases**: Sync help articles from Notion to WordPress
* **Portfolios**: Organize projects in Notion, showcase on WordPress
* **Blogs**: Draft in Notion's clean interface, publish to WordPress

= Requirements =

* WordPress 6.0 or higher
* PHP 8.0 or higher
* A Notion account (free or paid)
* A Notion Integration (free - takes 2 minutes to create)

= Privacy & Data =

This plugin does not:
* Track users or send analytics
* Store data on external servers
* Phone home or make external requests (except to Notion API)
* Require paid subscriptions or upgrades

All data is stored in your WordPress database. Your Notion API token is stored securely in your WordPress options table.

== Installation ==

= From WordPress.org =

1. Go to **Plugins > Add New** in your WordPress admin
2. Search for "Notion Sync"
3. Click **Install Now**, then **Activate**
4. Go to **Settings → Notion Sync** to configure

= Manual Installation =

1. Download the plugin ZIP file
2. Go to **Plugins > Add New > Upload Plugin**
3. Choose the ZIP file and click **Install Now**
4. Click **Activate** after installation
5. Go to **Settings → Notion Sync** to configure

= Setup Steps =

**Step 1: Create a Notion Integration**

1. Go to [https://www.notion.so/my-integrations](https://www.notion.so/my-integrations)
2. Click **+ New integration**
3. Give it a name (e.g., "WordPress Sync")
4. Select your workspace
5. Click **Submit**
6. Copy your **Internal Integration Token** (starts with `secret_`)

**Step 2: Share Pages with Your Integration**

1. Open a Notion page you want to sync
2. Click **Share** in the top right
3. Click **Invite** and select your integration
4. Repeat for all pages you want to sync

*Tip: If you share a parent page, all child pages are automatically shared!*

**Step 3: Connect WordPress to Notion**

1. In WordPress, go to **Settings → Notion Sync**
2. Paste your Integration Token
3. Click **Test Connection**
4. If successful, you'll see your available pages

**Step 4: Sync Your Pages**

1. Check the boxes next to pages you want to sync
2. Click **Sync Selected Pages**
3. The plugin will import your content in the background
4. View your synced content in **Posts** or **Pages**

**Step 5: Add Menu to Your Site**

1. Go to **Appearance → Menus**
2. Find the menu named **Notion Pages** (auto-created)
3. Assign it to a menu location in your theme
4. Visit your site to see the navigation!

== Frequently Asked Questions ==

= How do I get a Notion API token? =

1. Visit [https://www.notion.so/my-integrations](https://www.notion.so/my-integrations)
2. Click "New integration"
3. Give it a name and select your workspace
4. Copy the "Internal Integration Token"
5. Paste it into the Notion Sync settings page

Don't forget to share your Notion pages with the integration!

= Why aren't my pages showing up? =

Make sure you've shared the pages with your Notion integration:

1. Open the page in Notion
2. Click "Share" in the top right
3. Click "Invite"
4. Select your integration from the list

If you share a parent page, all child pages are automatically shared.

= What Notion block types are supported? =

We support 18+ block types including:

* Text (paragraphs, headings, lists)
* Images and files
* Tables
* Code blocks
* Callouts
* Toggles
* Quotes
* Embeds (YouTube, Twitter, etc.)
* Columns
* Dividers
* Child pages and databases

Unsupported blocks are preserved as HTML comments for future compatibility.

= Can I sync from WordPress back to Notion? =

Bi-directional sync (WordPress → Notion) is planned for a future release. Currently, the plugin only syncs from Notion to WordPress.

= How do I schedule automatic syncs? =

Scheduled automatic syncs are planned for a future release. Currently, you need to manually trigger syncs from the settings page.

Notion paid plans support webhooks for real-time sync, which we plan to add in a future version.

= What happens if I delete a page in Notion? =

Currently, deleting a page in Notion does not automatically delete it from WordPress. You'll need to manually delete the WordPress post.

A "Full Mirror" sync option (that removes deleted pages) is planned for a future release.

= How do I sync a Notion database? =

1. Create a page in Notion that embeds your database
2. Share that page with your integration
3. Sync the page to WordPress
4. The embedded database will appear as an interactive table

You can also sync databases as linked database views with custom filters and sorting.

= Does this work with Notion's free plan? =

Yes! The plugin works with both free and paid Notion accounts. You only need to create a free Notion Integration.

Real-time webhook sync requires a paid Notion plan, but manual sync works with free accounts.

= How is my Notion API token stored? =

Your token is stored securely in your WordPress options table using WordPress's built-in options API. It's only accessible to WordPress administrators.

The token is never sent to external servers (except Notion's official API for sync operations).

= Does this plugin track me or send analytics? =

No. This plugin does not track users, send analytics, or make any external requests except to Notion's official API for sync operations.

Your data stays in your WordPress database.

= I'm getting timeout errors during sync =

For large pages with many images, the sync happens in the background using WordPress's built-in Action Scheduler.

If you're experiencing issues:

1. Check **Settings → Notion Sync → Sync Logs** for details
2. Try syncing smaller pages first
3. Make sure your WordPress cron is working (`wp cron event list`)
4. Increase PHP's `max_execution_time` if needed

= Can I customize which fields sync from Notion databases? =

Field mapping customization is planned for a future release. Currently, standard Notion properties (title, date, select, multi-select) map to standard WordPress fields.

= Does this work with custom post types? =

Currently, pages sync as WordPress posts or pages. Custom post type support is planned for a future release.

= Is this compatible with Gutenberg/block editor? =

Yes! All Notion content is converted to native WordPress Gutenberg blocks. You can edit synced content in the WordPress block editor.

= What themes does this work with? =

Notion Sync works with any WordPress theme. We test with:

* Twenty Twenty-Four (block theme)
* Twenty Twenty-Three
* Astra
* GeneratePress

== Screenshots ==

1. Settings page - Connect your Notion account with an integration token
2. Page selection - Choose which Notion pages to sync to WordPress
3. Sync dashboard - Monitor sync status and manage your synced content
4. Database table view - Interactive Notion databases displayed as filterable, sortable tables
5. Published hierarchy - Nested Notion pages maintain their parent-child relationships in WordPress
6. Menu generation - WordPress navigation menus automatically generated from Notion page structure

== Changelog ==

= 1.0.0 - 2025-11-02 =

**Initial Public Release**

This is the first stable release of Notion Sync for WordPress. All core features are production-ready.

*Features Included:*

* **Page Sync** - Import Notion pages to WordPress with one click
* **Rich Content** - Support for 18+ Notion block types
* **Media Handling** - Automatic image download to WordPress Media Library
* **Hierarchy** - Maintain parent-child page relationships
* **Navigation** - Auto-generate WordPress menus from Notion structure
* **Database Views** - Display Notion databases as interactive tables
* **Link Resolution** - Convert Notion internal links to WordPress permalinks
* **Background Processing** - Handle large imports without timeouts
* **Comprehensive Logging** - Track sync status and troubleshoot issues
* **WP-CLI Support** - Command-line tools for automation

*Technical Achievements:*

* 261 PHPUnit tests with 641 assertions
* PHPCS and PHPStan code quality standards
* Action Scheduler for reliable background processing
* MediaRegistry for duplicate prevention
* Extensible block converter architecture
* GPL-3.0+ licensed

*Known Limitations (Coming Soon):*

* Board, gallery, timeline, calendar database views
* WordPress → Notion bi-directional sync
* Scheduled automatic syncs
* Real-time webhook sync

See the [GitHub repository](https://github.com/thevgergroup/notion-wp) for detailed changelog and development roadmap.

== Upgrade Notice ==

= 1.0.0 =

First stable release of Notion Sync for WordPress. Import Notion content with one click, auto-generate navigation menus, and display databases as interactive tables. Fully tested and production-ready.

== Development ==

This plugin is open source and welcomes contributions!

* **GitHub Repository:** [https://github.com/thevgergroup/notion-wp](https://github.com/thevgergroup/notion-wp)
* **Issue Tracker:** [https://github.com/thevgergroup/notion-wp/issues](https://github.com/thevgergroup/notion-wp/issues)
* **Developer Docs:** See DEVELOPMENT.md in the plugin directory

= Contributing =

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Write tests for new features
5. Ensure all linting passes
6. Submit a pull request

See CONTRIBUTING.md for detailed guidelines.

= Code Standards =

* WordPress Coding Standards (WPCS)
* PHPStan Level 5
* PSR-4 autoloading
* Comprehensive unit tests
* Security best practices

== Credits ==

**Developed by:** The Verger Group ([https://thevgergroup.com](https://thevgergroup.com))

**Dependencies:**
* Action Scheduler by Automattic ([https://actionscheduler.org](https://actionscheduler.org))
* Tabulator by Oli Folkerd for database table views

**Thanks to:**
* All beta testers who provided feedback
* The WordPress and Notion communities
