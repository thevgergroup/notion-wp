# Documentation Structure Plan

**Created:** 2025-11-01
**Status:** Planning - To be implemented in Phase 5

## Overview

This document outlines the new documentation structure that separates user-facing documentation from developer documentation, making the project accessible to both WordPress users and plugin developers.

---

## Current State

**README.md** - Currently developer-focused with:
- Docker setup and worktrees
- Development workflow
- Testing instructions
- Plugin architecture details
- Debugging guides

**Problem:** Non-technical WordPress users can't easily install or use the plugin

---

## Proposed Structure

```
notion-wp/
├── README.md                           # User-focused (NEW)
├── docs/
│   ├── INDEX.md                        # Documentation directory (NEW)
│   ├── images/                         # Screenshots (NEW)
│   │   ├── settings-connection.png
│   │   ├── settings-page-selection.png
│   │   ├── sync-dashboard.png
│   │   ├── database-table-view.png
│   │   ├── editor-database-block.png
│   │   ├── published-hierarchy.png
│   │   └── menu-generation.png
│   ├── development/                    # Developer docs (NEW)
│   │   ├── DEVELOPMENT.md              # Main dev guide
│   │   ├── ARCHITECTURE.md             # System architecture
│   │   ├── BLOCK_CONVERTERS.md         # Custom converters guide
│   │   ├── TESTING.md                  # Testing guide
│   │   ├── API.md                      # REST API reference
│   │   └── HOOKS.md                    # WordPress hooks/filters
│   ├── architecture/                   # Existing technical docs
│   ├── product/                        # Existing product docs
│   ├── requirements/                   # Existing requirements
│   └── plans/                          # Existing development plans
└── CLAUDE.md                           # Claude Code instructions
```

---

## README.md (User-Focused)

### Target Audience
Non-technical WordPress site owners who want to sync Notion content to their WordPress site.

### Sections

#### 1. Header
```markdown
# Notion-WP Sync

Sync your Notion pages and databases to WordPress with automatic navigation menus and embedded database views.

[Installation](#installation) | [Getting Started](#getting-started) | [Features](#features) | [Support](#support)
```

#### 2. Overview
- What the plugin does (1-2 paragraphs)
- Key benefits (bullet points)
- Who it's for

#### 3. Features

**Available Now:**
- ✅ Page synchronization from Notion to WordPress
- ✅ Database synchronization with field mapping
- ✅ Page hierarchy preservation
- ✅ Automatic navigation menu generation
- ✅ Media download and management
- ✅ Internal link conversion
- ✅ Database table views with filters and export

**Beta Features:**
- ⚠️ Additional database view types (list, gallery, board)

**Coming Soon:**
- 🚧 WordPress to Notion sync (bi-directional)
- 🚧 Real-time sync via webhooks
- 🚧 Advanced field mapping UI

#### 4. Requirements
- WordPress 6.0 or higher
- PHP 8.0 or higher
- Notion account (free or paid)
- Notion Integration token

#### 5. Installation

**Option A: WordPress.org (Recommended - Future)**
```
1. Go to Plugins → Add New
2. Search for "Notion-WP Sync"
3. Click Install Now
4. Click Activate
```

**Option B: Manual Installation (Current)**
```
1. Download the latest release
2. Upload to /wp-content/plugins/
3. Activate via Plugins menu
4. Follow setup wizard
```

#### 6. Getting Started

**Step 1: Create Notion Integration**
1. Go to https://www.notion.so/my-integrations
2. Click "+ New integration"
3. Name it "WordPress Sync"
4. Copy the Internal Integration Token
5. Share your Notion pages with this integration

[Screenshot: Notion integration creation]

**Step 2: Connect to WordPress**
1. Go to WordPress Admin → Notion Sync → Settings
2. Paste your Integration Token
3. Click "Connect to Notion"
4. Verify connection successful

[Screenshot: WordPress settings page]

**Step 3: Select Content to Sync**
1. Go to Notion Sync → Sync Dashboard
2. Browse available pages/databases
3. Select items to sync
4. Click "Sync Now"

[Screenshot: Sync dashboard]

**Step 4: Generate Navigation Menu (Optional)**
1. After sync, you'll see a prompt
2. Choose "Generate Menu from Pages"
3. Assign to theme location
4. Your Notion hierarchy becomes navigation

[Screenshot: Menu generation]

#### 7. Configuration

**Sync Settings**
- Sync frequency (manual, hourly, daily)
- Conflict resolution (Notion wins, WordPress wins)
- Media handling options

**Field Mapping**
- Map Notion properties to WordPress fields
- Configure custom post types
- Set taxonomy mappings

**Menu Settings**
- Choose which pages to include
- Set maximum depth
- Configure menu locations

**Database Views**
- Default view type (table, list, gallery)
- Entries per page
- Filter/sort options

[Screenshots for each setting section]

#### 8. Theme Integration

**Displaying Menus**

Most themes support WordPress menus out of the box:

1. Go to Appearance → Menus
2. Find your auto-generated Notion menu
3. Assign to Primary Menu location
4. Save

**Recommended Themes:**
- Twenty Twenty-Four (Block theme)
- Astra (Fast, Notion-friendly)
- GeneratePress (Clean structure)
- Kadence (Advanced customization)

#### 9. Features in Detail

**Page Hierarchy**
- Nested pages in Notion → Nested pages in WordPress
- Parent-child relationships preserved
- Breadcrumbs work automatically
- 10-level nesting supported

**Database Views**
- Embed Notion databases in pages
- Table view with sorting/filtering
- Export to CSV
- Responsive design

**Media Handling**
- Automatic image download
- Duplicate detection
- WordPress Media Library integration
- Alt text preservation

**Internal Links**
- Notion page links → WordPress permalinks
- Automatic link updates on sync
- Broken link detection

[Screenshots for each feature]

#### 10. Troubleshooting

**Connection Issues**
- Verify API token is correct
- Check Notion page sharing
- Ensure WordPress can make external requests

**Sync Errors**
- Check error logs at Notion Sync → Logs
- Verify Notion page permissions
- Ensure no conflicting plugins

**Missing Images**
- Re-run sync to retry downloads
- Check WordPress upload permissions
- Verify disk space

**Menu Not Appearing**
- Assign menu to theme location
- Check theme supports menus
- Refresh permalinks

#### 11. FAQ

**Q: Does this work with my theme?**
A: Yes, if your theme supports standard WordPress menus and posts.

**Q: Can I edit content in WordPress after sync?**
A: Yes, but changes will be overwritten on next sync. Use Notion as single source of truth.

**Q: How often does it sync?**
A: Manual by default. Configure automatic sync intervals in settings.

**Q: Is my Notion data secure?**
A: Your API token is encrypted in WordPress database. Data is transferred over HTTPS.

**Q: What happens if I delete in Notion?**
A: Deleted pages can be kept, moved to trash, or deleted in WordPress (configurable).

**Q: Does it work with Notion databases?**
A: Yes! Database entries sync as WordPress posts with field mapping.

#### 12. Support

- **Documentation:** [Full documentation](docs/)
- **Issues:** [GitHub Issues](https://github.com/thevgergroup/notion-wp/issues)
- **Discussions:** [GitHub Discussions](https://github.com/thevgergroup/notion-wp/discussions)

#### 13. Contributing

Interested in contributing? See our [Development Guide](docs/development/DEVELOPMENT.md).

#### 14. License

GPL-2.0-or-later - WordPress plugin license.

---

## docs/development/DEVELOPMENT.md

### Target Audience
Developers who want to contribute to the plugin or extend its functionality.

### Content to Move from README
- All Docker/worktree setup
- Development workflow
- Testing instructions
- Code standards
- Architecture details
- Plugin development guides
- Troubleshooting for developers

### New Sections to Add
- Setting up development environment (detailed)
- Understanding the codebase structure
- Plugin architecture deep-dive
- Testing strategies
- Contribution guidelines
- Code review process

---

## docs/development/ARCHITECTURE.md

- PSR-4 autoloading structure
- Dependency injection container
- Repository pattern usage
- Action Scheduler integration
- WordPress VIP standards compliance
- Database schema
- Custom tables design
- Background job processing
- Caching strategy

---

## docs/development/BLOCK_CONVERTERS.md

- How Notion blocks map to Gutenberg
- Creating custom converters
- BlockConverterInterface implementation
- Registering converters via filters
- Testing block converters
- Supported block types reference
- Handling unsupported blocks

---

## docs/development/TESTING.md

- PHPUnit setup
- Unit tests vs integration tests
- Brain\Monkey for WordPress mocks
- Test database setup
- Coverage reporting
- CI/CD integration
- Writing testable code
- Test naming conventions

---

## docs/development/API.md

- REST API endpoints
- `/notion-sync/v1/pages`
- `/notion-sync/v1/databases`
- Authentication
- Rate limiting
- Error responses
- Request/response examples
- Webhook endpoints (future)

---

## docs/development/HOOKS.md

Complete reference of WordPress hooks and filters:

**Actions:**
- `notion_sync_page_synced` - After page sync
- `notion_sync_database_synced` - After database sync
- `notion_wp_hierarchy_updated` - After hierarchy update
- `notion_sync_media_imported` - After media import

**Filters:**
- `notion_sync_block_converters` - Register custom converters
- `notion_sync_field_mappings` - Custom field mappings
- `notion_sync_post_data` - Modify post before insert
- `notion_sync_media_metadata` - Modify media metadata

---

## docs/INDEX.md

Documentation directory index linking to all docs:

```markdown
# Notion-WP Documentation

## For WordPress Users
- [Installation & Setup](../README.md#installation)
- [Getting Started Guide](../README.md#getting-started)
- [Configuration](../README.md#configuration)
- [Troubleshooting](../README.md#troubleshooting)

## For Developers
- [Development Setup](development/DEVELOPMENT.md)
- [Architecture Guide](development/ARCHITECTURE.md)
- [Creating Block Converters](development/BLOCK_CONVERTERS.md)
- [Testing Guide](development/TESTING.md)
- [REST API Reference](development/API.md)
- [Hooks & Filters](development/HOOKS.md)

## Technical Documentation
- [Project Structure](architecture/project-structure.md)
- [Git Worktrees](architecture/worktrees.md)
- [Product Requirements](product/prd.md)
- [Technical Requirements](requirements/requirements.md)

## Development Plans
- [Main Plan](plans/main-plan.md)
- [Phase 5: Hierarchy & Navigation](plans/phase-5-hierarchy-navigation.md)
- [Phase 5: Remaining Work](plans/phase-5-remaining-work.md)
```

---

## Screenshot Plan (Playwright)

### Screenshots to Capture

1. **settings-connection.png**
   - Settings page with API token field
   - "Connect to Notion" button
   - Connection status indicator

2. **settings-page-selection.png**
   - Available Notion pages list
   - Checkboxes for selection
   - Sync button

3. **sync-dashboard.png**
   - Sync status overview
   - Recent syncs list
   - Manual sync controls

4. **database-table-view.png**
   - Database view block in post
   - Table with data
   - Filter/sort controls

5. **editor-database-block.png**
   - Block editor with database view block
   - Block settings sidebar
   - Preview in editor

6. **published-hierarchy.png**
   - Published page showing nested structure
   - Breadcrumbs
   - Child pages list

7. **menu-generation.png**
   - WordPress Menus screen
   - Auto-generated Notion menu
   - Menu location assignment

### Playwright Script Structure

```javascript
// screenshots/capture-all.js
const { chromium } = require('playwright');

async function captureScreenshots() {
  const browser = await chromium.launch();
  const context = await browser.newContext({
    viewport: { width: 1200, height: 800 }
  });
  const page = await context.newPage();

  // Login to WordPress admin
  await page.goto('http://localhost:8080/wp-admin');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'admin');
  await page.click('#wp-submit');

  // Capture settings page
  await page.goto('http://localhost:8080/wp-admin/admin.php?page=notion-sync-settings');
  await page.screenshot({
    path: 'docs/images/settings-connection.png',
    fullPage: false
  });

  // ... more screenshots ...

  await browser.close();
}

captureScreenshots();
```

### Screenshot Requirements
- 1200px max width
- PNG format
- Compress with imageoptim or similar
- Show realistic sample content (not "Test Page 1", "Test Page 2")
- Professional appearance
- Consistent WordPress theme/admin style

---

## Implementation Checklist

### Phase 1: Content Migration
- [ ] Extract user content from README
- [ ] Extract developer content from README
- [ ] Create new user-focused README structure
- [ ] Create DEVELOPMENT.md with developer content
- [ ] Update internal links

### Phase 2: New Documentation
- [ ] Create ARCHITECTURE.md
- [ ] Create BLOCK_CONVERTERS.md
- [ ] Create TESTING.md
- [ ] Create API.md
- [ ] Create HOOKS.md
- [ ] Create INDEX.md

### Phase 3: Screenshots
- [ ] Set up Playwright test environment
- [ ] Create screenshot capture script
- [ ] Create docs/images/ directory
- [ ] Capture all planned screenshots
- [ ] Optimize images for web
- [ ] Update documentation with screenshot references

### Phase 4: Polish
- [ ] Proofread all documentation
- [ ] Test all links
- [ ] Verify code examples work
- [ ] Get feedback from test users
- [ ] Update CLAUDE.md references

---

## Success Metrics

**For Users:**
- Non-technical user can install plugin in < 5 minutes
- User can complete first sync in < 10 minutes
- Common questions answered in FAQ
- Screenshots provide clear guidance

**For Developers:**
- Developer can set up environment in < 15 minutes
- Can create custom block converter in < 30 minutes
- All hooks/filters documented
- Architecture is understandable

**General:**
- Documentation is well-organized and navigable
- No broken links
- Screenshots are professional and current
- Content is accurate and up-to-date
