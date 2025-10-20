/**
 * Admin JavaScript - Main Coordinator
 *
 * Coordinates all admin functionality modules for Notion Sync Settings Page.
 * Delegates to specialized modules for:
 * - Connection management (admin-connection.js)
 * - Sync operations (admin-sync.js)
 * - UI utilities (admin-ui.js)
 *
 * @package NotionSync
 */

import { initConnectionForm } from './modules/admin-connection.js';
import { initSyncFunctionality } from './modules/admin-sync.js';
import {
	enhanceKeyboardNavigation,
	initAdminNotices,
	initCopyButtons,
} from './modules/admin-ui.js';

/**
 * Initialize all admin functionality when DOM is ready
 */
function init() {
	// Initialize connection form handling.
	initConnectionForm();

	// Initialize sync functionality.
	initSyncFunctionality();

	// Enhance keyboard navigation for accessibility.
	enhanceKeyboardNavigation();

	// Initialize admin notices.
	initAdminNotices();

	// Initialize copy buttons.
	initCopyButtons();
}

// Initialize when DOM is ready.
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', init);
} else {
	init();
}
