# Makefile for WordPress Plugin Development with Docker
# Supports git worktrees with isolated environments per worktree
#
# Prerequisites:
# 1. Copy .env.template to .env and customize for this worktree
# 2. Ensure Docker and Docker Compose are installed
#
# Common commands:
#   make up         - Start the development environment
#   make install    - Install WordPress and activate plugin
#   make down       - Stop the environment
#   make clean      - Remove all data (volumes)
#   make logs       - View container logs
#   make shell      - Get shell access to WordPress container
#   make wp ARGS="plugin list" - Run WP-CLI commands

# Configuration
COMPOSE_FILE := docker/compose.yml
COMPOSE := docker compose -f $(COMPOSE_FILE)
WP := $(COMPOSE) exec -u www-data wpcli wp
WP_SHELL := $(COMPOSE) exec wordpress bash
DB_SHELL := $(COMPOSE) exec db bash

# Load environment variables from .env file
ifneq (,$(wildcard ./.env))
    include .env
    export
endif

# Colors for output
CYAN := \033[0;36m
GREEN := \033[0;32m
YELLOW := \033[0;33m
RED := \033[0;31m
NC := \033[0m # No Color

.PHONY: help
help: ## Show this help message
	@echo "$(CYAN)WordPress Plugin Development - Docker Commands$(NC)"
	@echo ""
	@echo "$(GREEN)Available commands:$(NC)"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' Makefile | awk 'BEGIN {FS = ":.*?## "}; {printf "  $(CYAN)%-20s$(NC) %s\n", $$1, $$2}'
	@echo ""
	@echo "$(YELLOW)Examples:$(NC)"
	@echo "  make up install          # Start and install WordPress"
	@echo "  make wp ARGS='plugin list'        # List installed plugins"
	@echo "  make wp ARGS='option get siteurl' # Get site URL"
	@echo ""

.PHONY: check-env
check-env: ## Check if .env file exists
	@if [ ! -f .env ]; then \
		echo "$(RED)Error: .env file not found$(NC)"; \
		echo "$(YELLOW)Run: cp .env.template .env$(NC)"; \
		echo "$(YELLOW)Then edit .env to set COMPOSE_PROJECT_NAME and WP_SITE_HOST$(NC)"; \
		exit 1; \
	fi
	@if [ -z "$(COMPOSE_PROJECT_NAME)" ]; then \
		echo "$(RED)Error: COMPOSE_PROJECT_NAME not set in .env$(NC)"; \
		exit 1; \
	fi
	@if [ -z "$(WP_SITE_HOST)" ]; then \
		echo "$(RED)Error: WP_SITE_HOST not set in .env$(NC)"; \
		exit 1; \
	fi

.PHONY: up
up: check-env ## Start all services
	@echo "$(CYAN)Starting WordPress development environment...$(NC)"
	@echo "$(YELLOW)Project: $(COMPOSE_PROJECT_NAME)$(NC)"
	@echo "$(YELLOW)Site URL: http://$(WP_SITE_HOST)$(NC)"
	@$(COMPOSE) up -d
	@echo "$(GREEN)Services started successfully!$(NC)"
	@echo ""
	@echo "$(CYAN)Next steps:$(NC)"
	@echo "  1. Run '$(YELLOW)make install$(NC)' to install WordPress"
	@echo "  2. Visit http://$(WP_SITE_HOST)"
	@echo "  3. Login with admin/admin (or credentials from .env)"
	@echo ""

.PHONY: down
down: ## Stop all services
	@echo "$(CYAN)Stopping services...$(NC)"
	@$(COMPOSE) down
	@echo "$(GREEN)Services stopped$(NC)"

.PHONY: restart
restart: down up ## Restart all services

.PHONY: ps
ps: ## List running containers
	@$(COMPOSE) ps

.PHONY: logs
logs: ## View logs from all services (use CTRL+C to exit)
	@$(COMPOSE) logs -f

.PHONY: logs-wp
logs-wp: ## View WordPress logs only
	@$(COMPOSE) logs -f wordpress

.PHONY: logs-db
logs-db: ## View database logs only
	@$(COMPOSE) logs -f db

.PHONY: logs-php
logs-php: ## View PHP error logs (last 100 lines)
	@echo "$(CYAN)PHP Error Logs (last 100 lines):$(NC)"
	@$(COMPOSE) logs --tail=100 wordpress 2>&1 | grep -E "\[php:|error_log" || echo "$(YELLOW)No PHP errors found in recent logs$(NC)"

.PHONY: logs-errors
logs-errors: ## View all error logs (last 100 lines)
	@echo "$(CYAN)All Error Logs (last 100 lines):$(NC)"
	@$(COMPOSE) logs --tail=100 wordpress 2>&1 | grep -iE "error|warning|failed" || echo "$(YELLOW)No errors found in recent logs$(NC)"

.PHONY: logs-perf
logs-perf: ## View performance logs (last 100 lines)
	@echo "$(CYAN)Performance Logs (last 100 lines):$(NC)"
	@$(COMPOSE) logs --tail=100 wordpress 2>&1 | grep "\[PERF" || echo "$(YELLOW)No performance logs found in recent logs$(NC)"

.PHONY: logs-perf-summary
logs-perf-summary: ## View performance summary only
	@echo "$(CYAN)Performance Summary:$(NC)"
	@$(COMPOSE) logs --tail=2000 wordpress 2>&1 | grep -A 30 "PERF SUMMARY" || echo "$(YELLOW)No performance summaries found in recent logs$(NC)"

.PHONY: logs-sync
logs-sync: ## View sync-related logs (last 100 lines)
	@echo "$(CYAN)Sync Logs (last 100 lines):$(NC)"
	@$(COMPOSE) logs --tail=100 wordpress 2>&1 | grep -E "PageSyncScheduler|NotionSync|ImageConverter|ImageDownloader" || echo "$(YELLOW)No sync logs found in recent logs$(NC)"

.PHONY: logs-live
logs-live: ## Live tail all logs with color filtering
	@echo "$(CYAN)Live logs (CTRL+C to exit):$(NC)"
	@$(COMPOSE) logs -f wordpress 2>&1 | grep --line-buffered -E "\[PERF|\[php:|error_log|PageSyncScheduler|NotionSync" || true

.PHONY: shell
shell: ## Open bash shell in WordPress container
	@echo "$(CYAN)Opening shell in WordPress container...$(NC)"
	@$(WP_SHELL)

.PHONY: shell-db
shell-db: ## Open bash shell in database container
	@echo "$(CYAN)Opening shell in database container...$(NC)"
	@$(DB_SHELL)

.PHONY: wp
wp: ## Run WP-CLI command (use: make wp ARGS="plugin list")
	@if [ -z "$(ARGS)" ]; then \
		echo "$(RED)Error: ARGS not provided$(NC)"; \
		echo "$(YELLOW)Usage: make wp ARGS='command'$(NC)"; \
		echo "$(YELLOW)Example: make wp ARGS='plugin list'$(NC)"; \
		exit 1; \
	fi
	@$(WP) $(ARGS)

.PHONY: install
install: check-env ## Install WordPress and activate plugin
	@echo "$(CYAN)Installing WordPress...$(NC)"
	@echo "$(YELLOW)Waiting for services to be ready...$(NC)"
	@sleep 10

	@echo "$(CYAN)Running WordPress installation...$(NC)"
	@$(WP) core install \
		--url=http://$(WP_SITE_HOST) \
		--title="$(WP_TITLE)" \
		--admin_user=$(WP_ADMIN_USER) \
		--admin_password=$(WP_ADMIN_PASSWORD) \
		--admin_email=$(WP_ADMIN_EMAIL) \
		--skip-email || echo "$(YELLOW)WordPress may already be installed$(NC)"

	@echo "$(CYAN)Activating notion-sync plugin...$(NC)"
	@$(WP) plugin activate notion-sync || echo "$(YELLOW)Plugin activation skipped$(NC)"

	@echo "$(GREEN)WordPress installed successfully!$(NC)"
	@echo ""
	@echo "$(CYAN)Site Information:$(NC)"
	@echo "  URL:      http://$(WP_SITE_HOST)"
	@echo "  Username: $(WP_ADMIN_USER)"
	@echo "  Password: $(WP_ADMIN_PASSWORD)"
	@echo "  Email:    $(WP_ADMIN_EMAIL)"
	@echo ""

.PHONY: activate
activate: ## Activate the notion-sync plugin
	@echo "$(CYAN)Activating notion-sync plugin...$(NC)"
	@$(WP) plugin activate notion-sync
	@echo "$(GREEN)Plugin activated!$(NC)"

.PHONY: deactivate
deactivate: ## Deactivate the notion-sync plugin
	@echo "$(CYAN)Deactivating notion-sync plugin...$(NC)"
	@$(WP) plugin deactivate notion-sync
	@echo "$(GREEN)Plugin deactivated!$(NC)"

.PHONY: plugin-status
plugin-status: ## Show plugin status
	@$(WP) plugin list --name=notion-sync

.PHONY: db-export
db-export: ## Export database to SQL file
	@echo "$(CYAN)Exporting database...$(NC)"
	@$(WP) db export - > dump-$(COMPOSE_PROJECT_NAME)-$(shell date +%Y%m%d-%H%M%S).sql
	@echo "$(GREEN)Database exported!$(NC)"

.PHONY: db-import
db-import: ## Import database from SQL file (use: make db-import FILE=dump.sql)
	@if [ -z "$(FILE)" ]; then \
		echo "$(RED)Error: FILE not provided$(NC)"; \
		echo "$(YELLOW)Usage: make db-import FILE=dump.sql$(NC)"; \
		exit 1; \
	fi
	@echo "$(CYAN)Importing database from $(FILE)...$(NC)"
	@$(WP) db import $(FILE)
	@echo "$(GREEN)Database imported!$(NC)"

.PHONY: reset-wp
reset-wp: ## Reset WordPress (delete and reinstall)
	@echo "$(RED)WARNING: This will delete all WordPress data!$(NC)"
	@echo "$(YELLOW)Press CTRL+C to cancel, or wait 5 seconds to continue...$(NC)"
	@sleep 5
	@echo "$(CYAN)Resetting WordPress...$(NC)"
	@$(WP) db reset --yes
	@$(MAKE) install

.PHONY: clean
clean: ## Remove all volumes and data (WARNING: destructive)
	@echo "$(RED)WARNING: This will delete all data for $(COMPOSE_PROJECT_NAME)!$(NC)"
	@echo "$(YELLOW)Press CTRL+C to cancel, or wait 5 seconds to continue...$(NC)"
	@sleep 5
	@echo "$(CYAN)Stopping services...$(NC)"
	@$(COMPOSE) down -v
	@echo "$(CYAN)Removing volumes...$(NC)"
	@docker volume rm $(COMPOSE_PROJECT_NAME)_db_data $(COMPOSE_PROJECT_NAME)_wp_data 2>/dev/null || true
	@echo "$(GREEN)All data removed!$(NC)"

.PHONY: clean-all
clean-all: clean ## Remove all data and containers (alias for clean)

.PHONY: rebuild
rebuild: ## Rebuild and restart all containers
	@echo "$(CYAN)Rebuilding containers...$(NC)"
	@$(COMPOSE) up -d --build --force-recreate
	@echo "$(GREEN)Rebuild complete!$(NC)"

.PHONY: status
status: ## Show environment status and URLs
	@echo "$(CYAN)Environment Status$(NC)"
	@echo "$(YELLOW)==================$(NC)"
	@echo "Project Name:    $(COMPOSE_PROJECT_NAME)"
	@echo "Site URL:        http://$(WP_SITE_HOST)"
	@echo "Traefik Dashboard: http://localhost:8080"
	@echo ""
	@echo "$(CYAN)Running Services:$(NC)"
	@$(COMPOSE) ps
	@echo ""
	@echo "$(CYAN)WordPress Status:$(NC)"
	@$(WP) core version --extra || echo "$(RED)WordPress not installed$(NC)"

.PHONY: info
info: status ## Show environment information (alias for status)

# Default target
.DEFAULT_GOAL := help
