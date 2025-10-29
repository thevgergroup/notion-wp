#!/bin/bash
# Build Admin JavaScript
# Combines modular JavaScript into a single file for WordPress admin

# Exit on error
set -e

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}Building admin JavaScript...${NC}"

# Source and output paths
SRC_DIR="src/js"
DIST_DIR="dist/js"

# Create dist directory if it doesn't exist
mkdir -p "$DIST_DIR"

# Output file
OUTPUT_FILE="$DIST_DIR/admin-navigation.js"

# Create the bundled file
cat > "$OUTPUT_FILE" << 'EOF'
/**
 * Admin Navigation JavaScript - Built Bundle
 *
 * Handles manual menu sync button functionality for Notion menu integration.
 *
 * @package NotionWP
 */

(function() {
	'use strict';

	/**
	 * Initialize menu sync functionality
	 */
	function initNavigationSync() {
		const syncButton = document.getElementById('notion-sync-menu-button');
		if (!syncButton) {
			return;
		}

		syncButton.addEventListener('click', handleMenuSync);
	}

	/**
	 * Handle manual menu sync
	 *
	 * @param {Event} event Click event
	 */
	async function handleMenuSync(event) {
		const button = event.target;
		const { nonce } = button.dataset;
		const messageContainer = document.getElementById(
			'notion-menu-sync-messages'
		);

		// Disable button and show loading state
		button.disabled = true;
		button.classList.add('updating-message');
		const originalText = button.textContent;
		button.textContent = 'Syncing...';

		// Clear previous messages
		messageContainer.innerHTML = '';

		try {
			const response = await fetch(
				window.ajaxurl || '/wp-admin/admin-ajax.php',
				{
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
					},
					body: new URLSearchParams({
						action: 'notion_sync_menu_now',
						nonce,
					}),
				}
			);

			const data = await response.json();

			if (data.success) {
				showMessage(messageContainer, 'success', data.data.message);

				// Update last sync time
				updateLastSyncTime();

				// Optionally reload page after short delay to show updated menu items
				setTimeout(() => {
					window.location.reload();
				}, 2000);
			} else {
				showMessage(
					messageContainer,
					'error',
					data.data?.message || 'Menu sync failed. Please try again.'
				);
			}
		} catch (error) {
			showMessage(messageContainer, 'error', `Error: ${error.message}`);
		} finally {
			// Restore button state
			button.disabled = false;
			button.classList.remove('updating-message');
			button.textContent = originalText;
		}
	}

	/**
	 * Show message in container
	 *
	 * @param {HTMLElement} container Message container element
	 * @param {string}      type      Message type (success, error, warning, info)
	 * @param {string}      message   Message text (can contain safe HTML from server)
	 */
	function showMessage(container, type, message) {
		const notice = document.createElement('div');
		notice.className = `notice notice-${type} inline`;
		// Message comes from wp_send_json_success which is already escaped on the server
		notice.innerHTML = `<p>${message}</p>`;
		container.appendChild(notice);
	}

	/**
	 * Update last sync time display
	 */
	function updateLastSyncTime() {
		const timeElement = document.querySelector('.notion-sync-last-synced time');
		if (timeElement) {
			const now = new Date();
			timeElement.setAttribute('datetime', now.toISOString());
			timeElement.textContent = 'Just now';
		}
	}

	// Initialize when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initNavigationSync);
	} else {
		initNavigationSync();
	}
})();
EOF

echo -e "${GREEN}✓ Built: $OUTPUT_FILE${NC}"
echo -e "${GREEN}✓ Admin navigation JavaScript build complete!${NC}"
