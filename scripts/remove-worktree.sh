#!/bin/bash

# Script to safely remove a git worktree and its Docker environment
# Usage: ./scripts/remove-worktree.sh <worktree-name>
#
# Examples:
#   ./scripts/remove-worktree.sh feature-blocks
#   ./scripts/remove-worktree.sh fix-auth

set -e

# Colors for output
CYAN='\033[0;36m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Check arguments
if [ $# -lt 1 ]; then
    echo -e "${RED}Error: Missing worktree name${NC}"
    echo ""
    echo "Usage: $0 <worktree-name>"
    echo ""
    echo "Examples:"
    echo "  $0 feature-blocks"
    echo "  $0 fix-auth"
    echo ""
    echo "Available worktrees:"
    git worktree list
    echo ""
    exit 1
fi

WORKTREE_NAME=$1
WORKTREE_DIR="../notion-wp-${WORKTREE_NAME}"
PROJECT_NAME="notionwp_${WORKTREE_NAME//-/_}"

echo -e "${CYAN}Removing Git Worktree and Docker Environment${NC}"
echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo "Worktree Name:    ${WORKTREE_NAME}"
echo "Directory:        ${WORKTREE_DIR}"
echo "Project Name:     ${PROJECT_NAME}"
echo ""

# Check if worktree exists
if [ ! -d "$WORKTREE_DIR" ]; then
    echo -e "${RED}Error: Worktree directory not found: $WORKTREE_DIR${NC}"
    echo ""
    echo "Available worktrees:"
    git worktree list
    exit 1
fi

# Warning
echo -e "${RED}WARNING: This will:${NC}"
echo "  1. Stop all Docker containers for this worktree"
echo "  2. Delete all Docker volumes (database and WordPress data)"
echo "  3. Remove the worktree directory and all files"
echo ""
echo -e "${RED}This action cannot be undone!${NC}"
echo ""

# Confirm
read -p "$(echo -e ${YELLOW}Are you sure you want to proceed? [y/N]: ${NC})" -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo -e "${RED}Aborted${NC}"
    exit 1
fi

# Optional: offer to export database first
read -p "$(echo -e ${YELLOW}Do you want to export the database before removing? [y/N]: ${NC})" -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo -e "${CYAN}Exporting database...${NC}"
    cd "$WORKTREE_DIR"
    DUMP_FILE="dump-${PROJECT_NAME}-$(date +%Y%m%d-%H%M%S).sql"
    if make db-export; then
        # Move dump to parent directory for safekeeping
        mv dump-*.sql "../${DUMP_FILE}" 2>/dev/null || true
        echo -e "${GREEN}✓ Database exported to ../${DUMP_FILE}${NC}"
    else
        echo -e "${YELLOW}⚠ Database export failed (continuing with removal)${NC}"
    fi
    cd - > /dev/null
    echo ""
fi

# Step 1: Stop and remove Docker services
echo -e "${CYAN}Step 1: Stopping Docker services...${NC}"
cd "$WORKTREE_DIR"
if [ -f .env ]; then
    # Load environment to get project name
    source .env

    # Stop services and remove volumes
    make down 2>/dev/null || docker compose -f ../docker/compose.yml down 2>/dev/null || true

    # Remove volumes explicitly
    docker volume rm "${COMPOSE_PROJECT_NAME}_db_data" 2>/dev/null || true
    docker volume rm "${COMPOSE_PROJECT_NAME}_wp_data" 2>/dev/null || true

    echo -e "${GREEN}✓ Docker services stopped and volumes removed${NC}"
else
    echo -e "${YELLOW}⚠ No .env file found, skipping Docker cleanup${NC}"
fi
cd - > /dev/null
echo ""

# Step 2: Remove git worktree
echo -e "${CYAN}Step 2: Removing git worktree...${NC}"
git worktree remove "$WORKTREE_DIR" --force
echo -e "${GREEN}✓ Worktree removed${NC}"
echo ""

# Success
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}Worktree removed successfully!${NC}"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo "Remaining worktrees:"
git worktree list
echo ""
