# Changelog

All notable changes to the Notion Sync plugin will be documented in this file.

## [1.0.0] - 2025-11-02

**Initial Public Release**

This is the first stable release of Notion Sync for WordPress. All core features are production-ready.

### Features Included

- **Page Sync** - Import Notion pages to WordPress with one click
- **Rich Content** - Support for 18+ Notion block types (paragraphs, headings, lists, images, tables, code, callouts, toggles, quotes, embeds, columns, dividers)
- **Media Handling** - Automatic image download to WordPress Media Library with TIFF → PNG conversion
- **Page Hierarchy** - Maintain parent-child page relationships from Notion
- **Navigation Menus** - Auto-generate WordPress menus from Notion page structure
- **Database Views** - Display Notion databases as interactive, filterable tables
- **Link Resolution** - Convert Notion internal links to WordPress permalinks
- **Background Processing** - Handle large imports without timeouts using Action Scheduler
- **Sync Logging** - Comprehensive sync log tracking with admin UI
- **WP-CLI Support** - Command-line tools for automation (`wp notion logs`, `wp notion scheduler_status`)
- **MediaRegistry** - Duplicate prevention for images and files
- **Extensibility** - Clean block converter architecture for future block types

### Technical Achievements

- 261 PHPUnit tests with 641 assertions
- PHPCS and PHPStan code quality standards
- GPL-3.0+ licensed
- WordPress Coding Standards compliant
- GitHub Actions CI/CD with automated testing

### Known Limitations (Coming in Future Releases)

- Board, gallery, timeline, and calendar database views (table view only in v1.0)
- WordPress → Notion bi-directional sync (Notion → WordPress only)
- Scheduled automatic syncs (manual sync only)
- Real-time webhook sync (manual triggers only)

## [0.2.0] - Phase 2: Link Registry & Public Viewer

### Added

- Link registry system for tracking Notion → WordPress URL mappings
- Public database viewer template
- Internal link rewriting from Notion URLs to WordPress permalinks
- Custom routing system for `/notion/{slug}` URLs

### Changed

- Database sync now stores entries in custom post type instead of options table
- Improved permalink structure for synced content

## [0.1.0] - Phase 1: Basic Sync

### Added

- Initial Notion API integration
- Basic page sync functionality
- Database sync with batch processing
- Block converter system (paragraphs, headings, lists, images)
- Admin settings page
- WP-CLI commands for manual sync operations
