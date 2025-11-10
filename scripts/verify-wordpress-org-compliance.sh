#!/bin/bash
# WordPress.org Compliance Verification Script
# Run from repository root: ./scripts/verify-wordpress-org-compliance.sh

set -e

echo "========================================="
echo "WordPress.org Compliance Verification"
echo "========================================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

success=0
warnings=0
errors=0

# Function to check and report
check() {
    local description="$1"
    local command="$2"
    local expected="$3"

    echo -n "Checking: $description ... "

    result=$(eval "$command" 2>/dev/null || echo "0")

    if [ "$result" = "$expected" ]; then
        echo -e "${GREEN}✓ PASS${NC} (found: $result)"
        ((success++))
    else
        echo -e "${RED}✗ FAIL${NC} (expected: $expected, found: $result)"
        ((errors++))
    fi
}

check_exists() {
    local description="$1"
    local path="$2"

    echo -n "Checking: $description ... "

    if [ -f "$path" ] || [ -d "$path" ]; then
        echo -e "${GREEN}✓ EXISTS${NC}"
        ((success++))
    else
        echo -e "${RED}✗ MISSING${NC}"
        ((errors++))
    fi
}

warn() {
    local description="$1"
    echo -e "${YELLOW}⚠ WARNING${NC}: $description"
    ((warnings++))
}

section() {
    echo ""
    echo "--- $1 ---"
}

# 1. File Structure
section "File Structure"

check_exists "Main plugin file exists" "plugin/vger-sync-for-notion.php"
check_exists "readme.txt exists" "plugin/readme.txt"

if [ -f "plugin/notion-sync.php" ]; then
    warn "Old plugin file still exists: plugin/notion-sync.php (should be removed)"
fi

# 2. Plugin Headers
section "Plugin Headers"

if [ -f "plugin/vger-sync-for-notion.php" ]; then
    plugin_name=$(grep "Plugin Name:" plugin/vger-sync-for-notion.php | head -1 || echo "NOT FOUND")
    if echo "$plugin_name" | grep -q "Vger Sync for Notion"; then
        echo -e "Plugin Name: ${GREEN}✓ CORRECT${NC}"
        ((success++))
    else
        echo -e "Plugin Name: ${RED}✗ INCORRECT${NC}"
        echo "  Found: $plugin_name"
        ((errors++))
    fi

    text_domain=$(grep "Text Domain:" plugin/vger-sync-for-notion.php | head -1 || echo "NOT FOUND")
    if echo "$text_domain" | grep -q "vger-sync-for-notion"; then
        echo -e "Text Domain: ${GREEN}✓ CORRECT${NC}"
        ((success++))
    else
        echo -e "Text Domain: ${RED}✗ INCORRECT${NC}"
        echo "  Found: $text_domain"
        ((errors++))
    fi

    description=$(grep "Description:" plugin/vger-sync-for-notion.php | head -1 || echo "NOT FOUND")
    if echo "$description" | grep -iq "bi-directional\|bidirectional"; then
        echo -e "Description: ${RED}✗ CONTAINS BI-DIRECTIONAL CLAIM${NC}"
        echo "  Found: $description"
        ((errors++))
    else
        echo -e "Description: ${GREEN}✓ NO BI-DIRECTIONAL CLAIM${NC}"
        ((success++))
    fi
fi

# 3. readme.txt
section "readme.txt"

if [ -f "plugin/readme.txt" ]; then
    readme_header=$(grep "^===" plugin/readme.txt | head -1 || echo "NOT FOUND")
    if echo "$readme_header" | grep -q "Vger Sync for Notion"; then
        echo -e "readme.txt Header: ${GREEN}✓ CORRECT${NC}"
        ((success++))
    else
        echo -e "readme.txt Header: ${RED}✗ INCORRECT${NC}"
        echo "  Found: $readme_header"
        ((errors++))
    fi

    # Check for bi-directional only in appropriate sections
    bidirectional_count=$(grep -i "bi-directional\|bidirectional" plugin/readme.txt | wc -l)
    echo "Bi-directional mentions in readme.txt: $bidirectional_count"

    # Should appear in "Coming Soon" and FAQ sections only
    if [ "$bidirectional_count" -gt 0 ]; then
        echo -e "${YELLOW}  Note: Verify these are only in 'Coming Soon' and FAQ sections${NC}"
    fi
fi

# 4. Old References
section "Old References (should be 0)"

check "Old plugin name 'Notion Sync' in PHP" \
    "grep -r 'Notion Sync' plugin/ --include='*.php' | grep -v '^\s*//' | grep -v 'Coming Soon' | grep -v 'FAQ' | wc -l | tr -d ' '" \
    "0"

check "Old text domain 'notion-sync' in PHP" \
    "grep -r \"'notion-sync'\" plugin/ --include='*.php' | wc -l | tr -d ' '" \
    "0"

check "Old slug in file references" \
    "grep -r 'notion-sync\\.php' plugin/ --include='*.php' | grep -v 'vger-sync-for-notion\\.php' | wc -l | tr -d ' '" \
    "0"

# 5. New References
section "New References (should be > 0)"

new_text_domain_count=$(grep -r "'vger-sync-for-notion'" plugin/ --include="*.php" | wc -l | tr -d ' ')
if [ "$new_text_domain_count" -gt 0 ]; then
    echo -e "New text domain 'vger-sync-for-notion': ${GREEN}✓ FOUND${NC} ($new_text_domain_count occurrences)"
    ((success++))
else
    echo -e "New text domain 'vger-sync-for-notion': ${RED}✗ NOT FOUND${NC}"
    ((errors++))
fi

# 6. Asset Handles
section "Asset Handles"

old_handles=$(grep -r "wp_enqueue.*'notion-sync" plugin/ --include="*.php" | wc -l | tr -d ' ')
if [ "$old_handles" -eq 0 ]; then
    echo -e "Old asset handles (notion-sync-*): ${GREEN}✓ NONE FOUND${NC}"
    ((success++))
else
    echo -e "Old asset handles (notion-sync-*): ${RED}✗ FOUND ${old_handles}${NC}"
    ((errors++))
fi

new_handles=$(grep -r "wp_enqueue.*'vger-sync" plugin/ --include="*.php" | wc -l | tr -d ' ')
if [ "$new_handles" -gt 0 ]; then
    echo -e "New asset handles (vger-sync-*): ${GREEN}✓ FOUND${NC} ($new_handles occurrences)"
    ((success++))
else
    echo -e "New asset handles (vger-sync-*): ${YELLOW}⚠ NOT FOUND${NC}"
    ((warnings++))
fi

# 7. Trademark Verification
section "Trademark Verification"

# Check for assets directory
if [ -d "plugin/assets" ]; then
    icon_files=$(find plugin/assets -type f \( -name "icon-*.png" -o -name "icon-*.jpg" \) 2>/dev/null | wc -l | tr -d ' ')
    banner_files=$(find plugin/assets -type f \( -name "banner-*.png" -o -name "banner-*.jpg" \) 2>/dev/null | wc -l | tr -d ' ')

    echo "Icon files found: $icon_files"
    echo "Banner files found: $banner_files"

    if [ "$icon_files" -gt 0 ] || [ "$banner_files" -gt 0 ]; then
        warn "Visual assets found - manually verify no Notion branding/trademark issues"
    fi
fi

# 8. Version Consistency
section "Version Consistency"

if [ -f "plugin/vger-sync-for-notion.php" ]; then
    version_header=$(grep "Version:" plugin/vger-sync-for-notion.php | head -1 | sed 's/.*Version:\s*//' | tr -d ' ')
    version_constant=$(grep "define.*VGER_SYNC_VERSION\|define.*NOTION_SYNC_VERSION" plugin/vger-sync-for-notion.php | head -1 | sed "s/.*'\([^']*\)'.*/\1/")

    echo "Version in header: $version_header"
    echo "Version in constant: $version_constant"

    if [ "$version_header" = "$version_constant" ]; then
        echo -e "${GREEN}✓ VERSIONS MATCH${NC}"
        ((success++))
    else
        echo -e "${RED}✗ VERSIONS DO NOT MATCH${NC}"
        ((errors++))
    fi
fi

if [ -f "plugin/readme.txt" ]; then
    readme_version=$(grep "Stable tag:" plugin/readme.txt | head -1 | sed 's/.*Stable tag:\s*//' | tr -d ' ')
    echo "Version in readme.txt: $readme_version"

    if [ -n "$version_header" ] && [ "$readme_version" = "$version_header" ]; then
        echo -e "${GREEN}✓ README VERSION MATCHES${NC}"
        ((success++))
    else
        echo -e "${RED}✗ README VERSION DOES NOT MATCH${NC}"
        ((errors++))
    fi
fi

# Summary
echo ""
echo "========================================="
echo "Summary"
echo "========================================="
echo -e "${GREEN}Passed: $success${NC}"
echo -e "${YELLOW}Warnings: $warnings${NC}"
echo -e "${RED}Errors: $errors${NC}"
echo ""

if [ "$errors" -eq 0 ]; then
    echo -e "${GREEN}✓ All critical checks passed!${NC}"
    exit 0
else
    echo -e "${RED}✗ Found $errors error(s) that need to be fixed${NC}"
    exit 1
fi
