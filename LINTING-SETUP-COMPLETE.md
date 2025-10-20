# Linting Infrastructure Setup - Complete ✅

This document summarizes the complete linting and code quality infrastructure that has been set up for the Notion-WP plugin.

## 📋 What Was Installed

### Configuration Files Created

#### PHP Linting
- ✅ `phpcs.xml.dist` - WordPress Coding Standards configuration
- ✅ `phpstan.neon` - PHPStan level 5 static analysis
- ✅ `.php-cs-fixer.php` - Auto-formatting rules
- ✅ `tests/bootstrap.php` - PHPStan WordPress stubs loader

#### JavaScript/TypeScript Linting
- ✅ `.eslintrc.json` - ESLint with WordPress preset
- ✅ `.prettierrc` - Prettier code formatting
- ✅ `.prettierignore` - Files to exclude from formatting

#### CSS/SCSS Linting
- ✅ `.stylelintrc.json` - Stylelint with WordPress standards

#### Pre-commit Hooks
- ✅ `.husky/pre-commit` - Runs all linters before commit
- ✅ `.husky/commit-msg` - Enforces conventional commit format

#### Dependency Management
- ✅ `composer.json` - PHP dependencies and scripts
- ✅ `package.json` - Node dependencies and scripts

#### CI/CD
- ✅ `.github/workflows/lint.yml` - GitHub Actions workflow
  - Tests PHP 8.0, 8.1, 8.2, 8.3
  - Runs all linters
  - Checks 500-line file limit
  - Blocks PRs on failures

#### IDE Integration
- ✅ `.vscode/settings.json` - VS Code configuration
- ✅ `.vscode/extensions.json` - Recommended extensions
- ✅ `.editorconfig` - Cross-editor consistency

#### Documentation
- ✅ `docs/development/linting.md` - Comprehensive guide (18KB)
- ✅ `LINTING-QUICKSTART.md` - Fast reference guide

#### Other
- ✅ `.gitignore` - Updated with linting caches

## 🚀 Getting Started

### 1. Install Dependencies (First Time)

```bash
# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Setup pre-commit hooks
npm run prepare

# Make hooks executable (Unix/macOS)
chmod +x .husky/pre-commit
chmod +x .husky/commit-msg
```

### 2. Verify Installation

```bash
# Check PHP linters work
composer lint

# Check JS/CSS linters work
npm run lint

# You should see output from all tools
```

### 3. Start Coding

All linters will run automatically on commit. See `LINTING-QUICKSTART.md` for daily usage.

## 🛠 Tools Installed

### PHP Tools

| Tool | Purpose | Version |
|------|---------|---------|
| PHP_CodeSniffer | WordPress Coding Standards | ^3.8 |
| WPCS | WordPress rulesets | ^3.0 |
| PHPStan | Static analysis (Level 5) | ^1.10 |
| PHP-CS-Fixer | Auto-formatting | ^3.40 |
| PHPCompatibility | PHP 8.0+ compatibility | ^9.3 |

### JavaScript/CSS Tools

| Tool | Purpose |
|------|---------|
| ESLint | JavaScript linting |
| @wordpress/eslint-plugin | WordPress JS standards |
| Prettier | Code formatting |
| Stylelint | CSS/SCSS linting |
| stylelint-config-wordpress | WordPress CSS standards |

### Pre-commit Tools

| Tool | Purpose |
|------|---------|
| Husky | Git hooks manager |
| lint-staged | Run linters on staged files |

## 📏 Code Quality Standards Enforced

### File Size Limit
- ✅ **Maximum 500 lines per file** (enforced in CI and locally)
- ✅ Checked automatically in GitHub Actions
- ✅ Configurable in `phpcs.xml.dist`

### PHP Standards
- ✅ WordPress Coding Standards (Core, Docs, Extra)
- ✅ PHPStan Level 5 static analysis
- ✅ PHP 8.0+ compatibility
- ✅ PSR-12 code style
- ✅ Nonce verification required
- ✅ Input sanitization required
- ✅ Prepared SQL statements required
- ✅ Global namespace prefixing (`notion_wp_`)

### JavaScript Standards
- ✅ WordPress JavaScript coding standards
- ✅ No `console.log` in production (only `console.error/warn`)
- ✅ JSDoc comments required
- ✅ ES2021+ features allowed
- ✅ React hooks rules (if using React)
- ✅ Text domain required for i18n: `'notion-wp'`

### CSS Standards
- ✅ WordPress CSS standards
- ✅ Property ordering enforced
- ✅ `!important` discouraged (warnings)
- ✅ Max 3-level nesting depth
- ✅ No duplicate selectors
- ✅ SCSS support included

### Commit Message Format
- ✅ Conventional Commits enforced
- ✅ Format: `type(scope): subject`
- ✅ Types: feat, fix, docs, style, refactor, perf, test, chore, build, ci, revert
- ✅ Example: `feat(sync): add notion to wordpress sync`

## 🔄 Automated Workflows

### Pre-commit (Automatic)

When you run `git commit`, these checks run automatically:

1. **PHP files:**
   - PHP-CS-Fixer auto-fixes issues
   - PHPCS checks WordPress standards
   - PHPStan runs static analysis
   - Fixed files are re-staged

2. **JavaScript files:**
   - ESLint auto-fixes issues
   - Prettier formats code
   - Fixed files are re-staged

3. **CSS files:**
   - Stylelint auto-fixes issues
   - Prettier formats code
   - Fixed files are re-staged

4. **Commit blocked if errors remain**

### GitHub Actions (On Every PR)

1. **PHP Linting Job:**
   - Matrix test: PHP 8.0, 8.1, 8.2, 8.3
   - Run PHPCS
   - Run PHPStan
   - Run PHP-CS-Fixer dry-run

2. **JavaScript/CSS Linting Job:**
   - Run ESLint
   - Run Stylelint
   - Check Prettier formatting

3. **File Size Check Job:**
   - Find all files > 500 lines
   - Fail if any found

4. **Summary Job:**
   - Aggregate results
   - Block PR if any job failed

## 📝 Available Commands

### PHP Commands

```bash
composer lint                    # Run all PHP checks
composer lint:phpcs             # WordPress Coding Standards
composer lint:phpstan           # Static analysis
composer lint:fix               # Auto-fix all issues
composer check                  # Run all checks (lint + analyze)
```

### JavaScript/CSS Commands

```bash
npm run lint                    # Check all JS/CSS
npm run lint:js                 # Check JavaScript only
npm run lint:css                # Check CSS only
npm run lint:fix                # Fix all JS/CSS issues
npm run format                  # Format with Prettier
npm run lint:all                # Run everything including format check
```

### Combined Commands

```bash
# Check everything
composer lint && npm run lint

# Fix everything
composer lint:fix && npm run lint:fix
```

## 🎯 What Gets Auto-Fixed

These issues are **automatically corrected** by the tools:

### PHP Auto-fixes (PHP-CS-Fixer + PHPCBF)
- ✅ Indentation (tabs for PHP)
- ✅ Line endings (Unix style)
- ✅ Trailing whitespace
- ✅ Import statement ordering
- ✅ Brace positioning
- ✅ Operator spacing
- ✅ Array syntax (short `[]` vs `array()`)

### JavaScript Auto-fixes (ESLint + Prettier)
- ✅ Indentation (tabs, 2-space equivalent)
- ✅ Semicolons
- ✅ Quote style (single quotes)
- ✅ Trailing commas
- ✅ Line length wrapping
- ✅ Import ordering

### CSS Auto-fixes (Stylelint + Prettier)
- ✅ Property ordering (position → box model → typography → visual → animation)
- ✅ Indentation
- ✅ Color format normalization
- ✅ Vendor prefix ordering

## 🚫 What Gets Blocked

Commits will be **blocked** if:

- ❌ PHPCS finds coding standard violations
- ❌ PHPStan finds type errors
- ❌ ESLint finds JavaScript errors
- ❌ Stylelint finds CSS errors
- ❌ Files exceed 500 lines
- ❌ Commit message doesn't follow format

Pull requests will be **blocked** in CI if:

- ❌ Any of the above checks fail
- ❌ Code doesn't work on PHP 8.0, 8.1, 8.2, or 8.3

## 🔧 IDE Integration

### VS Code

**Recommended Extensions** (`.vscode/extensions.json`):
- PHP Intelephense
- PHP Sniffer
- PHPStan
- PHP CS Fixer
- ESLint
- Prettier
- Stylelint
- EditorConfig

**Auto-configured** (`.vscode/settings.json`):
- Format on save enabled
- ESLint auto-fix on save
- Stylelint auto-fix on save
- PHP-CS-Fixer on save
- WordPress stubs loaded
- Proper indentation per file type

### PhpStorm

See `docs/development/linting.md` for PhpStorm setup instructions.

## 📚 Documentation

### Quick Reference
- `LINTING-QUICKSTART.md` - Fast commands and common tasks

### Comprehensive Guide
- `docs/development/linting.md` - Full documentation including:
  - Common error solutions
  - IDE setup guides (VS Code, PhpStorm)
  - Troubleshooting
  - Best practices
  - Rule explanations

### Principles
- `docs/development/principles.md` - Development standards and requirements

## 🎓 Learning Resources

### WordPress Standards
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [WordPress JavaScript Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/javascript/)
- [WordPress CSS Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/css/)

### Tool Documentation
- [PHPStan Documentation](https://phpstan.org/user-guide/getting-started)
- [PHP-CS-Fixer Rules](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer)
- [ESLint Rules](https://eslint.org/docs/rules/)
- [Stylelint Rules](https://stylelint.io/user-guide/rules/)
- [Conventional Commits](https://www.conventionalcommits.org/)

## 🐛 Troubleshooting

### Common Issues

1. **"Command not found: composer"**
   ```bash
   # Install Composer: https://getcomposer.org/
   ```

2. **"Command not found: phpcs"**
   ```bash
   composer install
   ```

3. **Pre-commit hooks not running**
   ```bash
   chmod +x .husky/pre-commit
   npm run prepare
   ```

4. **PHPStan out of memory**
   ```bash
   composer lint:phpstan -- --memory-limit=2G
   ```

See `docs/development/linting.md` for more troubleshooting.

## ✅ Verification Checklist

After setup, verify everything works:

- [ ] `composer install` succeeds
- [ ] `npm install` succeeds
- [ ] `composer lint` runs without errors (on empty codebase)
- [ ] `npm run lint` runs without errors
- [ ] `git commit` triggers pre-commit hooks
- [ ] Pre-commit hook blocks commits with intentional errors
- [ ] VS Code shows linting errors inline (if using VS Code)
- [ ] File-on-save formatting works (if configured in IDE)

## 🎯 Next Steps

1. **Start coding:**
   - Create your first PHP file in `plugin/src/`
   - Write code following WordPress standards
   - Commit and watch linters work

2. **Review examples:**
   - See `docs/development/linting.md` for common error examples
   - Learn how to fix typical issues

3. **Configure IDE:**
   - Install recommended VS Code extensions
   - Or setup PhpStorm following the guide

4. **Read principles:**
   - Review `docs/development/principles.md`
   - Understand the 500-line limit
   - Learn the development workflow

## 📞 Getting Help

If you encounter issues:

1. Check `docs/development/linting.md` troubleshooting section
2. Search the tool's GitHub issues
3. Ask in the team's development channel
4. File an issue with full error details

## 🎉 Summary

You now have a **production-ready linting infrastructure** that:

- ✅ Enforces WordPress coding standards automatically
- ✅ Blocks commits with quality issues
- ✅ Auto-fixes formatting on commit
- ✅ Tests against multiple PHP versions in CI
- ✅ Provides comprehensive IDE integration
- ✅ Maintains code quality at scale

**Happy coding!** 🚀

---

**Setup Date:** 2024-10-19
**Maintained By:** Development Team
**Next Review:** Quarterly (update dependencies)
