# Phase 5.3: Database Views - Quick Start Guide

**Read this first:** This is a 5-minute quick start. For comprehensive documentation, see [PHASE-5.3-COMPLETE.md](./PHASE-5.3-COMPLETE.md).

---

## What You Get

An interactive database table embedded in WordPress posts/pages:

- Sortable columns (click headers)
- Filterable data (header filter inputs)
- Paginated results (50 rows per page)
- Export to CSV button
- Secure REST API with caching

---

## Prerequisites

- WordPress 5.8+
- PHP 7.4+
- Node.js 18+ and npm 9+
- Notion database synced to WordPress

---

## Installation (2 minutes)

### 1. Install Dependencies

```bash
cd /path/to/notion-wp
npm install
cd plugin
composer install
```

### 2. Build Assets

```bash
cd ..  # Back to project root
npm run build:blocks
```

**Expected output:**

```
✓ Built blocks successfully
```

### 3. Verify Build

```bash
ls plugin/blocks/database-view/build/
```

**Should see:**

```
index.js
index.asset.php
editor.css
style.css
```

### 4. Activate Plugin (if needed)

```bash
wp plugin activate notion-wp
```

---

## Usage (1 minute)

### For End Users

**Add Database View Block:**

1. Edit any post/page
2. Add block: `/database` or search "Database View"
3. Select database from dropdown
4. (Optional) Toggle filters/export buttons
5. Publish

**Result:** Interactive table on frontend

### For Developers

**Programmatic Block Insertion:**

```php
<!-- wp:notion-wp/database-view {"databaseId":123,"viewType":"table"} /-->
```

**REST API Access:**

```bash
# Get schema
curl http://localhost/wp-json/notion-sync/v1/databases/123/schema

# Get rows
curl http://localhost/wp-json/notion-sync/v1/databases/123/rows?page=1&per_page=50
```

---

## Testing (1 minute)

### Verify Block Works

1. **Editor:**
   - Add block → Should see database selector
   - Select database → Should show preview

2. **Frontend:**
   - View published page → Should see interactive table
   - Click column header → Should sort
   - Type in header filter → Should filter
   - Click "Export to CSV" → Should download file

3. **API:**
   ```bash
   curl -I http://localhost/wp-json/notion-sync/v1/databases/123/schema
   ```

   **Should return:**
   ```
   HTTP/1.1 200 OK
   X-NotionWP-Cache: MISS
   X-NotionWP-Cache-Expires: 1730308800
   ```

### Run Unit Tests

```bash
cd plugin
vendor/bin/phpunit --testdox
```

**Expected:**

```
Database Rest Controller Caching
 ✓ Rows cache miss on first request
 ✓ Rows cache hit on second request
 [... 9 more tests ...]

Property Formatter
 ✓ Format null values
 ✓ Format text property
 [... 33 more tests ...]

Rich Text Converter
 ✓ Empty array returns empty string
 ✓ Plain text without annotations
 [... 17 more tests ...]
```

---

## Common Issues

### Block Not Showing

**Problem:** Block doesn't appear in inserter

**Fix:**

```bash
npm run build:blocks
# Hard refresh browser: Cmd+Shift+R (Mac) or Ctrl+Shift+R (Windows)
```

### Tabulator Not Loading

**Problem:** Table container appears but table doesn't render

**Fix:**

1. Check browser console for errors
2. Verify CDN is accessible: https://unpkg.com/tabulator-tables@6.3.0/dist/js/tabulator.min.js
3. Check network tab for failed requests

### API 403 Forbidden

**Problem:** Table shows "Unable to load database"

**Fix:**

1. Check database post status:
   ```bash
   wp post get <database-id> --field=post_status
   ```

   If "private" or "draft", only admins can view.

2. Publish database:
   ```bash
   wp post update <database-id> --post_status=publish
   ```

### Cache Not Updating

**Problem:** Database shows old data

**Fix:**

```bash
# Clear all database caches
wp transient delete --search='notion_db_*'

# Or wait for TTL expiration (30-60 minutes)
```

---

## Key Features

### Security

- WordPress permission integration (post visibility, user capabilities)
- Password-protected post support
- XSS/CSRF protection
- OWASP Top 10 compliant

### Performance

- Intelligent caching (60min schema, 30min rows, 5min admin)
- CDN asset delivery (Tabulator, Luxon)
- Remote pagination (50 rows/page)
- ~8ms response time (cached), ~150ms (uncached)

### Property Types Supported

All 21 Notion property types:

- Text types: title, rich_text, text
- Number: number (formatted: 1,234.56)
- Select: select, multi_select, status (colored badges)
- Boolean: checkbox (✓/✗)
- Date: date, created_time, last_edited_time (formatted)
- Relations: relation, rollup, formula (formatted)
- Media: files, url, email, phone_number (links)
- People: people, created_by, last_edited_by (names + avatars)

---

## Development

### Watch for Changes

```bash
npm run start:blocks
```

### Lint Code

```bash
# JavaScript
npm run lint:js

# PHP
cd plugin
composer lint
```

### Run Tests

```bash
# All tests
cd plugin
vendor/bin/phpunit

# Specific test suite
vendor/bin/phpunit tests/unit/Database/PropertyFormatterTest.php
```

---

## File Structure

```
plugin/
├── src/
│   ├── API/
│   │   └── DatabaseRestController.php     # REST API with caching
│   ├── Database/
│   │   ├── PropertyFormatter.php          # 21 property types
│   │   └── RichTextConverter.php          # Rich text → HTML
│   └── Blocks/
│       └── DatabaseViewBlock.php          # Block registration
├── blocks/
│   └── database-view/
│       ├── block.json                      # Block metadata
│       ├── src/
│       │   ├── index.js                    # Block registration (React)
│       │   ├── edit.js                     # Editor component
│       │   ├── frontend.js                 # Tabulator init
│       │   ├── editor.css                  # Editor styles
│       │   └── style.css                   # Frontend styles
│       ├── render.php                      # Server-side render
│       └── build/                          # Compiled assets
│           ├── index.js
│           ├── index.asset.php
│           ├── editor.css
│           └── style.css
tests/
└── unit/
    ├── API/
    │   └── DatabaseRestControllerCachingTest.php
    └── Database/
        ├── PropertyFormatterTest.php
        └── RichTextConverterTest.php
```

---

## Next Steps

1. **Read full documentation:** [PHASE-5.3-COMPLETE.md](./PHASE-5.3-COMPLETE.md)
2. **Explore property formatting:** See "Property Type Reference" section
3. **Customize styles:** Edit `/plugin/blocks/database-view/src/style.css`
4. **Add custom formatters:** Extend PropertyFormatter class
5. **Optimize performance:** Enable Redis/Memcached for object cache

---

## Support

**Documentation:**

- Full docs: [PHASE-5.3-COMPLETE.md](./PHASE-5.3-COMPLETE.md)
- Caching: [CACHING_IMPLEMENTATION.md](../CACHING_IMPLEMENTATION.md)
- Block docs: [plugin/blocks/database-view/README.md](../plugin/blocks/database-view/README.md)

**Testing:**

- Unit tests: `/tests/unit/Database/`
- API tests: `/tests/unit/API/`

**Code:**

- REST API: `/plugin/src/API/DatabaseRestController.php`
- Property formatting: `/plugin/src/Database/PropertyFormatter.php`
- Block class: `/plugin/src/Blocks/DatabaseViewBlock.php`

---

**Version:** 5.3.4
**Last Updated:** October 30, 2025
**Estimated Setup Time:** 5 minutes
