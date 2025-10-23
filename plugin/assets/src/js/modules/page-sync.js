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
	updateStatusBadge,
	updateWpPostColumn,
	updateLastSyncedColumn,
	updateRowActions,
} from './table-ui.js';

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

		// Also return false as an extra safeguard
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

		// Show queueing notice.
		let noticeEl = showAdminNotice(
			'info',
			`<strong>Queueing Sync...</strong><br>Preparing to sync ${pageIds.length} pages...`
		);

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
						queueData.data.message || 'Failed to queue bulk sync.'
					);
					formControls.forEach((control) => (control.disabled = false));
					return;
				}

				const batchId = queueData.data.batch_id;
				const total = queueData.data.total;

				// Update notice to show sync has started.
				const noticeP = noticeEl.querySelector('p');
				if (noticeP) {
					noticeP.innerHTML = `<strong>Bulk Sync in Progress...</strong><br>Processing 0 / ${total} pages...`;
				}

				// Poll for status updates.
				let pollCount = 0;
				const maxPolls = 300; // 5 minutes max (1 second intervals).

				const pollStatus = async () => {
					pollCount++;

					if (pollCount > maxPolls) {
						showAdminNotice(
							'warning',
							'Bulk sync is taking longer than expected. Please check the status later.'
						);
						formControls.forEach(
							(control) => (control.disabled = false)
						);
						return;
					}

					try {
						const statusBody = new URLSearchParams();
						statusBody.append('action', 'notion_bulk_sync_status');
						statusBody.append('nonce', notionSyncAdmin.nonce);
						statusBody.append('batch_id', batchId);

						const statusResponse = await fetch(
							notionSyncAdmin.ajaxUrl,
							{
								method: 'POST',
								headers: {
									'Content-Type':
										'application/x-www-form-urlencoded',
								},
								body: statusBody,
							}
						);

						const statusData = await statusResponse.json();

						if (statusData.success) {
							const progress = statusData.data;

							// Update progress notice.
							if (noticeP) {
								noticeP.innerHTML = `<strong>Bulk Sync in Progress...</strong><br>Processing ${progress.processed} / ${progress.total} pages... (${progress.percentage}%)`;
							}

							// Check if completed.
							if (progress.status === 'completed') {
								// Show completion summary.
								let summaryHTML = `<strong>Bulk Sync Complete!</strong><br>`;
								summaryHTML += `Successfully synced: ${progress.successful} / ${progress.total} pages`;

								if (progress.failed > 0) {
									summaryHTML += `<br><span style="color: #d63638;">Failed: ${progress.failed} pages</span>`;
								}

								if (noticeP) {
									noticeP.innerHTML = summaryHTML;
								}
								noticeEl.className = `notice notice-${
									progress.failed > 0 ? 'warning' : 'success'
								} is-dismissible`;

								// Re-enable form controls.
								formControls.forEach(
									(control) => (control.disabled = false)
								);

								// Reload page to show updated sync status.
								setTimeout(() => {
									window.location.reload();
								}, 2000);

								return;
							}

							// Continue polling.
							setTimeout(pollStatus, 1000);
						} else {
							showAdminNotice(
								'error',
								'Failed to get sync status. Please refresh the page.'
							);
							formControls.forEach(
								(control) => (control.disabled = false)
							);
						}
					} catch (error) {
						console.error('Error polling sync status:', error);
						showAdminNotice(
							'error',
							'Network error while checking sync status.'
						);
						formControls.forEach(
							(control) => (control.disabled = false)
						);
					}
				};

				// Start polling after a brief delay.
				setTimeout(pollStatus, 1000);
			} catch (error) {
				console.error('Error queueing bulk sync:', error);
				showAdminNotice('error', 'Network error. Please try again.');
				formControls.forEach((control) => (control.disabled = false));
			}
		})();
		},
		true // Use capture phase to intercept BEFORE other handlers
	);
}
