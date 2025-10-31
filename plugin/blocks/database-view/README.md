# Notion Database View Block

A Gutenberg block for embedding interactive Notion database views in WordPress posts and pages.

## Overview

The `notion-wp/database-view` block allows users to:

- Select from imported Notion databases
- Display database content in multiple view types (table, board, gallery, timeline, calendar)
- Enable/disable filtering and exporting capabilities
- Use alignment options (normal, wide, full)

## File Structure

```
database-view/
├── block.json              # Block metadata and configuration
├── render.php              # Server-side rendering template
├── src/
│   ├── index.js           # Block registration
│   ├── edit.js            # Editor interface
│   ├── editor.css         # Editor-only styles
│   └── style.css          # Frontend and editor styles
├── build/                 # Compiled assets (git-ignored)
│   ├── index.js
│   ├── index.asset.php
│   ├── editor.css
│   └── style.css
└── README.md              # This file
```

## Development

### Building the Block

```bash
# Build for production
npm run build:blocks

# Watch for development
npm run start:blocks
```

### Block Attributes

- `databaseId` (number): The WordPress post ID of the notion_database post
- `viewType` (string): View type - 'table', 'board', 'gallery', 'timeline', 'calendar'
- `showFilters` (boolean): Whether to show filter controls
- `showExport` (boolean): Whether to show export button

## PHP Class

The block is registered via `\NotionWP\Blocks\DatabaseViewBlock` class located at:
`/plugin/src/Blocks/DatabaseViewBlock.php`

### Key Methods

- `register()`: Hooks block registration and asset enqueueing
- `register_block_type()`: Registers the block with WordPress
- `render_callback()`: Server-side rendering logic
- `enqueue_editor_assets()`: Localizes data for the editor
- `enqueue_frontend_assets()`: Loads Tabulator and frontend scripts

## Usage in Editor

1. Add the "Notion Database View" block to a post or page
2. Select a database from the dropdown
3. Configure view type and display options in the sidebar
4. Preview updates automatically via ServerSideRender

## Frontend Rendering

The block renders:
- Database title header
- Optional export button
- Optional filter controls (populated by JavaScript)
- Tabulator table container
- Loading and error states

## Integration Points

### REST API
The block expects a REST endpoint at `/wp-json/notion-wp/v1/databases/{id}/data` to fetch:
- Column definitions
- Row data
- Metadata

### Tabulator
The frontend uses Tabulator 6.3.0 for interactive tables:
- Column sorting
- Filtering
- CSV export
- Responsive layouts

## Future Enhancements

- Board view (Kanban-style)
- Gallery view (card grid)
- Timeline view (chronological)
- Calendar view (event-based)
- Custom column visibility
- Advanced filter UI
- Pagination controls

## Related Files

- **Registration**: `/plugin/notion-sync.php` (line 116-118)
- **Database CPT**: `/plugin/src/Database/DatabasePostType.php`
- **Frontend JS**: `/plugin/blocks/database-view/build/frontend.js` (to be created)
