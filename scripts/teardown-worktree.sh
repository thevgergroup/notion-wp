#!/bin/bash
#
# Teardown Script for Notion-WP Git Worktree
#
# Usage: ./scripts/teardown-worktree.sh <worktree-name> [--delete-branch]
# Example: ./scripts/teardown-worktree.sh feature-sync
#

set -e

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Check arguments
if [ $# -lt 1 ]; then
    echo -e "${RED}Error: Missing worktree name${NC}"
    echo "Usage: $0 <worktree-name> [--delete-branch]"
    echo "Example: $0 feature-sync --delete-branch"
    exit 1
fi

WORKTREE_NAME=$1
DELETE_BRANCH=false

if [ "$2" == "--delete-branch" ]; then
    DELETE_BRANCH=true
fi

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
MAIN_REPO_DIR="$(dirname "$SCRIPT_DIR")"
WORKTREE_DIR="$MAIN_REPO_DIR/../$WORKTREE_NAME"
PROJECT_NAME=$(echo "$WORKTREE_NAME" | tr '-' '_')

echo -e "${YELLOW}Tearing down worktree: $WORKTREE_NAME${NC}"
echo ""

# Confirmation prompt
read -p "Are you sure you want to tear down $WORKTREE_NAME? (y/N) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Aborted."
    exit 0
fi

# Step 1: Stop and remove Docker containers
echo -e "${YELLOW}[1/4] Stopping Docker containers...${NC}"
cd "$WORKTREE_DIR" || {
    echo -e "${RED}Error: Worktree directory not found at $WORKTREE_DIR${NC}"
    exit 1
}

if [ -f ".env" ]; then
    source .env
    docker compose -f ../docker/compose.yml down -v
    echo -e "${GREEN}Docker containers and volumes removed${NC}"
else
    echo -e "${YELLOW}No .env file found, skipping Docker cleanup${NC}"
fi

# Step 2: Remove worktree
echo -e "${YELLOW}[2/4] Removing git worktree...${NC}"
cd "$MAIN_REPO_DIR"
git worktree remove "$WORKTREE_DIR" --force
echo -e "${GREEN}Worktree removed${NC}"

# Step 3: Optionally delete branch
if [ "$DELETE_BRANCH" = true ]; then
    echo -e "${YELLOW}[3/4] Deleting branch $WORKTREE_NAME...${NC}"
    git branch -D "$WORKTREE_NAME"
    echo -e "${GREEN}Branch deleted${NC}"
else
    echo -e "${YELLOW}[3/4] Keeping branch $WORKTREE_NAME (use --delete-branch to remove)${NC}"
fi

# Step 4: Cleanup any orphaned Docker resources
echo -e "${YELLOW}[4/4] Checking for orphaned Docker resources...${NC}"
ORPHANED_CONTAINERS=$(docker ps -a --filter "name=notionwp_${PROJECT_NAME}" -q)
if [ -n "$ORPHANED_CONTAINERS" ]; then
    echo "Removing orphaned containers..."
    docker rm -f $ORPHANED_CONTAINERS
fi

ORPHANED_VOLUMES=$(docker volume ls --filter "name=notionwp_${PROJECT_NAME}" -q)
if [ -n "$ORPHANED_VOLUMES" ]; then
    echo "Removing orphaned volumes..."
    docker volume rm $ORPHANED_VOLUMES
fi

echo ""
echo -e "${GREEN}=======================================${NC}"
echo -e "${GREEN}Worktree teardown complete!${NC}"
echo -e "${GREEN}=======================================${NC}"
echo ""
echo "Worktree $WORKTREE_NAME has been removed."
if [ "$DELETE_BRANCH" = true ]; then
    echo "Branch $WORKTREE_NAME has been deleted."
else
    echo "Branch $WORKTREE_NAME is still available."
    echo "To delete it later, run: git branch -D $WORKTREE_NAME"
fi
echo ""
