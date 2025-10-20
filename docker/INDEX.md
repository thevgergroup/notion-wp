# Docker Development Environment - Quick Reference

Complete Docker-based development environment for WordPress plugin with git worktree support.

## Quick Start (2 minutes)

```bash
# 1. Setup main worktree
cp .env.template .env
make up install

# 2. Open your site
open http://main.localtest.me
# Login: admin / admin

# 3. Create additional worktrees
./scripts/create-worktree.sh feature-blocks feature/block-mapping
```

## Essential Commands

```bash
make up                # Start services
make down              # Stop services
make install           # Install WordPress
make logs              # View logs
make shell             # Get bash shell
make wp ARGS="..."     # Run WP-CLI commands
make help              # Show all commands
```

## Helper Scripts

```bash
./scripts/create-worktree.sh <name> <branch>    # Create worktree
./scripts/remove-worktree.sh <name>             # Remove worktree
./scripts/list-worktrees.sh                     # List all worktrees
./scripts/health-check.sh                       # Validate environment
```

## Key URLs

- Site: http://main.localtest.me
- Admin: http://main.localtest.me/wp-admin
- Traefik: http://localhost:8080

## Documentation

- **DOCKER-QUICKSTART.md** - 2-minute quick start
- **docker/README.md** - Full documentation (comprehensive)
- **scripts/README.md** - Helper scripts guide
- **DOCKER-SETUP-COMPLETE.md** - Setup summary

## Architecture

Each worktree is completely isolated:
- Unique containers (prefixed by COMPOSE_PROJECT_NAME)
- Separate database with unique name
- Named volumes for data persistence
- Hostname-based routing via Traefik
- Plugin mounted from worktree directory

## Services

- **Traefik** (shared): Reverse proxy on port 80
- **WordPress**: php8.3-apache
- **MariaDB**: Database v11
- **WP-CLI**: Command-line tools

## Environment Variables

Required (must be unique per worktree):
- `COMPOSE_PROJECT_NAME` - Project identifier
- `WP_SITE_HOST` - Site hostname (*.localtest.me)
- `DB_NAME` - Database name

See `.env.template` for full list.

## Common Tasks

### Create Worktree
```bash
./scripts/create-worktree.sh feature-xyz feature/xyz
cd ../notion-wp-feature-xyz
```

### Remove Worktree
```bash
./scripts/remove-worktree.sh feature-xyz
```

### Export Database
```bash
make db-export
```

### Run WP-CLI
```bash
make wp ARGS="plugin list"
make wp ARGS="user list"
```

### Check Status
```bash
make status
./scripts/list-worktrees.sh
./scripts/health-check.sh
```

## Troubleshooting

```bash
# Validate environment
./scripts/health-check.sh

# View logs
make logs

# Start fresh
make clean
make up install
```

## File Locations

### Created Files
```
docker/
├── compose.yml              # Docker Compose config
├── config/php.ini           # PHP settings
├── README.md                # Full documentation
└── INDEX.md                 # This file

scripts/
├── create-worktree.sh       # Create worktree
├── remove-worktree.sh       # Remove worktree
├── list-worktrees.sh        # List worktrees
├── health-check.sh          # Health check
└── README.md                # Scripts docs

Root:
├── .env.template            # Environment template
├── .env.example             # Example config
├── Makefile                 # Commands
├── DOCKER-QUICKSTART.md     # Quick start
└── DOCKER-SETUP-COMPLETE.md # Setup summary
```

## Need Help?

1. Run `make help` for command reference
2. Read `docker/README.md` for full documentation
3. Check `DOCKER-QUICKSTART.md` for quick start
4. Run `./scripts/health-check.sh` to validate setup
