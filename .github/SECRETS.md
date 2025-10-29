# GitHub Secrets Configuration

This document explains the GitHub secrets required for CI/CD workflows.

## Required Secrets

### 1. CODECOV_TOKEN (Required)

**Purpose**: Uploads coverage reports to Codecov for tracking test coverage over time.

**How to get it**:
1. Go to [https://codecov.io/](https://codecov.io/)
2. Sign in with your GitHub account
3. Add the `thevgergroup/notion-wp` repository
4. Go to Settings → General
5. Copy the "Upload Token"

**How to add it**:
1. Go to GitHub repository → Settings → Secrets and variables → Actions
2. Click "New repository secret"
3. Name: `CODECOV_TOKEN`
4. Value: Paste the token from Codecov
5. Click "Add secret"

**Status**: ✅ Configured

---

### 2. GIST_SECRET (Required for custom badge)

**Purpose**: Creates/updates a GitHub Gist with coverage badge data. This allows displaying dynamic coverage percentage in the README.

**How to get it**:
1. Go to [https://github.com/settings/tokens](https://github.com/settings/tokens)
2. Click "Generate new token (classic)"
3. Give it a descriptive name: "notion-wp-coverage-badge"
4. Set expiration (recommend "No expiration" for CI/CD)
5. Select **only** the `gist` scope
6. Click "Generate token"
7. **Copy the token immediately** (you won't be able to see it again)

**How to add it**:
1. Go to GitHub repository → Settings → Secrets and variables → Actions
2. Click "New repository secret"
3. Name: `GIST_SECRET`
4. Value: Paste the personal access token
5. Click "Add secret"

**Status**: ✅ Configured

**Gist URL**: https://gist.github.com/pjaol/2cb753e52d7fcf0a1176d34f406ad613

---

### 3. GIST_ID (Required for custom badge)

**Purpose**: Identifies which Gist to update with coverage data.

**Value**: `2cb753e52d7fcf0a1176d34f406ad613`

**How to get it**:
1. Go to [https://gist.github.com/](https://gist.github.com/)
2. Click "New gist"
3. Create a gist with:
   - Filename: `notion-wp-coverage.json`
   - Content: `{"schemaVersion": 1, "label": "coverage", "message": "0%", "color": "red"}`
   - Make it **public** (required for badges)
4. Click "Create public gist"
5. Copy the Gist ID from the URL:
   - URL: `https://gist.github.com/{username}/{gist-id}`
   - Example: `https://gist.github.com/pjaol/2cb753e52d7fcf0a1176d34f406ad613`
   - Gist ID: `2cb753e52d7fcf0a1176d34f406ad613`

**How to add it**:
1. Go to GitHub repository → Settings → Secrets and variables → Actions
2. Click "New repository secret"
3. Name: `GIST_ID`
4. Value: Paste the Gist ID (just the ID, not the full URL)
5. Click "Add secret"

**Status**: ✅ Configured

---

## Verification

After adding all secrets, verify the setup:

1. **Trigger a workflow run**:
   ```bash
   git commit --allow-empty -m "test: trigger CI to verify codecov"
   git push
   ```

2. **Check workflow logs**:
   - Go to Actions tab in GitHub
   - Click on the latest "Tests & Coverage" workflow
   - Verify "Upload coverage to Codecov" step succeeds
   - Verify "Create coverage badge" step succeeds (if GIST secrets are configured)

3. **Check Codecov dashboard**:
   - Go to [https://codecov.io/gh/thevgergroup/notion-wp](https://codecov.io/gh/thevgergroup/notion-wp)
   - Verify coverage report appears after workflow completes

4. **Check coverage badge**:
   - If GIST secrets are configured, verify the badge in README.md updates
   - Badge URL: `https://gist.githubusercontent.com/{username}/{gist-id}/raw/notion-wp-coverage.json`

---

## Current Badge URLs

The README uses two coverage indicators:

### Custom Coverage Badge (via Gist)
```markdown
[![Coverage](https://img.shields.io/endpoint?url=https://gist.githubusercontent.com/pjaol/2cb753e52d7fcf0a1176d34f406ad613/raw/notion-wp-coverage.json)](https://gist.github.com/pjaol/2cb753e52d7fcf0a1176d34f406ad613)
```
- **Requires**: GIST_SECRET + GIST_ID
- **Updates**: On push to main branch only
- **Current status**: Shows 0% (secrets not configured)

### Codecov Badge
```markdown
[![codecov](https://codecov.io/gh/thevgergroup/notion-wp/branch/main/graph/badge.svg)](https://codecov.io/gh/thevgergroup/notion-wp)
```
- **Requires**: CODECOV_TOKEN
- **Updates**: On every push/PR
- **Current status**: Should show "unknown" until first coverage report uploads

---

## Troubleshooting

### Codecov badge shows "unknown"
- Verify CODECOV_TOKEN is set correctly
- Check that tests have run at least once on main branch
- Verify coverage.xml is being generated in workflow logs
- Check Codecov dashboard for upload errors

### Custom coverage badge shows 0%
- Verify both GIST_SECRET and GIST_ID are set
- Ensure the Gist is **public** (not secret)
- Check workflow logs for "Create coverage badge" step
- Verify the Gist filename matches exactly: `notion-wp-coverage.json`

### Tests are passing but no coverage
- Verify PHPUnit is configured with coverage driver (PCOV)
- Check phpunit.xml has coverage configuration
- Ensure tests are actually testing code (not just passing empty tests)

### Coverage shows 0% even though tests exist
This was the issue encountered in January 2025. Possible causes:
1. **Coverage XML not being generated**: Check workflow logs for PHPUnit errors
2. **Metrics extraction failing**: The coverage extraction script reads `coverage.xml`
3. **Extensive code exclusions**: Check `phpunit.xml` - large directories like `Admin/` and `API/` are intentionally excluded from unit test coverage (will be covered by integration tests)
4. **No actual coverage**: If all tested code is in excluded directories, coverage will be 0%

**Solution implemented**:
- Added debugging output to the "Extract coverage percentage" step
- Enhanced codecov upload with verbose logging
- Set `fail_ci_if_error: true` for codecov to catch upload failures
- Created `codecov.yml` configuration file for proper report processing

To diagnose:
1. Check the "Generate coverage report" step output for the coverage summary
2. Look for "Total statements:" and "Covered statements:" debug logs
3. Review the coverage.xml preview in the workflow logs
4. Verify that source files in `plugin/src/Blocks/`, `plugin/src/Media/`, etc. are being tested
