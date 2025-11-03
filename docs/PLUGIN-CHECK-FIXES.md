# WordPress Plugin Check - Action Plan

**Run Date:** 2025-11-03
**Plugin Version:** 1.0.0
**Total Issues:** 404 errors, 173 warnings

## Summary

WordPress Plugin Check identified several critical issues that must be resolved before WordPress.org submission:

- 335 text domain mismatches
- 8 CDN resource violations
- 46 unescaped outputs (XSS risk)
- 56 debug error_log() calls
- 36 uncached database queries
- 28 missing nonce verifications

## Priority 1: BLOCKERS (Must Fix for WordPress.org)

### 1. Text Domain Mismatches (335 errors)

**Issue:** All translation functions use `'notion-wp'` but the plugin text domain is `'notion-sync'`

**Impact:** BLOCKER - WordPress.org will reject the plugin

**Files Affected:** All PHP files with `__()`, `_e()`, `esc_html__()`, `esc_attr__()`, etc.

**Fix Strategy:**
```bash
# Find all instances
grep -r "notion-wp" plugin/src/ plugin/templates/

# Replace globally
find plugin/src plugin/templates -name "*.php" -exec sed -i '' "s/'notion-wp'/'notion-sync'/g" {} \;
```

**Verification:**
```bash
grep -r "notion-wp" plugin/src/ plugin/templates/ | grep -v "Binary" | wc -l
# Should return 0
```

**Estimated Time:** 15 minutes (automated)
**Risk:** Low (simple find/replace)

---

### 2. Enqueued Resource Offloading (8 errors)

**Issue:** Loading Tabulator CSS/JS from CDN (jsdelivr) is prohibited

**Files:**
- `plugin/src/Database/DatabaseTemplateLoader.php:76` - Tabulator CSS
- `plugin/src/Database/DatabaseTemplateLoader.php:84` - Tabulator JS
- `plugin/src/Database/DatabaseTemplateLoader.php:93` - Luxon JS (Tabulator dependency)

**Impact:** BLOCKER - External resources not allowed on WordPress.org

**Fix Strategy:**

1. **Download Tabulator locally:**
```bash
cd plugin/assets/vendor
mkdir -p tabulator
cd tabulator
curl -O https://cdn.jsdelivr.net/npm/tabulator-tables@6.3.0/dist/css/tabulator.min.css
curl -O https://cdn.jsdelivr.net/npm/tabulator-tables@6.3.0/dist/js/tabulator.min.js
curl -O https://cdn.jsdelivr.net/npm/luxon@3.5.0/build/global/luxon.min.js
```

2. **Update DatabaseTemplateLoader.php:**
```php
// Before (line 76):
wp_enqueue_style('tabulator', 'https://cdn.jsdelivr.net/npm/tabulator-tables@6.3.0/dist/css/tabulator.min.css', [], '6.3.0');

// After:
wp_enqueue_style('tabulator', NOTION_SYNC_URL . 'assets/vendor/tabulator/tabulator.min.css', [], '6.3.0');

// Before (line 84):
wp_enqueue_script('luxon', 'https://cdn.jsdelivr.net/npm/luxon@3.5.0/build/global/luxon.min.js', [], '3.5.0', true);

// After:
wp_enqueue_script('luxon', NOTION_SYNC_URL . 'assets/vendor/tabulator/luxon.min.js', [], '3.5.0', true);

// Before (line 93):
wp_enqueue_script('tabulator', 'https://cdn.jsdelivr.net/npm/tabulator-tables@6.3.0/dist/js/tabulator.min.js', ['luxon'], '6.3.0', true);

// After:
wp_enqueue_script('tabulator', NOTION_SYNC_URL . 'assets/vendor/tabulator/tabulator.min.js', ['luxon'], '6.3.0', true);
```

3. **Add to .gitignore exception:**
Ensure `plugin/assets/vendor/tabulator/` is NOT ignored (currently it might be under vendor/)

4. **Update build process:**
Add Tabulator files to the release build in `.github/workflows/release.yml`

**Estimated Time:** 30 minutes
**Risk:** Medium (need to test table functionality)

---

### 3. Output Not Escaped (46 errors)

**Issue:** User-generated or dynamic content output without escaping (XSS vulnerability)

**Impact:** BLOCKER - Security violation

**Common Patterns:**

```php
// BAD:
echo $variable;
echo $post->post_title;
echo $_GET['param'];

// GOOD:
echo esc_html( $variable );
echo esc_html( $post->post_title );
echo esc_html( sanitize_text_field( $_GET['param'] ) );

// For attributes:
echo '<a href="' . $url . '">'; // BAD
echo '<a href="' . esc_url( $url ) . '">'; // GOOD

// For JavaScript:
echo "<script>var data = '$value';</script>"; // BAD
echo '<script>var data = ' . wp_json_encode( $value ) . ';</script>'; // GOOD
```

**Fix Strategy:**

1. Get list of affected files:
```bash
docker exec wp-test-wordpress-1 wp plugin check notion-sync --allow-root 2>&1 | grep "OutputNotEscaped" > /tmp/escaping-issues.txt
```

2. Review each file and add appropriate escaping function

3. Use PHPStan rules to catch future violations

**Estimated Time:** 2 hours
**Risk:** Medium (need to choose correct escaping function)

---

## Priority 2: HIGH (Should Fix)

### 4. Development Functions (56 warnings)

**Issue:** `error_log()` calls left in production code

**Impact:** Performance, fills error logs, information disclosure

**Fix Strategy:**

Option A - Remove all debug logging:
```bash
# Find all error_log calls
grep -rn "error_log" plugin/src/
# Remove them manually or with sed
```

Option B - Wrap in WP_DEBUG checks (recommended):
```php
// Before:
error_log('Debug info: ' . print_r($data, true));

// After:
if ( defined('WP_DEBUG') && WP_DEBUG ) {
    error_log('Debug info: ' . print_r($data, true));
}
```

Option C - Use WordPress debug logging:
```php
// Before:
error_log('Error: ' . $message);

// After:
if ( defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ) {
    error_log('[Notion Sync] Error: ' . $message);
}
```

**Recommendation:** Use Option C for actual errors, remove debug/info logs

**Estimated Time:** 1.5 hours
**Risk:** Low

---

### 5. Direct Database Queries Without Caching (36 warnings)

**Issue:** Custom queries not using WordPress object cache

**Impact:** Performance degradation with persistent caching enabled

**Example Fix:**

```php
// Before:
$results = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}notion_sync_logs WHERE page_id = %s", $page_id));

// After:
$cache_key = 'notion_sync_logs_' . md5($page_id);
$results = wp_cache_get($cache_key, 'notion-sync');

if (false === $results) {
    $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}notion_sync_logs WHERE page_id = %s", $page_id));
    wp_cache_set($cache_key, $results, 'notion-sync', HOUR_IN_SECONDS);
}
```

**Cache Invalidation:**
```php
// When updating:
wp_cache_delete($cache_key, 'notion-sync');
// Or flush entire group:
wp_cache_flush_group('notion-sync');
```

**Estimated Time:** 3 hours
**Risk:** Medium (need proper cache invalidation)

---

### 6. Missing Nonce Verification (28 warnings)

**Issue:** Form submissions without CSRF protection

**Impact:** Security vulnerability (CSRF attacks)

**Files Affected:**
- Admin AJAX handlers
- Settings page forms
- List table actions

**Fix Pattern:**

```php
// In form:
<form method="post">
    <?php wp_nonce_field('notion_sync_action', 'notion_sync_nonce'); ?>
    <!-- form fields -->
</form>

// In handler:
public function handle_form_submission() {
    // Verify nonce
    if (!isset($_POST['notion_sync_nonce']) || !wp_verify_nonce($_POST['notion_sync_nonce'], 'notion_sync_action')) {
        wp_die(__('Security check failed', 'notion-sync'));
    }

    // Process form...
}

// For AJAX:
check_ajax_referer('notion_sync_ajax', 'nonce');
```

**Estimated Time:** 2 hours
**Risk:** Medium (must not break functionality)

---

## Priority 3: NICE TO HAVE

### 7. Non-Singular String Literals (9 warnings)

**Issue:** Dynamic translation strings

```php
// BAD:
$message = "Page {$count}";
__($message, 'notion-sync');

// GOOD:
sprintf(__('Page %d', 'notion-sync'), $count);
```

**Estimated Time:** 30 minutes
**Risk:** Low

---

### 8. Metadata Issues

**8a. Outdated "Tested up to" Header (1 error)**

**Files:**
- `plugin/readme.txt:5` - Change `Tested up to: 6.5` to `Tested up to: 6.7`
- `plugin/notion-sync.php` - Update if present

**8b. Too Many Tags (1 warning)**

**File:** `plugin/readme.txt`

Current tags:
```
Tags: notion, sync, content, database, blocks, menu, gutenberg, import, collaboration
```

Limit: 12 tags (currently 9, so this might be a false positive)

**Estimated Time:** 5 minutes
**Risk:** None

---

### 9. Hidden Files in ZIP (2 errors)

**Issue:** `.DS_Store` or other hidden files included in release

**Fix:** Update build script to exclude hidden files

**File:** `.github/workflows/release.yml`

Add to zip exclusions:
```bash
zip -r notion-sync.zip notion-sync \
    -x "*/.*" \
    -x "*/.DS_Store" \
    -x "*/Thumbs.db"
```

**Estimated Time:** 10 minutes
**Risk:** None

---

## Implementation Order

### Phase 1: Quick Wins (30 minutes)
1. ✅ Fix text domain mismatches (automated find/replace)
2. ✅ Update "Tested up to" header
3. ✅ Fix hidden files in build

### Phase 2: Critical Security (3-4 hours)
4. ✅ Bundle Tabulator locally
5. ✅ Fix output escaping (46 instances)
6. ✅ Add nonce verification (28 instances)

### Phase 3: Code Quality (5-6 hours)
7. ✅ Clean up error_log() calls (56 instances)
8. ✅ Add database query caching (36 instances)
9. ✅ Fix translation string literals (9 instances)

### Phase 4: Verification (1 hour)
10. ✅ Run Plugin Check again
11. ✅ Test all functionality
12. ✅ Update CHANGELOG.md
13. ✅ Create v1.0.1 release

**Total Estimated Time:** 10-12 hours

---

## Testing Checklist

After fixes:

- [ ] Run `wp plugin check notion-sync` - 0 errors, 0 warnings
- [ ] Test page sync functionality
- [ ] Test database table views (Tabulator still works)
- [ ] Test menu generation
- [ ] Test settings page (forms work with nonces)
- [ ] Test WP-CLI commands
- [ ] Verify translations load correctly
- [ ] Check error logs (no excessive logging)
- [ ] Test with object caching enabled (Redis/Memcached)

---

## Commands Reference

### Run Plugin Check
```bash
cd /tmp/wp-test
docker compose up -d
docker exec wp-test-wordpress-1 wp plugin check notion-sync --allow-root
```

### Get Error Counts
```bash
# By type
docker exec wp-test-wordpress-1 wp plugin check notion-sync --allow-root 2>&1 | grep "ERROR" | awk '{print $4}' | sort | uniq -c | sort -rn

# Total
docker exec wp-test-wordpress-1 wp plugin check notion-sync --allow-root 2>&1 | grep -c "ERROR"
```

### Find Specific Issues
```bash
# Text domain issues
docker exec wp-test-wordpress-1 wp plugin check notion-sync --allow-root 2>&1 | grep "TextDomainMismatch"

# Escaping issues
docker exec wp-test-wordpress-1 wp plugin check notion-sync --allow-root 2>&1 | grep "OutputNotEscaped"

# Nonce issues
docker exec wp-test-wordpress-1 wp plugin check notion-sync --allow-root 2>&1 | grep "NonceVerification"
```

---

## Notes

- All fixes should maintain backward compatibility
- Add tests for security-critical changes
- Update PHPStan/PHPCS rules to catch these issues in CI
- Consider adding Plugin Check to CI pipeline
- Document Tabulator bundling decision in CHANGELOG

---

## Resources

- [WordPress Plugin Check Documentation](https://wordpress.org/plugins/plugin-check/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [Plugin Review Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
- [Security Best Practices](https://developer.wordpress.org/plugins/security/)
