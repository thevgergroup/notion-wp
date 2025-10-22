# WP-CLI Quick Start Guide

Quick reference for the most common Notion Sync WP-CLI commands.

## Prerequisites

1. WP-CLI installed: `wp --version`
2. Plugin activated: `wp plugin activate notion-sync`
3. Notion token configured in Settings > Notion Sync

## Most Common Commands

### List Available Notion Resources

```bash
# Quick view of what's accessible
wp notion list
```

### Sync a Page

```bash
# Sync by Notion page ID
wp notion sync 75424b1c35d0476b836cbb0e776f3f7c

# Force re-sync
wp notion sync 75424b1c35d0476b836cbb0e776f3f7c --force
```

### Check Page Status

```bash
# See if a page is synced and view details
wp notion show 75424b1c35d0476b836cbb0e776f3f7c
```

### Sync a Database

```bash
# Queues database for background sync
wp notion sync abc123def456
```

### Check Your Links

```bash
# See Notion links in a WordPress post
wp notion links 123
```

## Getting Notion IDs

Notion IDs are the long alphanumeric strings in Notion URLs:

**From Page URL:**
```
https://notion.so/Getting-Started-75424b1c35d0476b836cbb0e776f3f7c
                                 └─────────── This is the ID ────────────┘
```

**Short Format (also works):**
```bash
# With dashes
wp notion sync 75424b1c-35d0-476b-836c-bb0e776f3f7c

# Without dashes
wp notion sync 75424b1c35d0476b836cbb0e776f3f7c
```

## Common Workflows

### Initial Setup: Import Pages

```bash
# 1. See what's available
wp notion list --type=page --limit=20

# 2. Sync important pages
wp notion sync <page-id-1>
wp notion sync <page-id-2>
wp notion sync <page-id-3>

# 3. Verify they synced
wp notion registry --sync-status=synced
```

### Daily Sync: Update Content

```bash
# Re-sync specific pages with latest content
wp notion sync 75424b1c35d0476b836cbb0e776f3f7c --force
```

### Audit: Check Link Health

```bash
# 1. Check links in a post
wp notion links 123

# 2. View all registry entries
wp notion registry

# 3. Find unsynced pages that are linked
wp notion registry --sync-status=not_synced
```

### Debug: Troubleshoot Issues

```bash
# 1. Test if page is accessible
wp notion show 75424b1c35d0476b836cbb0e776f3f7c

# 2. Test link rewriting
wp notion test-link "/75424b1c35d0476b836cbb0e776f3f7c"

# 3. Check connection
wp notion list --limit=1
```

## Output Formats

All list commands support multiple formats:

```bash
# Human-readable table (default)
wp notion list --format=table

# Export to CSV
wp notion list --format=csv > notion-pages.csv

# Get JSON for scripts
wp notion list --format=json

# YAML format
wp notion list --format=yaml
```

## Scripting Examples

### Sync All Pages from a List

```bash
#!/bin/bash
# sync-pages.sh

PAGES=(
  "75424b1c35d0476b836cbb0e776f3f7c"
  "abc123def456"
  "11223344556677889900"
)

for page_id in "${PAGES[@]}"; do
  echo "Syncing $page_id..."
  wp notion sync "$page_id" --force
  echo "---"
done
```

### Export Registry

```bash
# Backup registry to JSON
wp notion registry --format=json > notion-registry-$(date +%Y%m%d).json
```

### Find and Sync Unsynced Pages

```bash
# Get IDs of unsynced pages, sync each one
wp notion registry --sync-status=not_synced --format=csv | \
  tail -n +2 | \
  cut -d',' -f1 | \
  while read id; do
    wp notion sync "$id"
  done
```

## Integration with WordPress Commands

### List Synced Posts

```bash
# Show all posts created from Notion
wp post list --meta_key=notion_page_id --format=table
```

### Get Notion ID for a Post

```bash
# Show Notion page ID for WordPress post 123
wp post meta get 123 notion_page_id
```

### Delete Synced Post

```bash
# Delete WordPress post (doesn't delete from Notion)
wp post delete 123
```

## Error Messages

### "Notion API token not configured"

**Fix:** Configure token in WordPress admin:
```bash
wp admin
# Go to Settings > Notion Sync and add your integration token
```

### "Failed to connect to Notion API"

**Fix:** Verify token is valid:
```bash
# Test connection
wp notion list --limit=1
```

### "Page not found or integration lacks access"

**Fix:** Share the page with your integration in Notion:

1. Open page in Notion
2. Click "Share" → "Add people, emails, or integrations"
3. Select your integration
4. Try sync again

### "Unable to determine resource type"

**Fix:** Check the ID format and access:
```bash
# Verify ID is correct
wp notion show <notion-id>
```

## Performance Tips

### Large Database Syncs

```bash
# Use larger batch size for better performance
wp notion sync large-database-id --batch-size=50
```

### Monitor Background Jobs

```bash
# See pending sync jobs
wp action-scheduler list --status=pending --hook=notion_sync_process_batch
```

### Speed Up Repeated Commands

Use `--skip-plugins` to load only necessary plugins:

```bash
wp notion list --skip-plugins --plugin=notion-sync
```

## Get Help

View built-in help for any command:

```bash
# General help
wp help notion

# Command-specific help
wp help notion sync
wp help notion list
wp help notion registry
```

## Next Steps

- **Full documentation:** See `/docs/CLI.md`
- **Architecture details:** See `/docs/CLI-ARCHITECTURE.md`
- **Web UI:** Use WordPress admin for visual sync interface
- **Automation:** Set up cron jobs for scheduled syncs

## Quick Troubleshooting

| Problem                      | Command to Run                   |
| ---------------------------- | -------------------------------- |
| Can't see any pages          | `wp notion list`                 |
| Page won't sync              | `wp notion show <id>`            |
| Links not working            | `wp notion links <post-id>`      |
| Want to see what's synced    | `wp notion registry`             |
| Need to test link rewriting  | `wp notion test-link "<url>"`    |
| Sync jobs stuck              | `wp action-scheduler list`       |

---

**Pro Tip:** Add `alias wpn='wp notion'` to your `.bashrc` for shorter commands:

```bash
wpn list
wpn sync <id>
wpn show <id>
```
