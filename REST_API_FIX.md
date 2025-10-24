# REST API 404 Fix - Sync Status Endpoint

## Problem Summary

The `/notion-sync/v1/sync-status` REST endpoint was registered and visible in the namespace listing, but JavaScript calls were receiving 404 errors instead of the expected 401 (permission denied) responses.

## Root Cause

The issue was caused by WordPress URL canonicalization redirects:

1. **Original Code** used `get_rest_url()` which returns pretty permalinks:
    - URL: `http://phase3.localtest.me/wp-json/notion-sync/v1/sync-status`
    - Result: **301 redirect** to `http://phase3.localtest.me/wp-json/notion-sync/v1/sync-status/` (with trailing slash)

2. **Redirect Behavior**:
    - When fetch() follows the 301 redirect, the `X-WP-Nonce` authentication header is **not preserved** (security feature)
    - Without authentication, the endpoint returns 401 or routing fails entirely
    - JavaScript sees this as a 404 error

3. **Testing revealed**:

    ```bash
    # Pretty permalink format - CAUSES 301 REDIRECT
    curl -I "http://phase3.localtest.me/wp-json/notion-sync/v1/sync-status"
    # HTTP/1.1 301 Moved Permanently
    # X-Redirect-By: WordPress

    # Query string format - WORKS CORRECTLY
    curl "http://phase3.localtest.me/?rest_route=/notion-sync/v1/sync-status"
    # HTTP/1.1 401 Unauthorized (correct - needs auth)
    ```

## Solution

Changed the REST URL construction in `SettingsPage.php` from `get_rest_url()` to `rest_url()`:

```php
// BEFORE (incorrect):
'restUrl' => get_rest_url( null, 'notion-sync/v1/sync-status' ),

// AFTER (correct):
'restUrl' => rest_url( 'notion-sync/v1/sync-status' ),
```

### Why This Works

WordPress's `rest_url()` function returns the **query string format** which doesn't trigger canonicalization redirects:

- Returns: `http://phase3.localtest.me/index.php?rest_route=/notion-sync/v1/sync-status`
- No redirect occurs
- Authentication headers are preserved
- Endpoint correctly returns 401 (needs auth) or 200 (success with valid nonce)

## Files Modified

1. **`plugin/src/Admin/SettingsPage.php`** (line 108)
    - Changed `get_rest_url()` to `rest_url()`

2. **`plugin/assets/src/js/modules/sync-status-poller.js`** (line 186)
    - Added explicit `redirect: 'follow'` for clarity (though this is default behavior)

## Verification

To verify the fix works:

```bash
# 1. Endpoint responds without redirect
curl -I "http://phase3.localtest.me/index.php?rest_route=/notion-sync/v1/sync-status"
# Should return: HTTP/1.1 401 Unauthorized (no redirect)

# 2. With valid nonce (get from browser console: window.notionSyncAdmin.restNonce)
curl "http://phase3.localtest.me/index.php?rest_route=/notion-sync/v1/sync-status" \
  -H "X-WP-Nonce: YOUR_NONCE_HERE"
# Should return: JSON with pages and batch data
```

## Related WordPress Concepts

### URL Formats for REST API

WordPress supports multiple URL formats for REST endpoints:

1. **Pretty Permalinks** (requires mod_rewrite):

    ```
    /wp-json/namespace/v1/endpoint
    ```

2. **Query String** (always works):
    ```
    /?rest_route=/namespace/v1/endpoint
    /index.php?rest_route=/namespace/v1/endpoint
    ```

### Best Practices

- Always use `rest_url()` for JavaScript/AJAX calls to REST endpoints
- Use `get_rest_url()` only when you need pretty URLs for display purposes
- Be aware that canonical URL redirects can strip authentication headers
- Test REST endpoints with both authenticated and unauthenticated requests

## References

- WordPress REST API Handbook: https://developer.wordpress.org/rest-api/
- `rest_url()` function: https://developer.wordpress.org/reference/functions/rest_url/
- Fetch API redirect behavior: https://developer.mozilla.org/en-US/docs/Web/API/fetch#redirect
