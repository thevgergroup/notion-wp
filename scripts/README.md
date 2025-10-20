# Worktree Management Scripts

Helper scripts for managing git worktrees with isolated Docker environments.

## Available Scripts

### create-worktree.sh

Creates a new git worktree with a fully configured Docker environment.

**Usage:**

```bash
./scripts/create-worktree.sh <worktree-name> <branch-name>
```

**Examples:**

```bash
# Create worktree for feature branch
./scripts/create-worktree.sh feature-blocks feature/block-mapping

# Create worktree for bug fix
./scripts/create-worktree.sh fix-auth bugfix/auth-token

# Create worktree for refactoring
./scripts/create-worktree.sh refactor-sync refactor/sync-engine
```

**What it does:**

1. Creates git worktree at `../notion-wp-<worktree-name>`
2. Generates `.env` file with unique configuration
3. Starts Docker services (WordPress, MariaDB, Traefik)
4. Installs WordPress and activates notion-sync plugin
5. Provides site URL and credentials

**Result:**

- Site URL: `http://<worktree-name>.localtest.me`
- Admin: `http://<worktree-name>.localtest.me/wp-admin`
- Credentials: admin / admin

---

### remove-worktree.sh

Safely removes a worktree and cleans up its Docker environment.

**Usage:**

```bash
./scripts/remove-worktree.sh <worktree-name>
```

**Examples:**

```bash
./scripts/remove-worktree.sh feature-blocks
./scripts/remove-worktree.sh fix-auth
```

**What it does:**

1. Offers to export database before removal
2. Stops all Docker containers
3. Removes Docker volumes (database and WordPress data)
4. Removes git worktree directory
5. Cleans up all associated resources

**Safety features:**

- Confirmation prompt before deletion
- Optional database export
- Lists remaining worktrees after removal

---

### list-worktrees.sh

Displays all worktrees with their Docker environment status.

**Usage:**

```bash
./scripts/list-worktrees.sh
```

**Output includes:**

- Worktree path and branch name
- Docker project name and site URL
- Running status (containers, volumes)
- Traefik reverse proxy status

**Example output:**

```
Git Worktrees and Docker Environments
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Worktree: notion-wp
  Path:   /Users/user/Projects/notion-wp
  Branch: main
  Project: notionwp_main
  Site:    http://main.localtest.me
  Status:  Running (3 containers)
  Containers:
    - notionwp_main_wp (Up 2 hours)
    - notionwp_main_db (Up 2 hours)
    - notionwp_main_wpcli (Up 2 hours)
  Volumes: 2

Worktree: notion-wp-feature-blocks
  Path:   /Users/user/Projects/notion-wp-feature-blocks
  Branch: feature/block-mapping
  Project: notionwp_feature_blocks
  Site:    http://feature-blocks.localtest.me
  Status:  Stopped
  Volumes: 2

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Total worktrees: 2
Running environments: 1

Traefik reverse proxy: Running
  Dashboard: http://localhost:8080
```

---

## Common Workflows

### Starting a New Feature

```bash
# Create and set up worktree
./scripts/create-worktree.sh feature-xyz feature/xyz

# Work in the new worktree
cd ../notion-wp-feature-xyz

# Make changes to plugin code
# Site auto-reloads at http://feature-xyz.localtest.me

# When done, clean up
cd ../notion-wp
./scripts/remove-worktree.sh feature-xyz
```

### Working on Multiple Features Simultaneously

```bash
# Create multiple worktrees
./scripts/create-worktree.sh feature-blocks feature/block-mapping
./scripts/create-worktree.sh feature-sync feature/sync-engine
./scripts/create-worktree.sh fix-auth bugfix/auth-token

# Check status of all worktrees
./scripts/list-worktrees.sh

# All three sites run concurrently:
# - http://feature-blocks.localtest.me
# - http://feature-sync.localtest.me
# - http://fix-auth.localtest.me
```

### Cleaning Up After Branch Merge

```bash
# Feature merged to main, remove worktree
./scripts/remove-worktree.sh feature-blocks

# Or remove without database export
./scripts/remove-worktree.sh feature-blocks
# Answer 'n' to database export prompt
```

## Manual Worktree Setup

If you prefer manual setup instead of using `create-worktree.sh`:

```bash
# 1. Create worktree
git worktree add ../notion-wp-feature feature/my-feature

# 2. Navigate to worktree
cd ../notion-wp-feature

# 3. Copy and edit environment
cp ../notion-wp/.env.template .env
nano .env
# Set: COMPOSE_PROJECT_NAME=notionwp_feature
# Set: WP_SITE_HOST=feature.localtest.me
# Set: DB_NAME=wp_feature

# 4. Start and install
make up install

# 5. Open site
open http://feature.localtest.me
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
chmod +x scripts/create-worktree.sh
chmod +x scripts/remove-worktree.sh
chmod +x scripts/list-worktrees.sh
```

### Worktree creation fails

```bash
# Check Docker is running
docker ps

# Check disk space
df -h

# View detailed error
./scripts/create-worktree.sh feature-test feature/test 2>&1 | tee error.log
```

### Removal fails

```bash
# Force stop containers
docker ps -a | grep notionwp_feature | awk '{print $1}' | xargs docker rm -f

# Force remove volumes
docker volume ls | grep notionwp_feature | awk '{print $2}' | xargs docker volume rm

# Remove worktree manually
git worktree remove ../notion-wp-feature --force
```

## Best Practices

1. **Use descriptive names**: `feature-blocks` not `fb`, `fix-auth` not `fix`
2. **Clean up merged branches**: Remove worktrees after merging to main
3. **Export important data**: Use database export option when removing worktrees
4. **Check status regularly**: Run `list-worktrees.sh` to see what's running
5. **Stop unused worktrees**: Save system resources with `make down`

## See Also

- **Makefile**: Available commands for managing Docker environments
- **docker/README.md**: Detailed Docker configuration documentation
- **DOCKER-QUICKSTART.md**: Quick start guide for Docker environment
