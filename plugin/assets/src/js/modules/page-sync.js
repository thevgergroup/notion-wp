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
 * Uses the batch queue system for consistency with bulk sync and to enable:
 * - Dashboard feedback
 * - Background image processing
 * - No timeout issues
 *
 * @param {HTMLElement} button - The sync button element
 */
export function handleSyncNow(button) {
	const { pageId } = button.dataset;

	if (!pageId) {
		showAdminNotice('error', 'Page ID is missing. Cannot sync page.');
		return;
	}

	// Disable button and show loading state.
	button.disabled = true;
	const originalText = button.textContent;
	button.textContent = notionSyncAdmin.i18n.syncing;

	// Update status badge to syncing state.
	const statusBadge = getStatusBadgeForPage(pageId);
	if (statusBadge) {
		updateStatusBadge(statusBadge, { status: 'syncing' });
	}

	// Create or get dashboard container (insert at top of page, before .wrap).
	let dashboardContainer = document.getElementById(
		'single-page-sync-dashboard'
	);
	if (!dashboardContainer) {
		dashboardContainer = document.createElement('div');
		dashboardContainer.id = 'single-page-sync-dashboard';

		// Insert at the very top of the admin page content.
		const wrap = document.querySelector('.wrap');
		if (wrap) {
			wrap.parentNode.insertBefore(dashboardContainer, wrap);
		}
	}

	// Queue single-page batch with Action Scheduler.
	(async () => {
		try {
			const queueBody = new URLSearchParams();
			queueBody.append('action', 'notion_queue_bulk_sync');
			queueBody.append('nonce', notionSyncAdmin.nonce);
			queueBody.append('page_ids[]', pageId);

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
					queueData.data.message || 'Failed to queue page sync.'
				);
				button.disabled = false;
				button.textContent = originalText;
				if (statusBadge) {
					updateStatusBadge(statusBadge, {
						status: 'failed',
						error: queueData.data.message || 'Failed to queue',
					});
				}
				return;
			}

			const batchId = queueData.data.batch_id;

			// Start Preact dashboard with single page.
			if (typeof window.startSyncDashboard === 'function') {
				// Render dashboard in the dedicated container.
				const oldContainer = document.getElementById(
					'notion-sync-dashboard'
				);
				if (oldContainer) {
					oldContainer.remove();
				}

				const newContainer = document.createElement('div');
				newContainer.id = 'notion-sync-dashboard';
				dashboardContainer.appendChild(newContainer);

				window.startSyncDashboard(batchId, 1);
			}

			// Start watching batch with REST API poller.
			watchBatchStatus(batchId, {
				onProgress: (batchData) => {
					// Preact dashboard auto-updates via polling

					// Update status badge.
					if (
						batchData.page_statuses &&
						batchData.page_statuses[pageId]
					) {
						const status = batchData.page_statuses[pageId];
						let badgeStatus = 'not_synced';
						if (status === 'completed') {
							badgeStatus = 'synced';
						} else if (status === 'failed') {
							badgeStatus = 'failed';
						} else if (status === 'processing') {
							badgeStatus = 'syncing';
						}

						if (statusBadge) {
							updateStatusBadge(statusBadge, {
								status: badgeStatus,
							});
						}
					}
				},
				onComplete: (batchData) => {
					// Re-enable button.
					button.disabled = false;
					button.textContent = originalText;

					// Check if sync was successful.
					const result = batchData.results?.[pageId];
					if (result && result.success) {
						// Update row UI.
						const row = button.closest('tr');
						if (row) {
							updateWpPostColumn(
								row,
								result.post_id,
								result.edit_url
							);
							updateLastSyncedColumn(row, 'Just now');
							updateRowActions(
								button,
								result.edit_url,
								result.view_url
							);
						}

						showAdminNotice('success', 'Page synced successfully!');
					} else {
						showAdminNotice(
							'error',
							result?.error ||
								'Sync completed but page sync failed.'
						);
					}

					// Remove dashboard after 5 seconds.
					setTimeout(() => {
						if (dashboardContainer) {
							dashboardContainer.style.opacity = '0';
							dashboardContainer.style.transition =
								'opacity 0.5s';
							setTimeout(() => {
								dashboardContainer.remove();
							}, 500);
						}
					}, 5000);
				},
				onError: (error) => {
					console.error('Batch status polling error:', error);
					button.disabled = false;
					button.textContent = originalText;
					if (statusBadge) {
						updateStatusBadge(statusBadge, {
							status: 'failed',
							error: 'Polling error',
						});
					}
				},
			});
		} catch (error) {
			console.error('Error queueing page sync:', error);
			showAdminNotice('error', 'Network error. Please try again.');
			button.disabled = false;
			button.textContent = originalText;
			if (statusBadge) {
				updateStatusBadge(statusBadge, {
					status: 'failed',
					error: 'Network error',
				});
			}
		}
	})();
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

					// Start Preact dashboard with immediate "Queuing" feedback.
					if (typeof window.startSyncDashboard === 'function') {
						window.startSyncDashboard(batchId, pageIds.length);
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
