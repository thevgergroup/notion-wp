# Notion-WP Sync Plugin

[![Tests](https://github.com/thevgergroup/notion-wp/actions/workflows/test.yml/badge.svg)](https://github.com/thevgergroup/notion-wp/actions/workflows/test.yml)
[![Code Quality](https://github.com/thevgergroup/notion-wp/actions/workflows/lint.yml/badge.svg)](https://github.com/thevgergroup/notion-wp/actions/workflows/lint.yml)
[![codecov](https://codecov.io/gh/thevgergroup/notion-wp/branch/main/graph/badge.svg)](https://codecov.io/gh/thevgergroup/notion-wp)
[![PHP Version](https://img.shields.io/badge/php-8.0%2B-blue)](https://www.php.net/)
[![WordPress](https://img.shields.io/badge/wordpress-6.0%2B-blue)](https://wordpress.org/)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-green)](LICENSE)

A WordPress plugin for bi-directional synchronization between Notion and WordPress, designed for enterprise-grade performance and extensibility.

## Project Overview

This plugin enables content management in Notion while publishing to WordPress, addressing gaps in existing solutions:

- **Bi-directional sync**: Notion → WordPress (primary), WordPress → Notion (optional)
- **Complete block support**: Extensible converter system for all Notion block types
- **Navigation generation**: Automatic WordPress menu creation from Notion hierarchy
- **Internal link conversion**: Notion page links → WordPress permalinks
- **Media handling**: Download images from Notion, upload to WP Media Library with deduplication
- **Background processing**: Action Scheduler integration for large syncs
- **Field mapping**: Notion properties → WordPress fields (including custom fields, taxonomies, SEO meta)

## Architecture Highlights

- **PSR-4 Autoloading**: Clean namespace structure (`NotionSync\`)
- **Dependency Injection**: Container-based DI for loose coupling and testability
- **Repository Pattern**: Data access abstraction with custom tables for scale
- **Action Scheduler**: Reliable background job processing
- **WordPress VIP Standards**: Follows enterprise coding standards
- **Extensibility**: Hooks and filters for custom block converters and field mappings

See [docs/architecture/project-structure.md](docs/architecture/project-structure.md) for complete architectural details.

## Development Setup

This project uses **git worktrees** with isolated Docker environments for parallel feature development.

### Prerequisites

- Docker and Docker Compose
- Git 2.5+ (for worktree support)
- Node.js 18+ (for asset building)
- Composer (for PHP dependencies)

### Quick Start

1. **Clone the repository**

    ```bash
    git clone <repository-url>
    cd notion-wp
    ```

2. **Create your first worktree**

    ```bash
    ./scripts/setup-worktree.sh main 8080 3306
    ```

    This script:
    - Creates a git worktree named `main`
    - Sets up `.env` with unique ports (HTTP: 8080, DB: 3306)
    - Starts Docker containers (WordPress, MariaDB, Traefik)
    - Installs WordPress
    - Installs Composer/NPM dependencies
    - Builds assets
    - Activates the plugin

3. **Access WordPress**
    - URL: http://main.localtest.me
    - Admin: http://main.localtest.me/wp-admin
    - Username: `admin`
    - Password: `admin`

### Creating Additional Worktrees

For parallel feature development:

```bash
# Terminal 1: Work on sync engine
./scripts/setup-worktree.sh feature-sync 8081 3307
cd ../feature-sync
# Access: http://feature-sync.localtest.me

# Terminal 2: Work on block converters
./scripts/setup-worktree.sh feature-blocks 8082 3308
cd ../feature-blocks
# Access: http://feature-blocks.localtest.me
```

Each worktree has:

- Isolated WordPress installation
- Separate database
- Unique containers and volumes
- Hostname-based routing (via Traefik)

### Common Commands

```bash
# From any worktree directory:
make help              # Show all available commands
make up                # Start containers
make down              # Stop containers
make logs              # View logs
make shell             # Access WordPress container
make wp ARGS="..."     # Run WP-CLI commands
make test              # Run PHPUnit tests
make npm-watch         # Auto-rebuild assets on change
```

See `Makefile` for complete command reference.

## Project Structure

```
notion-wp/                      # Main repository
├── docker/                     # Shared Docker infrastructure
│   ├── compose.yml             # Docker Compose configuration
│   └── config/                 # PHP, Apache configs
├── plugin/                     # Plugin source code
│   ├── notion-sync.php         # Main plugin file
│   ├── composer.json           # PHP dependencies
│   ├── package.json            # Node.js dependencies
│   ├── src/                    # PSR-4 source code (NotionSync\)
│   │   ├── Admin/              # WordPress admin UI
│   │   ├── API/                # Notion API client
│   │   ├── Sync/               # Sync orchestration
│   │   ├── Converters/         # Block converters
│   │   ├── Media/              # Media handling
│   │   ├── Navigation/         # Menu generation
│   │   ├── Database/           # Data persistence
│   │   ├── Queue/              # Background jobs
│   │   └── REST/               # REST API endpoints
│   ├── assets/                 # Frontend assets
│   │   ├── src/                # Source files (committed)
│   │   └── dist/               # Built files (gitignored)
│   ├── templates/              # PHP templates
│   ├── tests/                  # Test suite
│   └── config/                 # Runtime config (gitignored per worktree)
├── scripts/                    # Development automation
│   ├── setup-worktree.sh       # Create new worktree
│   └── teardown-worktree.sh    # Remove worktree
├── docs/                       # Documentation
│   ├── architecture/           # Technical architecture docs
│   ├── product/                # Product requirements
│   └── requirements/           # Functional requirements
├── .env.template               # Environment variable template
├── Makefile                    # Common commands
└── README.md                   # This file

# Worktree structure (e.g., feature-sync/)
feature-sync/                   # Git worktree
├── .env                        # Worktree-specific config (GITIGNORED)
├── plugin/                     # Shared from main repo
│   └── config/                 # Worktree-specific configs (GITIGNORED)
└── logs/                       # Worktree logs (GITIGNORED)
```

## Git Worktree Workflow

### Creating a Feature Branch

```bash
# 1. Create worktree with setup script
./scripts/setup-worktree.sh feature-media-import 8083 3309

# 2. Navigate to worktree
cd ../feature-media-import

# 3. Start coding
vim plugin/src/Media/MediaImporter.php

# 4. Commit changes
git add plugin/src/Media/MediaImporter.php
git commit -m "Implement media import with deduplication"

# 5. Push to remote
git push origin feature-media-import
```

### Merging Changes

```bash
# From main worktree
cd ~/Projects/notion-wp/main
git merge feature-media-import
git push origin main
```

### Cleaning Up

```bash
# Teardown worktree and optionally delete branch
./scripts/teardown-worktree.sh feature-media-import --delete-branch
```

## Configuration

### Environment Variables

Each worktree has its own `.env` file (copied from `.env.template`):

```bash
# Docker isolation (must be unique)
COMPOSE_PROJECT_NAME=notionwp_main
WP_SITE_HOST=main.localtest.me
DB_NAME=wp_main
DB_PORT=3306    # For external DB access
HTTP_PORT=8080  # Not needed with Traefik

# WordPress credentials
WP_ADMIN_USER=admin
WP_ADMIN_PASSWORD=admin

# Plugin config (add after setup)
NOTION_TOKEN=secret_xxx
NOTION_WORKSPACE_ID=abc123
```

### Runtime Configuration

Worktree-specific plugin configs (gitignored):

```json
// plugin/config/block-maps.json
{
	"converters": {
		"paragraph": "NotionSync\\Converters\\NotionToGutenberg\\ParagraphConverter",
		"callout": "MyCustom\\CalloutConverter"
	}
}
```

```json
// plugin/config/field-maps.json
{
	"databases": {
		"notion-db-id": {
			"target_post_type": "post",
			"property_mappings": {
				"Name": "post_title",
				"Tags": "post_tag",
				"Meta Description": "_yoast_wpseo_metadesc"
			}
		}
	}
}
```

## Development Workflow

### Daily Development

1. **Start environment**

    ```bash
    cd ~/Projects/notion-wp/feature-x
    make up
    ```

2. **Watch assets** (auto-rebuild on changes)

    ```bash
    cd plugin
    npm run watch
    ```

3. **Make code changes**
    - Edit files in `plugin/src/`
    - Changes reflect immediately in container

4. **Test changes**

    ```bash
    make test           # PHPUnit tests
    make phpcs          # Code standards check
    ```

5. **Commit and push**
    ```bash
    git add .
    git commit -m "Descriptive message"
    git push
    ```

### Testing

```bash
# Unit tests (fast, no WordPress)
cd plugin
vendor/bin/phpunit tests/Unit/

# Integration tests (requires WordPress)
vendor/bin/phpunit tests/Integration/

# Code standards
vendor/bin/phpcs --standard=WordPress src/
vendor/bin/phpcbf --standard=WordPress src/  # Auto-fix
```

### Debugging

1. **View logs**

    ```bash
    make logs           # All containers
    make logs-wp        # WordPress only
    ```

2. **Access container shell**

    ```bash
    make shell          # WordPress container
    make shell-db       # Database container
    ```

3. **WP-CLI commands**

    ```bash
    make wp ARGS="plugin list"
    make wp ARGS="option get siteurl"
    make wp ARGS="db query 'SELECT * FROM wp_posts LIMIT 5'"
    ```

4. **Enable WordPress debug mode**

    ```php
    // Edit .env
    WP_DEBUG=1
    WP_DEBUG_LOG=1
    WP_DEBUG_DISPLAY=0

    // Restart containers
    make restart

    // View logs
    make shell
    tail -f /var/www/html/wp-content/debug.log
    ```

## Plugin Development

### Adding a New Block Converter

1. **Create converter class**

    ```php
    // plugin/src/Converters/NotionToGutenberg/MyBlockConverter.php
    namespace NotionSync\Converters\NotionToGutenberg;

    class MyBlockConverter implements BlockConverterInterface {
        public function convert(array $notion_block): string {
            // Convert Notion block to Gutenberg HTML
            return '<!-- wp:custom/block -->Content<!-- /wp:custom/block -->';
        }
    }
    ```

2. **Register converter via filter**
    ```php
    add_filter('notion_sync_block_converters', function($converters) {
        $converters['my_block_type'] = MyBlockConverter::class;
        return $converters;
    });
    ```

### Adding a Background Job

1. **Create job class**

    ```php
    // plugin/src/Queue/Jobs/MyJob.php
    namespace NotionSync\Queue\Jobs;

    class MyJob {
        public function execute($args) {
            // Job logic
        }
    }
    ```

2. **Dispatch job**

    ```php
    use NotionSync\Queue\JobDispatcher;

    $dispatcher = Container::get(JobDispatcher::class);
    $dispatcher->dispatch(MyJob::class, ['arg1' => 'value']);
    ```

### Adding a REST Endpoint

```php
// plugin/src/REST/CustomController.php
namespace NotionSync\REST;

class CustomController {
    public function register_routes() {
        register_rest_route('notion-sync/v1', '/custom', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_request'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);
    }

    public function handle_request($request) {
        // Handle request
        return rest_ensure_response(['status' => 'success']);
    }

    public function check_permissions() {
        return current_user_can('manage_options');
    }
}
```

## Troubleshooting

### Port Conflicts

If you get "port already in use" errors:

1. Check `.env` for unique `HTTP_PORT` and `DB_PORT`
2. Ensure `COMPOSE_PROJECT_NAME` is unique
3. Check for orphaned containers: `docker ps -a`

### Database Connection Errors

```bash
# Check database status
make ps

# View database logs
make logs-db

# Restart services
make restart
```

### Plugin Not Activating

```bash
# Check plugin status
make wp ARGS="plugin list"

# View error logs
make logs-wp

# Try manual activation
make activate-plugin
```

### Asset Build Failures

```bash
cd plugin
rm -rf node_modules/
npm install
npm run build
```

## Documentation

- [Project Structure](docs/architecture/project-structure.md) - Complete directory structure and architecture
- [Git Worktrees Guide](docs/architecture/worktrees.md) - Worktree setup and best practices
- [Product Requirements](docs/product/prd.md) - Feature specifications and research
- [Technical Requirements](docs/requirements/requirements.md) - Functional requirements

## Contributing

1. Create a new worktree for your feature
2. Follow WordPress VIP coding standards
3. Write tests for new functionality
4. Run `make phpcs` and `make test` before committing
5. Submit pull request with detailed description

## License

GPL-3.0+ (WordPress plugin license)

## Credits

Developed as a WordPress plugin architecture reference following enterprise best practices.

## Support

For questions or issues, please refer to:

- [CLAUDE.md](CLAUDE.md) - Claude Code development instructions
- [docs/](docs/) - Technical documentation
- Project issue tracker (add GitHub/GitLab URL)
