# Docker Development Environment - Setup Complete

Your WordPress plugin development environment with git worktree support is now fully configured!

## What's Been Created

### Core Configuration Files

```
notion-wp/
├── docker/
│   ├── compose.yml              # Docker Compose services configuration
│   ├── config/
│   │   └── php.ini              # PHP configuration overrides
│   ├── .gitignore               # Ignore worktree-specific files
│   └── README.md                # Comprehensive Docker documentation
│
├── scripts/
│   ├── create-worktree.sh       # Automated worktree creation
│   ├── remove-worktree.sh       # Safe worktree removal
│   ├── list-worktrees.sh        # View all worktrees and status
│   ├── health-check.sh          # Environment validation
│   └── README.md                # Scripts documentation
│
├── .env.template                # Environment template (commit this)
├── .env.example                 # Example configuration
├── .gitignore                   # Root gitignore
├── Makefile                     # Development commands
├── DOCKER-QUICKSTART.md         # Quick start guide
└── DOCKER-SETUP-COMPLETE.md     # This file
```

## Architecture Overview

### Isolation Strategy

Each git worktree gets completely isolated:

1. **Containers**: Unique names prefixed by `COMPOSE_PROJECT_NAME`
2. **Networks**: Separate internal network + shared Traefik network
3. **Volumes**: Named volumes for database and WordPress data
4. **Hostnames**: Traefik routes by subdomain (*.localtest.me)

### Services per Worktree

- **WordPress**: PHP 8.3 + Apache with plugin mounted from worktree
- **MariaDB**: Isolated database with unique name
- **WP-CLI**: Command-line WordPress management
- **Traefik** (shared): Reverse proxy for hostname routing

### Network Architecture

```
Host Machine
  ↓
Browser → http://main.localtest.me
       → http://feature.localtest.me
  ↓
Traefik (:80) → Routes to correct WordPress container
  ↓
WordPress Container (isolated per worktree)
  ↓
MariaDB Container (isolated per worktree)
```

## Quick Start

### 1. First Time Setup (Main Worktree)

```bash
# Copy environment template
cp .env.template .env

# Edit .env (use defaults or customize)
nano .env

# Start and install WordPress
make up install

# Open your site
open http://main.localtest.me
```

**Credentials**: admin / admin

### 2. Create Additional Worktrees

**Easy way (automated):**

```bash
./scripts/create-worktree.sh feature-blocks feature/block-mapping
```

**Manual way:**

```bash
# Create worktree
git worktree add ../notion-wp-feature feature/my-feature

# Navigate and configure
cd ../notion-wp-feature
cp ../notion-wp/.env.template .env
nano .env  # Set unique: COMPOSE_PROJECT_NAME, WP_SITE_HOST, DB_NAME

# Start and install
make up install
```

### 3. Run Multiple Worktrees Concurrently

```bash
# All these can run simultaneously:
# Terminal 1
cd ~/Projects/notion-wp
make up install
# → http://main.localtest.me

# Terminal 2
cd ~/Projects/notion-wp-feature-blocks
make up install
# → http://feature-blocks.localtest.me

# Terminal 3
cd ~/Projects/notion-wp-fix-auth
make up install
# → http://fix-auth.localtest.me
```

**No conflicts!** Each has isolated containers, databases, and volumes.

## Essential Commands

Run these from your **worktree directory**:

```bash
make help              # Show all commands
make up                # Start services
make down              # Stop services
make install           # Install WordPress + activate plugin
make logs              # View logs
make shell             # Bash shell in WordPress container
make wp ARGS="..."     # Run WP-CLI commands
make status            # Show environment info
make clean             # Delete all data (destructive!)
```

### WP-CLI Examples

```bash
make wp ARGS="plugin list"
make wp ARGS="user create editor editor@test.com --role=editor"
make wp ARGS="post create --post_title='Test' --post_status=publish"
make wp ARGS="option get siteurl"
make wp ARGS="cache flush"
```

## Helper Scripts

```bash
# Create new worktree with Docker environment
./scripts/create-worktree.sh feature-xyz feature/xyz

# List all worktrees and their status
./scripts/list-worktrees.sh

# Check environment health
./scripts/health-check.sh

# Remove worktree and clean up
./scripts/remove-worktree.sh feature-xyz
```

## Environment Configuration

### Required Variables (must be unique per worktree)

```bash
COMPOSE_PROJECT_NAME=notionwp_main    # Prefixes containers/volumes
WP_SITE_HOST=main.localtest.me        # Site hostname
DB_NAME=wp_main                        # Database name
```

### Optional Variables

```bash
DB_PORT=3307                          # External DB access
WP_VERSION=php8.3-apache              # WordPress version
WP_ADMIN_USER=admin                   # Admin username
WP_ADMIN_PASSWORD=admin               # Admin password
WP_DEBUG=1                            # Enable debug mode
```

### Why localtest.me?

- Automatically resolves to 127.0.0.1 (no /etc/hosts editing needed)
- Works for any subdomain: foo.localtest.me, bar.localtest.me
- No DNS configuration required

## URLs and Access

### Your WordPress Site

- **Site**: `http://{WP_SITE_HOST}`
- **Admin**: `http://{WP_SITE_HOST}/wp-admin`
- **Default credentials**: admin / admin

### Traefik Dashboard

- **URL**: http://localhost:8080
- **Shows**: All routed services and configurations

### Database Access (External)

If you set `DB_PORT` in .env:

```
Host:     127.0.0.1
Port:     {DB_PORT}
User:     wp
Password: wp
Database: {DB_NAME}
```

## Development Workflow

### Typical Day-to-Day Usage

```bash
# Start of day
cd ~/Projects/notion-wp-feature-blocks
make up

# Make changes to plugin code
# Changes reflect immediately (volume mounted)

# Test changes
open http://feature-blocks.localtest.me

# View logs while developing
make logs-wp

# Run WP-CLI commands as needed
make wp ARGS="plugin list"

# End of day (optional - can leave running)
make down
```

### Plugin Development

Plugin files are **mounted from your worktree directory**:

- Edit files locally
- Changes reflect immediately
- No container rebuild needed
- No file sync delays

### Testing Different Scenarios

```bash
# Test with fresh database
make clean
make up install

# Test with imported data
make db-import FILE=test-data.sql

# Test with debug enabled
# Edit .env: WP_DEBUG=1
make restart
```

## Troubleshooting

### Quick Diagnostics

```bash
# Run health check
./scripts/health-check.sh

# Check what's running
./scripts/list-worktrees.sh

# View environment status
make status

# View logs
make logs
```

### Common Issues

**Can't access site:**
```bash
# Check Traefik is running
docker ps | grep traefik

# Verify hostname matches .env
echo $WP_SITE_HOST

# Check container logs
make logs-wp
```

**WordPress not installed:**
```bash
make install
```

**Plugin not showing:**
```bash
# Check mount
docker compose -f docker/compose.yml exec wordpress \
  ls -la /var/www/html/wp-content/plugins/notion-sync
```

**Containers conflict:**
```bash
# Verify unique project names
grep COMPOSE_PROJECT_NAME */​.env

# Each worktree must have unique:
# - COMPOSE_PROJECT_NAME
# - WP_SITE_HOST
# - DB_NAME
```

**Start fresh:**
```bash
make clean
make up install
```

## Best Practices

### Worktree Management

1. **Use descriptive names**: `feature-blocks` not `temp`
2. **Clean up merged branches**: Remove worktrees after merging
3. **Export important data**: Backup before removing worktrees
4. **Stop unused worktrees**: Save resources with `make down`

### Environment Configuration

1. **Keep .env.template updated**: Document new variables
2. **Never commit .env**: Contains worktree-specific config
3. **Use consistent naming**: `notionwp_<worktree-name>`
4. **Use localtest.me**: Avoid /etc/hosts management

### Development

1. **Use health checks**: Run `./scripts/health-check.sh` regularly
2. **Monitor logs**: Keep `make logs-wp` open while developing
3. **Use WP-CLI**: Faster than clicking through admin
4. **Commit often**: Worktrees make branch switching easy

## File Locations

### Plugin Source

Mounted from worktree root:
```
/var/www/html/wp-content/plugins/notion-sync
```

### WordPress Core

Inside container volume (persisted):
```
/var/www/html
```

### Database Data

Inside container volume (persisted):
```
/var/lib/mysql
```

### Logs

```bash
# WordPress debug log
make shell
cat /var/www/html/wp-content/debug.log

# PHP error log
make shell
cat /var/log/apache2/error.log

# Or via Docker
make logs-wp
```

## Performance Tips

### Optimize Docker

1. **Use volumes for node_modules**: Keep outside mounted directory
2. **Stop unused worktrees**: Free up resources
3. **Clean old volumes**: `docker volume prune`
4. **Increase Docker resources**: Docker Desktop → Settings → Resources

### WordPress Optimization

1. **Disable unused plugins**: `make wp ARGS="plugin deactivate <name>"`
2. **Use object cache**: Install Redis (add to compose.yml if needed)
3. **Limit post revisions**: Set WP_POST_REVISIONS in wp-config.php

## Advanced Usage

### Custom PHP Settings

Edit `docker/config/php.ini`:

```ini
memory_limit = 512M
upload_max_filesize = 128M
max_execution_time = 600
```

Restart to apply:
```bash
make restart
```

### Different WordPress Versions

In .env:
```bash
WP_VERSION=php8.2-apache  # WordPress with PHP 8.2
WP_VERSION=php8.3-apache  # WordPress with PHP 8.3
```

### Multiple Traefik Instances

By default, one shared Traefik instance serves all worktrees. To use separate Traefik instances, modify `docker/compose.yml` and make the Traefik container name unique per worktree.

## Security Notes

⚠️ **This is a development environment** - NOT for production!

- Default credentials (admin/admin)
- Debug mode enabled
- No HTTPS enforcement
- Exposed database ports
- Traefik dashboard accessible

## Next Steps

1. ✅ **Environment is ready** - Start developing!
2. 📖 **Read full docs**: See `docker/README.md` for advanced features
3. 🔧 **Configure plugin**: Add Notion API token in WordPress admin
4. 🧪 **Set up testing**: (When test suite is implemented)
5. 🚀 **Create CI/CD**: (Next phase of DevOps setup)

## Getting Help

### Documentation

- **Docker README**: `docker/README.md` - Full Docker documentation
- **Quick Start**: `DOCKER-QUICKSTART.md` - 2-minute setup guide
- **Scripts README**: `scripts/README.md` - Helper scripts documentation

### Commands

```bash
make help                    # Show Makefile commands
./scripts/health-check.sh    # Validate environment
./scripts/list-worktrees.sh  # Show all worktrees
```

### Common Questions

**Q: Can I run multiple worktrees at once?**
A: Yes! Each is completely isolated. Just ensure unique COMPOSE_PROJECT_NAME, WP_SITE_HOST, and DB_NAME.

**Q: Do I need to edit /etc/hosts?**
A: No. Using *.localtest.me domains provides automatic DNS resolution to 127.0.0.1.

**Q: What happens to my data when I run `make down`?**
A: Data persists in Docker volumes. Use `make clean` to delete volumes.

**Q: How do I access the database?**
A: Set `DB_PORT` in .env, then connect via TablePlus/Sequel Pro to 127.0.0.1:{DB_PORT}.

**Q: Can I use a different domain instead of localtest.me?**
A: Yes, but you'll need to add entries to /etc/hosts or use a local DNS server.

## Contributing

When contributing to this Docker setup:

1. **Test thoroughly**: Use `make clean && make up install` for fresh installs
2. **Update documentation**: Keep READMEs in sync with changes
3. **Validate**: Run `./scripts/health-check.sh` before committing
4. **Document variables**: Add new .env variables to .env.template with comments

## License

This Docker configuration is part of the Notion-WordPress sync plugin project.

---

**Happy coding!** 🚀

Your worktree-based WordPress development environment is ready. Create as many isolated environments as you need and work on multiple features simultaneously without any conflicts.
