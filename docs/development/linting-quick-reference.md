# Linting Quick Reference

This guide provides quick access to linting commands, common error fixes, and emergency procedures for the Notion-WP plugin development.

## Table of Contents

- [Common Commands](#common-commands)
- [Configuration Files](#configuration-files)
- [Common Errors & Fixes](#common-errors--fixes)
- [Emergency Bypass Procedures](#emergency-bypass-procedures)
- [IDE Setup](#ide-setup)
- [Pre-commit Hooks](#pre-commit-hooks)
- [Troubleshooting](#troubleshooting)

---

## Common Commands

### PHP Linting

```bash
# Run all PHP linters (PHPCS + PHPStan)
composer lint

# Run only PHPCS (code style)
composer lint:phpcs

# Auto-fix PHPCS issues
composer lint:phpcbf

# Run only PHPStan (static analysis)
composer lint:phpstan

# Auto-fix all PHP issues
composer lint:fix

# Run all checks (lint + analyze)
composer check
```

### JavaScript/CSS Linting

```bash
# Run all linters (JS + CSS)
npm run lint

# Run only JavaScript/TypeScript linting
npm run lint:js

# Auto-fix JavaScript issues
npm run lint:js:fix

# Run only CSS/SCSS linting
npm run lint:css

# Auto-fix CSS issues
npm run lint:css:fix

# Auto-fix all JS/CSS issues
npm run lint:fix

# Run Prettier formatting
npm run format

# Check Prettier formatting without fixing
npm run format:check
```

### Combined Workflow

```bash
# Check everything
composer lint && npm run lint

# Fix everything
composer lint:fix && npm run lint:fix
```

---

## Configuration Files

### PHP Configuration

| File                     | Purpose                 | Key Settings                                    |
| ------------------------ | ----------------------- | ----------------------------------------------- |
| `phpcs.xml.dist`         | PHP_CodeSniffer rules   | WordPress Coding Standards, 500-line file limit |
| `phpstan.neon`           | PHPStan static analysis | Level 5, WordPress stubs                        |
| `.php-cs-fixer.dist.php` | PHP-CS-Fixer formatting | PSR-12, WordPress compatibility                 |

### JavaScript/CSS Configuration

| File                | Purpose             | Key Settings                     |
| ------------------- | ------------------- | -------------------------------- |
| `.eslintrc.json`    | ESLint rules        | WordPress preset, no console.log |
| `.stylelintrc.json` | Stylelint rules     | WordPress CSS standards          |
| `.prettierrc.json`  | Prettier formatting | WordPress defaults               |

### Git Hooks

| File                | Purpose                         |
| ------------------- | ------------------------------- |
| `.husky/pre-commit` | Runs linters before each commit |
| `.husky/commit-msg` | Validates commit message format |

---

## Common Errors & Fixes

### PHP Errors

#### Error: "File exceeds 500 lines"

**Fix:** Refactor the file into smaller, focused files.

```bash
# This is enforced by Generic.Files.LineCount rule
# Maximum: 500 lines per file (see principles.md)
```

**Solution:**

1. Extract classes/functions into separate files
2. Use proper PSR-4 autoloading
3. Keep one class per file
4. Move helpers to dedicated utility files

#### Error: "Missing nonce verification"

```php
// ❌ Bad
if ( isset( $_POST['action'] ) ) {
    update_option( 'my_option', $_POST['value'] );
}

// ✅ Good
if ( isset( $_POST['action'] ) && check_admin_referer( 'my_action_nonce' ) ) {
    update_option( 'my_option', sanitize_text_field( wp_unslash( $_POST['value'] ) ) );
}
```

**Fix:** Add nonce verification to all form submissions.

#### Error: "Output not escaped"

```php
// ❌ Bad
echo $user_input;
echo '<div>' . $data . '</div>';

// ✅ Good
echo esc_html( $user_input );
echo '<div>' . esc_html( $data ) . '</div>';
echo '<a href="' . esc_url( $link ) . '">' . esc_html( $text ) . '</a>';
```

**Fix:** Use appropriate escaping functions:

- `esc_html()` - For HTML content
- `esc_attr()` - For HTML attributes
- `esc_url()` - For URLs
- `esc_js()` - For JavaScript
- `wp_kses_post()` - For HTML with allowed tags

#### Error: "Input not sanitized"

```php
// ❌ Bad
$value = $_POST['field'];
update_option( 'my_option', $_POST['value'] );

// ✅ Good
$value = sanitize_text_field( wp_unslash( $_POST['field'] ) );
update_option( 'my_option', sanitize_text_field( wp_unslash( $_POST['value'] ) ) );
```

**Fix:** Use appropriate sanitization functions:

- `sanitize_text_field()` - For text inputs
- `sanitize_email()` - For email addresses
- `sanitize_url()` - For URLs
- `absint()` - For positive integers
- `wp_kses_post()` - For HTML content

#### Error: "Missing text domain"

```php
// ❌ Bad
__( 'Some text' );
_e( 'Some text' );

// ✅ Good
__( 'Some text', 'notion-wp' );
_e( 'Some text', 'notion-wp' );
```

**Fix:** Always include the `'notion-wp'` text domain in i18n functions.

#### Error: "Use of undefined variable"

**PHPStan Error:** Variable might not be defined

```php
// ❌ Bad
if ( $condition ) {
    $value = 'something';
}
echo $value; // Might not be defined

// ✅ Good
$value = '';
if ( $condition ) {
    $value = 'something';
}
echo $value;
```

**Fix:** Initialize variables before use or use null coalescing.

### JavaScript Errors

#### Error: "Unexpected console statement"

```javascript
// ❌ Bad
console.log('Debug info');

// ✅ Good - Use for errors/warnings only
console.error('Error occurred');
console.warn('Warning message');

// ✅ Best - Remove before commit
// (No console statements in production code)
```

**Fix:** Remove `console.log()` statements. Use `console.error()` or `console.warn()` only when necessary.

#### Error: "no-undef - Undefined variable"

```javascript
// ❌ Bad
jQuery(document).ready(function () {
	// 'jQuery' might not be defined
});

// ✅ Good
/* global jQuery */
jQuery(document).ready(function ($) {
	// Now jQuery is recognized
});

// ✅ Better - Use WordPress wrapper
(function ($) {
	$(document).ready(function () {
		// Safe to use $
	});
})(jQuery);
```

**Fix:** Declare globals or use proper WordPress JavaScript patterns.

#### Error: "Missing JSDoc comment"

```javascript
// ❌ Bad
function calculateTotal(price, tax) {
	return price + tax;
}

// ✅ Good
/**
 * Calculate the total price including tax.
 *
 * @param {number} price - The base price.
 * @param {number} tax   - The tax amount.
 * @return {number} The total price.
 */
function calculateTotal(price, tax) {
	return price + tax;
}
```

**Fix:** Add JSDoc comments to all functions.

### CSS Errors

#### Error: "Unexpected !important"

```css
/* ❌ Bad */
.my-class {
	color: red !important;
}

/* ✅ Good - Document why !important is needed */
.my-class {
	/* !important needed to override WordPress core styles */
	color: red !important;
}

/* ✅ Best - Avoid !important by increasing specificity */
.parent .my-class {
	color: red;
}
```

**Fix:** Avoid `!important` or document why it's necessary.

#### Error: "Unexpected ID selector"

```css
/* ❌ Bad */
#my-id {
	color: red;
}

/* ✅ Good - Use classes */
.my-class {
	color: red;
}
```

**Fix:** Use classes instead of IDs for styling.

---

## Emergency Bypass Procedures

### WARNING: Use with Extreme Caution

These procedures should ONLY be used in genuine emergencies and must be documented.

### Bypass Pre-commit Hook

```bash
# ⚠️ EMERGENCY ONLY - Commit without running linters
git commit --no-verify -m "Emergency fix: Brief description"

# ⚠️ Must fix linting issues in the next commit
# CI will still fail if linting doesn't pass
```

**When to use:**

- Critical production bug fix
- Hotfix that must be deployed immediately
- Reverting a broken commit

**Requirements:**

1. Document why bypass was needed in commit message
2. Create a follow-up issue to fix linting
3. Fix linting issues in the next commit
4. Inform team of bypass

### Skip Specific PHPCS Rules

```php
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG output is safe
echo $svg_markup;
// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
```

**Use sparingly and always document why.**

### Skip Specific ESLint Rules

```javascript
// eslint-disable-next-line no-console
console.log('Debugging critical issue');
```

**Use only when absolutely necessary.**

### Temporarily Disable PHPStan

```php
/** @phpstan-ignore-next-line */
$value = $this->legacyMethod();
```

**Use only for third-party code or legacy compatibility.**

---

## IDE Setup

### Visual Studio Code

#### Recommended Extensions

Install these extensions for automatic linting in VS Code:

```bash
# PHP
code --install-extension bmewburn.vscode-intelephense-client
code --install-extension wongjn.php-sniffer

# JavaScript/TypeScript
code --install-extension dbaeumer.vscode-eslint
code --install-extension esbenp.prettier-vscode

# CSS/SCSS
code --install-extension stylelint.vscode-stylelint

# WordPress
code --install-extension wordpresstoolbox.wordpress-toolbox
```

#### VS Code Settings

Create `.vscode/settings.json`:

```json
{
	"php.validate.enable": true,
	"php.validate.run": "onType",
	"phpcs.enable": true,
	"phpcs.standard": "phpcs.xml.dist",
	"eslint.enable": true,
	"eslint.validate": [
		"javascript",
		"javascriptreact",
		"typescript",
		"typescriptreact"
	],
	"editor.formatOnSave": true,
	"editor.defaultFormatter": "esbenp.prettier-vscode",
	"[php]": {
		"editor.defaultFormatter": "wongjn.php-sniffer"
	},
	"stylelint.enable": true,
	"css.validate": false,
	"scss.validate": false
}
```

### PHPStorm / IntelliJ IDEA

#### Enable PHP_CodeSniffer

1. Go to **Settings → PHP → Quality Tools → PHP_CodeSniffer**
2. Set path to `vendor/bin/phpcs`
3. Click **Validate** to test
4. Go to **Settings → Editor → Inspections → PHP → Quality Tools**
5. Enable **PHP_CodeSniffer validation**
6. Set coding standard to `phpcs.xml.dist`

#### Enable PHPStan

1. Go to **Settings → PHP → Quality Tools → PHPStan**
2. Set path to `vendor/bin/phpstan`
3. Set configuration file to `phpstan.neon`
4. Enable **PHPStan validation**

#### Enable ESLint

1. Go to **Settings → Languages & Frameworks → JavaScript → Code Quality Tools → ESLint**
2. Select **Automatic ESLint configuration**
3. Enable **Run eslint --fix on save**

### Sublime Text

#### Install Package Control Packages

1. Open Package Control (`Cmd+Shift+P` or `Ctrl+Shift+P`)
2. Install these packages:
    - **SublimeLinter**
    - **SublimeLinter-phpcs**
    - **SublimeLinter-eslint**
    - **SublimeLinter-stylelint**

---

## Pre-commit Hooks

### How Pre-commit Hooks Work

The `.husky/pre-commit` hook runs automatically before each commit:

1. **Detects staged files** (only checks files you're committing)
2. **Runs appropriate linters** (PHP, JS, CSS)
3. **Auto-fixes** what can be fixed
4. **Blocks commit** if errors remain
5. **Re-stages** fixed files

### What Gets Checked

- **PHP files**: PHPCS, PHPStan, PHP-CS-Fixer
- **JavaScript/TypeScript**: ESLint, Prettier
- **CSS/SCSS**: Stylelint, Prettier
- **JSON/YAML/Markdown**: Prettier

### Debugging Pre-commit Issues

If pre-commit hook fails:

```bash
# See detailed output
git commit -m "Your message"
# (Hook will show specific errors)

# Run linters manually to see all issues
composer lint
npm run lint

# Fix issues
composer lint:fix
npm run lint:fix

# Try commit again
git commit -m "Your message"
```

### Reinstalling Hooks

If hooks stop working:

```bash
# Reinstall Husky
npm run prepare

# Make hook executable
chmod +x .husky/pre-commit

# Verify
ls -la .husky/
```

---

## Troubleshooting

### "phpcs: command not found"

**Cause:** Composer dependencies not installed.

**Fix:**

```bash
composer install
```

### "eslint: command not found"

**Cause:** npm dependencies not installed.

**Fix:**

```bash
npm install
```

### "Pre-commit hook not running"

**Possible causes:**

1. **Not a Git repository**

    ```bash
    git init
    ```

2. **Husky not installed**

    ```bash
    npm run prepare
    ```

3. **Hook not executable**

    ```bash
    chmod +x .husky/pre-commit
    ```

4. **Wrong directory**
    ```bash
    # Hooks only run at repository root
    cd /path/to/notion-wp
    git commit
    ```

### "PHPStan: Out of memory"

**Fix:** Increase memory limit in the command:

```bash
composer lint:phpstan -- --memory-limit=2G
```

Or update `composer.json`:

```json
{
	"scripts": {
		"lint:phpstan": "phpstan analyse --memory-limit=2G"
	}
}
```

### "PHPCS taking too long"

**Fix:** Use parallel processing (already configured):

```xml
<!-- In phpcs.xml.dist -->
<arg name="parallel" value="8"/>
```

Or run on specific files only:

```bash
vendor/bin/phpcs plugin/src/Admin/SettingsPage.php
```

### "Linting passes locally but fails in CI"

**Possible causes:**

1. **Different PHP/Node versions**
    - Local: PHP 8.2, CI: PHP 8.0
    - Fix: Use Docker for consistent environment

2. **Dependencies not committed**
    - composer.lock or package-lock.json out of sync
    - Fix: Commit lock files

3. **Files not committed**
    - Configuration files missing
    - Fix: Ensure all config files are tracked

### "VS Code not showing lint errors inline"

**Fix:**

1. Restart VS Code
2. Check extension is installed and enabled
3. Check `.vscode/settings.json` exists
4. Check Output panel for errors (View → Output → Select extension)
5. Reload window: `Cmd/Ctrl+Shift+P` → "Reload Window"

---

## Quick Reference Card

### Most Common Commands

```bash
# Before starting work
composer install && npm install

# Before committing
composer lint:fix && npm run lint:fix

# Check everything
composer check && npm run lint

# Emergency bypass (document why!)
git commit --no-verify -m "Emergency: reason"
```

### File Size Limit

**CRITICAL:** Maximum 500 lines per file (enforced by PHPCS)

- **Why:** Keeps code maintainable and testable
- **Fix:** Refactor into smaller, focused files
- **Exceptions:** Configuration files only

### Text Domain

Always use `'notion-wp'` for i18n functions:

```php
__( 'Text', 'notion-wp' )
_e( 'Text', 'notion-wp' )
esc_html__( 'Text', 'notion-wp' )
```

### Security Checklist

- [ ] All output escaped
- [ ] All input sanitized
- [ ] Nonces verified on forms
- [ ] Capability checks on admin pages
- [ ] No SQL injection vectors

---

## Resources

### Official Documentation

- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer/wiki)
- [PHPStan](https://phpstan.org/user-guide/getting-started)
- [ESLint](https://eslint.org/docs/latest/)
- [Stylelint](https://stylelint.io/user-guide/get-started)

### Project Documentation

- [Development Principles](./principles.md)
- [Phase 0 Plan](../plans/phase-0.md)
- [Project README](../../README.md)

### Getting Help

1. Run verification script: `./scripts/verify-setup.sh`
2. Check linter output for specific errors
3. Search WordPress.org forums
4. Check Stack Overflow
5. Ask in project discussions

---

**Last Updated:** 2025-10-19
**Version:** 1.0
**Maintained by:** The VGER Group
