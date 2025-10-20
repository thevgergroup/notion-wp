# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a WordPress plugin project for bi-directional synchronization between Notion and WordPress. The plugin enables content management in Notion while publishing to WordPress, addressing gaps in existing solutions like WP Sync for Notion and Content Importer for Notion.

## Key Architecture Goals

### Primary Direction: Notion → WordPress
- Fetch content from Notion API (pages and databases)
- Convert Notion blocks to WordPress Gutenberg blocks or HTML
- Import images/media to WordPress Media Library
- Map Notion database properties to WordPress fields (title, date, categories, tags, custom fields)
- Support hierarchical page structures and generate WordPress navigation menus
- Handle internal Notion page links and convert to WordPress permalinks

### Secondary Direction: WordPress → Notion (Optional)
- Store Notion page IDs in WordPress custom fields for correspondence
- Convert WordPress content back to Notion block format
- Conflict resolution based on last-edited timestamps
- Manual push operations to avoid merge conflicts

## Critical Features to Implement

### Block Mapping System
- Extensible block converter allowing custom Notion block → WordPress block mappings
- Support for standard blocks: paragraphs, headings, lists, images, quotes, callouts, toggles, code blocks, tables
- Graceful handling of unsupported blocks (preserve as HTML or placeholder)
- Special handling for:
  - Embeds (YouTube, Twitter, etc.) → Gutenberg embed blocks
  - Images with captions and alt text
  - To-do checkboxes
  - Column layouts

### Navigation & Hierarchy
- Auto-generate WordPress navigation menus from Notion page structure
- Maintain parent/child page relationships
- Convert internal Notion links to WordPress permalinks using page ID mapping

### Sync Mechanisms
- Manual sync via admin UI button
- Scheduled polling via WP-Cron (configurable intervals)
- Webhook support for near-real-time updates (requires Notion paid plan)
- Sync strategies: Add Only, Add & Update, Full Mirror (add/update/delete)

### Media Handling
- Download images from Notion's time-limited S3 URLs
- Upload to WordPress Media Library
- Avoid duplicates on re-sync using block ID mapping
- Support for files (PDFs, docs) with similar logic

## Technical Requirements

### Authentication
- Secure storage of Notion Internal Integration Token
- Clear instructions for users to share Notion pages/databases with integration

### Field Mapping
- Admin UI for mapping Notion properties to WordPress fields
- Support for custom post types
- Optional integration with ACF (Advanced Custom Fields)
- SEO plugin support (Yoast, RankMath) for meta fields

### Performance Considerations
- Handle Notion API pagination (100 entries per query)
- Background processing for large syncs to avoid PHP timeouts
- Queue system for image imports
- Rate limit handling (Notion API: ~50 requests/second)

### Error Handling
- Retry logic for failed image imports or page fetches
- Dry run mode for testing syncs without committing changes
- Detailed logging of API calls and block processing
- Status tracking with last updated timestamps per item

## Development Commands

(Commands will be added as development infrastructure is set up)

## Known Gaps in Existing Solutions

This plugin aims to address:
- Lack of bi-directional sync
- Poor internal link conversion
- Missing navigation/menu generation
- Limited block type support (especially embeds, widgets, bookmarks)
- No real-time sync in free tools
- Expensive pricing for full-featured solutions

## Notion API Endpoints Used

- `retrieve block children` - Fetch page content blocks
- `query database` - Get database entries
- `retrieve a page` - Get page properties
- `create a page` - Create new Notion pages (for WP→Notion)
- `update page` - Update page properties
- `append/update blocks` - Modify page content
- Webhooks - Real-time change notifications

## References

- Technical Requirements: `docs/requirements/requirements.md`
- Product Research Document: `docs/product/prd.md`
- Notion API: https://developers.notion.com/reference
