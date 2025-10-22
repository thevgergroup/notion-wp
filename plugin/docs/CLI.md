# WP-CLI Commands for Notion Sync

The Notion Sync plugin provides comprehensive WP-CLI commands for managing synchronization between Notion and WordPress. All commands reuse existing plugin functionality and follow WP-CLI best practices.

## Installation

The CLI commands are automatically available when the plugin is activated and you have WP-CLI installed.

## Command Overview

```bash
wp notion list              # List Notion pages and databases
wp notion sync              # Sync a page or database to WordPress
wp notion show              # Show details for a Notion page
wp notion show-database     # Show details for a Notion database
wp notion links             # Show Notion links in a WordPress post
wp notion registry          # View link registry entries
wp notion test-link         # Test link rewriting
```

## Commands Reference

### `wp notion list`

List accessible Notion pages and databases.

**Options:**

- `--type=<type>` - Filter by type: `page` or `database`
- `--limit=<limit>` - Maximum number of items (default: 10, max: 100)
- `--format=<format>` - Output format: `table`, `csv`, `json`, or `yaml` (default: `table`)

**Examples:**

```bash
# List all accessible resources (pages and databases)
wp notion list

# List only pages
wp notion list --type=page --limit=20

# List databases in JSON format
wp notion list --type=database --format=json

# Export pages to CSV
wp notion list --type=page --limit=100 --format=csv > pages.csv
```

**Output:**

```
+----------+------------------+------------------------+---------------------+----------+
| Type     | ID               | Title                  | Last Edited         | Parent   |
+----------+------------------+------------------------+---------------------+----------+
| Page     | 75424b1c35d0... | Getting Started Guide  | 2025-01-15 14:23:10 | page     |
| Database | abc123def456... | Product Database       | 2025-01-14 09:15:42 | N/A      |
+----------+------------------+------------------------+---------------------+----------+
```

---

### `wp notion sync`

Sync a Notion page or database to WordPress.

**Arguments:**

- `<notion-id>` - Notion page or database ID (with or without dashes)

**Options:**

- `--force` - Force re-sync even if already synced
- `--batch-size=<size>` - For databases: entries per batch (default: 20)

**Examples:**

```bash
# Sync a page
wp notion sync 75424b1c35d0476b836cbb0e776f3f7c

# Sync a database with custom batch size
wp notion sync abc123def456 --batch-size=50

# Force re-sync an already synced page
wp notion sync 75424b1c35d0476b836cbb0e776f3f7c --force
```

**Behavior:**

- **Pages**: Creates or updates a WordPress post with the page content
- **Databases**: Queues entries for background processing via Action Scheduler
- Automatically detects whether the ID is a page or database
- Shows progress and provides post/batch IDs on success
- Registers links in the link registry for internal link routing

**Output (Page):**

```
Detecting resource type for 75424b1c35d0476b836cbb0e776f3f7c...
Syncing page 75424b1c35d0476b836cbb0e776f3f7c...
Success: Page synced successfully! WordPress post ID: 123
View post: https://example.com/getting-started-guide/
```

**Output (Database):**

```
Detecting resource type for abc123def456...
Syncing database abc123def456...
Success: Database sync queued! Batch ID: batch_a1b2c3d4e5
Use "wp notion batch-status batch_a1b2c3d4e5" to check progress.

Background processing will handle the sync via Action Scheduler.
```

---

### `wp notion show`

Show detailed information for a Notion page.

**Arguments:**

- `<notion-id>` - Notion page ID (with or without dashes)

**Options:**

- `--blocks` - Also display block structure

**Examples:**

```bash
# Show page properties and sync status
wp notion show 75424b1c35d0476b836cbb0e776f3f7c

# Show page with block structure
wp notion show 75424b1c35d0476b836cbb0e776f3f7c --blocks
```

**Output:**

```
Fetching page 75424b1c35d0476b836cbb0e776f3f7c...
Page Properties:
  ID:            75424b1c-35d0-476b-836c-bb0e776f3f7c
  Title:         Getting Started Guide
  Created:       2025-01-10 08:30:00
  Last Edited:   2025-01-15 14:23:10
  URL:           https://notion.so/75424b1c35d0476b836cbb0e776f3f7c

Sync Status:
  Synced:        Yes
  WP Post ID:    123
  Last Synced:   2025-01-15 14:25:00
  WP URL:        https://example.com/getting-started-guide/

Page Blocks:
  Total blocks: 15
  [1] heading_1
  [2] paragraph
  [3] bulleted_list_item
  [4] bulleted_list_item
  [5] image
  ...
```

---

### `wp notion show-database`

Show detailed information for a Notion database including schema and sample rows.

**Arguments:**

- `<notion-id>` - Notion database ID (with or without dashes)

**Options:**

- `--limit=<limit>` - Number of sample rows to display (default: 10)
- `--format=<format>` - Output format: `table`, `csv`, `json`, or `yaml` (default: `table`)

**Examples:**

```bash
# Show database with default sample size
wp notion show-database abc123def456

# Show first 5 rows in JSON format
wp notion show-database abc123def456 --limit=5 --format=json
```

**Output:**

```
Fetching database abc123def456...
Database Information:
  ID:            abc123de-f456-7890-abcd-ef1234567890
  Title:         Product Database
  Last Edited:   2025-01-14 09:15:42

Properties (Columns):
  - Name (title)
  - Status (select)
  - Price (number)
  - Launch Date (date)
  - Tags (multi_select)

Sample Rows (showing first 10):
+-------------+---------------------+------------+
| ID          | Created             | Properties |
+-------------+---------------------+------------+
| 12345678... | 2025-01-10 12:00:00 | 5          |
| 23456789... | 2025-01-09 15:30:00 | 5          |
+-------------+---------------------+------------+

Total entries in database: 47
```

---

### `wp notion links`

Show Notion internal links found in a WordPress post's content.

**Arguments:**

- `<post-id>` - WordPress post ID

**Examples:**

```bash
# Check links in post 123
wp notion links 123
```

**Output:**

```
Post Information:
  ID:      123
  Title:   Getting Started Guide
  Type:    post

Links Found (3):

  Notion Link:
    Original URL:    /75424b1c35d0476b836cbb0e776f3f7c
    Notion ID:       75424b1c35d0476b836cbb0e776f3f7c
    Rewritten URL:   https://example.com/notion/getting-started-guide
    Registry Status: Registered
    Slug:            getting-started-guide
    Sync Status:     synced
    WP Post ID:      123
    WP URL:          https://example.com/getting-started-guide/

  Notion Link:
    Original URL:    /abc123def456
    Notion ID:       abc123def456
    Rewritten URL:   https://example.com/notion/product-database
    Registry Status: Registered
    Slug:            product-database
    Sync Status:     not_synced
```

**Use Cases:**

- Audit which Notion pages are linked from WordPress content
- Identify broken links to pages that haven't been synced yet
- Verify link registry entries
- Troubleshoot link rewriting issues

---

### `wp notion registry`

View and search link registry entries.

**Options:**

- `--notion-id=<id>` - Filter by specific Notion ID
- `--sync-status=<status>` - Filter by status: `synced` or `not_synced`
- `--format=<format>` - Output format: `table`, `csv`, `json`, or `yaml` (default: `table`)

**Examples:**

```bash
# View all registry entries
wp notion registry

# Find specific Notion ID
wp notion registry --notion-id=75424b1c35d0476b836cbb0e776f3f7c

# Show only synced items
wp notion registry --sync-status=synced

# Export registry to JSON
wp notion registry --format=json > registry-backup.json
```

**Output:**

```
+--------------+------------------------+----------+------------------------+-----------+------------+----------+
| Notion ID    | Title                  | Type     | Slug                   | Status    | WP Post ID | Accessed |
+--------------+------------------------+----------+------------------------+-----------+------------+----------+
| 75424b1c3... | Getting Started Guide  | page     | getting-started-guide  | synced    | 123        | 15       |
| abc123def... | Product Database       | database | product-database       | synced    | 456        | 8        |
| 11223344f... | Troubleshooting        | page     | troubleshooting        | not_synced| N/A        | 0        |
+--------------+------------------------+----------+------------------------+-----------+------------+----------+

Total entries: 3
```

**Use Cases:**

- Audit all registered Notion resources
- Find WordPress post IDs for specific Notion pages
- Track which links have been accessed
- Export registry for backup or analysis

---

### `wp notion test-link`

Test link rewriting for a URL to see how it will be transformed.

**Arguments:**

- `<url>` - URL to test (Notion internal link format)

**Examples:**

```bash
# Test a Notion internal link
wp notion test-link "/75424b1c35d0476b836cbb0e776f3f7c"

# Test a full Notion URL
wp notion test-link "https://notion.so/abc123def456"

# Test a regular URL (should not be rewritten)
wp notion test-link "https://google.com"
```

**Output (Notion Link):**

```
Link Rewriting Test
  Original URL:    /75424b1c35d0476b836cbb0e776f3f7c
  Detection:       Notion Internal Link
  Notion ID:       75424b1c35d0476b836cbb0e776f3f7c
  Rewritten URL:   https://example.com/notion/getting-started-guide

Registry Status:
  Registered:      Yes
  Title:           Getting Started Guide
  Type:            page
  Slug:            getting-started-guide
  Sync Status:     synced
  WP Post ID:      123
  WP URL:          https://example.com/getting-started-guide/
```

**Output (Non-Notion Link):**

```
Link Rewriting Test
  Original URL:    https://google.com
  Detection:       Not a Notion Internal Link
  Rewritten URL:   https://google.com (unchanged)
```

**Use Cases:**

- Debug link rewriting behavior
- Verify registry lookups
- Test different URL formats
- Understand how internal links will be transformed

---

## Advanced Usage

### Batch Operations

Sync multiple pages in a loop:

```bash
# Sync multiple pages from a list
for page_id in 75424b1c35d0476b836cbb0e776f3f7c abc123def456; do
  wp notion sync "$page_id"
done
```

### Export and Import

Export registry for backup:

```bash
wp notion registry --format=json > notion-registry-backup.json
```

### Integration with Other Commands

Use with standard WP-CLI commands:

```bash
# Get all posts synced from Notion
wp post list --meta_key=notion_page_id --format=table

# Delete a synced post
wp post delete 123

# Update post content after re-sync
wp notion sync 75424b1c35d0476b836cbb0e776f3f7c --force
```

### Automation Scripts

Create a cron job to sync specific pages daily:

```bash
#!/bin/bash
# sync-notion-pages.sh

# List of important pages to keep in sync
PAGES=(
  "75424b1c35d0476b836cbb0e776f3f7c"
  "abc123def456"
)

for page_id in "${PAGES[@]}"; do
  echo "Syncing $page_id..."
  wp notion sync "$page_id" --force
done

echo "Sync complete!"
```

### Monitoring and Reporting

Generate a sync status report:

```bash
#!/bin/bash
# notion-status-report.sh

echo "=== Notion Sync Status Report ==="
echo ""
echo "Registry Entries:"
wp notion registry --format=table
echo ""
echo "Synced Pages:"
wp notion registry --sync-status=synced --format=table
echo ""
echo "Unsynced Pages:"
wp notion registry --sync-status=not_synced --format=table
```

---

## Error Handling

All commands provide clear error messages:

```bash
$ wp notion sync invalid-id
Error: Unable to determine resource type. Please check the Notion ID and integration access.

$ wp notion show 75424b1c35d0476b836cbb0e776f3f7c
Error: Page not found or integration lacks access.

$ wp notion links 999999
Error: Post 999999 not found.
```

---

## Performance Considerations

### Large Databases

When syncing large databases, use appropriate batch sizes:

```bash
# For databases with 1000+ entries, use larger batches
wp notion sync large-database-id --batch-size=50
```

### API Rate Limits

Notion API has rate limits (~50 requests/second). The plugin handles this automatically, but for bulk operations:

- Sync databases use background processing
- Add delays between manual syncs if needed
- Monitor Action Scheduler queue: `wp action-scheduler`

### Memory Usage

For pages with many blocks or large databases:

- Background processing prevents PHP timeouts
- Batch sizes are configurable
- Progress can be monitored via WP-CLI

---

## Troubleshooting

### Command Not Found

If `wp notion` returns command not found:

1. Verify plugin is activated: `wp plugin list`
2. Check WP-CLI version: `wp cli version` (requires 2.0+)
3. Verify autoloader: Check `/vendor/autoload.php` exists

### Authentication Errors

If you get authentication errors:

1. Check token is configured: `wp option get notion_wp_token`
2. Test connection: `wp notion list --limit=1`
3. Verify integration permissions in Notion

### Sync Failures

If syncs fail:

1. Use `wp notion show <id>` to verify access
2. Check error logs: `wp option get notion_wp_workspace_info`
3. Try with `--force` flag
4. Verify integration has page/database access in Notion

---

## Architecture Notes

### Code Reuse

All CLI commands reuse existing plugin functionality:

- `NotionCommand::list()` → uses `ContentFetcher::fetch_pages_list()` and `DatabaseFetcher::get_databases()`
- `NotionCommand::sync()` → uses `SyncManager::sync_page()` or `BatchProcessor::queue_database_sync()`
- `NotionCommand::show()` → uses `ContentFetcher::fetch_page_properties()` and `ContentFetcher::fetch_page_blocks()`
- `NotionCommand::registry()` → direct database queries on `wp_notion_links` table
- `NotionCommand::test_link()` → uses `LinkRewriter::rewrite_url()`

No functionality is duplicated - CLI commands are thin wrappers around existing classes.

### Background Processing

Database syncs use Action Scheduler:

```bash
# Queue a database sync
wp notion sync database-id

# Monitor Action Scheduler queue
wp action-scheduler

# View scheduled actions
wp db query "SELECT * FROM wp_actionscheduler_actions WHERE hook = 'notion_sync_process_batch'"
```

---

## Related WP-CLI Commands

### Action Scheduler

Monitor background jobs:

```bash
# List pending actions
wp action-scheduler list --status=pending

# Run pending actions now (for testing)
wp action-scheduler run

# Clean completed actions
wp action-scheduler clean
```

### Database Management

Work with synced posts:

```bash
# List all posts synced from Notion
wp post list --meta_key=notion_page_id --fields=ID,post_title,post_status

# Get meta for a specific post
wp post meta list 123

# Delete all Notion synced posts (use with caution!)
wp post list --meta_key=notion_page_id --format=ids | xargs wp post delete
```

---

## Support

For issues or feature requests:

- GitHub: https://github.com/thevgergroup/notion-wp
- Documentation: See `/docs/` directory
- WP-CLI Docs: https://wp-cli.org/
