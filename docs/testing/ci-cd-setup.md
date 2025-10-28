# CI/CD Testing & Coverage Setup

## Overview

The project uses GitHub Actions for continuous integration, running tests and reporting code coverage automatically on every pull request and push to main/develop branches.

## Workflows

### 1. Tests & Coverage (`.github/workflows/test.yml`)

**Triggers:**
- Pull requests to `main` or `develop`
- Pushes to `main` or `develop`
- Only when PHP files, tests, or dependencies change

**Jobs:**

#### Unit Tests
- **Matrix:** PHP 8.0, 8.1, 8.2, 8.3
- **Coverage driver:** PCOV (fast, designed for coverage)
- **Test suite:** Unit tests only (fast, no WordPress dependencies)
- **Output:** Testdox format with colors

**Steps:**
1. Checkout code
2. Setup PHP with PCOV extension
3. Cache Composer dependencies
4. Install dependencies
5. Run unit tests
6. Generate coverage (PHP 8.3 only)
7. Upload to Codecov (PHP 8.3 only)

#### Coverage Report (main branch only)
- Runs after unit tests complete
- Generates full coverage report
- Extracts coverage percentage
- Creates dynamic coverage badge
- Updates badge in GitHub Gist

#### Test Summary
- Aggregates results from all test jobs
- Fails if any test job fails
- Provides clear pass/fail status

### 2. Code Quality & Linting (`.github/workflows/lint.yml`)

Runs separately from tests to provide faster feedback on code style issues.

## Code Coverage

### Codecov Integration

**Setup:**
1. Sign up at [codecov.io](https://codecov.io) with GitHub account
2. Add repository to Codecov
3. Get repository upload token
4. Add to GitHub secrets as `CODECOV_TOKEN`

**Features:**
- Line, branch, and method coverage
- Coverage trends over time
- Pull request comments with coverage diff
- Coverage sunburst visualization
- Suggested reviewers based on coverage

**Configuration:**
Coverage settings in `phpunit.xml`:
```xml
<coverage processUncoveredFiles="true">
    <include>
        <directory suffix=".php">plugin/src</directory>
    </include>
    <exclude>
        <directory>plugin/src/Admin</directory>
        <directory>plugin/src/API</directory>
        <file>plugin/src/Database/Schema.php</file>
        <file>plugin/src/Database/SyncLogSchema.php</file>
    </exclude>
</coverage>
```

### Coverage Badges

**Dynamic Badge (recommended):**
Uses `schneegans/dynamic-badges-action` to create a live coverage badge.

**Setup:**
1. Create a GitHub Gist (any name, e.g., `notion-wp-badges.json`)
2. Create a GitHub personal access token with `gist` scope
3. Add to GitHub secrets:
   - `GIST_SECRET`: Personal access token
   - `GIST_ID`: Gist ID (from URL)

**Badge URL:**
```markdown
![Coverage](https://img.shields.io/endpoint?url=https://gist.githubusercontent.com/USERNAME/GIST_ID/raw/notion-wp-coverage.json)
```

**Color coding:**
- 🟢 Green: 70%+ coverage
- 🟡 Yellow: 50-70% coverage
- 🔴 Red: <50% coverage

## Coverage Targets

| Test Type | Target | Current | Status |
|-----------|--------|---------|--------|
| Unit Tests | 80% | 18% | 🔴 In Progress |
| Integration | 60% | 0% | ⏳ Not Started |
| Combined | 70% | 18% | 🔴 In Progress |

## Local Testing

**Run tests before pushing:**
```bash
# Run unit tests
composer test:unit

# Run with coverage
composer test:coverage

# Open coverage report
open coverage-html/index.html
```

**Pre-commit checklist:**
- [ ] All unit tests pass locally
- [ ] Coverage doesn't decrease (check diff)
- [ ] New code has tests
- [ ] Tests follow naming conventions

## CI/CD Best Practices

### 1. Test Isolation
- Unit tests run without WordPress (fast)
- Integration tests will use WordPress test framework (future)
- No database or file system dependencies in unit tests

### 2. Fast Feedback
- Unit tests complete in <10 seconds
- Matrix runs parallel for faster results
- Coverage only on PHP 8.3 (not all versions)

### 3. Coverage Strategy
- Only unit tests generate coverage (for now)
- Excluded: Admin UI, REST API, database schemas
- Focus: Business logic, block converters, sync engine

### 4. Pull Request Workflow
1. PR opened → Tests & linting run
2. Codecov comments with coverage change
3. All checks must pass before merge
4. Coverage badge updates on merge to main

## Troubleshooting

### Tests fail in CI but pass locally

**Common causes:**
1. **PHP version difference**
   - Local: Check `php -v`
   - CI: Tests run on PHP 8.0-8.3
   - Solution: Test locally with multiple PHP versions

2. **Dependency mismatch**
   - Solution: Delete `vendor/` and run `composer install`

3. **Platform-specific code**
   - Solution: Use cross-platform functions
   - Avoid: `\\` paths, Windows-only functions

### Coverage not uploading

**Check:**
1. `CODECOV_TOKEN` secret is set in GitHub
2. `coverage.xml` file is generated
3. Codecov action version is current
4. Token has correct permissions

**Debug:**
```bash
# Generate coverage locally
php vendor/bin/phpunit --coverage-clover coverage.xml

# Check file exists
ls -la coverage.xml

# Validate XML
xmllint coverage.xml
```

### Badge not updating

**Check:**
1. `GIST_SECRET` and `GIST_ID` are set
2. Gist is public
3. Token has `gist` scope
4. Workflow runs on `main` branch only

**Force update:**
1. Push to main branch
2. Check workflow run in Actions tab
3. Verify "Coverage Report & Badges" job succeeded
4. Clear browser cache

## GitHub Secrets Required

Add these secrets in repository Settings → Secrets and variables → Actions:

| Secret | Purpose | How to Get |
|--------|---------|------------|
| `CODECOV_TOKEN` | Upload coverage to Codecov | [codecov.io](https://codecov.io) → Repository Settings → Copy token |
| `GIST_SECRET` | Update coverage badge | GitHub Settings → Developer settings → Personal access tokens → Generate (needs `gist` scope) |
| `GIST_ID` | Badge storage location | Create gist → Copy ID from URL (`gist.github.com/username/{GIST_ID}`) |

## Adding New Tests

**When adding tests:**
1. Follow existing test structure in `tests/unit/`
2. Extend `BaseTestCase` or `BaseConverterTestCase`
3. Use descriptive test method names: `test_converts_external_image_to_gutenberg_block()`
4. Add docblocks explaining what the test verifies
5. Run locally before pushing

**Coverage considerations:**
- Aim for 80%+ coverage on new code
- Don't write tests just to hit 100% coverage
- Focus on valuable test cases (business logic, edge cases, error handling)
- Skip integration-level tests in unit suite (mark with `$this->markTestSkipped()`)

## Future Enhancements

### Integration Tests (Planned)
- WordPress test framework setup
- Database integration tests
- Media upload/download tests
- REST API endpoint tests

### E2E Tests (Future)
- Playwright/Cypress for admin UI
- Full sync workflow tests
- User acceptance testing

### Coverage Goals
- **Q1 2025:** 40% combined coverage (current: 18%)
- **Q2 2025:** 60% combined coverage
- **Q3 2025:** 70%+ combined coverage

## Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Codecov Documentation](https://docs.codecov.com/)
- [GitHub Actions - PHP](https://docs.github.com/en/actions/automating-builds-and-tests/building-and-testing-php)
- [shivammathur/setup-php](https://github.com/shivammathur/setup-php)
- [PCOV Coverage](https://github.com/krakjoe/pcov)
