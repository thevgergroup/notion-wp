# CI/CD Setup - Quick Start Guide

## ⚡ 5-Minute Setup

### Prerequisites
- GitHub repository with push access
- Codecov.io account (free for open source)

### Step 1: Enable GitHub Actions (Already Done ✅)

The workflows are already configured:
- `.github/workflows/test.yml` - Tests & coverage
- `.github/workflows/lint.yml` - Code quality

### Step 2: Setup Codecov (Optional but Recommended)

1. **Sign up:** Go to [codecov.io](https://codecov.io) and sign in with GitHub

2. **Add repository:**
   - Click "Add new repository"
   - Find `thevgergroup/notion-wp`
   - Click "Setup repo"

3. **Get upload token:**
   - Copy the repository upload token
   - Go to GitHub repo → Settings → Secrets and variables → Actions
   - Click "New repository secret"
   - Name: `CODECOV_TOKEN`
   - Value: Paste token
   - Click "Add secret"

**That's it!** Coverage will now upload on every push.

### Step 3: Setup Coverage Badge (Optional)

This creates a dynamic badge showing current coverage percentage.

1. **Create a Gist:**
   - Go to [gist.github.com](https://gist.github.com)
   - Create a new gist named `notion-wp-badges.json`
   - Content: `{}`
   - Create as **public gist**
   - Copy the Gist ID from URL: `gist.github.com/username/{THIS_PART}`

2. **Create Personal Access Token:**
   - GitHub → Settings → Developer settings → Personal access tokens → Tokens (classic)
   - Click "Generate new token (classic)"
   - Name: "Codecov Badge Updater"
   - Scopes: Check ✅ `gist` only
   - Click "Generate token"
   - **Copy the token immediately** (you won't see it again)

3. **Add GitHub Secrets:**
   - Go to repo → Settings → Secrets and variables → Actions
   - Add two secrets:
     - `GIST_SECRET`: Paste personal access token
     - `GIST_ID`: Paste gist ID from step 1

4. **Update README badge (already done):**
   The badge URL is already in README.md:
   ```markdown
   [![codecov](https://codecov.io/gh/thevgergroup/notion-wp/branch/main/graph/badge.svg)](https://codecov.io/gh/thevgergroup/notion-wp)
   ```

## How It Works

### On Every Pull Request:
1. ✅ Linting runs (PHPCS + PHPStan + ESLint)
2. ✅ Tests run on PHP 8.0, 8.1, 8.2, 8.3
3. ✅ Coverage report uploads to Codecov
4. 📊 Codecov comments on PR with coverage change

### On Push to Main:
1. ✅ All PR checks run
2. 📊 Coverage badge updates automatically
3. 📈 Coverage trends tracked over time

## Verify It's Working

### Check Workflow Status:
1. Push a commit or create a PR
2. Go to repo → Actions tab
3. You should see "Tests & Coverage" running
4. Click on run to see detailed output

### Check Coverage Upload:
1. Wait for workflow to complete
2. Go to [codecov.io/gh/thevgergroup/notion-wp](https://codecov.io/gh/thevgergroup/notion-wp)
3. You should see coverage reports

### Check Badge:
1. Push to main branch
2. Wait for workflow to complete
3. Refresh README on GitHub
4. Badge should show current coverage %

## Troubleshooting

### "Codecov: Failed to upload coverage"
**Solution:** Check that `CODECOV_TOKEN` secret is set correctly

### "Badge not updating"
**Solutions:**
- Verify `GIST_SECRET` and `GIST_ID` are set
- Check token has `gist` scope
- Ensure gist is public
- Badge only updates on push to `main` branch

### "Tests failing in CI but pass locally"
**Solutions:**
- Check PHP version: `php -v` (CI uses 8.0-8.3)
- Clear vendor: `rm -rf vendor && composer install`
- Run tests: `composer test:unit`

## What's Included

### Test Workflow Features:
- ✅ Matrix testing across PHP 8.0-8.3
- ✅ PCOV coverage (fast, designed for CI)
- ✅ Codecov integration
- ✅ Coverage badge generation
- ✅ Testdox output for readable results
- ✅ Parallel job execution
- ✅ Dependency caching (faster builds)

### Coverage Exclusions:
- Admin UI templates (visual components)
- REST API controllers (require WordPress bootstrap)
- Database schemas (WordPress handles SQL)

### Current Status:
- **Tests:** 71 passing, 6 skipped (documented)
- **Coverage:** 18% (expected at this stage)
- **Target:** 70% combined coverage

## Next Steps

After setup is complete:

1. **Monitor coverage trends** on Codecov dashboard
2. **Review PR coverage comments** before merging
3. **Add tests for new features** to maintain/improve coverage
4. **Set up integration tests** (future enhancement)

## Quick Commands

```bash
# Run tests locally (as CI does)
composer test:unit

# Generate coverage report
composer test:coverage

# View coverage HTML
open coverage-html/index.html

# Run linting (as CI does)
composer lint
```

## Need Help?

- 📖 Full documentation: `docs/testing/ci-cd-setup.md`
- 🧪 Testing guide: `docs/testing/TESTING-SUMMARY.md`
- 📊 Coverage setup: `docs/testing/code-coverage-setup.md`
- 🐛 Issues: [GitHub Issues](https://github.com/thevgergroup/notion-wp/issues)
