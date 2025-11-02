# Database REST API Caching Implementation

**Phase:** 5.3 - Database Frontend Views
**Date:** 2025-10-30
**Status:** Complete ✅

## Overview

Implemented intelligent caching for the DatabaseRestController REST API to improve performance and reduce database load for public-facing database views. The implementation uses WordPress Transients with tiered TTL strategy, automatic invalidation, and comprehensive cache headers.

## Implementation Summary

### Files Modified

1. **`/plugin/src/API/DatabaseRestController.php`**
   - Added intelligent caching layer to `get_rows()` and `get_schema()` endpoints
   - Implemented cache helper methods
   - Added cache invalidation hooks
   - Added AJAX handler for manual cache flushing

### Files Created

1. **`/tests/unit/API/DatabaseRestControllerCachingTest.php`** - Comprehensive test suite (11 tests, 53 assertions)
2. **`/tests/mocks/WP_REST_Request.php`** - Mock class for unit testing
3. **`/tests/mocks/WP_REST_Response.php`** - Mock class for unit testing

## Features Implemented

### 1. Intelligent Cache Keys

Cache keys include:
- Post ID (database identifier)
- Request type ('rows' or 'schema')
- Pagination parameters (page, per_page) for rows
- Last modified timestamp (for automatic invalidation on updates)

**Format:**
```
notion_db_rows_{post_id}_{md5_hash}_{modified_time}
notion_db_schema_{post_id}_{modified_time}
```

**Example:**
```
notion_db_rows_123_328fce44eb422305234aaf15d435b83c_1698765432
notion_db_schema_123_1698765432
```

### 2. Tiered Cache TTL Strategy

| Cache Type | Public Users | Admin Users | Rationale |
|------------|--------------|-------------|-----------|
| Schema     | 60 minutes   | 5 minutes   | Schemas change rarely |
| Rows       | 30 minutes   | 5 minutes   | Data changes more frequently |

**Implementation:**
```php
private const CACHE_TTL_SCHEMA = 3600;  // 60 minutes
private const CACHE_TTL_ROWS = 1800;    // 30 minutes
private const CACHE_TTL_ADMIN = 300;    // 5 minutes for admins
```

### 3. Automatic Cache Invalidation

Cache is automatically cleared when:

1. **Database post is saved** - `save_post_notion_database` hook
2. **Database post is deleted** - `before_delete_post` hook
3. **Manual flush requested** - AJAX handler `wp_ajax_notion_flush_database_cache`

**Pattern-based deletion** clears all pagination variants:
```php
DELETE FROM wp_options WHERE option_name LIKE '_transient_notion_db_%_123_%'
```

### 4. Cache Size Limits

Maximum cache size: **1MB** (prevents caching huge responses)

If response exceeds limit:
- Data is NOT cached
- Query executes on every request
- Debug log records the overflow

### 5. Cache Headers

All REST responses include cache status headers:

```http
X-NotionWP-Cache: HIT|MISS
X-NotionWP-Cache-Expires: 1730308890
```

Benefits:
- Frontend can monitor cache performance
- Debugging cache issues is easier
- CDN can leverage cache signals

## Test Coverage

### Test Suite: DatabaseRestControllerCachingTest

**11 tests, 53 assertions - All passing ✅**

1. ✅ **Rows cache miss on first request** - Verifies first request executes query
2. ✅ **Rows cache hit on second request** - Verifies second request uses cache
3. ✅ **Different pagination creates different cache keys** - Verifies pagination isolation
4. ✅ **Schema cache miss on first request** - Verifies schema caching works
5. ✅ **Schema cache hit on second request** - Verifies schema cache reuse
6. ✅ **Cache TTL shorter for admin users** - Verifies 5-minute admin TTL
7. ✅ **Cache invalidation on post save** - Verifies save hook clears cache
8. ✅ **Cache not created for oversized responses** - Verifies 1MB size limit
9. ✅ **Empty database returns empty schema** - Verifies graceful empty handling
10. ✅ **Cache headers include expiration timestamp** - Verifies header presence
11. ✅ **Cache invalidation on post delete** - Verifies delete hook clears cache

### Test Execution

```bash
./plugin/vendor/bin/phpunit tests/unit/API/DatabaseRestControllerCachingTest.php --testdox

OK (11 tests, 53 assertions)
Time: 00:00.206, Memory: 26.77 MB
```

## Performance Impact

### Expected Improvements

| Metric | Before Caching | After Caching (Cache Hit) | Improvement |
|--------|----------------|---------------------------|-------------|
| Database Queries | 2-3 per request | 0 | 100% reduction |
| Response Time | ~100-300ms | ~5-10ms | ~95% faster |
| Server Load | High (DB queries) | Minimal (transient lookup) | ~90% reduction |
| Concurrent Users | Limited by DB | Limited by memory | 10x+ improvement |

### Cache Hit Ratio Estimates

- **First page view:** 0% (cold cache)
- **Subsequent views (within TTL):** 90%+ for public users
- **Admin users:** 70%+ (shorter TTL)

## Usage Examples

### Frontend Database View

When a visitor loads a page with a database view:

1. **First request** (cache miss):
   ```
   GET /wp-json/notion-sync/v1/databases/123/rows?page=1&per_page=50

   X-NotionWP-Cache: MISS
   X-NotionWP-Cache-Expires: 1730308890
   Response time: ~150ms
   ```

2. **Second request** (cache hit):
   ```
   GET /wp-json/notion-sync/v1/databases/123/rows?page=1&per_page=50

   X-NotionWP-Cache: HIT
   X-NotionWP-Cache-Expires: 1730308890
   Response time: ~8ms
   ```

### Manual Cache Flush (Admin)

```javascript
jQuery.ajax({
    url: ajaxurl,
    method: 'POST',
    data: {
        action: 'notion_flush_database_cache',
        post_id: 123,
        nonce: notionWpNonce
    }
});
```

## Debugging

### Enable Debug Logging

Set `WP_DEBUG` to true in `wp-config.php`:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

### Debug Log Output

```
[NotionWP Cache] MISS: notion_db_rows_123_328fce44eb422305234aaf15d435b83c_1698765432
[NotionWP Cache] HIT: notion_db_schema_123_1698765432
[NotionWP Cache] Invalidated cache for database post ID: 123
[NotionWP Cache] Data too large to cache (11286977 bytes): notion_db_rows_123_...
```

## Security Considerations

1. **Permission Checks:** Cache respects existing permission callbacks
2. **Nonce Verification:** Manual cache flush requires valid nonce
3. **Capability Checks:** Admin-only operations require `manage_options`
4. **SQL Injection:** Pattern-based deletion uses `wpdb->prepare()`
5. **Cache Poisoning:** Cache keys include modified time (auto-invalidation)

## Backward Compatibility

The caching implementation is **fully backward compatible**:

- No API changes
- No database schema changes
- Graceful degradation if transients fail
- Same response format (only headers added)
- No breaking changes to existing code

## Future Enhancements (Optional)

### Possible Improvements

1. **Object Cache Support** - Add Redis/Memcached support for high-traffic sites
2. **Cache Warming** - Pre-populate cache after database sync completes
3. **Partial Cache Invalidation** - Only invalidate affected pages, not all pages
4. **Cache Statistics** - Track hit ratio, response times, cache size
5. **Stale-While-Revalidate** - Serve stale cache while refreshing in background
6. **Conditional Requests** - Support ETag/Last-Modified headers

### Monitoring Metrics

Track these metrics in production:

- Cache hit ratio (target: >90%)
- Average response time (target: <50ms)
- Cache size (monitor memory usage)
- Invalidation frequency (detect sync patterns)

## Deployment Notes

### Post-Deployment Checklist

- [ ] Verify cache headers appear in REST responses
- [ ] Test cache invalidation after database sync
- [ ] Monitor error logs for cache-related issues
- [ ] Check transient table size (run cleanup if needed)
- [ ] Verify admin users get 5-minute TTL
- [ ] Test manual cache flush from admin UI

### Transient Cleanup

WordPress auto-deletes expired transients, but for large sites consider periodic cleanup:

```sql
DELETE FROM wp_options WHERE option_name LIKE '_transient_timeout_notion_db_%' AND option_value < UNIX_TIMESTAMP();
DELETE FROM wp_options WHERE option_name LIKE '_transient_notion_db_%' AND option_name NOT IN (
    SELECT CONCAT('_transient_', SUBSTRING(option_name, 19)) FROM wp_options WHERE option_name LIKE '_transient_timeout_notion_db_%'
);
```

## Conclusion

The caching implementation provides significant performance improvements for public-facing database views while maintaining data freshness through intelligent invalidation. The comprehensive test suite ensures reliability and the tiered TTL strategy balances performance with data accuracy.

**Status: Production Ready ✅**

---

**Implementation Date:** 2025-10-30
**Tests Passing:** 11/11 (100%)
**Code Coverage:** DatabaseRestController caching methods fully covered
