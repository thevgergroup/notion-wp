# Quick Reference Guide

## Setup Commands

### Create New Worktree

```bash
./scripts/setup-worktree.sh <name> <http-port> <db-port>
# Example: ./scripts/setup-worktree.sh feature-sync 8081 3307
```

### Teardown Worktree

```bash
./scripts/teardown-worktree.sh <name> [--delete-branch]
# Example: ./scripts/teardown-worktree.sh feature-sync --delete-branch
```

## Make Commands

| Command                   | Description                      |
| ------------------------- | -------------------------------- |
| `make help`               | Show all available commands      |
| `make up`                 | Start Docker containers          |
| `make down`               | Stop Docker containers           |
| `make restart`            | Restart containers               |
| `make logs`               | View all container logs          |
| `make logs-wp`            | View WordPress logs only         |
| `make shell`              | Access WordPress container shell |
| `make shell-db`           | Access database shell            |
| `make wp ARGS="..."`      | Run WP-CLI command               |
| `make install-wp`         | Install WordPress                |
| `make activate-plugin`    | Activate notion-sync plugin      |
| `make composer-install`   | Install PHP dependencies         |
| `make npm-install`        | Install Node dependencies        |
| `make npm-build`          | Build production assets          |
| `make npm-watch`          | Watch and rebuild assets         |
| `make test`               | Run all tests                    |
| `make test-unit`          | Run unit tests only              |
| `make phpcs`              | Check code standards             |
| `make phpcbf`             | Auto-fix code standards          |
| `make db-export`          | Export database to file          |
| `make db-import FILE=...` | Import database from file        |
| `make clean`              | Remove build artifacts           |
| `make setup`              | Full setup (all steps)           |
| `make info`               | Show environment info            |

## Directory Structure

```
notion-wp/
├── docker/           # Shared Docker infrastructure
├── plugin/           # Plugin source code (shared)
│   ├── src/          # PSR-4 source code
│   ├── assets/       # Frontend assets
│   ├── templates/    # PHP templates
│   ├── tests/        # Test suite
│   └── config/       # Runtime config (gitignored per worktree)
├── scripts/          # Automation scripts
├── docs/             # Documentation
├── .env.template     # Environment template
├── Makefile          # Common commands
└── README.md         # Project overview

# Per Worktree
worktree-name/
├── .env              # Worktree-specific config (GITIGNORED)
├── plugin/           # Same as main repo (Git branch)
│   └── config/       # Worktree-specific configs
└── logs/             # Worktree logs (GITIGNORED)
```

## Important File Paths

| File                                                                          | Purpose                       |
| ----------------------------------------------------------------------------- | ----------------------------- |
| `/Users/patrick/Projects/thevgergroup/notion-wp/docker/compose.yml`           | Docker Compose configuration  |
| `/Users/patrick/Projects/thevgergroup/notion-wp/.env.template`                | Environment variable template |
| `/Users/patrick/Projects/thevgergroup/notion-wp/Makefile`                     | Common commands               |
| `/Users/patrick/Projects/thevgergroup/notion-wp/plugin/notion-sync.php`       | Main plugin file              |
| `/Users/patrick/Projects/thevgergroup/notion-wp/plugin/composer.json`         | PHP dependencies              |
| `/Users/patrick/Projects/thevgergroup/notion-wp/plugin/package.json`          | Node dependencies             |
| `/Users/patrick/Projects/thevgergroup/notion-wp/plugin/config/*.example.json` | Config templates              |

## Environment Variables

### Required (Must be unique per worktree)

```bash
COMPOSE_PROJECT_NAME=notionwp_main     # Unique project name
WP_SITE_HOST=main.localtest.me         # Unique hostname
DB_NAME=wp_main                        # Unique database name
```

### Optional

```bash
HTTP_PORT=8080                         # HTTP port (with Traefik, not needed)
DB_PORT=3306                           # Database external port
WP_TABLE_PREFIX=wp_                    # WordPress table prefix
WP_DEBUG=1                             # Enable WordPress debug mode
```

### Plugin Config

```bash
NOTION_TOKEN=secret_xxx                # Notion integration token
NOTION_WORKSPACE_ID=abc123             # Notion workspace ID
```

## URL Access

| Worktree       | URL                                |
| -------------- | ---------------------------------- |
| main           | http://main.localtest.me           |
| feature-sync   | http://feature-sync.localtest.me   |
| feature-blocks | http://feature-blocks.localtest.me |

**Admin**: Add `/wp-admin` to any URL (username/password: `admin`/`admin`)

**Traefik Dashboard**: http://localhost:8080

## WP-CLI Examples

```bash
# List plugins
make wp ARGS="plugin list"

# Get site URL
make wp ARGS="option get siteurl"

# Export database
make wp ARGS="db export"

# Search/replace URL
make wp ARGS="search-replace 'http://old.com' 'http://new.com'"

# Create test posts
make wp ARGS="post generate --count=10"

# Flush rewrite rules
make wp ARGS="rewrite flush"

# Update permalink structure
make wp ARGS="rewrite structure '/%postname%/'"
```

## Common Tasks

### Start Development Session

```bash
cd ~/Projects/thevgergroup/notion-wp/feature-x
make up
cd plugin
npm run watch
# Edit code, test at http://feature-x.localtest.me
```

### Run Tests Before Commit

```bash
cd plugin
vendor/bin/phpcs --standard=WordPress src/
vendor/bin/phpunit
```

### Debug WordPress Errors

```bash
make logs-wp
# or
make shell
tail -f /var/www/html/wp-content/debug.log
```

### Reset Environment

```bash
make down
make clean  # WARNING: Deletes all data
make setup  # Fresh installation
```

### Import Test Data

```bash
make wp ARGS="import test-data.xml --authors=create"
```

### Access Database via CLI

```bash
make shell-db
# Inside container:
mysql -u root -p  # Password: root
USE wp_main;
SHOW TABLES;
```

### Check Container Status

```bash
make ps
# or
docker ps | grep notionwp
```

## Git Workflow

### Create Feature Branch

```bash
./scripts/setup-worktree.sh feature-name 8081 3307
cd ../feature-name
# Make changes
git add .
git commit -m "Description"
git push origin feature-name
```

### Merge Feature

```bash
cd ~/Projects/thevgergroup/notion-wp/main
git merge feature-name
git push origin main
```

### Clean Up

```bash
./scripts/teardown-worktree.sh feature-name --delete-branch
```

## Troubleshooting

### Port Already in Use

```bash
# Check .env for unique ports
# Or use Traefik without port mapping
```

### Database Connection Failed

```bash
make restart
make logs-db
```

### Plugin Not Activating

```bash
make logs-wp
make wp ARGS="plugin list"
```

### Assets Not Updating

```bash
cd plugin
rm -rf node_modules/
npm install
npm run build
```

### Clear All Caches

```bash
make wp ARGS="cache flush"
make wp ARGS="transient delete --all"
make restart
```

### View All Docker Volumes

```bash
docker volume ls | grep notionwp
```

### Remove Orphaned Resources

```bash
docker system prune -a --volumes
# WARNING: Removes all unused Docker resources
```

## File Permissions

If you encounter permission issues:

```bash
# Fix WordPress file permissions
make shell
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html

# Fix plugin file permissions (from host)
sudo chown -R $USER:$USER plugin/
```

## Performance Tips

1. **Enable Object Caching**: Install Redis or Memcached
2. **Increase PHP Memory**: Edit `docker/wordpress/php.ini`
3. **Optimize Database**: Run `make wp ARGS="db optimize"`
4. **Use Production Builds**: `npm run build` instead of `npm run watch`

## Security Checklist

- [ ] Change default admin password
- [ ] Set strong Notion token
- [ ] Enable HTTPS in production
- [ ] Restrict file permissions
- [ ] Enable WordPress firewall
- [ ] Disable debug mode in production
- [ ] Use environment variables for secrets

## Documentation Links

- [Complete Project Structure](project-structure.md)
- [Architecture Summary](ARCHITECTURE-SUMMARY.md)
- [Worktree Diagrams](worktree-architecture-diagram.md)
- [Product Requirements](../product/prd.md)
- [Technical Requirements](../requirements/requirements.md)
- [CLAUDE.md](../../CLAUDE.md) - Claude Code instructions

## Support

For issues or questions:

1. Check logs: `make logs`
2. Review documentation in `docs/`
3. Check GitHub issues (add URL)
4. Contact team (add contact info)

## Useful External Links

- [WordPress VIP Docs](https://docs.wpvip.com/)
- [Notion API Reference](https://developers.notion.com/)
- [Action Scheduler](https://actionscheduler.org/)
- [Docker Compose Docs](https://docs.docker.com/compose/)
- [Traefik Docs](https://doc.traefik.io/traefik/)
