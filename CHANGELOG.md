# Changelog

All notable changes to the Notion Sync plugin will be documented in this file.

## [Unreleased] - Phase 3: Media Handling

### Added

- **Media Import System**: Download images from Notion S3 URLs to WordPress Media Library
- **TIFF Image Handling**: Skip unsupported TIFF format, link to original URL, log as warning
- **Image Duplicate Detection**: MediaRegistry prevents re-downloading existing images using block IDs
- **Sync Logging System**: Comprehensive sync log tracking with admin UI and WP-CLI commands
- **Action Scheduler Reliability Improvements**:
    - Force WP Cron runner instead of unreliable async requests
    - Increase timeout from 5 minutes to 10 minutes for image-heavy pages
    - Automatic retry logic for failed sync actions
    - New `wp notion scheduler_status` command to monitor configuration
- **WP-CLI Commands**:
    - `wp notion logs` - View and manage sync logs with filtering
    - `wp notion logs --stats` - Display sync log statistics
    - `wp notion scheduler_status` - Check Action Scheduler configuration

### Changed

- **Image Blocks**: External/unsupported images now use `wp:html` blocks instead of `wp:image` to avoid Gutenberg validation errors
- **composer.json**: Updated Action Scheduler requirement from ^3.7 to ^3.9

### Fixed

- Gutenberg block validation errors for external images (TIFF, Unsplash, Giphy)
- Action Scheduler async request timeout failures during bulk sync operations
- TIFF image conversion infinite retry loops

### Performance

- Image download time: ~0.66s per image (4 images in 2.64s)
- Page sync with 4 images: 4.4 seconds (previously 18+ seconds with TIFF conversion attempts)
- Bulk sync: 18 of 19 pages completed successfully in testing

### Known Issues

- Action Scheduler version 3.7.4 requires manual update to 3.9.3 (composer not available in Docker)
- One action timeout during bulk sync testing (mitigated by automatic retry system)

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
