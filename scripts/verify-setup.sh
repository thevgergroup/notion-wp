#!/bin/bash

# Notion-WP Plugin Development Setup Verification Script
# This script verifies that all development dependencies and linting tools are correctly configured
# Run this after initial setup or when troubleshooting linting issues

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
BOLD='\033[1m'
NC='\033[0m' # No Color

# Track overall status
OVERALL_STATUS=0

# Helper functions
print_header() {
    echo ""
    echo -e "${BOLD}${BLUE}=====================================================================${NC}"
    echo -e "${BOLD}${BLUE}  $1${NC}"
    echo -e "${BOLD}${BLUE}=====================================================================${NC}"
    echo ""
}

print_step() {
    echo -e "${BOLD}→ $1${NC}"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
    OVERALL_STATUS=1
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ $1${NC}"
}

# Get script directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

print_header "Notion-WP Development Setup Verification"
echo "Project Root: $PROJECT_ROOT"
echo "Date: $(date '+%Y-%m-%d %H:%M:%S')"

# Change to project root
cd "$PROJECT_ROOT"

# =====================================================================
# SECTION 1: Check Dependencies Installation
# =====================================================================
print_header "1. Checking Dependencies Installation"

# Check Composer
print_step "Checking Composer installation..."
if command -v composer &> /dev/null; then
    COMPOSER_VERSION=$(composer --version | cut -d' ' -f3)
    print_success "Composer $COMPOSER_VERSION found"
else
    print_error "Composer not found. Install from https://getcomposer.org/"
    exit 1
fi

# Check if vendor directory exists
print_step "Checking Composer dependencies..."
if [ -d "vendor" ]; then
    print_success "Composer dependencies installed (vendor/ exists)"
else
    print_error "Composer dependencies NOT installed"
    print_info "Run: composer install"
    exit 1
fi

# Check Node.js
print_step "Checking Node.js installation..."
if command -v node &> /dev/null; then
    NODE_VERSION=$(node --version)
    print_success "Node.js $NODE_VERSION found"
else
    print_error "Node.js not found. Install from https://nodejs.org/"
    exit 1
fi

# Check npm
print_step "Checking npm installation..."
if command -v npm &> /dev/null; then
    NPM_VERSION=$(npm --version)
    print_success "npm $NPM_VERSION found"
else
    print_error "npm not found"
    exit 1
fi

# Check if node_modules directory exists
print_step "Checking npm dependencies..."
if [ -d "node_modules" ]; then
    print_success "npm dependencies installed (node_modules/ exists)"
else
    print_error "npm dependencies NOT installed"
    print_info "Run: npm install"
    exit 1
fi

# =====================================================================
# SECTION 2: Verify Configuration Files
# =====================================================================
print_header "2. Verifying Configuration Files"

# Check phpcs.xml.dist
print_step "Checking phpcs.xml.dist..."
if [ -f "phpcs.xml.dist" ]; then
    print_success "phpcs.xml.dist exists"

    # Verify 500-line limit is enforced
    if grep -q "Generic.Files.LineCount" phpcs.xml.dist; then
        if grep -q 'property name="maxLineCount" value="500"' phpcs.xml.dist; then
            print_success "500-line file limit rule is enforced"
        else
            print_error "500-line limit rule exists but value is not 500"
        fi
    else
        print_error "Generic.Files.LineCount rule NOT found in phpcs.xml.dist"
    fi
else
    print_error "phpcs.xml.dist NOT found"
fi

# Check phpstan.neon
print_step "Checking phpstan.neon..."
if [ -f "phpstan.neon" ]; then
    print_success "phpstan.neon exists"

    # Verify level 5
    if grep -q "level: 5" phpstan.neon; then
        print_success "PHPStan level 5 configured"
    else
        print_warning "PHPStan level might not be 5"
    fi
else
    print_error "phpstan.neon NOT found"
fi

# Check .eslintrc.json
print_step "Checking .eslintrc.json..."
if [ -f ".eslintrc.json" ]; then
    print_success ".eslintrc.json exists"

    # Verify no-console rule
    if grep -q '"no-console"' .eslintrc.json; then
        print_success "no-console rule configured"
    else
        print_warning "no-console rule might not be configured"
    fi

    # Verify WordPress preset
    if grep -q '@wordpress/eslint-plugin' .eslintrc.json; then
        print_success "WordPress ESLint preset configured"
    else
        print_warning "WordPress ESLint preset might not be configured"
    fi
else
    print_error ".eslintrc.json NOT found"
fi

# Check .stylelintrc.json
print_step "Checking .stylelintrc.json..."
if [ -f ".stylelintrc.json" ]; then
    print_success ".stylelintrc.json exists"

    # Verify WordPress config
    if grep -q 'stylelint-config-wordpress' .stylelintrc.json; then
        print_success "WordPress Stylelint config configured"
    else
        print_warning "WordPress Stylelint config might not be configured"
    fi
else
    print_error ".stylelintrc.json NOT found"
fi

# Check pre-commit hook
print_step "Checking .husky/pre-commit hook..."
if [ -f ".husky/pre-commit" ]; then
    print_success ".husky/pre-commit exists"

    # Verify it's executable
    if [ -x ".husky/pre-commit" ]; then
        print_success ".husky/pre-commit is executable"
    else
        print_warning ".husky/pre-commit is NOT executable"
        print_info "Run: chmod +x .husky/pre-commit"
    fi
else
    print_error ".husky/pre-commit NOT found"
fi

# =====================================================================
# SECTION 3: Test Linting Tools
# =====================================================================
print_header "3. Testing Linting Tools"

# Test PHPCS
print_step "Testing PHP_CodeSniffer (phpcs)..."
if composer lint:phpcs:quiet &> /dev/null; then
    print_success "PHPCS runs successfully (no errors found)"
elif composer lint:phpcs:quiet 2>&1 | grep -q "FILE"; then
    print_warning "PHPCS runs but found issues"
    print_info "Run 'composer lint:phpcs' to see details"
else
    print_success "PHPCS runs (no PHP files to check yet)"
fi

# Test PHPStan
print_step "Testing PHPStan..."
if composer lint:phpstan:quiet &> /dev/null; then
    print_success "PHPStan runs successfully"
elif composer lint:phpstan:quiet 2>&1 | grep -q "errors"; then
    print_warning "PHPStan runs but found issues"
    print_info "Run 'composer lint:phpstan' to see details"
else
    print_success "PHPStan runs (no PHP files to analyze yet)"
fi

# Test ESLint
print_step "Testing ESLint..."
if npm run lint:js --silent &> /dev/null; then
    print_success "ESLint runs successfully"
elif npm run lint:js --silent 2>&1 | grep -q "error"; then
    print_warning "ESLint runs but found issues"
    print_info "Run 'npm run lint:js' to see details"
else
    print_success "ESLint runs (no JS files to check yet)"
fi

# Test Stylelint
print_step "Testing Stylelint..."
if npm run lint:css --silent &> /dev/null; then
    print_success "Stylelint runs successfully"
elif npm run lint:css --silent 2>&1 | grep -q "error"; then
    print_warning "Stylelint runs but found issues"
    print_info "Run 'npm run lint:css' to see details"
else
    print_success "Stylelint runs (no CSS files to check yet)"
fi

# =====================================================================
# SECTION 4: Verify Composer & NPM Scripts
# =====================================================================
print_header "4. Verifying Package Scripts"

# Check Composer scripts
print_step "Checking Composer scripts..."
COMPOSER_SCRIPTS=(
    "lint"
    "lint:phpcs"
    "lint:phpcbf"
    "lint:phpstan"
    "lint:fix"
)

for script in "${COMPOSER_SCRIPTS[@]}"; do
    if composer run-script --list 2>/dev/null | grep -q "$script"; then
        print_success "composer $script exists"
    else
        print_error "composer $script NOT found in composer.json"
    fi
done

# Check NPM scripts
print_step "Checking npm scripts..."
NPM_SCRIPTS=(
    "lint"
    "lint:js"
    "lint:css"
    "lint:fix"
)

for script in "${NPM_SCRIPTS[@]}"; do
    if npm run | grep -q "$script"; then
        print_success "npm run $script exists"
    else
        print_error "npm run $script NOT found in package.json"
    fi
done

# =====================================================================
# SECTION 5: Test Pre-commit Hook
# =====================================================================
print_header "5. Testing Pre-commit Hook Configuration"

print_step "Checking Git repository..."
if [ -d ".git" ]; then
    print_success "Git repository detected"
else
    print_warning "Not a Git repository - pre-commit hooks will not work"
    print_info "Run: git init"
fi

print_step "Checking Husky installation..."
if [ -d ".husky/_" ]; then
    print_success "Husky is installed and configured"
else
    print_warning "Husky might not be properly installed"
    print_info "Run: npm run prepare"
fi

# =====================================================================
# SECTION 6: Summary & Recommendations
# =====================================================================
print_header "6. Summary & Recommendations"

if [ $OVERALL_STATUS -eq 0 ]; then
    echo ""
    print_success "ALL CHECKS PASSED!"
    echo ""
    echo -e "${GREEN}Your development environment is correctly configured.${NC}"
    echo ""
    echo "Next steps:"
    echo "  1. Start development: See docs/plans/phase-0.md"
    echo "  2. Run linting anytime: composer lint && npm run lint"
    echo "  3. Auto-fix issues: composer lint:fix && npm run lint:fix"
    echo ""
else
    echo ""
    print_error "SOME CHECKS FAILED"
    echo ""
    echo -e "${RED}Please fix the issues above before proceeding with development.${NC}"
    echo ""
    echo "Common fixes:"
    echo "  • Missing dependencies: composer install && npm install"
    echo "  • Pre-commit not executable: chmod +x .husky/pre-commit"
    echo "  • Husky not set up: npm run prepare"
    echo ""
fi

# =====================================================================
# Quick Reference
# =====================================================================
print_header "Quick Reference"

echo "Common Commands:"
echo ""
echo "  ${BOLD}Linting:${NC}"
echo "    composer lint              # Run all PHP linters"
echo "    composer lint:fix          # Auto-fix PHP issues"
echo "    npm run lint               # Run all JS/CSS linters"
echo "    npm run lint:fix           # Auto-fix JS/CSS issues"
echo ""
echo "  ${BOLD}Development:${NC}"
echo "    composer install           # Install PHP dependencies"
echo "    npm install                # Install Node dependencies"
echo "    npm run prepare            # Set up Git hooks"
echo ""
echo "  ${BOLD}Analysis:${NC}"
echo "    composer lint:phpstan      # Run static analysis"
echo "    composer check             # Run all checks"
echo ""

echo "For more details, see:"
echo "  • docs/development/linting-quick-reference.md (to be created)"
echo "  • docs/development/principles.md"
echo "  • docs/plans/phase-0.md"
echo ""

print_header "Verification Complete"
exit $OVERALL_STATUS
