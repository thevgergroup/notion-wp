# Usage Guide

Learn how to sync pages, databases, and manage your Notion content in WordPress.

---

## Syncing Pages

### Manual Sync

1. Go to **Settings → Notion Sync**
2. Select pages to sync
3. Click **Sync Now**

### What Gets Synced

- Page title
- Page content (all supported block types)
- Images and media files
- Parent-child relationships
- Page hierarchy

### Sync Frequency

- Currently manual sync only
- Automatic scheduled sync coming soon
- Real-time webhook sync coming soon (Notion paid plans)

---

## Syncing Databases

### Display Notion Databases

1. Sync a page that contains a database
2. The database appears as an interactive table
3. Users can filter, sort, and search
4. Export to CSV available

### Current Support

- Table view with filters and sorting
- Board, gallery, timeline, calendar views coming soon

---

## Embedding Database Views

### In the Block Editor

1. Add a new block
2. Search for "Notion Database"
3. Select your database
4. Configure display options
5. Publish!

### On the Frontend

- Interactive tables with live filtering
- Sorting by any column
- Search across all fields
- Export to CSV

---

## Managing Menus

### Auto-Generated Menus

- Created automatically from Notion hierarchy
- Updates on each sync
- Maintains nesting up to 3 levels deep

### Manual Menu Items

- Add custom items to the Notion menu
- Plugin preserves your manual additions
- Mix Notion pages with custom links

### Assigning Menus

1. **Appearance → Menus**
2. Select **Notion Pages** menu
3. Assign to a menu location
4. Save

---

## Displaying Sidebar Navigation

**Use Block Patterns for Easy Setup:**

The plugin includes ready-to-use block patterns that display your Notion pages as a collapsible hierarchical sidebar navigation - perfect for documentation sites, knowledge bases, or any site with nested content.

**Quick Start:**
1. Edit any page/post or template in the Site Editor
2. Click **`+`** → **Patterns** → **"Notion Sync"**
3. Insert **"Notion Navigation Hierarchy"**
4. Done! Your navigation sidebar appears with collapsible sections

**Features:**
- Collapsible sections with animated chevron icons
- Mobile-friendly responsive design
- Accessible with proper ARIA attributes
- Customizable colors, spacing, and headings
- Works with Twenty Twenty-Four & Twenty Twenty-Five

**[Complete Pattern Documentation →](features/BLOCK-PATTERNS.md)**

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

## Next Steps

- [FAQ](FAQ.md) - Common questions and troubleshooting
- [Getting Started](GETTING-STARTED.md) - Initial setup guide
- [Block Patterns Guide](features/BLOCK-PATTERNS.md) - Sidebar navigation patterns

---

**Need help?** See our [Support](https://github.com/thevgergroup/notion-wp/issues) page.
