<div align="center">
  <img src="docs/images/notion-wp-logo.png" alt="Notion Sync Logo" width="128">
</div>

# Notion Sync for WordPress

[![Tests](https://github.com/thevgergroup/notion-wp/actions/workflows/test.yml/badge.svg)](https://github.com/thevgergroup/notion-wp/actions/workflows/test.yml)
[![Code Quality](https://github.com/thevgergroup/notion-wp/actions/workflows/lint.yml/badge.svg)](https://github.com/thevgergroup/notion-wp/actions/workflows/lint.yml)
[![Coverage](https://img.shields.io/endpoint?url=https://gist.githubusercontent.com/pjaol/2cb753e52d7fcf0a1176d34f406ad613/raw/notion-wp-coverage.json)](https://gist.github.com/pjaol/2cb753e52d7fcf0a1176d34f406ad613)
[![PHP Version](https://img.shields.io/badge/php-8.0%2B-blue)](https://www.php.net/)
[![WordPress](https://img.shields.io/badge/wordpress-6.0%2B-blue)](https://wordpress.org/)
[![License](https://img.shields.io/badge/license-GPL--3.0-green)](LICENSE)

**Sync your Notion pages and databases to WordPress with automatic navigation menus and embedded database views.**

Write and organize your content in Notion, then publish it to your WordPress site with one click. Perfect for teams who love Notion's collaborative writing experience but need WordPress for their public-facing website.

---

## Table of Contents

- [Features](#features)
- [Screenshots](#screenshots)
- [Installation](#installation)
- [Getting Started](#getting-started)
- [Usage Guide](#usage-guide)
- [Theme Integration](#theme-integration)
- [FAQ](#faq)
- [Requirements](#requirements)
- [Documentation](#documentation)
- [Support](#support)
- [Roadmap](#roadmap)
- [Contributing](#contributing)
- [License](#license)
- [Credits](#credits)
- [Show Your Support](#show-your-support)

---

## Features

- **One-Click Page Sync** - Import Notion pages as WordPress posts with rich content
- **Automatic Menus** - Generate WordPress navigation menus from your Notion page hierarchy
- **Collapsible Sidebar Navigation** - Ready-to-use block patterns with hierarchical menus ([Learn more](docs/features/BLOCK-PATTERNS.md))
- **Embedded Database Tables** - Display Notion databases as interactive tables on your site
- **Rich Content Support** - Images, tables, code blocks, callouts, toggles, and more
- **Background Processing** - Handle large imports without timeouts
- **Parent-Child Hierarchies** - Maintain nested page structures from Notion
- **Internal Link Resolution** - Notion page links automatically convert to WordPress permalinks
- **Coming Soon:** Board, gallery, timeline, and calendar database views
- **Coming Soon:** WordPress → Notion bi-directional sync

---

## Screenshots

### Settings - Connection
Configure your Notion integration token to connect WordPress with Notion.

![Settings Connection](docs/images/settings-connection.png)

### Page Selection
Choose which Notion pages to sync to your WordPress site.

![Page Selection](docs/images/settings-page-selection.png)

### Sync Dashboard
Monitor sync status and manage your synced content.

![Sync Dashboard](docs/images/sync-dashboard.png)

**[View more screenshots →](docs/SCREENSHOTS.md)** - See examples of published content, database tables, and navigation menus.

---

## Installation

### From GitHub (Manual Installation)

1. Download the latest release from the [Releases page](https://github.com/thevgergroup/notion-wp/releases)
2. Upload the `notion-sync` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to **Settings → Notion Sync** to configure

### Requirements

- **WordPress:** 6.0 or higher
- **PHP:** 8.0 or higher
- **Notion Account:** Free or paid plan
- **Notion Integration:** You'll need to create a Notion integration (free)

> **Coming Soon:** Installation from WordPress.org plugin directory

---

## Getting Started

### Step 1: Create a Notion Integration

1. Go to [Notion Integrations](https://www.notion.so/my-integrations)
2. Click **+ New integration**
3. Give it a name (e.g., "WordPress Sync")
4. Select the workspace you want to use
5. Click **Submit**
6. Copy your **Internal Integration Token** (starts with `secret_`)

### Step 2: Share Pages with Your Integration

1. Open the Notion page you want to sync
2. Click **Share** in the top right
3. Click **Invite** and select your integration
4. Repeat for all pages you want to sync

> **Tip:** If you share a parent page, all child pages are automatically shared!

### Step 3: Connect WordPress to Notion

1. In WordPress, go to **Settings → Notion Sync**
2. Paste your **Integration Token**
3. Click **Test Connection**
4. If successful, you'll see your available pages

### Step 4: Select Pages to Sync

1. Check the boxes next to pages you want to sync
2. Click **Sync Selected Pages**
3. The plugin will import your content in the background

### Step 5: Add Menu to Your Site

1. Go to **Appearance → Menus**
2. Find the menu named **Notion Pages** (auto-created)
3. Assign it to a menu location in your theme
4. Visit your site to see the navigation!

---

## Usage Guide

### Syncing Pages

**Manual Sync:**
1. Go to **Settings → Notion Sync**
2. Select pages to sync
3. Click **Sync Now**

**What Gets Synced:**
- Page title
- Page content (all supported block types)
- Images and media files
- Parent-child relationships
- Page hierarchy

**Sync Frequency:**
- Currently manual sync only
- Automatic scheduled sync coming soon
- Real-time webhook sync coming soon (Notion paid plans)

### Syncing Databases

**Display Notion Databases:**
1. Sync a page that contains a database
2. The database appears as an interactive table
3. Users can filter, sort, and search
4. Export to CSV available

**Current Support:**
- Table view with filters and sorting
- Board, gallery, timeline, calendar views coming soon

### Embedding Database Views

**In the Block Editor:**
1. Add a new block
2. Search for "Notion Database"
3. Select your database
4. Configure display options
5. Publish!

**On the Frontend:**
- Interactive tables with live filtering
- Sorting by any column
- Search across all fields
- Export to CSV

### Managing Menus

**Auto-Generated Menus:**
- Created automatically from Notion hierarchy
- Updates on each sync
- Maintains nesting up to 3 levels deep

**Manual Menu Items:**
- Add custom items to the Notion menu
- Plugin preserves your manual additions
- Mix Notion pages with custom links

**Assigning Menus:**
1. **Appearance → Menus**
2. Select **Notion Pages** menu
3. Assign to a menu location
4. Save

### Displaying Sidebar Navigation

**Use Block Patterns for Easy Setup:**

The plugin includes ready-to-use block patterns that display your Notion pages as a collapsible hierarchical sidebar navigation - perfect for documentation sites, knowledge bases, or any site with nested content.

**Quick Start:**
1. Edit any page/post or template in the Site Editor
2. Click **`+`** → **Patterns** → **"Notion Sync"**
3. Insert **"Notion Navigation Hierarchy"**
4. Done! Your navigation sidebar appears with collapsible sections

**Features:**
- **Collapsible sections** with animated chevron icons
- **Mobile-friendly** responsive design
- **Accessible** with proper ARIA attributes
- **Customizable** colors, spacing, and headings
- **Works with Twenty Twenty-Four & Twenty Twenty-Five**

**[Complete Pattern Documentation →](docs/features/BLOCK-PATTERNS.md)**

---

## Theme Integration

### Adding Menus to Your Theme

**For Block Themes (2024+):**
1. Open Site Editor (**Appearance → Editor**)
2. Click on the Navigation block
3. Select **Notion Pages** from the menu dropdown
4. Save

**For Classic Themes:**
1. **Appearance → Menus**
2. Find "Theme Locations" section
3. Select **Notion Pages** for your primary location
4. Save Menu

**Popular Themes:**

| Theme | Menu Location |
|-------|---------------|
| Twenty Twenty-Four | Site Editor → Navigation block |
| Twenty Twenty-Three | Site Editor → Navigation block |
| Astra | Appearance → Menus → Primary Menu |
| GeneratePress | Appearance → Menus → Primary Navigation |
| Neve | Appearance → Menus → Primary Menu |

### Styling Notion Content

The plugin outputs standard WordPress blocks with class names for styling:

```css
/* Callout blocks */
.notion-callout {
  padding: 1rem;
  border-left: 4px solid;
}

/* Database tables */
.notion-database-table {
  width: 100%;
}

/* Toggle blocks */
.notion-toggle summary {
  cursor: pointer;
  font-weight: bold;
}
```

---

## FAQ

### What Notion content is supported?

**Fully Supported:**
- Paragraphs, headings, lists
- Images and file attachments
- Tables
- Code blocks with syntax highlighting
- Callouts
- Toggles (collapsible content)
- Quotes
- Dividers
- Embeds (YouTube, Twitter, etc.)
- Database table views

**Coming Soon:**
- Board views
- Gallery views
- Timeline views
- Calendar views

### How often should I sync?

- **Manual sync** whenever you update content in Notion
- Syncing is safe - it won't create duplicates
- Large syncs process in the background
- Automatic scheduled sync coming soon

### What happens to WordPress edits?

Currently, syncing is **one-way only** (Notion → WordPress):
- WordPress edits will be overwritten on next sync
- Make all content changes in Notion
- Bi-directional sync coming in a future release

### Can I sync private Notion pages?

Yes! As long as:
1. The page is shared with your integration
2. Your integration has the right permissions
3. The page is in the connected workspace

The plugin respects Notion permissions:
- Private pages → Private WordPress posts
- Public pages → Public WordPress posts

### Does this work with page builders?

The plugin outputs standard WordPress Gutenberg blocks, which work with:
- WordPress Block Editor (Gutenberg)
- Full Site Editing themes
- Limited support for page builders (Elementor, Divi, etc.)

For page builders, content syncs as HTML that you can copy/paste into page builder modules.

### Can I sync multiple Notion workspaces?

Currently, one workspace per WordPress site. To sync multiple workspaces:
- Use WordPress Multisite
- Create separate integration tokens
- Native multi-workspace support coming soon

### How do I uninstall?

1. **Deactivate** the plugin
2. **Delete** it from the Plugins page
3. **Optional:** Delete Notion sync data
   - Go to **Settings → Notion Sync**
   - Click **Delete All Sync Data**
   - Confirm deletion

This removes all sync history but keeps your WordPress posts.

### My images aren't showing up!

Images process in the background to avoid timeouts:
1. Check **Settings → Notion Sync → Sync Status**
2. Look for image processing jobs
3. Refresh your page after a few minutes

For troubleshooting:
- Ensure your WordPress site can access Notion's S3 URLs
- Check PHP max_execution_time setting
- Review error logs

### Internal links aren't working!

Make sure both pages are synced to WordPress:
1. Sync the page you're linking FROM
2. Sync the page you're linking TO
3. Links resolve automatically after both pages sync

If still broken:
- Check **Settings → Notion Sync → Link Status**
- Look for unresolved links
- Sync missing pages

---

## Requirements

- **WordPress:** 6.0+
- **PHP:** 8.0+
- **Notion:** Free or paid account
- **Server Requirements:**
  - `curl` extension enabled
  - `gd` or `imagick` for image processing
  - Minimum 128MB PHP memory limit

---

## Documentation

- [Getting Started Guide](docs/getting-started.md) - Detailed setup and usage instructions
- [Block Patterns Guide](docs/features/BLOCK-PATTERNS.md) - How to use collapsible sidebar navigation
- [Security Guide](docs/SECURITY.md) - Security features, best practices, and vulnerability reporting
- [Testing Guide](docs/TESTING.md) - How to run tests, write tests, and contribute test coverage
- [Development Guide](DEVELOPMENT.md) - For contributors and developers
- [Architecture Documentation](docs/architecture/) - Technical architecture details
- [Testing Documentation](docs/testing/) - Detailed testing strategies and examples

---

## Support

Need help? Here's how to get support:

- **Bug Reports:** [GitHub Issues](https://github.com/thevgergroup/notion-wp/issues)
- **Feature Requests:** [GitHub Discussions](https://github.com/thevgergroup/notion-wp/discussions)
- **Documentation:** [docs/](docs/)

---

## Roadmap

**Current Version:** Supports core sync, menus, and table views

**Coming Soon:**
- Additional database views (board, gallery, timeline, calendar)
- Scheduled automatic sync
- Webhook support for real-time updates
- WordPress → Notion bi-directional sync
- Advanced field mapping UI
- Custom post type support

---

## Contributing

We welcome contributions! See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

For development setup and technical documentation, see [DEVELOPMENT.md](DEVELOPMENT.md).

---

## License

This plugin is licensed under [GPL v3.0](LICENSE).

---

## Credits

Developed by [The VGER Group](https://github.com/thevgergroup)

**Built with:**
- [Notion API](https://developers.notion.com/)
- [WordPress](https://wordpress.org/)
- [Tabulator](http://tabulator.info/) for interactive tables
- [Action Scheduler](https://actionscheduler.org/) for background processing

---

## Show Your Support

If this plugin helps you, please:
- Star the repository on GitHub
- Report bugs and issues
- Suggest new features
- Improve documentation
- Contribute code

Thank you for using Notion Sync for WordPress!
