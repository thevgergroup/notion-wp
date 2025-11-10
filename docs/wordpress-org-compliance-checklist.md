# WordPress.org Compliance Checklist

**Branch:** `wordpress-org-compliance`
**Review ID:** AUTOPREREVIEW ❗TRM-DESC notion-sync/pjaol/5Nov25/T1 5Nov25/3.7B
**Date Created:** 2025-11-09

## Issues to Resolve

### 1. Trademark Violation (Critical)
**Problem:** Plugin name "Notion Sync" begins with trademarked name "Notion", implying false affiliation.

**Solution:** Rename to "Vger Sync for Notion" (slug: `vger-sync-for-notion`)

### 2. Description Inconsistency (Critical)
**Problem:** Plugin header claims "Bi-directional synchronization" but readme states only Notion → WordPress works.

**Solution:** Update description to accurately reflect one-way sync (Notion → WordPress only), note bi-directional as planned feature.

---

## Touchpoint Categories

### A. Plugin Name & Slug Updates

#### A1. Main Plugin File
- [ ] **File Rename:** `plugin/notion-sync.php` → `plugin/vger-sync-for-notion.php`
- [ ] **Plugin Name Header:** `Plugin Name: Notion Sync` → `Plugin Name: Vger Sync for Notion`
- [ ] **Text Domain:** `Text Domain: notion-sync` → `Text Domain: vger-sync-for-notion`

**Verification:**
```bash
# Check main plugin file exists with new name
ls -la plugin/vger-sync-for-notion.php

# Verify headers
grep "Plugin Name:" plugin/vger-sync-for-notion.php
grep "Text Domain:" plugin/vger-sync-for-notion.php
```

#### A2. readme.txt Updates
- [ ] **Header:** `=== Notion Sync ===` → `=== Vger Sync for Notion ===`
- [ ] **Contributors:** Verify `thevgergroup` is correct (no trademark issues)
- [ ] **First Line:** Update "Sync Notion pages..." to "Vger Sync brings..."
- [ ] **All "Notion Sync" References:** Update throughout file

**Files to Update:**
- `plugin/readme.txt`

**Verification:**
```bash
grep -c "Notion Sync" plugin/readme.txt  # Should be 0
grep -c "Vger Sync for Notion" plugin/readme.txt  # Should be > 0
grep "===" plugin/readme.txt | head -1  # Verify header
```

#### A3. Text Domain Updates (673 occurrences)
**Pattern:** `'notion-sync'` → `'vger-sync-for-notion'`

**Critical Files:**
- [ ] `plugin/notion-sync.php` (main file)
- [ ] All PHP files in `plugin/src/` (59 files total)
- [ ] All template files in `plugin/templates/`
- [ ] All block files in `plugin/blocks/`

**Verification:**
```bash
# Find remaining old text domain references
grep -r "'notion-sync'" plugin/ --include="*.php" | wc -l  # Should be 0

# Verify new text domain
grep -r "'vger-sync-for-notion'" plugin/ --include="*.php" | wc -l  # Should be 673+
```

---

### B. Description Updates (Bi-directional → One-way)

#### B1. Main Plugin File
- [ ] **Header Description:** `Description: Bi-directional synchronization between Notion and WordPress`
  → `Description: Sync content from Notion to WordPress with automatic navigation menus and embedded database views`

**File:** `plugin/vger-sync-for-notion.php` (renamed)

**Verification:**
```bash
grep "Description:" plugin/vger-sync-for-notion.php
```

#### B2. readme.txt Updates
- [ ] **Short Description (line 11):** Update to match new plugin header
- [ ] **"Coming Soon" Section (line 46-51):** Ensure bi-directional is listed as future feature
- [ ] **FAQ "Can I sync from WordPress back to Notion?" (line 177-179):** Verify correct

**File:** `plugin/readme.txt`

**Current Correct Text (keep):**
```
= Can I sync from WordPress back to Notion? =

Bi-directional sync (WordPress → Notion) is planned for a future release. Currently, the plugin only syncs from Notion to WordPress.
```

**Verification:**
```bash
# Should NOT find "bi-directional" or "Bi-directional" in description/short description
grep -n "irectional" plugin/readme.txt

# Verify it only appears in "Coming Soon" and FAQ sections
```

#### B3. Documentation Files
- [ ] `plugin/README.md` - Check for bi-directional claims
- [ ] `plugin/docs/CLI-README.md` - Verify accurate functionality description
- [ ] Root `README.md` - Update if needed

**Verification:**
```bash
grep -i "bi-directional\|bidirectional" plugin/README.md
grep -i "bi-directional\|bidirectional" README.md
```

---

### C. Constants & Namespaces

#### C1. PHP Constants (73 occurrences)
**Decision Required:** Update or keep for backwards compatibility?

**Current:**
- `NOTION_SYNC_VERSION`
- `NOTION_SYNC_FILE`
- `NOTION_SYNC_PATH`
- `NOTION_SYNC_URL`
- `NOTION_SYNC_BASENAME`

**Proposed:**
- `VGER_SYNC_VERSION`
- `VGER_SYNC_FILE`
- `VGER_SYNC_PATH`
- `VGER_SYNC_URL`
- `VGER_SYNC_BASENAME`

**Status:** ⚠️ **DECISION PENDING**

**Files Affected (if updating):**
- [ ] `plugin/vger-sync-for-notion.php` (define statements)
- [ ] All files using these constants (11 files)

**Verification (if updating):**
```bash
grep -r "NOTION_SYNC_" plugin/ --include="*.php" | wc -l  # Should be 0
grep -r "VGER_SYNC_" plugin/ --include="*.php" | wc -l  # Should be 73+
```

#### C2. PHP Namespaces
**Decision Required:** Update or keep?

**Current:** `NotionSync\`, `NotionWP\`
**Proposed:** `VgerSync\`, `VgerWP\`

**Status:** ⚠️ **DECISION PENDING** (Breaking change for existing installations)

---

### D. Asset Handles & Hooks

#### D1. WordPress Asset Handles
**Pattern:** `notion-sync-*` → `vger-sync-*`

**Examples:**
- `notion-sync-callout-blocks` → `vger-sync-callout-blocks`
- `notion-sync-toggle-blocks` → `vger-sync-toggle-blocks`
- `notion-sync-navigation-patterns` → `vger-sync-navigation-patterns`
- `notion-sync-image-block` → `vger-sync-image-block`

**Files to Update:**
- [ ] `plugin/vger-sync-for-notion.php` (enqueue functions)
- [ ] `plugin/src/Blocks/NotionImageBlock.php`
- [ ] `plugin/src/Blocks/DatabaseViewBlock.php`

**Verification:**
```bash
grep -r "wp_enqueue_.*'notion-sync" plugin/ --include="*.php"  # Should be 0
grep -r "wp_enqueue_.*'vger-sync" plugin/ --include="*.php"  # Should match all enqueues
```

#### D2. WordPress Hooks & Actions
**Decision Required:** Update or keep for backwards compatibility?

**Current Examples:**
- `notion_sync_loaded`
- `notion_sync_process_batch`

**Status:** ⚠️ **DECISION PENDING**

---

### E. Database Options & Meta Keys

#### E1. WordPress Options
**Decision Required:** Keep current names for backwards compatibility?

**Current:**
- `notion_wp_token`
- `notion_wp_workspace_info`
- `notion_wp_delete_data_on_uninstall`

**Status:** ⚠️ **RECOMMEND KEEPING** (for existing users)

#### E2. Post Meta Keys
**Current:**
- `_notion_icon_type`
- `_notion_icon`
- `_notion_page_id`
- etc.

**Status:** ⚠️ **RECOMMEND KEEPING** (for existing users)

---

### F. File & Directory Names

#### F1. CSS/JS Asset Files
**Pattern:** `notion-sync-*` → `vger-sync-*`

**Files to Rename:**
- [ ] Asset file names (if any match pattern)

**Verification:**
```bash
find plugin/assets -name "*notion-sync*" -type f
```

#### F2. Language Files
- [ ] `plugin/languages/notion-wp.pot` - Update references inside
- [ ] Consider renaming to `vger-sync-for-notion.pot`

**Status:** Review after text domain updates

---

### G. Trademark Verification

#### G1. Check All Resources
- [ ] **Username:** `pjaol` - ✅ No trademark issues
- [ ] **Display Name:** Verify on WordPress.org profile
- [ ] **Plugin URL:** `https://github.com/thevgergroup/notion-wp` - ✅ OK (not user-facing)
- [ ] **Author:** `The Vger Group` - ✅ No trademark issues
- [ ] **Author URI:** `https://thevgergroup.com` - ✅ No trademark issues
- [ ] **Icon:** Check if exists - verify no "Notion" branding
- [ ] **Banner:** Check if exists - verify no "Notion" branding
- [ ] **Screenshots:** Verify no trademark violations

**Files to Check:**
```bash
# Check for icon/banner files
ls -la plugin/assets/*.png plugin/assets/*.jpg 2>/dev/null

# Check SVN assets directory if it exists
ls -la .wordpress-org/ 2>/dev/null
```

---

## WordPress.org Submission Steps

### 1. Request New Slug Reservation
- [ ] Reply to WordPress.org review email
- [ ] Request slug: `vger-sync-for-notion`
- [ ] Do NOT wait for confirmation before uploading

### 2. Upload New Version
- [ ] Upload via "Add your plugin" page while logged in as `pjaol`
- [ ] Version: Update to `1.0.4`

### 3. Reply to Review Email
- [ ] Brief, concise response (not AI-bloated)
- [ ] List changes made:
  - Renamed plugin to "Vger Sync for Notion"
  - Updated description to reflect one-way sync (Notion → WordPress)
  - Requested new slug: `vger-sync-for-notion`
  - Updated text domain throughout

---

## Verification Commands

### Complete Verification Script
```bash
#!/bin/bash
# Run from repository root

echo "=== Verification Script ==="
echo ""

echo "1. Check main plugin file exists:"
ls -la plugin/vger-sync-for-notion.php
echo ""

echo "2. Old slug references (should be 0):"
grep -r "notion-sync" plugin/ --include="*.php" | grep -v "comment" | wc -l
echo ""

echo "3. Old plugin name in code (excluding comments/docs):"
grep -r "Notion Sync" plugin/ --include="*.php" | grep -v "^\s*//" | wc -l
echo ""

echo "4. Bi-directional references (should only be in 'Coming Soon' and FAQ):"
grep -n "irectional" plugin/readme.txt
echo ""

echo "5. Text domain consistency:"
grep -r "load_plugin_textdomain\|wp_set_script_translations" plugin/ --include="*.php" -A 2
echo ""

echo "6. Plugin headers in main file:"
head -20 plugin/vger-sync-for-notion.php
echo ""

echo "7. readme.txt header:"
head -15 plugin/readme.txt
echo ""

echo "=== Verification Complete ==="
```

---

## Migration Strategy (for existing users)

**Note:** First release to WordPress.org = no existing users to migrate.

If we update constants/namespaces/hooks, document:
- [ ] Migration guide for developers using the plugin
- [ ] Backwards compatibility layer (if needed)

---

## Checklist Summary

**Critical Path (Must Complete):**
1. ✅ Create git branch `wordpress-org-compliance`
2. ⬜ Rename main plugin file
3. ⬜ Update plugin headers (name, description, text domain)
4. ⬜ Update readme.txt (header, description, all references)
5. ⬜ Update all text domain references (673 occurrences)
6. ⬜ Update asset handles
7. ⬜ Verify trademark compliance
8. ⬜ Test plugin functionality
9. ⬜ Request slug reservation from WordPress.org
10. ⬜ Upload new version
11. ⬜ Reply to review email

**Optional (Decisions Pending):**
- Constants rename (NOTION_SYNC → VGER_SYNC)
- Namespace rename (NotionSync → VgerSync)
- Hook/action rename
- Database option/meta key rename

---

## Testing Checklist

After all updates, verify:
- [ ] Plugin activates without errors
- [ ] Settings page loads
- [ ] API connection works
- [ ] Page sync works
- [ ] Database sync works
- [ ] Navigation menu generation works
- [ ] Frontend display works
- [ ] WP-CLI commands work
- [ ] No PHP errors in debug.log
- [ ] PHPCS passes
- [ ] PHPStan passes
- [ ] Unit tests pass

**Test Command:**
```bash
composer test
composer phpcs
composer phpstan
```

---

## Search Patterns for Review

### Find Remaining Issues
```bash
# Old plugin name (should only be in comments/docs after update)
rg "Notion Sync" plugin/

# Old text domain
rg "'notion-sync'" plugin/

# Old slug in file paths
rg "notion-sync\\.php" plugin/

# Bi-directional claims (should only be in Coming Soon/FAQ)
rg -i "bi-?directional" plugin/

# Old constants (if we decide to update them)
rg "NOTION_SYNC_" plugin/

# Old asset handles
rg "wp_enqueue.*notion-sync" plugin/
```

---

## Notes

- This is the **first submission** to WordPress.org, so no backwards compatibility concerns for end users
- Internal namespaces/constants can be updated without user impact
- Focus on user-facing changes first (name, slug, text domain, descriptions)
- Can update internal constants/namespaces in a second commit if desired

---

**Status:** 🚧 In Progress
**Last Updated:** 2025-11-09
**Updated By:** Claude Code
