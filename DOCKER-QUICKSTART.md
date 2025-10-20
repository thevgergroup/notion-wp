# Docker Quick Start Guide

Get your WordPress development environment running in **under 2 minutes**.

## First Time Setup

```bash
# 1. Copy environment template
cp .env.template .env

# 2. Edit .env (or use defaults for main worktree)
# Required: Set COMPOSE_PROJECT_NAME and WP_SITE_HOST
nano .env

# 3. Start and install WordPress
make up install

# 4. Open your site
open http://main.localtest.me
```

**Default credentials**: admin / admin

That's it! Your WordPress site is running with the notion-sync plugin activated.

## Creating Additional Worktrees

```bash
# From main repository
git worktree add ../notion-wp-feature feature/my-feature

# Navigate to worktree
cd ../notion-wp-feature

# Copy environment and customize
cp ../notion-wp/.env.template .env

# Edit .env with UNIQUE values:
# COMPOSE_PROJECT_NAME=notionwp_feature
# WP_SITE_HOST=feature.localtest.me
nano .env

# Start isolated environment
make up install

# Open worktree site
open http://feature.localtest.me
```

Both environments run **simultaneously** without conflicts!

## Essential Commands

```bash
make up              # Start services
make down            # Stop services
make install         # Install WordPress
make logs            # View logs
make shell           # Get bash shell
make wp ARGS="..."   # Run WP-CLI commands
make clean           # Delete all data (destructive!)
make status          # Show environment info
```

## Common Tasks

### Run WP-CLI Commands

```bash
make wp ARGS="plugin list"
make wp ARGS="user list"
make wp ARGS="option get siteurl"
make wp ARGS="post create --post_title='Test' --post_status=publish"
```

### Database Operations

```bash
# Export database
make db-export

# Import database
make db-import FILE=dump.sql

# Reset WordPress
make reset-wp
```

### Troubleshooting

```bash
# Site not loading?
make status          # Check if services are running
make logs-wp         # View WordPress logs

# Start fresh
make clean           # Remove all data
make up install      # Reinstall
```

## Environment Variables (.env)

**Required (must be unique per worktree)**:

```bash
COMPOSE_PROJECT_NAME=notionwp_main    # Unique identifier
WP_SITE_HOST=main.localtest.me        # Unique hostname
DB_NAME=wp_main                        # Unique database name
```

**Optional**:

```bash
DB_PORT=3307                          # External DB access port
WP_VERSION=php8.3-apache              # WordPress version
WP_ADMIN_USER=admin                   # Admin username
WP_ADMIN_PASSWORD=admin               # Admin password
```

## URLs

- **Your Site**: http://{WP_SITE_HOST} (from .env)
- **Admin Panel**: http://{WP_SITE_HOST}/wp-admin
- **Traefik Dashboard**: http://localhost:8080

## File Structure

```
notion-wp/                    # Main repository
├── docker/
│   ├── compose.yml          # Docker services
│   ├── config/php.ini       # PHP settings
│   └── README.md            # Full documentation
├── .env.template            # Template (commit this)
├── .env                     # Your config (gitignored)
├── Makefile                 # Commands
└── ...                      # Plugin source

notion-wp-feature/           # Git worktree
├── .env                     # Unique config
└── ...                      # Shared source
```

## How Worktrees Work

Each worktree has:

- **Isolated containers**: Separate WordPress, database, WP-CLI
- **Isolated networks**: No communication between worktrees
- **Isolated volumes**: Separate database and WordPress data
- **Unique hostname**: Traefik routes by hostname (*.localtest.me)
- **Shared plugin code**: Mounted from worktree directory

## Next Steps

1. **Read full docs**: See `docker/README.md` for advanced usage
2. **Configure plugin**: Add your Notion API token in WordPress admin
3. **Start developing**: Edit plugin files, changes reflect immediately
4. **Run tests**: (When test suite is set up)

## Need Help?

```bash
# Show all available commands
make help

# Check environment status
make status

# View detailed logs
make logs

# Get shell access
make shell
```

## Common Mistakes

**Mistake**: Using same COMPOSE_PROJECT_NAME in multiple worktrees
**Fix**: Each worktree needs unique COMPOSE_PROJECT_NAME, WP_SITE_HOST, DB_NAME

**Mistake**: Running `docker compose up` directly
**Fix**: Use `make up` from worktree directory (handles correct compose file path)

**Mistake**: Can't access site
**Fix**: Ensure Traefik is running and WP_SITE_HOST matches browser URL

**Mistake**: Plugin not showing in WordPress
**Fix**: Check volume mount in `docker/compose.yml` points to correct plugin directory
