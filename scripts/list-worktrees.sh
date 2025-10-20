#!/bin/bash

# Script to list all worktrees with their Docker environment status
# Usage: ./scripts/list-worktrees.sh

# Colors for output
CYAN='\033[0;36m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${CYAN}Git Worktrees and Docker Environments${NC}"
echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

# Get list of worktrees
WORKTREES=$(git worktree list --porcelain | grep -E "^worktree " | cut -d' ' -f2)

if [ -z "$WORKTREES" ]; then
    echo -e "${YELLOW}No worktrees found${NC}"
    exit 0
fi

# Iterate through worktrees
for worktree in $WORKTREES; do
    WORKTREE_NAME=$(basename "$worktree")
    BRANCH=$(cd "$worktree" && git branch --show-current 2>/dev/null || echo "detached")

    echo -e "${CYAN}Worktree: ${WORKTREE_NAME}${NC}"
    echo "  Path:   $worktree"
    echo "  Branch: $BRANCH"

    # Check if .env exists
    if [ -f "$worktree/.env" ]; then
        # Load environment variables
        source "$worktree/.env"

        echo "  Project: $COMPOSE_PROJECT_NAME"
        echo "  Site:    http://$WP_SITE_HOST"

        # Check Docker status
        if docker ps --format "{{.Names}}" | grep -q "^${COMPOSE_PROJECT_NAME}_"; then
            RUNNING_CONTAINERS=$(docker ps --format "{{.Names}}" | grep "^${COMPOSE_PROJECT_NAME}_" | wc -l | tr -d ' ')
            echo -e "  Status:  ${GREEN}Running${NC} ($RUNNING_CONTAINERS containers)"

            # List running containers
            echo "  Containers:"
            docker ps --filter "name=^${COMPOSE_PROJECT_NAME}_" --format "    - {{.Names}} ({{.Status}})"
        else
            echo -e "  Status:  ${YELLOW}Stopped${NC}"
        fi

        # Check volumes
        VOLUMES=$(docker volume ls --format "{{.Name}}" | grep "^${COMPOSE_PROJECT_NAME}_" | wc -l | tr -d ' ')
        if [ "$VOLUMES" -gt 0 ]; then
            echo "  Volumes: $VOLUMES"
        fi
    else
        echo -e "  ${YELLOW}No .env file (Docker not configured)${NC}"
    fi

    echo ""
done

# Summary
echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
TOTAL_WORKTREES=$(echo "$WORKTREES" | wc -l | tr -d ' ')
RUNNING_PROJECTS=$(docker ps --format "{{.Names}}" | grep "^notionwp_" | sed 's/_.*$//' | sort -u | wc -l | tr -d ' ')

echo "Total worktrees: $TOTAL_WORKTREES"
echo "Running environments: $RUNNING_PROJECTS"
echo ""

# Traefik status
if docker ps --format "{{.Names}}" | grep -q "^notionwp_traefik$"; then
    echo -e "${GREEN}Traefik reverse proxy: Running${NC}"
    echo "  Dashboard: http://localhost:8080"
else
    echo -e "${YELLOW}Traefik reverse proxy: Stopped${NC}"
    echo "  (Start with: make up from any worktree)"
fi
echo ""
