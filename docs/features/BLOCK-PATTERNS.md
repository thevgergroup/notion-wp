# Block Patterns for Notion Sync

**Version:** 1.0.0+
**Compatibility:** WordPress 6.0+, Twenty Twenty-Four, Twenty Twenty-Five

---

## Overview

Notion Sync provides pre-configured block patterns that make it easy to display your Notion content on your WordPress site. These patterns are specifically designed for modern block themes like Twenty Twenty-Four and Twenty Twenty-Five.

---

## Available Patterns

### Notion Navigation Hierarchy

**Purpose:** Display your synced Notion pages as a hierarchical navigation menu.

**Best For:**
- Sidebars
- Documentation sites
- Knowledge bases
- Nested page structures

**Pattern Details:**
- **Category:** Notion Sync
- **Keywords:** notion, navigation, menu, sidebar, hierarchy
- **Compatible With:** Twenty Twenty-Four, Twenty Twenty-Five, all block themes

---

## How to Use Patterns

### Method 1: Block Inserter (Recommended)

1. **Open the Block Editor**
   - Create or edit any page/post

2. **Open Pattern Library**
   - Click the `+` (Add block) button
   - Go to the "Patterns" tab
   - Look for the "Notion Sync" category

3. **Insert Pattern**
   - Click "Notion Navigation Hierarchy"
   - The pattern will be inserted at your cursor

4. **Customize (Optional)**
   - Change heading text
   - Adjust spacing
   - Modify colors to match your theme

### Method 2: Site Editor (For Block Themes)

1. **Open Site Editor**
   - Go to Appearance → Editor

2. **Edit Template**
   - Choose the template you want to edit (e.g., Page, Single Post)
   - Or edit a template part (e.g., Sidebar)

3. **Add Pattern**
   - Click `+` to add a block
   - Go to Patterns → Notion Sync
   - Insert "Notion Navigation Hierarchy"

4. **Save Template**
   - Click Save to apply changes site-wide

### Method 3: Widget Area (Legacy Themes)

For classic themes with widget areas:

1. **Go to Widgets**
   - Navigate to Appearance → Widgets

2. **Add Block Widget**
   - Add a "Block" widget to your sidebar

3. **Insert Pattern**
   - Inside the block widget, click `+`
   - Go to Patterns → Notion Sync
   - Insert "Notion Navigation Hierarchy"

---

## Pattern Structure

### Notion Navigation Hierarchy Pattern

The pattern consists of:

```
┌─────────────────────────┐
│  Notion Pages (Heading) │  ← H3 heading
├─────────────────────────┤
│  • Page 1               │
│    • Child Page 1.1     │  ← Navigation block
│    • Child Page 1.2     │     (hierarchical)
│  • Page 2               │
│  • Page 3               │
└─────────────────────────┘
```

**Block Composition:**
1. **Group Block** - Container with constrained layout
2. **Heading Block** - "Notion Pages" title (H3, medium font)
3. **Navigation Block** - Displays the Notion menu with hierarchy

**Navigation Block Settings:**
- **Layout:** Vertical flex layout
- **Overlay Menu:** Disabled (never shows)
- **Menu Reference:** Points to your Notion Navigation menu
- **Spacing:** 0.5rem between items

---

## Customization Options

### Change the Heading

1. Click on the "Notion Pages" heading
2. Type your custom text (e.g., "Documentation", "Knowledge Base")
3. Change heading level if needed (H2-H6)

### Adjust Spacing

1. Select the Navigation block
2. In the sidebar, go to "Styles" → "Spacing"
3. Modify block gap (default: 0.5rem)
4. Adjust padding/margin as needed

### Change Colors

1. Select the Group block (outer container)
2. Go to "Styles" → "Color"
3. Set background color, text color, or link color

### Modify Layout

1. Select the Group block
2. Go to "Settings" → "Layout"
3. Change from "Constrained" to "Full width" if needed

---

## Theme Compatibility

### Twenty Twenty-Four

**Status:** ✅ Fully Compatible

**Recommended Placement:**
- Sidebar template part
- Page template
- Footer navigation

**Styling:**
- Uses theme's default link colors
- Respects theme spacing scale
- Matches navigation block styling

### Twenty Twenty-Five

**Status:** ✅ Fully Compatible

**Recommended Placement:**
- Same as Twenty Twenty-Four
- Works great in column layouts

**Styling:**
- Inherits theme typography
- Supports theme color palette

### Classic Themes

**Status:** ⚠️ Limited Compatibility

**Notes:**
- Pattern works but requires manual styling
- Use in widget areas or post content
- May need custom CSS for optimal display

---

## Technical Details

### Block Pattern Registration

Patterns are registered in `plugin/src/Blocks/Patterns.php`

**Key Features:**
- Dynamic menu reference (automatically uses your Notion menu)
- Only registers if Notion menu exists
- Supports localization (all text translatable)

### Menu Detection

The pattern automatically detects your Notion menu:

```php
$menu_name = get_option( 'notion_sync_menu_name', 'Notion Navigation' );
$menu = wp_get_nav_menu_object( $menu_name );
```

**Requirements:**
- Notion menu must exist (created by menu sync)
- Menu must have at least one item
- Menu name matches setting (default: "Notion Navigation")

---

## Troubleshooting

### Pattern Not Showing

**Cause:** Notion menu doesn't exist yet

**Solution:**
1. Go to Notion Sync settings
2. Click "Sync Menu Now"
3. Wait for menu creation
4. Refresh the pattern library

### Pattern Is Empty

**Cause:** No pages synced from Notion

**Solution:**
1. Sync some pages from Notion first
2. Run menu sync again
3. The pattern will populate with your pages

### Navigation Not Hierarchical

**Cause:** Pages don't have parent-child relationships

**Solution:**
1. In Notion, organize pages as sub-pages
2. Re-sync the pages
3. Re-sync the menu
4. Hierarchy will appear in navigation

### Styling Doesn't Match Theme

**Cause:** Theme doesn't fully support Navigation block

**Solution:**
1. Switch to a block theme (Twenty Twenty-Four recommended)
2. Or add custom CSS to your theme
3. Target `.wp-block-navigation` for styling

---

## Advanced Usage

### Multiple Instances

You can insert the pattern multiple times:
- Different pages/templates
- Different sidebars
- Each instance is independent

**Tip:** Customize each instance differently (colors, headings, spacing)

### Custom Categories

To add patterns to additional categories, modify the registration:

```php
'categories' => array( 'notion-sync', 'featured', 'your-custom-category' ),
```

### Programmatic Insertion

Insert the pattern via code:

```php
echo do_blocks( '<!-- wp:pattern {"slug":"notion-sync/navigation-hierarchy"} /-->' );
```

---

## Upcoming Patterns

**Planned for Future Versions:**

1. **Notion Database Grid** - Display database entries as cards
2. **Notion Recent Posts** - Show recently synced pages
3. **Notion Breadcrumbs** - Page hierarchy breadcrumb trail
4. **Notion Search** - Search widget for synced content

---

## Support

### Pattern Issues

If you encounter issues with patterns:

1. **Check Requirements:**
   - WordPress 6.0+
   - Notion menu exists
   - Pages synced from Notion

2. **Enable Debug Mode:**
   - Add to wp-config.php: `define( 'WP_DEBUG', true );`
   - Check for PHP errors

3. **Get Help:**
   - GitHub Issues: https://github.com/thevgergroup/notion-wp/issues
   - WordPress.org Support: (coming soon)

---

**Last Updated:** 2025-11-02
**Plugin Version:** 1.0.0
