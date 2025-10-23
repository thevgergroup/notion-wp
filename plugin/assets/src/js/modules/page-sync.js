/**
 * Page Sync Operations Module
 *
 * Handles individual page sync and bulk sync operations.
 *
 * @package
 */

/**
 * Internal dependencies
 */
import { showAdminNotice } from './admin-ui.js';
import {
	updateWpPostColumn,
	updateLastSyncedColumn,
	updateRowActions,
} from './table-ui.js';
import { updateStatusBadge, getStatusBadgeForPage } from './status-badge.js';
import { watchBatchStatus } from './sync-status-poller.js';

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

	// Update status badge to syncing state.
	const statusBadge = getStatusBadgeForPage(pageId);
	if (statusBadge) {
		updateStatusBadge(statusBadge, { status: 'syncing' });
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
					updateStatusBadge(statusBadge, { status: 'synced' });
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
				// Update status badge to failed.
				if (statusBadge) {
					updateStatusBadge(statusBadge, {
						status: 'failed',
						error: data.data.message || 'Sync failed',
					});
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

			// Update status badge to failed.
			if (statusBadge) {
				updateStatusBadge(statusBadge, {
					status: 'failed',
					error: 'Network error',
				});
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
	// Create progress dashboard (will be inserted before the table).
	let dashboardContainer = document.getElementById(
		'bulk-sync-progress-container'
	);
	if (!dashboardContainer) {
		dashboardContainer = document.createElement('div');
		dashboardContainer.id = 'bulk-sync-progress-container';
		dashboardContainer.style.display = 'none';

		// Insert before the form.
		form.parentNode.insertBefore(dashboardContainer, form);
	}

	form.addEventListener(
		'submit',
		(event) => {
			// Get selected action.
			const actionSelect = form.querySelector('select[name="action"]');
			const actionSelect2 = form.querySelector('select[name="action2"]');
			const action = actionSelect?.value || actionSelect2?.value;

			// Only handle our bulk sync action.
			if (action !== 'bulk_sync') {
				return;
			}

			// Prevent default form submission IMMEDIATELY and stop all propagation.
			event.preventDefault();
			event.stopPropagation();
			event.stopImmediatePropagation();

			// Also return false as an extra safeguard.
			if (event.returnValue !== undefined) {
				event.returnValue = false;
			}

			// Get selected page IDs.
			const checkboxes = form.querySelectorAll(
				'input[name="notion_page[]"]:checked'
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

			// Queue bulk sync with Action Scheduler.
			(async () => {
				try {
					const queueBody = new URLSearchParams();
					queueBody.append('action', 'notion_queue_bulk_sync');
					queueBody.append('nonce', notionSyncAdmin.nonce);
					pageIds.forEach((id) => queueBody.append('page_ids[]', id));

					const queueResponse = await fetch(notionSyncAdmin.ajaxUrl, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/x-www-form-urlencoded',
						},
						body: queueBody,
					});

					const queueData = await queueResponse.json();

					if (!queueData.success) {
						showAdminNotice(
							'error',
							queueData.data.message ||
								'Failed to queue bulk sync.'
						);
						formControls.forEach(
							(control) => (control.disabled = false)
						);
						return;
					}

					const batchId = queueData.data.batch_id;

					// Start Preact dashboard.
					if (typeof window.startSyncDashboard === 'function') {
						window.startSyncDashboard(batchId);
					}

					// Start watching batch with REST API poller.
					watchBatchStatus(batchId, {
						onProgress: (batchData) => {
							// Preact dashboard auto-updates via polling

							// Update individual page status badges.
							if (batchData.page_statuses) {
								Object.entries(batchData.page_statuses).forEach(
									([pageId, status]) => {
										const badge =
											getStatusBadgeForPage(pageId);
										if (badge) {
											// Map batch status to badge status.
											let badgeStatus = 'not_synced';
											if (status === 'completed') {
												badgeStatus = 'synced';
											} else if (status === 'failed') {
												badgeStatus = 'failed';
											} else if (
												status === 'processing'
											) {
												badgeStatus = 'syncing';
											}

											updateStatusBadge(badge, {
												status: badgeStatus,
											});
										}
									}
								);
							}
						},
						onComplete: (batchData) => {
							// Re-enable form controls.
							formControls.forEach(
								(control) => (control.disabled = false)
							);

							// Show completion notice.
							const failed = batchData.failed || 0;
							const message =
								failed > 0
									? `Bulk sync completed with ${failed} failed pages.`
									: 'Bulk sync completed successfully!';

							showAdminNotice(
								failed > 0 ? 'warning' : 'success',
								message
							);

							// Reload page after 30 seconds to allow users to see dashboard completion state
							// (increased from 3 seconds to give dashboard time to display)
							setTimeout(() => {
								window.location.reload();
							}, 30000);
						},
						onError: (error) => {
							console.error('Batch status polling error:', error);
						},
					});
				} catch (error) {
					console.error('Error queueing bulk sync:', error);
					showAdminNotice(
						'error',
						'Network error. Please try again.'
					);
					formControls.forEach(
						(control) => (control.disabled = false)
					);
				}
			})();
		},
		true // Use capture phase to intercept BEFORE other handlers.
	);
}
