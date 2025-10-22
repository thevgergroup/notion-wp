# WP-CLI Commands for Notion Sync

Command-line interface for managing Notion synchronization with WordPress.

## Quick Start

```bash
# List available pages and databases
wp notion list

# Sync a page to WordPress
wp notion sync 75424b1c35d0476b836cbb0e776f3f7c

# Check page status
wp notion show 75424b1c35d0476b836cbb0e776f3f7c

# View all synced items
wp notion registry --sync-status=synced
```

## Available Commands

| Command                        | Purpose                               |
| ------------------------------ | ------------------------------------- |
| `wp notion list`               | List Notion pages and databases       |
| `wp notion sync <id>`          | Sync a page or database to WordPress  |
| `wp notion show <id>`          | Show details for a Notion page        |
| `wp notion show-database <id>` | Show database schema and sample rows  |
| `wp notion links <post-id>`    | Show Notion links in a WordPress post |
| `wp notion registry`           | View link registry entries            |
| `wp notion test-link <url>`    | Test link rewriting transformation    |

## Documentation

### For Users

- **[Quick Start Guide](CLI-QUICK-START.md)** - Get started quickly with common commands
- **[Complete Reference](CLI.md)** - Full documentation with all options and examples

### For Developers

- **[Architecture Documentation](CLI-ARCHITECTURE.md)** - How CLI commands reuse existing code
- **[Implementation Summary](CLI-IMPLEMENTATION-SUMMARY.md)** - Detailed implementation overview

## Installation

The CLI commands are automatically available when:

1. ✅ WP-CLI is installed (`wp --version`)
2. ✅ Plugin is activated (`wp plugin activate notion-sync`)
3. ✅ Notion token is configured (Settings > Notion Sync)

No additional setup required.

## Common Use Cases

### Content Management

```bash
# Import a page from Notion
wp notion sync <page-id>

# Update existing content
wp notion sync <page-id> --force

# Check what's available to sync
wp notion list --type=page --limit=20
```

### Database Synchronization

```bash
# Sync a database (background processing)
wp notion sync <database-id>

# View database structure
wp notion show-database <database-id>

# Export database rows
wp notion show-database <database-id> --limit=100 --format=csv > data.csv
```

### Link Management

```bash
# Check links in a post
wp notion links 123

# View all registered links
wp notion registry

# Test how a link will be rewritten
wp notion test-link "/75424b1c35d0476b836cbb0e776f3f7c"
```

### Automation

```bash
# Daily sync script
#!/bin/bash
PAGES=("page-id-1" "page-id-2")
for id in "${PAGES[@]}"; do
  wp notion sync "$id" --force
done
```

## Output Formats

All list commands support multiple formats:

```bash
# Human-readable table
wp notion list --format=table

# Export to CSV
wp notion list --format=csv > pages.csv

# Get JSON for scripts
wp notion list --format=json

# YAML format
wp notion list --format=yaml
```

## Help

View help for any command:

```bash
# General help
wp help notion

# Command-specific help
wp help notion sync
wp help notion list
```

## Architecture

The CLI implementation:

- ✅ **Reuses ~90% of existing code** - zero duplication of business logic
- ✅ **Follows WP-CLI best practices** - standard command structure
- ✅ **Thin presentation layer** - all logic delegated to existing classes
- ✅ **Comprehensive error handling** - clear, actionable messages

See [Architecture Documentation](CLI-ARCHITECTURE.md) for details.

## Examples

### Daily Workflow

```bash
# Morning routine
wp notion list --type=page --limit=10
wp notion sync <page-id-1>
wp notion sync <page-id-2> --force
wp notion registry --sync-status=synced
```

### Debugging

```bash
# Check page access
wp notion show <page-id>

# Test link transformation
wp notion test-link "<notion-url>"

# View registry
wp notion registry --notion-id=<id>
```

### Data Export

```bash
# Export all synced pages
wp notion registry --sync-status=synced --format=json > backup.json

# Export database rows
wp notion show-database <id> --limit=100 --format=csv > data.csv

# Export page list
wp notion list --type=page --limit=100 --format=json > pages.json
```

## Support

- **Documentation:** See files in `/docs/` directory
- **GitHub:** https://github.com/thevgergroup/notion-wp
- **WP-CLI Docs:** https://wp-cli.org/

## Next Steps

1. **Read the Quick Start:** [CLI-QUICK-START.md](CLI-QUICK-START.md)
2. **Browse examples:** [CLI.md](CLI.md)
3. **Understand architecture:** [CLI-ARCHITECTURE.md](CLI-ARCHITECTURE.md)
4. **Try commands:** `wp notion --help`
