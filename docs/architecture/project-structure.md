# Project Structure: Notion-WP Sync Plugin

## Table of Contents
1. [Overview](#overview)
2. [Complete Directory Structure](#complete-directory-structure)
3. [Directory Explanations](#directory-explanations)
4. [Git Worktree Integration](#git-worktree-integration)
5. [Docker Environment Integration](#docker-environment-integration)
6. [Plugin Architecture](#plugin-architecture)
7. [Configuration Management](#configuration-management)
8. [Development Workflow](#development-workflow)

---

## Overview

This project implements a WordPress plugin for bi-directional Notion ↔ WordPress synchronization. The structure is optimized for:
- **Multiple git worktrees** for parallel feature development
- **Isolated Docker environments** per worktree
- **PSR-4 autoloading** with proper namespacing
- **WordPress VIP coding standards**
- **Extensibility** through hooks and dependency injection

**Key Principle**: Shared code lives in the main repo; environment-specific configurations live in worktrees.

---

## Complete Directory Structure

```
notion-wp/                          # Main repository (bare or primary worktree)
├── .git/                           # Git repository data
├── .gitignore                      # Version control exclusions
├── .env.template                   # Template for worktree-specific .env files
│
├── docker/                         # Shared Docker infrastructure
│   ├── compose.yml                 # Main Docker Compose configuration
│   ├── compose.override.example.yml # Example for custom overrides
│   ├── traefik/                    # Optional reverse proxy setup
│   │   ├── traefik.yml
│   │   └── config/
│   │       └── dynamic.yml
│   ├── mysql/                      # MySQL/MariaDB customizations
│   │   └── init/
│   │       └── create-test-db.sql
│   └── wordpress/                  # WordPress container customizations
│       ├── php.ini                 # PHP configuration
│       └── .htaccess.template
│
├── plugin/                         # Plugin source code (shared across worktrees)
│   ├── notion-sync.php             # Main plugin file (WordPress headers)
│   ├── composer.json               # PHP dependencies & autoloading
│   ├── composer.lock
│   ├── package.json                # Node.js dependencies for builds
│   ├── package-lock.json
│   │
│   ├── src/                        # PSR-4 source code (NotionSync\)
│   │   ├── Bootstrap.php           # Plugin initialization
│   │   ├── Container.php           # Dependency injection container
│   │   │
│   │   ├── Admin/                  # WordPress admin interface
│   │   │   ├── AdminController.php
│   │   │   ├── SettingsPage.php
│   │   │   ├── SyncManager.php
│   │   │   ├── FieldMapper.php
│   │   │   └── StatusLogger.php
│   │   │
│   │   ├── API/                    # External API clients
│   │   │   ├── NotionClient.php
│   │   │   ├── NotionAuth.php
│   │   │   ├── RateLimiter.php
│   │   │   └── RequestLogger.php
│   │   │
│   │   ├── Sync/                   # Synchronization engine
│   │   │   ├── SyncOrchestrator.php
│   │   │   ├── NotionToWP.php      # Notion → WordPress sync
│   │   │   ├── WPToNotion.php      # WordPress → Notion sync
│   │   │   ├── ConflictResolver.php
│   │   │   ├── DeltaDetector.php
│   │   │   └── BatchProcessor.php
│   │   │
│   │   ├── Converters/             # Block/content converters
│   │   │   ├── BlockConverterInterface.php
│   │   │   ├── BlockConverterRegistry.php
│   │   │   ├── NotionToGutenberg/
│   │   │   │   ├── ParagraphConverter.php
│   │   │   │   ├── HeadingConverter.php
│   │   │   │   ├── ListConverter.php
│   │   │   │   ├── ImageConverter.php
│   │   │   │   ├── CodeConverter.php
│   │   │   │   ├── TableConverter.php
│   │   │   │   ├── CalloutConverter.php
│   │   │   │   ├── ToggleConverter.php
│   │   │   │   ├── EmbedConverter.php
│   │   │   │   └── FallbackConverter.php
│   │   │   └── GutenbergToNotion/
│   │   │       ├── ParagraphConverter.php
│   │   │       ├── HeadingConverter.php
│   │   │       └── ... (mirrors above)
│   │   │
│   │   ├── Media/                  # Media handling
│   │   │   ├── MediaImporter.php
│   │   │   ├── ImageDownloader.php
│   │   │   ├── FileUploader.php
│   │   │   ├── MediaCache.php
│   │   │   └── DuplicationChecker.php
│   │   │
│   │   ├── Navigation/             # Menu & hierarchy management
│   │   │   ├── MenuGenerator.php
│   │   │   ├── HierarchyMapper.php
│   │   │   ├── LinkConverter.php   # Internal Notion links → WP permalinks
│   │   │   └── PageTreeBuilder.php
│   │   │
│   │   ├── Database/               # Data persistence layer
│   │   │   ├── Models/
│   │   │   │   ├── SyncMapping.php # Notion ID ↔ WP Post ID
│   │   │   │   ├── SyncLog.php
│   │   │   │   └── FieldMap.php
│   │   │   ├── Repositories/
│   │   │   │   ├── SyncMappingRepository.php
│   │   │   │   ├── SyncLogRepository.php
│   │   │   │   └── FieldMapRepository.php
│   │   │   └── Schema/
│   │   │       └── DatabaseSchema.php # Custom table definitions
│   │   │
│   │   ├── Queue/                  # Background job processing
│   │   │   ├── QueueInterface.php
│   │   │   ├── ActionSchedulerQueue.php # Action Scheduler adapter
│   │   │   ├── Jobs/
│   │   │   │   ├── ImportPageJob.php
│   │   │   │   ├── ImportImageJob.php
│   │   │   │   ├── SyncDatabaseJob.php
│   │   │   │   └── PollNotionJob.php
│   │   │   └── JobDispatcher.php
│   │   │
│   │   ├── REST/                   # WordPress REST API endpoints
│   │   │   ├── RestController.php
│   │   │   ├── WebhookController.php # Notion webhook receiver
│   │   │   └── SyncController.php
│   │   │
│   │   ├── Caching/                # Caching strategies
│   │   │   ├── CacheInterface.php
│   │   │   ├── ObjectCache.php     # WordPress object cache wrapper
│   │   │   ├── TransientCache.php
│   │   │   └── CacheWarmer.php
│   │   │
│   │   └── Utilities/              # Helper classes
│   │       ├── Logger.php
│   │       ├── Sanitizer.php
│   │       ├── Validator.php
│   │       └── ErrorHandler.php
│   │
│   ├── config/                     # Configuration files (gitignored per worktree)
│   │   ├── .gitignore              # Ignore all config except examples
│   │   ├── block-maps.example.json # Example block mapping config
│   │   ├── field-maps.example.json # Example field mapping config
│   │   └── sync-strategies.example.json
│   │
│   ├── assets/                     # Frontend assets
│   │   ├── src/                    # Source files (not gitignored)
│   │   │   ├── js/
│   │   │   │   ├── admin.js
│   │   │   │   ├── field-mapper.js
│   │   │   │   └── sync-status.js
│   │   │   └── scss/
│   │   │       ├── admin.scss
│   │   │       └── components/
│   │   │           ├── _settings.scss
│   │   │           └── _sync-log.scss
│   │   │
│   │   └── dist/                   # Built assets (gitignored)
│   │       ├── js/
│   │       │   ├── admin.min.js
│   │       │   └── admin.min.js.map
│   │       └── css/
│   │           ├── admin.min.css
│   │           └── admin.min.css.map
│   │
│   ├── templates/                  # PHP template files
│   │   ├── admin/
│   │   │   ├── settings-page.php
│   │   │   ├── sync-dashboard.php
│   │   │   ├── field-mapping.php
│   │   │   ├── sync-log.php
│   │   │   └── partials/
│   │   │       ├── connection-status.php
│   │   │       └── sync-options.php
│   │   └── blocks/
│   │       └── notion-embed.php    # If we create custom Gutenberg blocks
│   │
│   ├── languages/                  # Internationalization
│   │   ├── notion-sync.pot
│   │   └── ... (generated .po/.mo files)
│   │
│   ├── tests/                      # Test suite
│   │   ├── bootstrap.php
│   │   ├── Unit/
│   │   │   ├── Converters/
│   │   │   │   ├── ParagraphConverterTest.php
│   │   │   │   └── ...
│   │   │   ├── Sync/
│   │   │   │   └── SyncOrchestratorTest.php
│   │   │   └── ...
│   │   ├── Integration/
│   │   │   ├── NotionAPITest.php
│   │   │   └── SyncWorkflowTest.php
│   │   └── Fixtures/
│   │       ├── notion-responses/
│   │       │   ├── page-response.json
│   │       │   └── database-response.json
│   │       └── wordpress/
│   │           └── test-data.sql
│   │
│   ├── build/                      # Build tooling (Webpack, Gulp, etc.)
│   │   ├── webpack.config.js
│   │   ├── webpack.dev.js
│   │   ├── webpack.prod.js
│   │   └── .babelrc
│   │
│   ├── vendor/                     # Composer dependencies (gitignored)
│   ├── node_modules/               # NPM dependencies (gitignored)
│   │
│   ├── .phpcs.xml                  # PHP CodeSniffer config (WordPress standards)
│   ├── phpunit.xml                 # PHPUnit configuration
│   └── README.md                   # Plugin development documentation
│
├── docs/                           # Project documentation
│   ├── architecture/
│   │   ├── project-structure.md    # This file
│   │   ├── worktrees.md
│   │   ├── dependency-injection.md
│   │   └── sync-architecture.md
│   ├── product/
│   │   └── prd.md
│   ├── requirements/
│   │   └── requirements.md
│   └── api/
│       ├── notion-api-reference.md
│       └── block-mapping-guide.md
│
├── scripts/                        # Development scripts
│   ├── setup-worktree.sh           # Initialize new worktree with .env
│   ├── teardown-worktree.sh        # Cleanup worktree environment
│   ├── wp-cli.sh                   # WP-CLI wrapper for current worktree
│   └── install-wp.sh               # Fresh WordPress installation script
│
├── Makefile                        # Common development commands
├── CLAUDE.md                       # Claude Code instructions (project-level)
└── README.md                       # Project overview

# WORKTREE STRUCTURE (example: feature-block-mapping)
feature-block-mapping/              # Git worktree for specific feature
├── .env                            # Worktree-specific environment (GITIGNORED)
├── .env.backup                     # Optional backup (GITIGNORED)
├── docker-compose.override.yml     # Worktree-specific overrides (GITIGNORED)
│
├── plugin/                         # Symlink or shared code (from main repo)
│   ├── config/                     # Worktree-specific configs
│   │   ├── block-maps.json         # Custom block mappings for testing
│   │   └── field-maps.json
│   ├── assets/dist/                # Built assets (GITIGNORED)
│   └── ... (rest is shared from main repo)
│
├── logs/                           # Worktree-specific logs (GITIGNORED)
│   ├── sync.log
│   ├── api.log
│   └── php-error.log
│
└── .wp-cli/                        # WP-CLI worktree config (GITIGNORED)
    └── config.yml
```

---

## Directory Explanations

### Root Level

#### `/docker/` - Shared Docker Infrastructure
- **Purpose**: Contains Docker Compose configurations and service customizations shared across all worktrees
- **Key Files**:
  - `compose.yml`: Main service definitions (MariaDB, WordPress, optional Traefik)
  - `traefik/`: Reverse proxy for hostname-based routing (`feature-foo.localtest.me`)
  - `mysql/init/`: Database initialization scripts
  - `wordpress/`: PHP and Apache configurations
- **Why Here**: Docker configs are environment-agnostic; all worktrees reference `../docker/compose.yml`

#### `/plugin/` - Plugin Source Code
All plugin code lives here and is shared across worktrees via Git. Changes made in any worktree affect this directory.

##### `/plugin/src/` - PSR-4 Namespaced Code
- **Namespace Root**: `NotionSync\`
- **Autoloading**: Configured in `composer.json` as `"NotionSync\\": "src/"`
- **Structure Philosophy**: Organize by domain concern (Admin, Sync, API, etc.) not by technical layer

**Key Subdirectories**:

1. **`Admin/`**: WordPress admin interface components
   - Settings pages, field mapping UI, sync dashboard
   - Uses WordPress Settings API and admin_menu hooks

2. **`API/`**: External service integration
   - `NotionClient.php`: Wraps Notion API with retry logic
   - `RateLimiter.php`: Enforces 50 req/sec Notion limit
   - `RequestLogger.php`: Logs API calls for debugging

3. **`Sync/`**: Core synchronization engine
   - `SyncOrchestrator.php`: Coordinates sync jobs
   - `NotionToWP.php` / `WPToNotion.php`: Directional sync logic
   - `BatchProcessor.php`: Handles pagination (100 entries/query)

4. **`Converters/`**: Block mapping system
   - **Extensibility Point**: Implement `BlockConverterInterface` for custom converters
   - `BlockConverterRegistry.php`: Manages converter registration via filters
   - Separate directories for each direction (NotionToGutenberg, GutenbergToNotion)
   - **Pattern**: One converter per block type (Single Responsibility Principle)

5. **`Media/`**: Media handling with deduplication
   - `MediaImporter.php`: Downloads Notion images to WP Media Library
   - `MediaCache.php`: Tracks Notion block ID → WP attachment ID mapping
   - `DuplicationChecker.php`: Prevents re-importing unchanged images

6. **`Navigation/`**: Hierarchy and internal link management
   - `MenuGenerator.php`: Creates/updates WordPress nav menus from Notion structure
   - `LinkConverter.php`: Replaces Notion page links with WP permalinks
   - Uses `SyncMappingRepository` to resolve Notion IDs → WP URLs

7. **`Database/`**: Repository pattern for data persistence
   - **Models**: Plain PHP objects representing data structures
   - **Repositories**: Data access layer (CRUD operations)
   - **Schema**: Custom table definitions (e.g., `wp_notion_sync_mappings`)
   - **Why Custom Tables**: Post meta queries don't scale beyond 1000+ pages

8. **`Queue/`**: Background job processing
   - **Action Scheduler Integration**: Uses existing WP plugin for reliability
   - Each job is a class implementing `execute()` method
   - `QueueInterface` allows swapping implementations (e.g., future Redis support)

9. **`REST/`**: REST API endpoints
   - `WebhookController.php`: Receives Notion webhook POST requests
   - `SyncController.php`: Manual sync triggers via AJAX
   - Registered using `rest_api_init` hook

##### `/plugin/config/` - Runtime Configuration
- **Gitignored**: Each worktree has its own config files
- **Committed**: `.example` files showing structure
- **Use Cases**:
  - Testing different block mappings without code changes
  - Worktree-specific field mapping strategies
  - Per-environment sync options (e.g., dry-run mode in test worktree)

##### `/plugin/assets/` - Frontend Assets
- **`src/`**: Source files (ES6, SCSS) - **committed to Git**
- **`dist/`**: Compiled, minified files - **gitignored**
- **Build Process**: `npm run build` or `npm run watch`
- **Why Gitignore Dist**: Each worktree may be on different branches; avoid merge conflicts on compiled output

##### `/plugin/templates/` - PHP View Templates
- Separate presentation from logic (MVC pattern)
- Loaded via `include` with scoped variables
- Uses WordPress template conventions (e.g., `the_*` functions)

##### `/plugin/tests/` - Test Suite
- **Unit Tests**: Test individual classes in isolation (mocked dependencies)
- **Integration Tests**: Test full workflows (requires WordPress test environment)
- **Fixtures**: JSON responses from Notion API, SQL test data
- **Run Tests**: `composer test` or `vendor/bin/phpunit`

#### `/scripts/` - Development Automation
Helper scripts to streamline worktree workflow:

- **`setup-worktree.sh`**:
  ```bash
  # Usage: ./scripts/setup-worktree.sh feature-foo 8081 3307
  # Creates .env, spins up Docker, installs WP, activates plugin
  ```
- **`teardown-worktree.sh`**: Stops containers, removes volumes, deletes worktree
- **`wp-cli.sh`**: Wrapper to run WP-CLI in current worktree's container
  ```bash
  ./scripts/wp-cli.sh plugin activate notion-sync
  ```

#### `/Makefile` - Common Commands
Provides consistent interface across worktrees:
```makefile
up:           # docker compose up -d
down:         # docker compose down
logs:         # docker compose logs -f wordpress
shell:        # docker exec -it ${COMPOSE_PROJECT_NAME}_wp bash
wp:           # WP-CLI shortcut
test:         # Run PHPUnit tests
build-assets: # npm run build
```

---

## Git Worktree Integration

### What Gets Tracked vs. Ignored

#### **Tracked (Shared Across Worktrees)**
- All `/plugin/` source code (`src/`, `templates/`, `tests/`)
- Asset source files (`assets/src/`)
- Docker infrastructure (`docker/`)
- Configuration examples (`.example` files)
- Documentation (`docs/`)
- Scripts (`scripts/`)

#### **Gitignored (Worktree-Specific)**
- `.env` - Contains unique ports, project names, DB names
- `docker-compose.override.yml` - Worktree-specific overrides
- `plugin/config/*.json` (except `*.example.json`)
- `plugin/assets/dist/` - Compiled assets
- `plugin/vendor/` - Composer dependencies (rebuilt per worktree)
- `plugin/node_modules/` - NPM dependencies (rebuilt per worktree)
- `logs/` - Per-worktree log files
- `.wp-cli/config.yml` - Worktree-specific WP-CLI config

### Root `.gitignore` Template
```gitignore
# Environment-specific files
.env
.env.*
!.env.template
docker-compose.override.yml

# Worktree-specific configs
plugin/config/*.json
plugin/config/*.yaml
!plugin/config/*.example.json
!plugin/config/*.example.yaml

# Build artifacts
plugin/assets/dist/
plugin/vendor/
plugin/node_modules/

# Logs
logs/
*.log

# IDE files
.vscode/
.idea/
*.swp
*.swo

# WordPress core (if mounted as volume)
wordpress/
!wordpress/.htaccess.template

# OS files
.DS_Store
Thumbs.db

# WP-CLI
.wp-cli/config.yml
```

### Worktree Workflow

#### Creating a New Worktree
```bash
# 1. Create git worktree for new feature
git worktree add ../feature-block-mapping feature-block-mapping

# 2. Navigate to worktree
cd ../feature-block-mapping

# 3. Copy and configure environment
cp ../.env.template .env

# Edit .env with unique values:
# COMPOSE_PROJECT_NAME=notionwp_block_mapping
# HTTP_PORT=8081
# DB_PORT=3307
# WP_SITE_HOST=block-mapping.localtest.me
# DB_NAME=wp_block_mapping
# WP_TABLE_PREFIX=wpbm_

# 4. Spin up Docker environment
docker compose -f ../docker/compose.yml up -d

# 5. Install WordPress
./scripts/install-wp.sh

# 6. Activate plugin and set up test data
docker exec notionwp_block_mapping_wp wp plugin activate notion-sync
docker exec notionwp_block_mapping_wp wp option update notion_sync_token "secret_xxx"

# 7. Install dependencies and build assets
cd plugin
composer install
npm install
npm run build
```

#### Switching Between Worktrees
Each worktree is completely isolated. Simply `cd` between directories:
```bash
# Work on feature A
cd ~/Projects/notion-wp/feature-block-mapping
docker compose -f ../docker/compose.yml logs -f

# Switch to feature B (different terminal)
cd ~/Projects/notion-wp/feature-navigation
docker compose -f ../docker/compose.yml up -d
# Access at http://navigation.localtest.me:8082
```

#### Cleaning Up a Worktree
```bash
# 1. Stop and remove containers/volumes
cd ../feature-block-mapping
docker compose -f ../docker/compose.yml down -v

# 2. Remove worktree
cd ..
git worktree remove feature-block-mapping

# 3. (Optional) Delete branch
git branch -d feature-block-mapping
```

---

## Docker Environment Integration

### Compose Project Isolation
Each worktree uses unique Docker resources via `.env`:

```yaml
# docker/compose.yml (shared)
services:
  db:
    container_name: ${COMPOSE_PROJECT_NAME}_db
    ports:
      - "${DB_PORT:-3306}:3306"
    volumes:
      - db_data:/var/lib/mysql
    environment:
      MYSQL_DATABASE: ${DB_NAME}
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD:-root}

  wordpress:
    container_name: ${COMPOSE_PROJECT_NAME}_wp
    ports:
      - "${HTTP_PORT:-8080}:80"
    volumes:
      - ./plugin:/var/www/html/wp-content/plugins/notion-sync:rw
      - wp_data:/var/www/html
    environment:
      WORDPRESS_DB_HOST: db
      WORDPRESS_DB_NAME: ${DB_NAME}
      WORDPRESS_TABLE_PREFIX: ${WP_TABLE_PREFIX}
    depends_on:
      - db

volumes:
  db_data:
    name: ${COMPOSE_PROJECT_NAME}_db
  wp_data:
    name: ${COMPOSE_PROJECT_NAME}_wp
```

### Key Isolation Mechanisms
1. **Container Names**: `${COMPOSE_PROJECT_NAME}_db` prevents conflicts
2. **Ports**: Each worktree uses unique `HTTP_PORT` and `DB_PORT`
3. **Volumes**: Named volumes include project name to avoid data collisions
4. **Database**: Separate `DB_NAME` and `WP_TABLE_PREFIX` per worktree
5. **Hostnames**: Traefik routes `*.localtest.me` to correct container

### Plugin Mount Strategy
```yaml
volumes:
  - ./plugin:/var/www/html/wp-content/plugins/notion-sync:rw
```

**Why This Works**:
- WordPress core lives in named volume (`wp_data`)
- Only plugin directory is mounted from host
- Changes to plugin code are immediately reflected in all containers mounting same path
- Each worktree's checkout points to different Git branch → different code

### Traefik Setup (Optional)
For hostname-based routing without port juggling:

```yaml
# docker/traefik/traefik.yml
entryPoints:
  web:
    address: ":80"

providers:
  docker:
    exposedByDefault: false

# docker/compose.yml additions
services:
  traefik:
    image: traefik:v3.0
    ports:
      - "80:80"
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock:ro
      - ./docker/traefik/traefik.yml:/etc/traefik/traefik.yml:ro

  wordpress:
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.${COMPOSE_PROJECT_NAME}.rule=Host(`${WP_SITE_HOST}`)"
      - "traefik.http.services.${COMPOSE_PROJECT_NAME}.loadbalancer.server.port=80"
```

**Access**: `http://block-mapping.localtest.me` (no port needed)

---

## Plugin Architecture

### Namespace Structure
```
NotionSync\                         (root namespace)
├── Admin\                          (admin UI components)
├── API\                            (external API clients)
├── Sync\                           (sync orchestration)
├── Converters\                     (block converters)
│   ├── NotionToGutenberg\
│   └── GutenbergToNotion\
├── Media\                          (media handling)
├── Navigation\                     (menus & hierarchy)
├── Database\                       (data persistence)
│   ├── Models\
│   └── Repositories\
├── Queue\                          (background jobs)
│   └── Jobs\
├── REST\                           (REST API endpoints)
├── Caching\                        (caching strategies)
└── Utilities\                      (helpers)
```

### Autoloading Configuration
```json
// plugin/composer.json
{
  "autoload": {
    "psr-4": {
      "NotionSync\\": "src/"
    }
  },
  "require": {
    "php": "^8.1",
    "guzzlehttp/guzzle": "^7.0",
    "monolog/monolog": "^3.0"
  },
  "require-dev": {
    "phpunit/phpunit": "^10.0",
    "mockery/mockery": "^1.5",
    "squizlabs/php_codesniffer": "^3.7"
  }
}
```

### Dependency Injection Container
```php
// plugin/src/Container.php
namespace NotionSync;

class Container {
    private static $instances = [];

    public static function get($class) {
        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = self::resolve($class);
        }
        return self::$instances[$class];
    }

    private static function resolve($class) {
        // Constructor injection with dependency resolution
        $reflection = new \ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if (!$constructor) {
            return new $class();
        }

        $dependencies = [];
        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType()?->getName();
            $dependencies[] = $type ? self::get($type) : null;
        }

        return $reflection->newInstanceArgs($dependencies);
    }
}
```

### Plugin Bootstrap
```php
// plugin/notion-sync.php
/**
 * Plugin Name: Notion Sync
 * Description: Bi-directional synchronization between Notion and WordPress
 * Version: 1.0.0
 * Requires PHP: 8.1
 * Author: Your Name
 * License: GPL-3.0+
 * Text Domain: notion-sync
 */

namespace NotionSync;

if (!defined('ABSPATH')) exit;

// Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Plugin constants
define('NOTION_SYNC_VERSION', '1.0.0');
define('NOTION_SYNC_PATH', plugin_dir_path(__FILE__));
define('NOTION_SYNC_URL', plugin_dir_url(__FILE__));

// Initialize plugin
add_action('plugins_loaded', function() {
    $bootstrap = Container::get(Bootstrap::class);
    $bootstrap->init();
});

// Activation hook
register_activation_hook(__FILE__, function() {
    require_once NOTION_SYNC_PATH . 'src/Database/Schema/DatabaseSchema.php';
    Database\Schema\DatabaseSchema::create();
    flush_rewrite_rules();
});
```

### Hook Registration Strategy
```php
// plugin/src/Bootstrap.php
namespace NotionSync;

class Bootstrap {
    public function init() {
        // Admin interface
        if (is_admin()) {
            add_action('admin_menu', [$this, 'register_admin_menu']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        }

        // REST API
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // Background jobs (Action Scheduler)
        add_action('init', [$this, 'register_scheduled_actions']);

        // Extensibility hooks
        do_action('notion_sync_loaded');
    }

    public function register_admin_menu() {
        $controller = Container::get(Admin\AdminController::class);
        add_menu_page(
            'Notion Sync',
            'Notion Sync',
            'manage_options',
            'notion-sync',
            [$controller, 'render_dashboard'],
            'dashicons-cloud',
            30
        );
    }
}
```

### Extensibility: Custom Block Converters
```php
// Allow developers to register custom converters
add_filter('notion_sync_block_converters', function($converters) {
    $converters['custom_block_type'] = MyCustomConverter::class;
    return $converters;
});

// Usage in registry
namespace NotionSync\Converters;

class BlockConverterRegistry {
    public function get_converter($block_type) {
        $converters = apply_filters('notion_sync_block_converters', [
            'paragraph' => NotionToGutenberg\ParagraphConverter::class,
            'heading_1' => NotionToGutenberg\HeadingConverter::class,
            // ... defaults
        ]);

        $class = $converters[$block_type] ?? FallbackConverter::class;
        return Container::get($class);
    }
}
```

---

## Configuration Management

### Environment Variables (`.env`)
```bash
# Worktree-specific - NEVER commit this file

# Docker Compose
COMPOSE_PROJECT_NAME=notionwp_main
HTTP_PORT=8080
DB_PORT=3306

# WordPress
WP_SITE_HOST=main.localtest.me
WP_SITE_URL=http://main.localtest.me:8080
DB_NAME=wp_main
DB_ROOT_PASSWORD=root
WP_TABLE_PREFIX=wp_

# Plugin Config
NOTION_TOKEN=secret_xxx
NOTION_WORKSPACE_ID=abc123
```

### Runtime Configuration Files
Each worktree can test different mapping strategies:

```json
// plugin/config/block-maps.json (gitignored)
{
  "version": "1.0",
  "converters": {
    "notion_to_gutenberg": {
      "paragraph": "NotionSync\\Converters\\NotionToGutenberg\\ParagraphConverter",
      "callout": "MyPlugin\\CustomCalloutConverter"
    }
  },
  "fallback_strategy": "preserve_as_html"
}
```

```json
// plugin/config/field-maps.json (gitignored)
{
  "databases": {
    "abc123def456": {
      "target_post_type": "post",
      "property_mappings": {
        "Name": "post_title",
        "Published": "post_date",
        "Tags": "post_tag",
        "Category": "category",
        "Meta Description": "_yoast_wpseo_metadesc"
      }
    }
  }
}
```

### Configuration Loading
```php
// plugin/src/Admin/ConfigLoader.php
namespace NotionSync\Admin;

class ConfigLoader {
    public static function load($config_name) {
        $path = NOTION_SYNC_PATH . "config/{$config_name}.json";
        $example_path = NOTION_SYNC_PATH . "config/{$config_name}.example.json";

        if (file_exists($path)) {
            $content = file_get_contents($path);
        } elseif (file_exists($example_path)) {
            $content = file_get_contents($example_path);
        } else {
            return [];
        }

        return json_decode($content, true);
    }
}
```

---

## Development Workflow

### Daily Development Cycle

1. **Start Worktree Environment**
   ```bash
   cd ~/Projects/notion-wp/feature-x
   make up
   ```

2. **Make Code Changes**
   - Edit files in `plugin/src/`
   - Changes are immediately reflected in Docker container

3. **Rebuild Assets (if changed)**
   ```bash
   cd plugin
   npm run watch  # Auto-rebuild on changes
   ```

4. **Test Changes**
   ```bash
   # Manual testing
   open http://feature-x.localtest.me:8081/wp-admin

   # Unit tests
   make test

   # Integration tests (requires WP test environment)
   make test-integration
   ```

5. **Commit Changes**
   ```bash
   git add plugin/src/Admin/NewFeature.php
   git commit -m "Add new admin feature for X"
   ```

6. **Push to Remote**
   ```bash
   git push origin feature-x
   ```

### Working Across Multiple Worktrees

**Scenario**: Testing navigation feature requires testing with block mapping changes.

```bash
# Terminal 1: Main feature
cd ~/Projects/notion-wp/feature-navigation
make up
# Access: http://navigation.localtest.me:8081

# Terminal 2: Dependency feature
cd ~/Projects/notion-wp/feature-block-mapping
make up
# Access: http://block-mapping.localtest.me:8082

# Test interaction by importing changes:
cd ~/Projects/notion-wp/feature-navigation
git merge feature-block-mapping
# Or cherry-pick specific commits
git cherry-pick abc123def
```

### Asset Build Pipeline

**Development Mode** (auto-rebuild):
```bash
cd plugin
npm run watch
# webpack --mode development --watch
```

**Production Build** (before commit):
```bash
npm run build
# webpack --mode production
# Outputs minified files to assets/dist/
```

**Webpack Configuration** (`plugin/build/webpack.config.js`):
```javascript
module.exports = {
  entry: {
    admin: './assets/src/js/admin.js',
  },
  output: {
    path: path.resolve(__dirname, '../assets/dist/js'),
    filename: '[name].min.js',
  },
  module: {
    rules: [
      {
        test: /\.scss$/,
        use: ['style-loader', 'css-loader', 'sass-loader'],
      },
    ],
  },
};
```

### Testing Strategy

**Unit Tests** (fast, no WordPress):
```bash
cd plugin
vendor/bin/phpunit tests/Unit/
```

**Integration Tests** (requires WordPress test environment):
```bash
# Setup test database
./bin/install-wp-tests.sh wp_test root root localhost latest

# Run tests
vendor/bin/phpunit tests/Integration/
```

**Manual Testing Checklist** (per feature):
1. Fresh WordPress installation in worktree
2. Activate plugin
3. Connect to test Notion workspace
4. Sync test database (5-10 pages)
5. Verify:
   - Content accuracy
   - Image imports
   - Internal links
   - Menu generation
   - No PHP errors/warnings

### Code Quality Tools

**PHP CodeSniffer** (WordPress Coding Standards):
```bash
cd plugin
vendor/bin/phpcs --standard=WordPress src/
```

**Auto-fix violations**:
```bash
vendor/bin/phpcbf --standard=WordPress src/
```

**Pre-commit Hook** (`.git/hooks/pre-commit`):
```bash
#!/bin/bash
cd plugin
vendor/bin/phpcs --standard=WordPress src/ || exit 1
npm run lint || exit 1
```

---

## Summary

This structure provides:

1. **Clean Separation**: Shared code in `plugin/`, environment configs in worktrees
2. **Scalability**: PSR-4 autoloading, repository pattern, custom tables for large datasets
3. **Extensibility**: Filter hooks for custom converters, dependency injection for swappable implementations
4. **Maintainability**: Single Responsibility classes, clear directory organization, comprehensive documentation
5. **WordPress Standards**: Follows VIP coding standards, uses WordPress APIs (Settings, REST, Cron)
6. **Worktree Optimization**: Isolated Docker environments, no merge conflicts on generated files
7. **Developer Experience**: Makefile shortcuts, automated setup scripts, asset build pipeline

**Next Steps**:
1. Run `./scripts/setup-worktree.sh main 8080 3306` to create first environment
2. Implement core classes starting with `NotionClient` and `SyncOrchestrator`
3. Build out converter infrastructure for common block types
4. Add Action Scheduler integration for background jobs
5. Create admin UI for connection setup and field mapping

**Questions to Address**:
- Should we use DDEV instead of Docker Compose for simpler multi-site management?
- Do we need multisite support in initial version? (Affects database schema)
- Should configuration be stored in database (wp_options) or JSON files?
- Do we need a staging/preview mode before committing synced content?
