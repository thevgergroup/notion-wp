/**
 * Page Sync Operations Module
 *
 * Handles individual page sync and bulk sync operations.
 *
 * @package NotionSync
 */

/**
 * Internal dependencies
 */
import { showAdminNotice } from './admin-ui.js';
import { updateStatusBadge, updateWpPostColumn, updateLastSyncedColumn, updateRowActions } from './table-ui.js';

/**
 * Handle individual page sync ("Sync Now" button)
 *
 * @param {HTMLElement} button - The sync button element
 */
export function handleSyncNow(button) {
	const { pageId } = button.dataset;

	if (!pageId) {
		showAdminNotice('error', 'Page ID is missing. Cannot sync page.');
		return;
	}

	// Get row element for status updates.
	const row = button.closest('tr');

	// Disable button and show loading state.
	button.disabled = true;
	const originalText = button.textContent;
	button.textContent = notionSyncAdmin.i18n.syncing;

	// Update status badge.
	const statusBadge = row.querySelector('.notion-sync-badge');
	if (statusBadge) {
		updateStatusBadge(statusBadge, 'syncing', notionSyncAdmin.i18n.syncing);
	}

	// Make AJAX request.
	fetch(notionSyncAdmin.ajaxUrl, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
		},
		body: new URLSearchParams({
			action: 'notion_sync_page',
			page_id: pageId,
			nonce: notionSyncAdmin.nonce,
		}),
	})
		.then((response) => response.json())
		.then((data) => {
			// Re-enable button.
			button.disabled = false;
			button.textContent = originalText;

			if (data.success) {
				// Update status badge to synced.
				if (statusBadge) {
					updateStatusBadge(
						statusBadge,
						'synced',
						notionSyncAdmin.i18n.synced
					);
				}

				// Update WordPress post column.
				updateWpPostColumn(row, data.data.post_id, data.data.edit_url);

				// Update last synced column.
				updateLastSyncedColumn(row, 'Just now');

				// Show success notice.
				showAdminNotice(
					'success',
					data.data.message || 'Page synced successfully!'
				);

				// Update row actions to include edit/view links.
				updateRowActions(
					button,
					data.data.edit_url,
					data.data.view_url
				);
			} else {
				// Update status badge to error.
				if (statusBadge) {
					updateStatusBadge(
						statusBadge,
						'error',
						notionSyncAdmin.i18n.syncError
					);
				}

				// Show error notice.
				showAdminNotice(
					'error',
					data.data.message ||
						'Failed to sync page. Please try again.'
				);
			}
		})
		.catch((error) => {
			// Re-enable button.
			button.disabled = false;
			button.textContent = originalText;

			// Update status badge to error.
			if (statusBadge) {
				updateStatusBadge(
					statusBadge,
					'error',
					notionSyncAdmin.i18n.syncError
				);
			}

			// Show error notice.
			showAdminNotice('error', 'Network error. Please try again.');
			console.error('Sync error:', error);
		});
}

/**
 * Handle bulk actions (Sync Selected)
 *
 * @param {HTMLFormElement} form - The bulk actions form
 */
export function handleBulkActions(form) {
	form.addEventListener('submit', (event) => {
		// Get selected action.
		const actionSelect = form.querySelector('select[name="action"]');
		const actionSelect2 = form.querySelector('select[name="action2"]');
		const action = actionSelect?.value || actionSelect2?.value;

		// Only handle our bulk sync action.
		if (action !== 'bulk_sync') {
			return;
		}

		// Prevent default form submission.
		event.preventDefault();

		// Get selected page IDs.
		const checkboxes = form.querySelectorAll(
			'input[name="page_ids[]"]:checked'
		);
		const pageIds = Array.from(checkboxes).map((cb) => cb.value);

		if (pageIds.length === 0) {
			showAdminNotice('warning', notionSyncAdmin.i18n.selectPages);
			return;
		}

		// Confirm bulk action.
		if (!confirm(notionSyncAdmin.i18n.confirmBulkSync)) {
			return;
		}

		// Disable all checkboxes and form controls.
		const formControls = form.querySelectorAll('input, select, button');
		formControls.forEach((control) => (control.disabled = true));

		// Show loading notice.
		showAdminNotice('info', `Syncing ${pageIds.length} pages...`);

		// Build form body to properly send array as PHP expects.
		const formBody = new URLSearchParams();
		formBody.append('action', 'notion_bulk_sync');
		formBody.append('nonce', notionSyncAdmin.nonce);
		pageIds.forEach((pageId) => formBody.append('page_ids[]', pageId));

		fetch(notionSyncAdmin.ajaxUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
			},
			body: formBody,
		})
			.then((response) => response.json())
			.then((data) => {
				// Re-enable form controls.
				formControls.forEach((control) => (control.disabled = false));

				if (data.success) {
					// Show success message.
					showAdminNotice('success', data.data.message);

					// Reload page to show updated sync status.
					setTimeout(() => {
						window.location.reload();
					}, 1500);
				} else {
					showAdminNotice(
						'error',
						data.data.message ||
							'Bulk sync failed. Please try again.'
					);
				}
			})
			.catch((error) => {
				// Re-enable form controls.
				formControls.forEach((control) => (control.disabled = false));

				showAdminNotice('error', 'Network error during bulk sync.');
				console.error('Bulk sync error:', error);
			});
	});
}
