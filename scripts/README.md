# Utility Scripts

Helper scripts for development and maintenance tasks.

## Available Scripts

### health-check.sh

Checks the health of the Docker environment and WordPress installation.

**Usage:**

```bash
./scripts/health-check.sh
```

**What it checks:**

- Docker daemon is running
- Required containers are up
- WordPress is accessible
- Database connectivity
- Plugin activation status

**Example output:**

```
✓ Docker is running
✓ WordPress container is healthy
✓ Database container is healthy
✓ WordPress site is accessible
✓ Notion Sync plugin is active
```

---

## Common Workflows

### Checking Environment Health

```bash
# Run health check
./scripts/health-check.sh

# If issues are found, check logs
make logs
```

### Development Workflow

```bash
# Start development environment
make up

# Check everything is working
./scripts/health-check.sh

# Make changes to code
# ...

# Run tests
cd plugin
composer test

# Stop environment when done
make down
```

## Troubleshooting

### Script not found

```bash
# Make sure you're in the main repository
cd /path/to/notion-wp

# Make scripts executable
chmod +x scripts/*.sh
```

### Permission denied

```bash
chmod +x scripts/health-check.sh
```

## See Also

- **Makefile**: Available commands for managing Docker environments
- **docker/README.md**: Detailed Docker configuration documentation
- **docs/development/BRANCHING-STRATEGY.md**: Git workflow guide
