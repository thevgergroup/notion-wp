# Release Process

This document outlines the process for creating releases of the Notion Sync plugin.

---

## Semantic Versioning

We follow [Semantic Versioning](https://semver.org/) (SemVer):

- **MAJOR** version (1.x.x) - Incompatible API changes
- **MINOR** version (x.1.x) - New features, backwards-compatible
- **PATCH** version (x.x.1) - Bug fixes, backwards-compatible

**Examples:**
- `1.0.0` → `1.0.1` - Bug fix release
- `1.0.0` → `1.1.0` - New feature release
- `1.0.0` → `2.0.0` - Breaking changes

---

## Pre-Release Checklist

Before creating a release:

- [ ] All tests passing (`make test`)
- [ ] All linting checks passing (`make lint`)
- [ ] CHANGELOG.md updated with changes
- [ ] Documentation updated if needed
- [ ] Screenshots updated if UI changed
- [ ] Version numbers consistent across files

---

## Release Steps

### 1. Bump Version

Use the version bump script:

```bash
./scripts/bump-version.sh 1.0.1
```

This updates version in:
- `plugin/notion-sync.php` (header and constant)
- `package.json`
- `plugin/readme.txt`

### 2. Review Changes

```bash
git diff
```

Verify all version numbers are correct.

### 3. Commit Version Bump

```bash
git add -A
git commit -m "chore: bump version to 1.0.1"
```

### 4. Create Git Tag

```bash
git tag -a v1.0.1 -m "Release v1.0.1"
```

### 5. Push Changes

```bash
git push origin main
git push origin --tags
```

### 6. GitHub Actions Automatically:

- Builds production-ready plugin zip
- Excludes development files (tests, node_modules, etc.)
- Creates GitHub Release
- Uploads release asset
- Generates release notes from commits

---

## Manual Release Build (Optional)

To build locally for testing:

```bash
# Install production dependencies
cd plugin
composer install --no-dev --optimize-autoloader

# Build frontend assets
cd ..
npm ci
npm run build

# Create zip (excluding dev files)
zip -r notion-sync.zip plugin/ \
  -x "plugin/tests/*" \
  -x "plugin/node_modules/*" \
  -x "plugin/vendor/*/tests/*" \
  -x "plugin/.phpcs.xml.dist" \
  -x "plugin/phpunit.xml.dist"
```

---

## Testing a Release

### Test Installation

1. Download the release zip from GitHub
2. Install on a fresh WordPress site:
   ```bash
   wp plugin install notion-sync-1.0.1.zip
   wp plugin activate notion-sync
   ```
3. Verify plugin activates without errors
4. Test core functionality

### Test Upgrade

1. Install previous version
2. Install new version over it
3. Verify upgrade works smoothly
4. Check for database migration issues

---

## Release Asset Contents

The release zip includes:

**Included:**
- All PHP source files
- Compiled CSS/JS assets
- Production Composer dependencies
- License and documentation

**Excluded:**
- Tests and test files
- Development dependencies
- Build configuration files
- Source maps
- Documentation source files

---

## Rollback Procedure

If a release has critical issues:

1. **Immediate:**
   - Mark GitHub release as draft or delete
   - Create hotfix branch from previous tag

2. **Fix and Release:**
   ```bash
   git checkout -b hotfix/1.0.2
   # Fix the issue
   git commit -m "fix: critical bug description"
   ./scripts/bump-version.sh 1.0.2
   git tag -a v1.0.2 -m "Hotfix v1.0.2"
   git push origin hotfix/1.0.2 --tags
   ```

3. **Notify users** via GitHub release notes

---

## WordPress.org Releases

For WordPress.org plugin directory (when ready):

1. Complete normal GitHub release process
2. Check out the WordPress.org SVN repository
3. Copy files to `/trunk`
4. Create tag in `/tags/1.0.1`
5. Commit to SVN

**Detailed guide:** See `docs/WORDPRESS-ORG-SUBMISSION.md`

---

## Changelog Guidelines

Keep CHANGELOG.md updated with:

- **Added:** New features
- **Changed:** Changes in existing functionality
- **Deprecated:** Features to be removed
- **Removed:** Removed features
- **Fixed:** Bug fixes
- **Security:** Security fixes

**Example:**

```markdown
## [1.0.1] - 2025-11-03

### Fixed
- Fix image import timeout for large files
- Correct internal link resolution for nested pages

### Changed
- Improve sync performance for large databases
```

---

## Version Number Locations

Version must be updated in:

1. `plugin/notion-sync.php` - Line 6 (Plugin header)
2. `plugin/notion-sync.php` - Line 27 (NOTION_SYNC_VERSION constant)
3. `package.json` - Line 3 (version field)
4. `plugin/readme.txt` - Line 7 (Stable tag)

**Use the bump-version.sh script to update all locations at once!**

---

## Troubleshooting

### Release workflow fails

Check:
- Version in plugin file matches tag
- All tests passing
- Composer/npm dependencies installable
- No syntax errors

### Release zip is too large

Check for:
- Uncommitted node_modules
- Development dependencies not excluded
- Large test fixtures

### Plugin won't activate from zip

Test locally:
```bash
unzip notion-sync-1.0.1.zip -d /tmp/test
php -l /tmp/test/notion-sync/notion-sync.php
```

---

## Questions?

See [GitHub Discussions](https://github.com/thevgergroup/notion-wp/discussions) or file an [Issue](https://github.com/thevgergroup/notion-wp/issues).
