#!/bin/bash
#
# Setup Script for Notion-WP Git Worktree
#
# Usage: ./scripts/setup-worktree.sh <worktree-name> <http-port> <db-port>
# Example: ./scripts/setup-worktree.sh feature-sync 8081 3307
#

set -e  # Exit on error

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check arguments
if [ $# -lt 3 ]; then
    echo -e "${RED}Error: Missing required arguments${NC}"
    echo "Usage: $0 <worktree-name> <http-port> <db-port>"
    echo "Example: $0 feature-sync 8081 3307"
    exit 1
fi

WORKTREE_NAME=$1
HTTP_PORT=$2
DB_PORT=$3
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
MAIN_REPO_DIR="$(dirname "$SCRIPT_DIR")"
WORKTREE_DIR="$MAIN_REPO_DIR/../$WORKTREE_NAME"

echo -e "${GREEN}Setting up worktree: $WORKTREE_NAME${NC}"
echo "HTTP Port: $HTTP_PORT"
echo "DB Port: $DB_PORT"
echo ""

# Step 1: Create git worktree
echo -e "${YELLOW}[1/8] Creating git worktree...${NC}"
cd "$MAIN_REPO_DIR"

# Check if branch exists
if git show-ref --verify --quiet "refs/heads/$WORKTREE_NAME"; then
    echo "Branch $WORKTREE_NAME already exists, checking out..."
    git worktree add "$WORKTREE_DIR" "$WORKTREE_NAME"
else
    echo "Creating new branch $WORKTREE_NAME..."
    git worktree add -b "$WORKTREE_NAME" "$WORKTREE_DIR"
fi

# Step 2: Create .env file
echo -e "${YELLOW}[2/8] Creating .env file...${NC}"
cd "$WORKTREE_DIR"

# Generate sanitized project name (replace hyphens with underscores)
PROJECT_NAME=$(echo "$WORKTREE_NAME" | tr '-' '_')
DB_NAME="wp_${PROJECT_NAME}"
TABLE_PREFIX="wp${PROJECT_NAME:0:3}_"
SITE_HOST="${WORKTREE_NAME}.localtest.me"

cat > .env << EOF
# Docker Compose Configuration
COMPOSE_PROJECT_NAME=notionwp_${PROJECT_NAME}

# Port Configuration (must be unique per worktree)
HTTP_PORT=$HTTP_PORT
DB_PORT=$DB_PORT

# WordPress Configuration
WP_SITE_HOST=$SITE_HOST
WP_SITE_URL=http://$SITE_HOST:$HTTP_PORT
DB_NAME=$DB_NAME
DB_ROOT_PASSWORD=root
WP_TABLE_PREFIX=$TABLE_PREFIX

# WordPress Admin Credentials
WP_ADMIN_USER=admin
WP_ADMIN_PASSWORD=admin
WP_ADMIN_EMAIL=admin@example.com

# Plugin Configuration (set these after initial setup)
# NOTION_TOKEN=secret_xxx
# NOTION_WORKSPACE_ID=abc123
EOF

echo -e "${GREEN}Created .env file${NC}"

# Step 3: Install vendor dependencies (before Docker to avoid conflicts)
echo -e "${YELLOW}[3/9] Installing vendor dependencies...${NC}"

# Install Composer dependencies
if [ -f "composer.json" ]; then
    echo "Installing Composer dependencies..."
    if [ -f "composer.phar" ]; then
        php composer.phar install --no-interaction --prefer-dist
    elif command -v composer &> /dev/null; then
        composer install --no-interaction --prefer-dist
    else
        echo -e "${YELLOW}Warning: Composer not found. Run 'composer install' manually.${NC}"
    fi
else
    echo "No composer.json found, skipping Composer install"
fi

# Install NPM dependencies
if [ -f "package.json" ]; then
    echo "Installing NPM dependencies..."
    if command -v npm &> /dev/null; then
        npm install
    else
        echo -e "${YELLOW}Warning: npm not found. Run 'npm install' manually.${NC}"
    fi
else
    echo "No package.json found, skipping NPM install"
fi

echo -e "${GREEN}Vendor dependencies installed${NC}"

# Step 4: Configure Serena MCP (via Docker)
echo -e "${YELLOW}[4/10] Configuring Serena MCP...${NC}"
# Create .serena directory for cache persistence
mkdir -p .serena

# Copy .serenaignore from main repo if it exists
if [ -f "$MAIN_REPO_DIR/.serenaignore" ]; then
    cp "$MAIN_REPO_DIR/.serenaignore" .serenaignore
    echo "Copied .serenaignore from main repo"
fi

echo -e "${GREEN}Serena MCP will start with Docker Compose on port 9121${NC}"
echo "Note: Serena will be available at http://localhost:9121/mcp after Docker starts"

# Step 5: Create logs directory
echo -e "${YELLOW}[5/10] Creating logs directory...${NC}"
mkdir -p logs
touch logs/.gitkeep

# Step 6: Create plugin config directory
echo -e "${YELLOW}[6/10] Setting up plugin config...${NC}"
mkdir -p plugin/config

# Copy example configs if they exist
if [ -f "$MAIN_REPO_DIR/plugin/config/block-maps.example.json" ]; then
    cp "$MAIN_REPO_DIR/plugin/config/block-maps.example.json" plugin/config/block-maps.json
    echo "Created plugin/config/block-maps.json"
fi

if [ -f "$MAIN_REPO_DIR/plugin/config/field-maps.example.json" ]; then
    cp "$MAIN_REPO_DIR/plugin/config/field-maps.example.json" plugin/config/field-maps.json
    echo "Created plugin/config/field-maps.json"
fi

# Step 7: Start Docker environment
echo -e "${YELLOW}[7/10] Starting Docker containers (including Serena)...${NC}"
# Use --profile worktree to include Serena service
docker compose -f ../docker/compose.yml --profile worktree up -d

echo "Waiting for services to be ready..."
sleep 10

# Step 8: Install WordPress
echo -e "${YELLOW}[8/10] Installing WordPress...${NC}"

# Wait for MySQL to be ready
echo "Waiting for MySQL to be ready..."
until docker exec "notionwp_${PROJECT_NAME}_db" mysqladmin ping -h localhost --silent; do
    echo -n "."
    sleep 2
done
echo ""

# Install WordPress core
docker exec "notionwp_${PROJECT_NAME}_wp" wp core install \
    --url="$WP_SITE_URL" \
    --title="Notion Sync Dev - $WORKTREE_NAME" \
    --admin_user=admin \
    --admin_password=admin \
    --admin_email=admin@example.com \
    --skip-email \
    --allow-root

echo -e "${GREEN}WordPress installed successfully${NC}"

# Step 9: Build assets and activate plugin
echo -e "${YELLOW}[9/10] Building plugin assets...${NC}"

# Navigate back to worktree root
cd "$WORKTREE_DIR"

# Build assets if npm build script exists
if [ -f "package.json" ] && npm run --silent 2>&1 | grep -q "build"; then
    echo "Building assets..."
    npm run build || echo "Build script failed, continuing..."
fi

# Step 10: Activate plugin
echo -e "${YELLOW}[10/10] Activating plugin...${NC}"
docker exec "notionwp_${PROJECT_NAME}_wp" wp plugin activate notion-sync --allow-root || \
    echo "Plugin activation will be done manually (plugin files may not exist yet)"

# Display summary
echo ""
echo -e "${GREEN}=======================================${NC}"
echo -e "${GREEN}Worktree setup complete!${NC}"
echo -e "${GREEN}=======================================${NC}"
echo ""
echo "Worktree: $WORKTREE_NAME"
echo "Location: $WORKTREE_DIR"
echo ""
echo -e "${YELLOW}Access Information:${NC}"
echo "  WordPress URL: $WP_SITE_URL"
echo "  Admin URL: $WP_SITE_URL/wp-admin"
echo "  Username: admin"
echo "  Password: admin"
echo ""
echo -e "${YELLOW}Database:${NC}"
echo "  Host: localhost:$DB_PORT"
echo "  Database: $DB_NAME"
echo "  User: root"
echo "  Password: root"
echo ""
echo -e "${YELLOW}Docker Containers:${NC}"
echo "  WordPress: notionwp_${PROJECT_NAME}_wp"
echo "  Database: notionwp_${PROJECT_NAME}_db"
echo "  Serena MCP: notionwp_${PROJECT_NAME}_serena (port 9121)"
echo ""
echo -e "${YELLOW}Serena MCP Server:${NC}"
echo "  URL: http://localhost:9121/mcp"
echo "  Add to .mcp.json:"
echo '    "serena": { "url": "http://localhost:9121/mcp" }'
echo ""
echo -e "${YELLOW}Useful Commands:${NC}"
echo "  View logs: docker compose -f ../docker/compose.yml --profile worktree logs -f"
echo "  View Serena logs: docker compose -f ../docker/compose.yml --profile worktree logs -f serena"
echo "  Stop: docker compose -f ../docker/compose.yml --profile worktree down"
echo "  WP-CLI: docker exec notionwp_${PROJECT_NAME}_wp wp --allow-root <command>"
echo "  Shell: docker exec -it notionwp_${PROJECT_NAME}_wp bash"
echo ""
echo -e "${YELLOW}Next Steps:${NC}"
echo "  1. Navigate to worktree: cd $WORKTREE_DIR"
echo "  2. Edit .env to add Notion credentials (NOTION_TOKEN)"
echo "  3. Add Serena to .mcp.json (see above) and restart Claude Code"
echo "  4. Configure plugin at: $WP_SITE_URL/wp-admin/admin.php?page=notion-sync"
echo "  5. Start developing!"
echo ""
