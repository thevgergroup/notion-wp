/**
 * Sync Operations Module
 *
 * Main orchestrator for sync functionality.
 * Imports and coordinates functionality from specialized modules.
 *
 * @package
 */

/**
 * Internal dependencies
 */
import { handleSyncNow, handleBulkActions } from './page-sync.js';
import { handleDatabaseSync, handleCancelBatch } from './database-sync.js';
import { handleCopyNotionId, handleUpdateLinks } from './link-utils.js';

/**
 * Re-export table UI functions for backward compatibility
 */
export {
	updateStatusBadge,
	updateWpPostColumn,
	updateLastSyncedColumn,
} from './table-ui.js';

/**
 * Initialize sync functionality for pages list table
 */
export function initSyncFunctionality() {
	// Handle "Sync Now" button clicks (individual page sync).
	document.addEventListener('click', (event) => {
		if (event.target.classList.contains('notion-sync-now')) {
			event.preventDefault();
			handleSyncNow(event.target);
		}
	});

	// Handle database sync button clicks.
	document.addEventListener('click', (event) => {
		if (event.target.classList.contains('sync-database')) {
			event.preventDefault();
			handleDatabaseSync(event.target);
		}
	});

	// Handle batch cancel button clicks.
	document.addEventListener('click', (event) => {
		if (event.target.classList.contains('cancel-batch')) {
			event.preventDefault();
			handleCancelBatch(event.target);
		}
	});

	// Handle bulk sync form submission.
	const bulkForm = document.getElementById('notion-pages-form');
	if (bulkForm) {
		// Disable WordPress's built-in bulk action validation for our custom form.
		// WordPress validates on submit, but we're using AJAX, so we need to bypass it.
		const originalOnSubmit = bulkForm.onsubmit;
		bulkForm.onsubmit = function (event) {
			const actionSelect = bulkForm.querySelector(
				'select[name="action"]'
			);
			const actionSelect2 = bulkForm.querySelector(
				'select[name="action2"]'
			);
			const action = actionSelect?.value || actionSelect2?.value;

			// If it's our bulk_sync action, bypass WordPress validation.
			if (action === 'bulk_sync') {
				// Our event listener will handle it.
				return true;
			}

			// For other actions, use WordPress's original validation.
			if (originalOnSubmit) {
				return originalOnSubmit.call(this, event);
			}
			return true;
		};

		handleBulkActions(bulkForm);
	}

	// Handle copy Notion ID buttons.
	document.addEventListener('click', (event) => {
		if (event.target.closest('.notion-copy-id')) {
			event.preventDefault();
			handleCopyNotionId(event.target.closest('.notion-copy-id'));
		}
	});

	// Handle update links button.
	const updateLinksBtn = document.getElementById('update-links-btn');
	if (updateLinksBtn) {
		updateLinksBtn.addEventListener('click', handleUpdateLinks);
	}
}
