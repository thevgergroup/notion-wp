# Linting Quick Start Guide

Fast reference for the Notion-WP linting infrastructure.

## 🚀 Initial Setup (One Time)

```bash
# Install dependencies
composer install
npm install

# Setup pre-commit hooks
npm run prepare

# Make hooks executable (Unix/macOS)
chmod +x .husky/pre-commit
chmod +x .husky/commit-msg
```

## ✅ Daily Usage

### Before You Start Coding

```bash
# Update dependencies
composer install
npm install

# Verify linters work
composer lint
npm run lint
```

### While Coding

```bash
# Quick check (run every 30-60 minutes)
composer lint          # PHP only
npm run lint          # JS/CSS only

# Auto-fix issues
composer lint:fix     # Fix PHP
npm run lint:fix      # Fix JS/CSS
```

### Before Committing

```bash
# Auto-fix everything
composer lint:fix && npm run lint:fix

# Verify all checks pass
composer check
npm run lint:all

# Commit (pre-commit hooks run automatically)
git add .
git commit -m "feat(sync): add notion sync feature"
```

## 📋 Common Commands

### PHP Linting

```bash
composer lint                    # Run all PHP checks
composer lint:phpcs             # WordPress Coding Standards
composer lint:phpstan           # Static analysis
composer lint:fix               # Auto-fix all issues
composer lint:phpcbf            # Fix PHPCS issues
composer lint:php-cs-fixer:fix  # Fix PHP-CS-Fixer issues
```

### JavaScript/CSS Linting

```bash
npm run lint            # Check all JS/CSS
npm run lint:js         # Check JavaScript only
npm run lint:css        # Check CSS only
npm run lint:fix        # Fix all JS/CSS issues
npm run format          # Format with Prettier
```

### All Linting

```bash
# Check everything
composer lint && npm run lint

# Fix everything
composer lint:fix && npm run lint:fix
```

## 🔧 Troubleshooting

### Pre-commit hooks not running?

```bash
chmod +x .husky/pre-commit
npm run prepare
```

### "Command not found: phpcs"?

```bash
composer install
```

### "Command not found: eslint"?

```bash
npm install
```

### PHPStan out of memory?

```bash
composer lint:phpstan -- --memory-limit=2G
```

### Linting too slow?

```bash
# Check specific files only
./vendor/bin/phpcs plugin/src/YourFile.php
npx eslint plugin/assets/js/your-file.js
```

## 🚫 What Gets Blocked

Pre-commit hooks will **block** commits if:

- ❌ PHPCS finds coding standard violations
- ❌ PHPStan finds type errors
- ❌ ESLint finds JavaScript errors
- ❌ Stylelint finds CSS errors
- ❌ Files exceed 500 lines
- ❌ Commit message doesn't follow format

## ✨ What Gets Auto-Fixed

These issues are **automatically fixed** on commit:

- ✅ Code formatting (tabs, spaces, indentation)
- ✅ Import ordering
- ✅ Missing semicolons
- ✅ Trailing whitespace
- ✅ Property ordering in CSS

## 📐 Code Quality Rules

### File Size Limit

**Maximum 500 lines per file** (including comments)

```bash
# Check file sizes
find plugin -name "*.php" -exec wc -l {} \; | awk '$1 > 500'
```

### Commit Message Format

```
type(scope): subject

Examples:
  feat(sync): add notion to wordpress sync
  fix(auth): handle invalid token gracefully
  docs(readme): update installation instructions
```

**Types:** feat, fix, docs, style, refactor, perf, test, chore, build, ci, revert

## 🆘 Emergency: Skip Hooks

**⚠️ Only use in true emergencies!**

```bash
git commit --no-verify -m "Emergency hotfix"
```

## 📚 Full Documentation

See `docs/development/linting.md` for:
- Detailed IDE setup (VS Code, PhpStorm)
- Common error solutions
- Rule explanations
- Advanced configuration

## 🔗 Quick Links

- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [PHPStan Documentation](https://phpstan.org/)
- [ESLint Rules](https://eslint.org/docs/rules/)
- [Full Linting Guide](docs/development/linting.md)

---

**Questions?** Check `docs/development/linting.md` or ask the team!
