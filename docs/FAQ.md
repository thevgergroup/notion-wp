# Frequently Asked Questions

Common questions and troubleshooting for Notion Sync for WordPress.

---

## What Notion content is supported?

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

---

## How often should I sync?

- **Manual sync** whenever you update content in Notion
- Syncing is safe - it won't create duplicates
- Large syncs process in the background
- Automatic scheduled sync coming soon

---

## What happens to WordPress edits?

Currently, syncing is **one-way only** (Notion → WordPress):
- WordPress edits will be overwritten on next sync
- Make all content changes in Notion
- Bi-directional sync coming in a future release

---

## Can I sync private Notion pages?

Yes! As long as:
1. The page is shared with your integration
2. Your integration has the right permissions
3. The page is in the connected workspace

The plugin respects Notion permissions:
- Private pages → Private WordPress posts
- Public pages → Public WordPress posts

---

## Does this work with page builders?

The plugin outputs standard WordPress Gutenberg blocks, which work with:
- WordPress Block Editor (Gutenberg)
- Full Site Editing themes
- Limited support for page builders (Elementor, Divi, etc.)

For page builders, content syncs as HTML that you can copy/paste into page builder modules.

---

## Can I sync multiple Notion workspaces?

Currently, one workspace per WordPress site. To sync multiple workspaces:
- Use WordPress Multisite
- Create separate integration tokens
- Native multi-workspace support coming soon

---

## How do I uninstall?

1. **Deactivate** the plugin
2. **Delete** it from the Plugins page
3. **Optional:** Delete Notion sync data
   - Go to **Settings → Notion Sync**
   - Click **Delete All Sync Data**
   - Confirm deletion

This removes all sync history but keeps your WordPress posts.

---

## My images aren't showing up!

Images process in the background to avoid timeouts:
1. Check **Settings → Notion Sync → Sync Status**
2. Look for image processing jobs
3. Refresh your page after a few minutes

For troubleshooting:
- Ensure your WordPress site can access Notion's S3 URLs
- Check PHP max_execution_time setting
- Review error logs

---

## Internal links aren't working!

Make sure both pages are synced to WordPress:
1. Sync the page you're linking FROM
2. Sync the page you're linking TO
3. Links resolve automatically after both pages sync

If still broken:
- Check **Settings → Notion Sync → Link Status**
- Look for unresolved links
- Sync missing pages

---

## Next Steps

- [Getting Started](getting-started.md) - Initial setup guide
- [Usage Guide](USAGE.md) - Learn how to use the plugin
- [Security Guide](SECURITY.md) - Security features and best practices

---

**Need help?** See our [Support](https://github.com/thevgergroup/notion-wp/issues) page.
