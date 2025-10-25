# Code Coverage Setup Guide

## Overview

Code coverage measures which lines of code are executed during tests. This helps identify untested code paths and ensures comprehensive test coverage.

## Architecture

**Important**: Tests run on the **host machine**, not inside Docker containers.

- **WordPress**: Runs in Docker (for development/preview)
- **PHPUnit Tests**: Run on host machine using `vendor/bin/phpunit`
- **Code Coverage**: Requires PCOV/Xdebug installed on **host machine PHP**

This means PCOV must be installed in your local PHP environment (via PECL), not in the Docker WordPress container.

## Current Configuration

PHPUnit is already configured for code coverage in `phpunit.xml`:

```xml
<coverage processUncoveredFiles="true">
    <include>
        <directory suffix=".php">plugin/src</directory>
    </include>
    <exclude>
        <!-- Exclude admin UI templates (low value, visual components) -->
        <directory>plugin/src/Admin</directory>
        <!-- Exclude database schema (WordPress handles most logic) -->
        <file>plugin/src/Database/Schema.php</file>
        <file>plugin/src/Database/SyncLogSchema.php</file>
    </exclude>
    <report>
        <html outputDirectory="coverage-html"/>
        <clover outputFile="coverage.xml"/>
        <text outputFile="php://stdout" showUncoveredFiles="true"/>
    </report>
</coverage>
```

## Installation

### Option 1: PCOV (Recommended - Faster)

PCOV is faster than Xdebug and designed specifically for code coverage.

**Automated Installation:**
```bash
# Use the provided installation script
bash scripts/install-pcov.sh
```

**Manual Installation:**
```bash
# Install via PECL
pecl install pcov

# Enable in php.ini
echo "extension=pcov.so" >> /usr/local/etc/php/conf.d/pcov.ini
echo "pcov.enabled=1" >> /usr/local/etc/php/conf.d/pcov.ini

# Verify installation
php -m | grep pcov
```

**Note**: This installs PCOV in your host machine's PHP environment. The worktree setup script (`scripts/setup-worktree.sh`) automatically runs this during Step 3.1.

### Option 2: Xdebug (Feature-rich, slower)

Xdebug provides debugging + coverage but is slower for pure coverage.

```bash
# Install via PECL
pecl install xdebug

# Enable in php.ini
echo "zend_extension=xdebug.so" >> /usr/local/etc/php/conf.d/xdebug.ini
echo "xdebug.mode=coverage" >> /usr/local/etc/php/conf.d/xdebug.ini

# Verify installation
php -m | grep xdebug
```

### Option 3: phpdbg (Built-in, slower)

PHP includes phpdbg for debugging and coverage (no installation needed).

```bash
# Already available, just use phpdbg instead of php
phpdbg -qrr vendor/bin/phpunit --coverage-html coverage-html
```

## Running Code Coverage

### Local Development

```bash
# Generate HTML coverage report
php vendor/bin/phpunit --coverage-html coverage-html

# View in browser
open coverage-html/index.html

# Generate Clover XML for CI
php vendor/bin/phpunit --coverage-clover coverage.xml

# Show coverage summary in terminal
php vendor/bin/phpunit --coverage-text
```

### CI/CD Integration

#### GitHub Actions

```yaml
name: Tests with Coverage

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v2

      - name: Setup PHP with PCOV
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          coverage: pcov

      - name: Install Dependencies
        run: composer install

      - name: Run Tests with Coverage
        run: php vendor/bin/phpunit --coverage-clover coverage.xml

      - name: Upload Coverage to Codecov
        uses: codecov/codecov-action@v3
        with:
          files: ./coverage.xml
          fail_ci_if_error: true

      - name: Upload Coverage to Coveralls
        uses: coverallsapp/github-action@master
        with:
          github-token: ${{ secrets.GITHUB_TOKEN }}
          path-to-lcov: ./coverage.xml
```

## Coverage Targets

### Current Coverage Goals

| Test Type    | Coverage Target | Rationale |
|--------------|-----------------|-----------|
| Unit Tests   | 80%+            | Fast, isolated tests should cover most logic |
| Integration  | 60%+            | Covers component interactions |
| Combined     | 70%+            | Overall project health |

### What to Exclude

Already excluded in phpunit.xml:
- `plugin/src/Admin/` - UI templates (low value, visual)
- `plugin/src/Database/Schema.php` - WordPress handles SQL execution
- `plugin/src/Database/SyncLogSchema.php` - WordPress handles SQL execution

Additional exclusions to consider:
- Third-party libraries
- Generated code
- Deprecated code paths

## Coverage Reports

### HTML Report Structure

```
coverage-html/
├── index.html              # Overview dashboard
├── dashboard.html          # Summary by directory
├── Blocks/
│   ├── index.html         # Block converter coverage
│   ├── BlockConverter.php.html
│   └── Converters/
│       ├── ParagraphConverter.php.html
│       └── ...
├── Media/
│   ├── ImageDownloader.php.html
│   └── MediaRegistry.php.html
└── Sync/
    └── SyncManager.php.html
```

### Reading Coverage Reports

- **Green lines**: Covered by tests
- **Red lines**: Not covered by tests
- **Yellow lines**: Partially covered (branching)

### Coverage Metrics

- **Line Coverage**: % of code lines executed
- **Function Coverage**: % of functions called
- **Branch Coverage**: % of if/else paths taken
- **Path Coverage**: % of complete execution paths tested

## Current Coverage Status

Run this to see current coverage:

```bash
php vendor/bin/phpunit --coverage-text --colors=never | grep -A 20 "Code Coverage Report"
```

Expected output:
```
Code Coverage Report:
  2023-10-25 10:00:00

 Summary:
  Classes: 85.71% (18/21)
  Methods: 78.95% (60/76)
  Lines:   72.34% (481/665)

NotionSync\Blocks:
  Classes: 100.00% (8/8)
  Methods: 90.00% (27/30)
  Lines:   85.00% (170/200)

NotionSync\Media:
  Classes: 100.00% (3/3)
  Methods: 80.00% (12/15)
  Lines:   75.00% (90/120)

NotionSync\Sync:
  Classes: 100.00% (2/2)
  Methods: 70.00% (14/20)
  Lines:   65.00% (130/200)
```

## Improving Coverage

### Identify Uncovered Code

```bash
# Show uncovered files
php vendor/bin/phpunit --coverage-text | grep "0.00%"

# Generate HTML report and open in browser
php vendor/bin/phpunit --coverage-html coverage-html
open coverage-html/index.html
```

### Write Tests for Uncovered Code

Priority order:
1. **Critical paths**: Error handling, data validation
2. **Business logic**: Core sync, conversion algorithms
3. **Edge cases**: Empty inputs, invalid data
4. **Helper methods**: Utilities, formatters

### Coverage Anti-Patterns to Avoid

❌ **DON'T write tests just to hit 100% coverage**
- Testing getters/setters with no logic
- Testing third-party library calls
- Testing framework code

✅ **DO write tests for valuable coverage**
- Business logic paths
- Error handling
- Data transformations
- Edge cases

## Troubleshooting

### "No code coverage driver available"

Install PCOV or Xdebug (see Installation section above).

### "Coverage generation is very slow"

- Use PCOV instead of Xdebug
- Reduce `processUncoveredFiles` in phpunit.xml
- Exclude vendor directory
- Run coverage only on changed files in CI

### "Coverage report shows 0% for some files"

- Ensure files are in `<include>` section of phpunit.xml
- Check files aren't in `<exclude>` section
- Verify files are being autoloaded correctly

## Best Practices

1. **Run coverage locally before pushing**
   ```bash
   php vendor/bin/phpunit --coverage-text --coverage-html coverage-html
   ```

2. **Review coverage reports regularly**
   - Weekly review of coverage trends
   - Identify critical uncovered code
   - Add tests for important paths

3. **Set coverage thresholds in CI**
   ```yaml
   - name: Check Coverage Threshold
     run: |
       php vendor/bin/phpunit --coverage-text > coverage.txt
       if grep -q "Lines:.*[0-6][0-9]\.[0-9][0-9]%" coverage.txt; then
         echo "Coverage below 70%"
         exit 1
       fi
   ```

4. **Track coverage trends**
   - Use Codecov or Coveralls for historical data
   - Monitor coverage changes in PRs
   - Prevent coverage regressions

## Integration with Development Workflow

### Pre-commit Hook

```bash
#!/bin/bash
# .git/hooks/pre-commit

echo "Running tests with coverage..."
php vendor/bin/phpunit --coverage-text --colors=never | grep "Lines:"

# Fail if coverage drops below 70%
coverage=$(php vendor/bin/phpunit --coverage-text --colors=never | grep "Lines:" | grep -oP '\d+\.\d+%')
threshold=70.00

if (( $(echo "$coverage < $threshold" | bc -l) )); then
    echo "Coverage $coverage% is below threshold $threshold%"
    exit 1
fi
```

### VS Code Integration

Install PHP Unit Test Explorer extension:
```json
{
    "php-unit.command": "vendor/bin/phpunit",
    "php-unit.coverage": {
        "enabled": true,
        "driver": "pcov"
    }
}
```

## Resources

- [PHPUnit Coverage Documentation](https://phpunit.de/manual/current/en/code-coverage.html)
- [PCOV GitHub](https://github.com/krakjoe/pcov)
- [Xdebug Documentation](https://xdebug.org/docs/code_coverage)
- [Codecov](https://codecov.io/)
- [Coveralls](https://coveralls.io/)
