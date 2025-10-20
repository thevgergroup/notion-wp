# Code Quality & Linting Guide

This document explains how to use the linting and code quality tools for the Notion-WP plugin. All code must pass these checks before being committed.

## Table of Contents

- [Quick Start](#quick-start)
- [PHP Linting](#php-linting)
- [JavaScript/TypeScript Linting](#javascripttypescript-linting)
- [CSS/SCSS Linting](#cssscss-linting)
- [Pre-commit Hooks](#pre-commit-hooks)
- [IDE Integration](#ide-integration)
- [Troubleshooting](#troubleshooting)
- [CI/CD Integration](#cicd-integration)

## Quick Start

### Initial Setup

1. **Install PHP dependencies:**

    ```bash
    composer install
    ```

2. **Install Node dependencies:**

    ```bash
    npm install
    ```

3. **Setup pre-commit hooks:**
    ```bash
    npm run prepare
    ```

### Running Linters

**Check all code:**

```bash
composer lint        # Check all PHP code
npm run lint         # Check all JS/CSS code
```

**Auto-fix issues:**

```bash
composer lint:fix    # Fix all PHP issues
npm run lint:fix     # Fix all JS/CSS issues
```

## PHP Linting

### Tools Used

1. **PHP_CodeSniffer (PHPCS)** - WordPress Coding Standards compliance
2. **PHPStan** - Static analysis (Level 5)
3. **PHP-CS-Fixer** - Auto-formatting

### Configuration Files

- `phpcs.xml.dist` - PHPCS ruleset
- `phpstan.neon` - PHPStan configuration
- `.php-cs-fixer.php` - PHP-CS-Fixer rules

### Available Commands

```bash
# Run all PHP linting
composer lint

# Run individual tools
composer lint:phpcs          # Check WordPress Coding Standards
composer lint:phpstan        # Run static analysis
composer lint:php-cs-fixer   # Check code style (dry-run)

# Auto-fix issues
composer lint:fix            # Fix all auto-fixable issues
composer lint:phpcbf         # Fix PHPCS issues
composer lint:php-cs-fixer:fix  # Fix PHP-CS-Fixer issues

# Quiet output (useful in CI)
composer lint:phpcs:quiet
composer lint:phpstan:quiet
composer lint:fix:quiet
```

### Common PHP Issues

#### 1. File Size Limit (500 lines)

**Error:**

```
FILE: plugin/src/SomeClass.php
----------------------------------------------------------------------
FOUND 1 ERROR AFFECTING 1 LINE
----------------------------------------------------------------------
 1 | ERROR | File exceeds maximum line count of 500; contains 623 lines
----------------------------------------------------------------------
```

**Solution:** Refactor the file into smaller, focused modules.

```php
// Before: One large class (623 lines)
class NotionSync {
    // Too many responsibilities
}

// After: Split into focused classes
class NotionSync {
    private NotionApiClient $api;
    private ContentConverter $converter;
    private MediaImporter $media;
}
```

#### 2. Missing Nonce Verification

**Error:**

```
Processing form data without nonce verification.
```

**Solution:**

```php
// Add nonce verification
if ( ! isset( $_POST['notion_wp_nonce'] ) ||
     ! wp_verify_nonce( $_POST['notion_wp_nonce'], 'notion_wp_action' ) ) {
    wp_die( 'Security check failed' );
}
```

#### 3. Unsanitized Input

**Error:**

```
Detected usage of a non-sanitized input variable
```

**Solution:**

```php
// Before
$api_key = $_POST['api_key'];

// After
$api_key = sanitize_text_field( wp_unslash( $_POST['api_key'] ) );
```

#### 4. Missing Text Domain

**Error:**

```
Text strings must be translatable
```

**Solution:**

```php
// Before
echo 'Settings saved';

// After
echo esc_html__( 'Settings saved', 'notion-wp' );
```

#### 5. Global Namespace Pollution

**Error:**

```
All functions and classes must be prefixed
```

**Solution:**

```php
// Before
function get_settings() { }

// After
function notion_wp_get_settings() { }

// Or use namespaces
namespace NotionWP;

function get_settings() { }
```

### PHPStan Common Issues

#### 1. Undefined Variables

**Error:**

```
Variable $wpdb might not be defined.
```

**Solution:**

```php
// Add proper type hints and checks
global $wpdb;

if ( ! $wpdb instanceof wpdb ) {
    return;
}

// Now PHPStan knows $wpdb is defined and typed
$wpdb->query( '...' );
```

#### 2. Missing Return Types

**Error:**

```
Method has no return type specified
```

**Solution:**

```php
// Before
public function get_api_key() {
    return get_option( 'notion_wp_api_key' );
}

// After
public function get_api_key(): string {
    return get_option( 'notion_wp_api_key', '' );
}
```

#### 3. Mixed Types

**Error:**

```
Parameter type expects string, mixed given
```

**Solution:**

```php
// Add type validation
$value = get_option( 'some_option' );

if ( ! is_string( $value ) ) {
    return;
}

// Now PHPStan knows $value is a string
$this->process_string( $value );
```

## JavaScript/TypeScript Linting

### Tools Used

1. **ESLint** - WordPress JavaScript standards
2. **Prettier** - Code formatting

### Configuration Files

- `.eslintrc.json` - ESLint rules
- `.prettierrc` - Prettier configuration
- `.prettierignore` - Files to exclude from formatting

### Available Commands

```bash
# Run all JS/CSS linting
npm run lint

# Run individual tools
npm run lint:js          # Check JavaScript/TypeScript
npm run lint:css         # Check CSS/SCSS
npm run format:check     # Check Prettier formatting

# Auto-fix issues
npm run lint:fix         # Fix all issues
npm run lint:js:fix      # Fix JS issues only
npm run lint:css:fix     # Fix CSS issues only
npm run format           # Format with Prettier
```

### Common JavaScript Issues

#### 1. Console Statements in Production

**Error:**

```
Unexpected console statement (no-console)
```

**Solution:**

```javascript
// Before
console.log('Debug info');

// After (if needed for debugging)
if (process.env.NODE_ENV === 'development') {
	console.log('Debug info');
}

// Or use allowed methods
console.error('Error occurred'); // Allowed
console.warn('Warning'); // Allowed
```

#### 2. Missing JSDoc

**Error:**

```
Missing JSDoc comment
```

**Solution:**

```javascript
/**
 * Fetches pages from Notion API.
 *
 * @param {string} apiKey - The Notion API key.
 * @param {number} limit  - Maximum pages to fetch.
 * @return {Promise<Array>} Array of Notion pages.
 */
async function fetchPages(apiKey, limit = 10) {
	// Implementation
}
```

#### 3. Unused Variables

**Error:**

```
'response' is defined but never used
```

**Solution:**

```javascript
// If intentionally unused, prefix with underscore
async function fetchData() {
	const _response = await fetch(url); // Intentionally unused
	// Or remove it if truly not needed
}
```

#### 4. Missing Text Domain in i18n

**Error:**

```
Missing text domain in translation function
```

**Solution:**

```javascript
// Before
const message = __('Save settings');

// After
const message = __('Save settings', 'notion-wp');
```

## CSS/SCSS Linting

### Tools Used

1. **Stylelint** - WordPress CSS standards

### Configuration Files

- `.stylelintrc.json` - Stylelint rules with property ordering

### Available Commands

```bash
# Check CSS/SCSS
npm run lint:css

# Auto-fix CSS/SCSS
npm run lint:css:fix
```

### Common CSS Issues

#### 1. Using !important

**Warning:**

```
Avoid using !important (declaration-no-important)
```

**Solution:**

```css
/* Before */
.element {
	color: red !important;
}

/* After - Increase specificity instead */
.parent .element {
	color: red;
}

/* Or document why !important is necessary */
.element {
	/* !important required to override inline styles from third-party plugin */
	color: red !important;
}
```

#### 2. Property Order

**Error:**

```
Expected "display" to come before "color"
```

**Solution:** Properties should be ordered logically (position, box model, typography, visual, animation). Stylelint will auto-fix this with `npm run lint:css:fix`.

```css
/* Auto-fixed order */
.element {
	/* Position */
	position: relative;
	top: 0;
	z-index: 10;

	/* Box Model */
	display: flex;
	width: 100%;
	padding: 1rem;

	/* Typography */
	color: #333;
	font-size: 1rem;

	/* Visual */
	background: white;
	border: 1px solid #ccc;
}
```

#### 3. Too Deep Nesting

**Warning:**

```
Expected nesting depth to be no more than 3 (max-nesting-depth)
```

**Solution:**

```scss
// Before - 4 levels deep
.parent {
	.child {
		.grandchild {
			.great-grandchild {
				// Too deep!
				color: red;
			}
		}
	}
}

// After - Use BEM naming
.parent {
}
.parent__child {
}
.parent__grandchild {
}
.parent__great-grandchild {
	color: red;
}
```

## Pre-commit Hooks

Pre-commit hooks automatically run linters before each commit and auto-fix what they can.

### How It Works

1. You run `git commit`
2. Husky triggers the pre-commit hook
3. Staged files are linted:
    - PHP files: PHP-CS-Fixer → PHPCS → PHPStan
    - JS files: ESLint → Prettier
    - CSS files: Stylelint → Prettier
4. Auto-fixable issues are corrected and re-staged
5. If any errors remain, the commit is blocked
6. You fix the errors and commit again

### What Gets Checked

- **PHP files:** PHPCS, PHPStan, PHP-CS-Fixer
- **JS/TS files:** ESLint, Prettier
- **CSS/SCSS files:** Stylelint, Prettier
- **JSON/MD/YAML:** Prettier formatting

### Bypassing Pre-commit Hooks

**Warning:** Only bypass hooks in emergencies!

```bash
# Skip pre-commit hooks (NOT RECOMMENDED)
git commit --no-verify -m "Emergency fix"
```

### Disabling Specific Hooks

Edit `.husky/pre-commit` to comment out specific checks:

```bash
# Temporarily disable PHPStan
# if ! composer lint:phpstan:quiet 2>&1; then
#     echo "  ✗ PHPStan found issues"
#     HAS_ERRORS=1
# fi
```

## IDE Integration

### Visual Studio Code

#### Recommended Extensions

Install these extensions:

```json
{
	"recommendations": [
		"bmewburn.vscode-intelephense-client",
		"wongjn.php-sniffer",
		"swordev.phpstan",
		"junstyle.php-cs-fixer",
		"dbaeumer.vscode-eslint",
		"esbenp.prettier-vscode",
		"stylelint.vscode-stylelint"
	]
}
```

#### Settings Configuration

Create `.vscode/settings.json`:

```json
{
	// PHP Settings
	"php.validate.executablePath": "/usr/bin/php",
	"phpSniffer.standard": "WordPress",
	"phpSniffer.autoDetect": true,
	"phpstan.enabled": true,
	"phpstan.level": "5",

	// Format on Save
	"editor.formatOnSave": true,
	"editor.codeActionsOnSave": {
		"source.fixAll.eslint": true,
		"source.fixAll.stylelint": true
	},

	// ESLint
	"eslint.validate": [
		"javascript",
		"javascriptreact",
		"typescript",
		"typescriptreact"
	],

	// Prettier
	"prettier.requireConfig": true,
	"prettier.useEditorConfig": false,

	// Stylelint
	"stylelint.validate": ["css", "scss", "sass"],
	"css.validate": false,
	"scss.validate": false
}
```

#### Keyboard Shortcuts

Add to `.vscode/keybindings.json`:

```json
[
	{
		"key": "cmd+shift+l",
		"command": "workbench.action.tasks.runTask",
		"args": "Lint All"
	}
]
```

### PhpStorm

#### Configuration

1. **PHPCS:**
    - Settings → PHP → Quality Tools → PHP_CodeSniffer
    - Configuration: `/path/to/project/vendor/bin/phpcs`
    - Coding standard: WordPress

2. **PHPStan:**
    - Settings → PHP → Quality Tools → PHPStan
    - Configuration: `/path/to/project/vendor/bin/phpstan`
    - Configuration file: `phpstan.neon`

3. **ESLint:**
    - Settings → Languages & Frameworks → JavaScript → Code Quality Tools → ESLint
    - Automatic ESLint configuration
    - Run eslint --fix on save: ✓

4. **Prettier:**
    - Settings → Languages & Frameworks → JavaScript → Prettier
    - Prettier package: `./node_modules/prettier`
    - Run on save: ✓

#### File Watchers

Set up file watchers for auto-formatting:

- Settings → Tools → File Watchers
- Add: PHP CS Fixer, ESLint, Stylelint

## Troubleshooting

### "Command not found: composer"

**Solution:**

```bash
# Install Composer globally
# macOS/Linux:
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
sudo mv composer.phar /usr/local/bin/composer

# Verify
composer --version
```

### "Command not found: phpcs"

**Solution:**

```bash
# Install Composer dependencies
composer install

# Verify
./vendor/bin/phpcs --version
```

### "npm install fails"

**Solution:**

```bash
# Clear npm cache
npm cache clean --force

# Delete node_modules and package-lock.json
rm -rf node_modules package-lock.json

# Reinstall
npm install
```

### "Pre-commit hook not executing"

**Solution:**

```bash
# Make hooks executable
chmod +x .husky/pre-commit
chmod +x .husky/commit-msg

# Reinstall husky
npm run prepare
```

### "PHPStan out of memory"

**Error:**

```
Fatal error: Allowed memory size exhausted
```

**Solution:**

```bash
# Increase memory limit
composer lint:phpstan -- --memory-limit=2G

# Or edit phpstan.neon to reduce scope
```

### "PHPCS taking too long"

**Solution:**

```bash
# Run on specific files only
./vendor/bin/phpcs plugin/src/SpecificFile.php

# Use parallel processing (already enabled in phpcs.xml.dist)
# Ensure your system supports it
```

### "ESLint cache issues"

**Solution:**

```bash
# Clear ESLint cache
rm -rf node_modules/.cache

# Or disable cache
npm run lint:js -- --no-cache
```

### "Conflicting rules between tools"

Some rules may conflict (e.g., PHPCS vs PHP-CS-Fixer). The pre-commit hook runs them in order of priority:

1. PHP-CS-Fixer (auto-fixes)
2. PHPCS (checks standards)
3. PHPStan (static analysis)

If you get conflicting errors, prioritize PHPCS rules and adjust `.php-cs-fixer.php` to match.

## CI/CD Integration

### GitHub Actions

The project includes a GitHub Actions workflow (`.github/workflows/lint.yml`) that:

- Runs on every pull request and push to `main`/`develop`
- Tests against PHP 8.0, 8.1, 8.2, 8.3
- Runs all linting tools
- Checks file size limits (500 lines)
- Blocks merge if any checks fail

### Viewing CI Results

1. Go to your PR on GitHub
2. Scroll to the "Checks" section
3. Click "Details" on any failed check
4. Review the error output
5. Fix locally and push again

### Local CI Simulation

To simulate CI locally before pushing:

```bash
# Run all checks like CI does
composer check
npm run lint:all

# Check file sizes
find plugin -type f \( -name "*.php" -o -name "*.js" \) -exec wc -l {} \; | awk '$1 > 500'
```

## Best Practices

### 1. Lint Early and Often

```bash
# Before starting work
git pull
composer install
npm install

# While working (every 30-60 minutes)
composer lint
npm run lint

# Before committing
composer lint:fix
npm run lint:fix
git add .
git commit
```

### 2. Fix Issues Immediately

Don't accumulate linting errors. Fix them as they appear:

```bash
# See what's wrong
composer lint:phpcs

# Fix it
composer lint:fix

# Verify
composer lint:phpcs
```

### 3. Understand the Rules

Don't just auto-fix blindly. Understand WHY a rule exists:

- Read WordPress Coding Standards: https://developer.wordpress.org/coding-standards/
- Check PHPStan rule details: https://phpstan.org/
- Review ESLint rules: https://eslint.org/docs/rules/

### 4. Keep Configuration Updated

```bash
# Update linting tools quarterly
composer update --dev
npm update --dev

# Review changelogs for breaking changes
```

### 5. Document Exceptions

If you must violate a rule, document why:

```php
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- AJAX handler verified by custom middleware
$action = $_GET['action'];
// phpcs:enable
```

```javascript
// eslint-disable-next-line no-console -- Debugging production issue #1234
console.log('Critical debug info');
```

## Resources

### Documentation

- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [PHPStan Documentation](https://phpstan.org/user-guide/getting-started)
- [PHP-CS-Fixer Documentation](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer)
- [ESLint Rules](https://eslint.org/docs/rules/)
- [Stylelint Rules](https://stylelint.io/user-guide/rules/)
- [Prettier Options](https://prettier.io/docs/en/options.html)

### WordPress-Specific

- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WordPress JavaScript Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/javascript/)
- [WordPress CSS Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/css/)

### Getting Help

If you encounter issues not covered here:

1. Check the tool's official documentation
2. Search existing GitHub issues in the tool's repository
3. Ask in the team's development channel
4. File an issue in this repository with:
    - Full error message
    - Steps to reproduce
    - Your environment (PHP version, Node version, OS)

---

**Last Updated:** 2024-10-19
**Maintained By:** Development Team
