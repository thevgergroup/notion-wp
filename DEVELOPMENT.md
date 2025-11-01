# Development Guide

Technical documentation for contributing to Notion Sync for WordPress.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Development Setup](#development-setup)
3. [Project Structure](#project-structure)
4. [Branching Strategy](#branching-strategy)
5. [Testing](#testing)
6. [Code Quality](#code-quality)
7. [Building for Production](#building-for-production)
8. [Debugging](#debugging)
9. [Architecture](#architecture)
10. [Contributing](#contributing)

---

## Prerequisites

Before starting development, ensure you have:

- **Git 2.0+**
- **Docker and Docker Compose** - For local WordPress environment
- **Node.js 20+** - For asset building
- **PHP 8.0+** - `composer.phar` will be downloaded during setup
- **Make** - For running project commands

### Recommended Tools

- **Visual Studio Code** with PHP Intelephense extension
- **TablePlus** or similar for database inspection
- **Postman** or **Insomnia** for API testing

---

## Development Setup

### Quick Start

1. **Clone the repository:**

    ```bash
    git clone https://github.com/thevgergroup/notion-wp.git
    cd notion-wp
    ```

2. **Start the Docker environment:**

    ```bash
    make up
    ```

    This starts:
    - WordPress: http://localhost:8080
    - Admin: http://localhost:8080/wp-admin
    - Credentials: `admin` / `admin`
    - Database: MariaDB on port 3307

3. **Install PHP dependencies:**

    ```bash
    cd plugin

    # Download composer if not already present
    ../get-composer.sh

    # Install dependencies
    php ../composer.phar install
    ```

4. **Install Node dependencies and build assets:**

    ```bash
    npm install
    npm run build
    ```

5. **Verify setup:**

    ```bash
    # Run tests
    php ../composer.phar test

    # Run linters
    php ../composer.phar lint
    ```

### Docker Commands

The project includes a Makefile with helpful commands:

```bash
make up      # Start WordPress + MariaDB containers
make down    # Stop containers
make clean   # Stop and remove all containers + volumes
make shell   # Open bash shell in WordPress container
make logs    # View container logs
make restart # Restart containers
```

### Environment Configuration

Docker Compose configuration is in `docker/docker-compose.yml`:

- **WordPress container**: Exposes port 8080
- **MariaDB container**: Exposes port 3307
- **Plugin volume**: `./plugin` mounted to `/var/www/html/wp-content/plugins/notion-sync`
- **Persistence**: Database data persisted in Docker volumes

---

## Project Structure

```
notion-wp/
├── plugin/                     # WordPress plugin code
│   ├── src/                    # PSR-4 source code (NotionWP\)
│   │   ├── Admin/              # WordPress admin UI
│   │   ├── API/                # Notion API client
│   │   ├── Blocks/             # Gutenberg block registration
│   │   ├── Converters/         # Notion → WordPress block converters
│   │   ├── Database/           # Database views and formatting
│   │   ├── Media/              # Media library handling
│   │   ├── Navigation/         # Menu generation
│   │   ├── Registry/           # Converter and view registries
│   │   └── Sync/               # Sync orchestration
│   ├── assets/
│   │   ├── src/                # Source SCSS/JS (committed)
│   │   └── dist/               # Built CSS/JS (gitignored)
│   ├── templates/              # PHP templates
│   │   └── admin/              # Admin page templates
│   ├── tests/                  # PHPUnit test suite
│   ├── notion-sync.php         # Plugin entry point
│   ├── composer.json           # PHP dependencies
│   └── package.json            # Node dependencies
├── docker/                     # Docker configuration
│   ├── docker-compose.yml      # Service definitions
│   └── README.md               # Docker documentation
├── docs/                       # Project documentation
│   ├── plans/                  # Phase implementation plans
│   ├── architecture/           # Architecture documentation
│   └── development/            # Development guides
├── scripts/                    # Helper scripts
│   ├── health-check.sh         # Environment health check
│   └── README.md               # Scripts documentation
├── Makefile                    # Development commands
├── README.md                   # User documentation
├── CONTRIBUTING.md             # Contributor guide
└── LICENSE                     # GPL-2.0-or-later
```

### Key Directories

**`plugin/src/`** - All PHP source code using PSR-4 autoloading under `NotionWP\` namespace

**`plugin/assets/src/`** - Source SCSS and JavaScript files (committed to Git)

**`plugin/assets/dist/`** - Compiled/minified CSS and JS (generated, not committed)

**`plugin/tests/`** - PHPUnit unit and integration tests

**`docs/plans/`** - Phase-by-phase implementation plans and roadmap

---

## Branching Strategy

We use a simple feature branch workflow. See [Branching Strategy](docs/development/BRANCHING-STRATEGY.md) for complete details.

### Quick Reference

```bash
# Create feature branch
git checkout main
git pull origin main
git checkout -b feature/my-feature

# Make changes, test, commit
# ...

# Push to your fork
git push -u origin feature/my-feature

# Create pull request on GitHub

# After PR is merged, clean up
git checkout main
git pull origin main
git branch -d feature/my-feature
git push origin --delete feature/my-feature
```

**Branch Naming:**
- `feature/` - New features
- `fix/` - Bug fixes
- `epic/` - Multi-PR features
- `phase-X` - Major phase implementations

---

## Testing

### Running Tests

```bash
cd plugin

# Run all tests
php ../composer.phar test

# Run specific test file
php ../composer.phar test tests/Converters/ParagraphConverterTest.php

# Run with coverage
php ../composer.phar test:coverage
```

### Test Structure

```
plugin/tests/
├── API/                        # API client tests
├── Converters/                 # Block converter tests
├── Database/                   # Database view tests
└── bootstrap.php               # Test bootstrap
```

### Writing Tests

Example test class:

```php
<?php
namespace NotionWP\Tests\Converters;

use NotionWP\Converters\ParagraphConverter;
use PHPUnit\Framework\TestCase;

class ParagraphConverterTest extends TestCase {
    private ParagraphConverter $converter;

    protected function setUp(): void {
        $this->converter = new ParagraphConverter();
    }

    public function test_converts_simple_paragraph(): void {
        $block = [
            'type' => 'paragraph',
            'paragraph' => [
                'rich_text' => [
                    ['type' => 'text', 'text' => ['content' => 'Hello world']]
                ]
            ]
        ];

        $result = $this->converter->convert($block);

        $this->assertStringContainsString('Hello world', $result);
    }
}
```

### Test Coverage

Current coverage is tracked via GitHub Actions and displayed in README badge.

**Coverage goals:**
- Overall: 80%+
- Converters: 90%+
- API client: 85%+
- Database views: 80%+

---

## Code Quality

### PHP Linting

**All linting commands run from project root (not plugin directory):**

```bash
# Run all linters
php composer.phar lint

# Individual linters
php composer.phar lint:phpcs          # WordPress Coding Standards
php composer.phar lint:phpstan        # Static analysis (level 5)

# Auto-fix issues
php composer.phar lint:fix            # Fix all auto-fixable issues
php composer.phar lint:phpcbf         # Fix coding standards only
```

### JavaScript Linting

```bash
cd plugin

# Run ESLint
npm run lint:js

# Auto-fix
npm run lint:js:fix
```

### CSS Linting

```bash
cd plugin

# Run Stylelint
npm run lint:css

# Auto-fix
npm run lint:css:fix
```

### Pre-commit Hooks

Husky is configured to run linters automatically before commits:

```bash
# Install hooks (done automatically by npm install)
npm run prepare

# Hooks run on every commit
git commit -m "Your message"

# If linting fails, commit is blocked - fix issues and try again
```

### Code Standards

**PHP:**
- WordPress Coding Standards (WPCS)
- PSR-4 autoloading
- Type declarations required (PHP 8.0+)
- PHPDoc comments for all public methods
- Maximum 500 lines per file

**JavaScript:**
- ESLint with WordPress preset
- ES6+ syntax
- No `console.log` in production
- Use `const`/`let` (not `var`)

**CSS:**
- Stylelint with WordPress config
- BEM naming preferred
- Mobile-first responsive design

---

## Building for Production

### Asset Compilation

```bash
cd plugin

# Development build (with sourcemaps)
npm run build

# Production build (minified)
npm run build:production

# Watch mode for development
npm run watch
```

### Creating Release Builds

```bash
# 1. Update version in plugin/notion-sync.php
# 2. Update CHANGELOG.md
# 3. Build production assets
cd plugin
npm run build:production

# 4. Create release commit
git add .
git commit -m "chore: bump version to 1.2.0"

# 5. Create git tag
git tag -a v1.2.0 -m "Release version 1.2.0"

# 6. Push to GitHub
git push origin main --tags

# 7. GitHub Actions will create release automatically
```

---

## Debugging

### Enable WordPress Debug Mode

Add to `wp-config.php` in Docker container:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

View logs:

```bash
# From host machine
docker exec -it notion-wp-wordpress tail -f /var/www/html/wp-content/debug.log

# Or use make command
make shell
tail -f wp-content/debug.log
```

### Xdebug Setup

Xdebug is pre-configured in the Docker environment:

**VS Code - `.vscode/launch.json`:**

```json
{
    "version": "0.2.0",
    "configurations": [
        {
            "name": "Listen for Xdebug",
            "type": "php",
            "request": "launch",
            "port": 9003,
            "pathMappings": {
                "/var/www/html/wp-content/plugins/notion-sync": "${workspaceFolder}/plugin"
            }
        }
    ]
}
```

### Database Inspection

Connect to MariaDB:

```bash
# Connection details
Host: localhost
Port: 3307
Database: wordpress
User: wordpress
Password: wordpress

# Using command line
docker exec -it notion-wp-db mysql -u wordpress -pwordpress wordpress

# Or use TablePlus/Sequel Pro/DBeaver
```

### Common Issues

**Issue: Port 8080 already in use**

```bash
# Find what's using the port
lsof -i :8080

# Change port in docker/docker-compose.yml
ports:
  - "8081:80"  # Use 8081 instead
```

**Issue: Docker containers won't start**

```bash
# Clean everything and start fresh
make clean
make up
```

**Issue: Composer dependencies missing**

```bash
cd plugin
rm -rf vendor
php ../composer.phar install
```

---

## Architecture

### Overview

The plugin follows a modular architecture with clear separation of concerns:

**API Layer** (`NotionWP\API\`)
- `NotionClient` - Handles Notion API requests
- `DatabaseRestController` - REST API for database views

**Conversion Layer** (`NotionWP\Converters\`)
- Individual converters for each Notion block type
- Registry pattern for extensibility
- Converts Notion blocks → WordPress Gutenberg blocks

**Sync Layer** (`NotionWP\Sync\`)
- Orchestrates page and database synchronization
- Handles media downloads via Action Scheduler
- Manages sync state and history

**Database Layer** (`NotionWP\Database\`)
- Database view rendering (table, board, gallery, timeline, calendar)
- Property formatters for different data types
- Filter and sort operations

**Navigation Layer** (`NotionWP\Navigation\`)
- Generates WordPress menus from Notion hierarchy
- Maintains parent-child relationships

### Key Patterns

**Registry Pattern:**
- `ConverterRegistry` - Registers block converters
- `ViewRegistry` - Registers database view handlers

**Factory Pattern:**
- `ConverterFactory` - Creates appropriate converter for block type
- `PropertyFormatter` - Formats properties by type

**Strategy Pattern:**
- Different database view implementations (Table, Board, etc.)
- Different property formatters (Text, Number, Date, etc.)

### WordPress Integration

**Hooks Used:**
- `admin_menu` - Register settings page
- `admin_enqueue_scripts` - Load admin assets
- `rest_api_init` - Register REST endpoints
- `init` - Register Gutenberg blocks
- `wp_enqueue_scripts` - Load frontend assets

**Custom Post Meta:**
- `_notion_page_id` - Stores Notion page ID
- `_notion_last_synced` - Last sync timestamp
- `_notion_block_map` - Block ID mapping for updates

---

## Contributing

For contribution guidelines, see [CONTRIBUTING.md](CONTRIBUTING.md).

**Quick checklist before submitting PR:**

- [ ] All linters pass (`php composer.phar lint` from root, `npm run lint` from plugin/)
- [ ] Tests pass (`php composer.phar test` from plugin/)
- [ ] Code follows WordPress Coding Standards
- [ ] No files exceed 500 lines
- [ ] All output is escaped (security)
- [ ] All input is sanitized (security)
- [ ] Changes tested manually in browser
- [ ] Inline comments explain complex logic
- [ ] No debug code or console.logs

---

## Useful Commands Reference

```bash
# Docker
make up              # Start environment
make down            # Stop environment
make clean           # Remove all containers and volumes
make shell           # Open shell in WordPress container
make logs            # View logs

# PHP
cd plugin
php ../composer.phar install        # Install dependencies
php ../composer.phar test            # Run tests
php ../composer.phar test:coverage   # Run tests with coverage
php ../composer.phar lint            # Run all linters
php ../composer.phar lint:fix        # Auto-fix linting issues

# JavaScript/CSS
cd plugin
npm install          # Install dependencies
npm run build        # Build assets for development
npm run build:production  # Build minified assets
npm run watch        # Watch mode
npm run lint:js      # Lint JavaScript
npm run lint:css     # Lint CSS

# Git
git checkout -b feature/my-feature   # Create feature branch
git push -u origin feature/my-feature # Push branch
```

---

## Additional Resources

- [Branching Strategy](docs/development/BRANCHING-STRATEGY.md)
- [Contributing Guide](CONTRIBUTING.md)
- [Phase Plans](docs/plans/)
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [Notion API Reference](https://developers.notion.com/reference)
- [Action Scheduler Documentation](https://actionscheduler.org/usage/)

---

## Need Help?

- **Bug Reports:** [GitHub Issues](https://github.com/thevgergroup/notion-wp/issues)
- **Feature Requests:** [GitHub Discussions](https://github.com/thevgergroup/notion-wp/discussions)
- **Documentation:** [docs/](docs/)

Happy coding!
