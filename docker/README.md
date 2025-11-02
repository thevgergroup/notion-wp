# Docker Development Environment for Notion-WordPress Plugin

Complete Docker-based development environment with **full git worktree support**. Each worktree runs an isolated WordPress instance with its own database, containers, and hostname—enabling concurrent development across multiple branches without conflicts.

## Architecture Overview

### Isolation Strategy

Every worktree is completely isolated using:

1. **Unique Project Names**: `COMPOSE_PROJECT_NAME` prefixes all containers, networks, and volumes
2. **Named Volumes**: Database and WordPress data stored separately per worktree
3. **Traefik Routing**: Hostname-based routing (e.g., `foo.localtest.me`, `bar.localtest.me`)
4. **Separate Networks**: Internal network per worktree + shared Traefik network

### Services

- **Traefik** (shared): Reverse proxy for hostname-based routing
- **MariaDB** (per worktree): Isolated database with unique name
- **WordPress** (per worktree): PHP 8.3 + Apache with plugin mounted
- **WP-CLI** (per worktree): Command-line WordPress management

### Technology Stack

- **Docker Compose v3.8**
- **WordPress**: php8.3-apache (configurable)
- **MariaDB**: v11
- **Traefik**: v2.11
- **WP-CLI**: php8.3 (configurable)

## Quick Start

### 1. Prerequisites

- Docker Desktop (or Docker Engine + Docker Compose)
- Git
- Make (optional, but recommended)

### 2. Initial Setup (Main Worktree)

```bash
# Navigate to your main repository
cd /path/to/notion-wp

# Copy environment template
cp .env.template .env

# Edit .env with your preferred editor
# Set COMPOSE_PROJECT_NAME=notionwp_main
# Set WP_SITE_HOST=main.localtest.me
nano .env

# Start services
make up

# Install WordPress and activate plugin
make install

# Visit your site
open http://main.localtest.me
```

### 3. Create Additional Worktrees

```bash
# From main repository
cd /path/to/notion-wp

# Create worktree for feature branch
git worktree add ../notion-wp-feature-blocks feature/block-mapping

# Navigate to worktree
cd ../notion-wp-feature-blocks

# Copy and customize environment
cp ../notion-wp/.env.template .env

# Edit .env with UNIQUE values:
# COMPOSE_PROJECT_NAME=notionwp_feature_blocks
# WP_SITE_HOST=feature-blocks.localtest.me
nano .env

# Start isolated environment
make up install

# Visit worktree site
open http://feature-blocks.localtest.me
```

Both environments now run **simultaneously** without conflicts!

## Environment Configuration

### Required Variables (.env)

Each worktree **must** have unique values for:

```bash
# Unique identifier for this worktree
COMPOSE_PROJECT_NAME=notionwp_<worktree-name>

# Unique hostname (uses localtest.me for automatic DNS)
WP_SITE_HOST=<worktree-name>.localtest.me

# Unique database name
DB_NAME=wp_<worktree-name>
```

### Optional Variables

```bash
# External database port (for DB clients like TablePlus)
# Only needed if accessing DB externally
# Must be unique if multiple worktrees running
DB_PORT=3307

# WordPress version
WP_VERSION=php8.3-apache

# WordPress debug settings
WP_DEBUG=1
WP_DEBUG_LOG=1
WP_DEBUG_DISPLAY=0

# Site installation defaults
WP_TITLE="Notion Sync Dev"
WP_ADMIN_USER=admin
WP_ADMIN_PASSWORD=admin
WP_ADMIN_EMAIL=admin@example.com
```

### Full Example (.env)

```bash
# Project isolation
COMPOSE_PROJECT_NAME=notionwp_fix_auth
WP_SITE_HOST=fix-auth.localtest.me

# Database
DB_NAME=wp_fix_auth
WP_TABLE_PREFIX=wp_
DB_USER=wp
DB_PASSWORD=wp
DB_ROOT_PASSWORD=root

# WordPress
WP_VERSION=php8.3-apache
WP_DEBUG=1

# Installation
WP_TITLE="Auth Fix Branch"
WP_ADMIN_USER=admin
WP_ADMIN_PASSWORD=admin
WP_ADMIN_EMAIL=dev@example.com
```

## Volume Management

### Database Volume Strategy (NEW in Phase 5.8)

By default, the database volume is **shared across all branches** to preserve Notion tokens and WordPress settings when switching branches. This dramatically improves developer experience by eliminating the need to reconfigure settings on every branch switch.

#### Shared Volume (Default)

```bash
# In your .env file
DB_VOLUME_NAME=notionwp_shared_db
```

**Benefits:**
- ✅ Notion API token persists across all branches
- ✅ WordPress admin settings (permalink structure, theme, plugins) preserved
- ✅ Test content and sample data available everywhere
- ✅ Faster branch switching (no re-setup needed)
- ✅ Consistent development environment

**Use for:**
- Normal feature development
- Bug fixes
- Documentation updates
- Most development workflows

#### Isolated Volume (Optional)

```bash
# In your .env file - uncomment to enable
DB_VOLUME_NAME=${COMPOSE_PROJECT_NAME}_db_data
```

**Benefits:**
- ✅ Clean slate for each branch
- ✅ Test fresh WordPress installations
- ✅ Verify plugin activation hooks
- ✅ Test database migrations

**Use for:**
- Testing plugin installation from scratch
- Database migration testing
- Clean environment verification
- WordPress version compatibility testing

### Migrating Existing Data to Shared Volume

If you have an existing database with Notion token configured:

```bash
# 1. Export your database
make db-export

# This creates: wordpress/db-dump-YYYY-MM-DD-HHMMSS.sql

# 2. Update .env to use shared volume
echo "DB_VOLUME_NAME=notionwp_shared_db" >> .env

# 3. Restart services (creates new shared volume)
make down
make up

# 4. Import your data
make db-import FILE=wordpress/db-dump-YYYY-MM-DD-HHMMSS.sql

# 5. Verify
make wp ARGS="option get notion_sync_api_token"
```

### Volume Management Commands

```bash
# List all Docker volumes
docker volume ls | grep notionwp

# Inspect shared database volume
docker volume inspect notionwp_shared_db

# Remove shared volume (WARNING: deletes all data)
docker volume rm notionwp_shared_db

# Remove isolated volume for specific branch
docker volume rm notionwp_main_db_data
```

### Switching Between Strategies

You can switch between shared and isolated volumes at any time:

```bash
# Switch from shared to isolated
# 1. Edit .env
DB_VOLUME_NAME=${COMPOSE_PROJECT_NAME}_db_data

# 2. Restart
make down && make up && make install

# Switch from isolated to shared
# 1. Edit .env
DB_VOLUME_NAME=notionwp_shared_db

# 2. Restart
make down && make up && make install
```

**Note:** Changing volume strategy creates a new, empty database. Export your data first if you need to preserve it.

## Makefile Commands

All commands should be run from the **worktree directory** (not the docker/ directory).

### Essential Commands

```bash
make up              # Start all services
make down            # Stop all services
make install         # Install WordPress and activate plugin
make logs            # View all service logs
make shell           # Open bash shell in WordPress container
make wp ARGS="..."   # Run WP-CLI commands
make status          # Show environment status and URLs
make clean           # Remove all volumes and data (destructive!)
```

### WordPress Management

```bash
# Activate/deactivate plugin
make activate
make deactivate
make plugin-status

# Database operations
make db-export                    # Export to SQL file
make db-import FILE=dump.sql      # Import from SQL file
make reset-wp                     # Delete and reinstall WordPress
```

### Development Workflow

```bash
# Start fresh environment
make up install

# Work on your code (changes reflect immediately)
# Plugin files are mounted from worktree root

# View logs while developing
make logs-wp

# Run WP-CLI commands
make wp ARGS="plugin list"
make wp ARGS="option get siteurl"
make wp ARGS="user create testuser test@example.com --role=editor"

# Restart after config changes
make restart

# Clean up when done
make down
```

### Example: Running Multiple Worktrees

```bash
# Terminal 1: Main branch
cd ~/Projects/notion-wp
make up install
# Site: http://main.localtest.me

# Terminal 2: Feature branch
cd ~/Projects/notion-wp-feature-blocks
make up install
# Site: http://feature-blocks.localtest.me

# Terminal 3: Bug fix branch
cd ~/Projects/notion-wp-fix-sync
make up install
# Site: http://fix-sync.localtest.me

# All three environments run simultaneously!
# Each has isolated: containers, networks, volumes, databases
```

## WP-CLI Usage

### Via Make (Recommended)

```bash
make wp ARGS="plugin list"
make wp ARGS="plugin activate notion-sync"
make wp ARGS="option update siteurl http://main.localtest.me"
make wp ARGS="post create --post_title='Test Post' --post_status=publish"
```

### Direct Docker Exec

```bash
docker compose -f docker/compose.yml exec -u www-data wpcli wp plugin list
```

## Accessing Services

### Your WordPress Site

- **URL**: `http://{WP_SITE_HOST}` (from .env)
- **Example**: `http://main.localtest.me`
- **Admin**: `http://main.localtest.me/wp-admin`
- **Credentials**: admin/admin (or from .env)

### Traefik Dashboard

- **URL**: `http://localhost:8080`
- **Shows**: All routed services and their configurations

### Database Access (External)

If you set `DB_PORT` in .env:

```bash
Host: 127.0.0.1
Port: {DB_PORT from .env}
User: wp
Password: wp
Database: {DB_NAME from .env}
```

**Example with TablePlus**:
- Host: `127.0.0.1`
- Port: `3307` (if DB_PORT=3307 in .env)
- User: `wp`
- Password: `wp`
- Database: `wp_main`

## Troubleshooting

### Services Won't Start

```bash
# Check if .env exists and has required variables
cat .env | grep COMPOSE_PROJECT_NAME
cat .env | grep WP_SITE_HOST

# Check for port conflicts
docker ps | grep 80

# View service logs
make logs
```

### Can't Access Site

```bash
# Verify Traefik is running
docker ps | grep traefik

# Check Traefik routing
open http://localhost:8080

# Verify hostname in browser matches .env
echo $WP_SITE_HOST

# Check WordPress container
make logs-wp
```

### WordPress Installation Fails

```bash
# Wait longer for DB to initialize
sleep 20 && make install

# Check database connectivity
make wp ARGS="db check"

# Reinstall
make reset-wp
```

### Plugin Not Visible

```bash
# Check plugin mount
docker compose -f docker/compose.yml exec wordpress ls -la /var/www/html/wp-content/plugins/notion-sync

# Should show files from your worktree directory
# If empty, check docker/compose.yml volume mount path
```

### Multiple Worktrees Conflict

```bash
# Ensure each .env has UNIQUE values for:
# - COMPOSE_PROJECT_NAME
# - WP_SITE_HOST
# - DB_NAME
# - DB_PORT (if used)

# Check running containers
docker ps --format "table {{.Names}}\t{{.Ports}}"

# Verify unique project names
grep COMPOSE_PROJECT_NAME */​.env
```

### Clean Slate

```bash
# Nuclear option: remove everything
make clean

# Restart fresh
make up install
```

## File Structure

```
notion-wp/                          # Main repository
├── docker/
│   ├── compose.yml                 # Docker Compose configuration
│   ├── config/
│   │   └── php.ini                 # PHP configuration overrides
│   ├── .gitignore                  # Ignore worktree-specific files
│   └── README.md                   # This file
├── .env.template                   # Environment template
├── .env                            # Worktree-specific environment (gitignored)
├── Makefile                        # Development commands
└── ...                             # Plugin source files

notion-wp-feature-blocks/           # Git worktree 1
├── .env                            # Unique configuration
└── ...                             # Shared plugin source

notion-wp-fix-sync/                 # Git worktree 2
├── .env                            # Unique configuration
└── ...                             # Shared plugin source
```

## Network Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                         Host Machine                         │
│                                                              │
│  Browser → http://main.localtest.me → Traefik :80          │
│         → http://feature.localtest.me → Traefik :80        │
│                                                              │
│  ┌────────────────────────────────────────────────────┐    │
│  │              Traefik Network (Shared)              │    │
│  │                                                     │    │
│  │  ┌──────────────┐                                  │    │
│  │  │   Traefik    │                                  │    │
│  │  │   Container  │                                  │    │
│  │  └──────┬───────┘                                  │    │
│  │         │                                          │    │
│  │    ┌────┴────┬─────────────┐                      │    │
│  │    │         │             │                      │    │
│  │  ┌─▼──┐   ┌─▼──┐       ┌─▼──┐                    │    │
│  │  │WP  │   │WP  │       │WP  │                    │    │
│  │  │main│   │feat│       │fix │                    │    │
│  │  └─┬──┘   └─┬──┘       └─┬──┘                    │    │
│  └────┼───────┼─────────────┼──────────────────────┘    │
│       │       │             │                            │
│  ┌────▼───┐ ┌▼─────┐  ┌────▼───┐                       │
│  │Internal│ │Internal│  │Internal│                       │
│  │Network │ │Network │  │Network │ (Isolated)            │
│  │  main  │ │  feat  │  │  fix   │                       │
│  │        │ │        │  │        │                       │
│  │ ┌────┐ │ │ ┌────┐ │  │ ┌────┐│                       │
│  │ │DB  │ │ │ │DB  │ │  │ │DB  ││                       │
│  │ │main│ │ │ │feat│ │  │ │fix ││                       │
│  │ └────┘ │ │ └────┘ │  │ └────┘│                       │
│  └────────┘ └────────┘  └────────┘                       │
│                                                           │
└───────────────────────────────────────────────────────────┘
```

## Advanced Usage

### Custom PHP Configuration

Edit `docker/config/php.ini` to modify PHP settings:

```ini
memory_limit = 512M
upload_max_filesize = 128M
max_execution_time = 600
```

Changes apply to all worktrees. Restart to apply:

```bash
make restart
```

### Using Different WordPress Versions

In your .env:

```bash
# WordPress 6.4 with PHP 8.2
WP_VERSION=php8.2-apache

# WordPress 6.5 with PHP 8.3
WP_VERSION=php8.3-apache

# Check available tags: https://hub.docker.com/_/wordpress
```

### Background Job Testing

Test WP-Cron and Action Scheduler:

```bash
# Trigger WP-Cron manually
make wp ARGS="cron event run --due-now"

# List scheduled events
make wp ARGS="cron event list"

# Test Action Scheduler (if plugin uses it)
make wp ARGS="action-scheduler run"
```

### Performance Testing with Large Databases

```bash
# Import large database dump
make db-import FILE=large-dump.sql

# Monitor performance
make logs-db | grep "Time:"

# Check query performance
make wp ARGS="db query 'SHOW FULL PROCESSLIST'"
```

## Best Practices

### Worktree Naming

Use descriptive, URL-friendly names:

```bash
# Good
notionwp_feature_blocks
notionwp_fix_auth_token
notionwp_refactor_sync

# Avoid
notionwp_123
notionwp_temp
notionwp_test
```

### Environment Management

```bash
# Keep .env.template updated in main repo
git add .env.template
git commit -m "Update environment template"

# Never commit .env files
git check-ignore .env  # Should show .env

# Document required variables in .env.template comments
```

### Resource Cleanup

```bash
# Stop worktree when not actively developing
make down

# Remove data when branch is merged/deleted
make clean

# Remove worktree
git worktree remove ../notion-wp-feature-blocks
```

### Plugin Development

```bash
# Changes to plugin files reflect immediately (mounted volume)
# No container rebuild needed for PHP changes

# After modifying plugin:
# 1. Refresh browser (for frontend changes)
# 2. Deactivate/reactivate plugin (for activation hooks)
# 3. Clear WP cache if needed
make wp ARGS="cache flush"
```

## Security Notes

### Development Only

This configuration is **not suitable for production**:

- Uses default credentials (admin/admin, wp/wp)
- Debug mode enabled
- No HTTPS enforcement
- Traefik dashboard exposed
- Database ports exposed

### Protecting Sensitive Data

```bash
# Never commit .env files
echo ".env" >> .gitignore

# Use secure passwords in production
# Store Notion API tokens in WordPress options, not environment
```

## Contributing

When sharing this environment:

1. **Commit**: `docker/compose.yml`, `.env.template`, `Makefile`
2. **Don't commit**: `.env`, database dumps, volumes
3. **Document**: Any custom modifications in this README
4. **Test**: Clean install with `cp .env.template .env && make up install`

## License

This Docker configuration is part of the Notion-WordPress sync plugin project.

## Support

For issues with this Docker environment:

1. Check [Troubleshooting](#troubleshooting) section
2. Review Docker logs: `make logs`
3. Verify .env configuration
4. Test with clean install: `make clean && make up install`
