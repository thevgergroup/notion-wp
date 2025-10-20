#!/bin/bash

# Health check script for Docker development environment
# Validates configuration and checks service status
# Usage: ./scripts/health-check.sh

# Colors for output
CYAN='\033[0;36m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Counters
ERRORS=0
WARNINGS=0
CHECKS=0

# Helper functions
check_pass() {
    echo -e "  ${GREEN}✓${NC} $1"
    ((CHECKS++))
}

check_fail() {
    echo -e "  ${RED}✗${NC} $1"
    ((ERRORS++))
    ((CHECKS++))
}

check_warn() {
    echo -e "  ${YELLOW}!${NC} $1"
    ((WARNINGS++))
    ((CHECKS++))
}

echo -e "${CYAN}Docker Development Environment - Health Check${NC}"
echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

# Check 1: Docker availability
echo -e "${CYAN}System Requirements${NC}"
if command -v docker &> /dev/null; then
    DOCKER_VERSION=$(docker --version | cut -d' ' -f3 | tr -d ',')
    check_pass "Docker installed (version $DOCKER_VERSION)"
else
    check_fail "Docker not found - install Docker Desktop"
fi

if command -v docker compose &> /dev/null; then
    check_pass "Docker Compose available"
else
    check_fail "Docker Compose not found"
fi

if docker info &> /dev/null; then
    check_pass "Docker daemon running"
else
    check_fail "Docker daemon not running - start Docker Desktop"
fi

if command -v make &> /dev/null; then
    check_pass "Make installed"
else
    check_warn "Make not found - install with: xcode-select --install"
fi

if command -v git &> /dev/null; then
    GIT_VERSION=$(git --version | cut -d' ' -f3)
    check_pass "Git installed (version $GIT_VERSION)"
else
    check_fail "Git not found"
fi
echo ""

# Check 2: File structure
echo -e "${CYAN}Project Files${NC}"
if [ -f "docker/compose.yml" ]; then
    check_pass "docker/compose.yml exists"
else
    check_fail "docker/compose.yml not found"
fi

if [ -f "docker/config/php.ini" ]; then
    check_pass "docker/config/php.ini exists"
else
    check_warn "docker/config/php.ini not found"
fi

if [ -f ".env.template" ]; then
    check_pass ".env.template exists"
else
    check_warn ".env.template not found"
fi

if [ -f "Makefile" ]; then
    check_pass "Makefile exists"
else
    check_fail "Makefile not found"
fi

if [ -f ".env" ]; then
    check_pass ".env file exists"
else
    check_warn ".env file not found - copy from .env.template"
fi
echo ""

# Check 3: Environment configuration
if [ -f ".env" ]; then
    echo -e "${CYAN}Environment Configuration${NC}"
    source .env

    if [ -n "$COMPOSE_PROJECT_NAME" ]; then
        check_pass "COMPOSE_PROJECT_NAME set to: $COMPOSE_PROJECT_NAME"
    else
        check_fail "COMPOSE_PROJECT_NAME not set in .env"
    fi

    if [ -n "$WP_SITE_HOST" ]; then
        check_pass "WP_SITE_HOST set to: $WP_SITE_HOST"

        # Validate localtest.me domain
        if [[ "$WP_SITE_HOST" == *.localtest.me ]]; then
            check_pass "Using localtest.me domain (automatic DNS)"
        else
            check_warn "Not using localtest.me - may need /etc/hosts entry"
        fi
    else
        check_fail "WP_SITE_HOST not set in .env"
    fi

    if [ -n "$DB_NAME" ]; then
        check_pass "DB_NAME set to: $DB_NAME"
    else
        check_fail "DB_NAME not set in .env"
    fi

    if [ -n "$WP_ADMIN_USER" ]; then
        check_pass "WordPress admin user: $WP_ADMIN_USER"
    else
        check_warn "WP_ADMIN_USER not set (will use default)"
    fi
    echo ""
fi

# Check 4: Docker services
echo -e "${CYAN}Docker Services${NC}"

# Check Traefik
if docker ps --format "{{.Names}}" | grep -q "^notionwp_traefik$"; then
    TRAEFIK_STATUS=$(docker inspect notionwp_traefik --format='{{.State.Status}}')
    if [ "$TRAEFIK_STATUS" == "running" ]; then
        check_pass "Traefik reverse proxy running"
    else
        check_warn "Traefik exists but not running (status: $TRAEFIK_STATUS)"
    fi
else
    check_warn "Traefik not running (will start with 'make up')"
fi

# Check worktree services
if [ -n "$COMPOSE_PROJECT_NAME" ]; then
    if docker ps --format "{{.Names}}" | grep -q "^${COMPOSE_PROJECT_NAME}_"; then
        check_pass "Worktree containers running"

        # Check individual services
        if docker ps --format "{{.Names}}" | grep -q "^${COMPOSE_PROJECT_NAME}_db$"; then
            DB_HEALTH=$(docker inspect ${COMPOSE_PROJECT_NAME}_db --format='{{.State.Health.Status}}' 2>/dev/null || echo "unknown")
            if [ "$DB_HEALTH" == "healthy" ]; then
                check_pass "Database container healthy"
            else
                check_warn "Database container status: $DB_HEALTH"
            fi
        else
            check_warn "Database container not running"
        fi

        if docker ps --format "{{.Names}}" | grep -q "^${COMPOSE_PROJECT_NAME}_wp$"; then
            check_pass "WordPress container running"
        else
            check_warn "WordPress container not running"
        fi

        if docker ps --format "{{.Names}}" | grep -q "^${COMPOSE_PROJECT_NAME}_wpcli$"; then
            check_pass "WP-CLI container running"
        else
            check_warn "WP-CLI container not running"
        fi
    else
        check_warn "No containers running for this worktree (run 'make up')"
    fi
fi
echo ""

# Check 5: Docker volumes
echo -e "${CYAN}Docker Volumes${NC}"
if [ -n "$COMPOSE_PROJECT_NAME" ]; then
    if docker volume ls --format "{{.Name}}" | grep -q "^${COMPOSE_PROJECT_NAME}_db_data$"; then
        check_pass "Database volume exists"
    else
        check_warn "Database volume not found (will be created on 'make up')"
    fi

    if docker volume ls --format "{{.Name}}" | grep -q "^${COMPOSE_PROJECT_NAME}_wp_data$"; then
        check_pass "WordPress volume exists"
    else
        check_warn "WordPress volume not found (will be created on 'make up')"
    fi
fi
echo ""

# Check 6: Network connectivity
echo -e "${CYAN}Network Connectivity${NC}"
if docker network ls --format "{{.Name}}" | grep -q "^traefik_network$"; then
    check_pass "Traefik network exists"
else
    check_warn "Traefik network not found (will be created on 'make up')"
fi

if [ -n "$COMPOSE_PROJECT_NAME" ]; then
    if docker network ls --format "{{.Name}}" | grep -q "^${COMPOSE_PROJECT_NAME}_internal$"; then
        check_pass "Internal network exists"
    else
        check_warn "Internal network not found (will be created on 'make up')"
    fi
fi
echo ""

# Check 7: WordPress installation
if [ -n "$COMPOSE_PROJECT_NAME" ]; then
    echo -e "${CYAN}WordPress Status${NC}"
    if docker ps --format "{{.Names}}" | grep -q "^${COMPOSE_PROJECT_NAME}_wpcli$"; then
        # Try to check WordPress core
        if docker compose -f docker/compose.yml exec -u www-data wpcli wp core is-installed 2>/dev/null; then
            check_pass "WordPress installed"

            # Check plugin status
            if docker compose -f docker/compose.yml exec -u www-data wpcli wp plugin is-active notion-sync 2>/dev/null; then
                check_pass "notion-sync plugin active"
            else
                check_warn "notion-sync plugin not active (run 'make activate')"
            fi

            # Get site URL
            SITE_URL=$(docker compose -f docker/compose.yml exec -u www-data wpcli wp option get siteurl 2>/dev/null | tr -d '\r')
            if [ -n "$SITE_URL" ]; then
                check_pass "Site URL configured: $SITE_URL"

                # Verify site URL matches .env
                if [ -n "$WP_SITE_HOST" ] && [[ "$SITE_URL" == "http://$WP_SITE_HOST" ]]; then
                    check_pass "Site URL matches WP_SITE_HOST"
                else
                    check_warn "Site URL mismatch (expected: http://$WP_SITE_HOST)"
                fi
            fi
        else
            check_warn "WordPress not installed (run 'make install')"
        fi
    fi
    echo ""
fi

# Summary
echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}Health Check Summary${NC}"
echo ""
echo "Total checks: $CHECKS"
echo -e "${GREEN}Passed: $((CHECKS - ERRORS - WARNINGS))${NC}"
echo -e "${YELLOW}Warnings: $WARNINGS${NC}"
echo -e "${RED}Errors: $ERRORS${NC}"
echo ""

if [ $ERRORS -gt 0 ]; then
    echo -e "${RED}Issues found - please address errors before proceeding${NC}"
    echo ""
    exit 1
elif [ $WARNINGS -gt 0 ]; then
    echo -e "${YELLOW}Environment functional with minor warnings${NC}"
    echo ""
    exit 0
else
    echo -e "${GREEN}Environment healthy - ready for development!${NC}"
    echo ""
    if [ -n "$WP_SITE_HOST" ]; then
        echo -e "${CYAN}Quick Links:${NC}"
        echo "  Site:    http://$WP_SITE_HOST"
        echo "  Admin:   http://$WP_SITE_HOST/wp-admin"
        echo "  Traefik: http://localhost:8080"
        echo ""
    fi
    exit 0
fi
