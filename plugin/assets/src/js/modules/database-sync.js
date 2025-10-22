/**
 * Database Sync Module
 *
 * Handles database sync operations with batch progress tracking.
 *
 * @package NotionSync
 */

/**
 * Internal dependencies
 */
import { showAdminNotice } from './admin-ui.js';
import { escapeHtml } from './table-ui.js';

/**
 * Handle database sync button click
 *
 * @param {HTMLElement} button - The sync database button
 */
export function handleDatabaseSync(button) {
	const { databaseId } = button.dataset;

	if (!databaseId) {
		showAdminNotice('error', 'Database ID is missing. Cannot sync database.');
		return;
	}

	// Confirm sync action.
	if (!confirm(notionSyncAdmin.i18n.confirmDatabaseSync)) {
		return;
	}

	// Disable button and show loading state.
	button.disabled = true;
	const originalText = button.textContent;
	button.textContent = notionSyncAdmin.i18n.syncing;

	// Make AJAX request to start batch sync.
	fetch(notionSyncAdmin.ajaxUrl, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
		},
		body: new URLSearchParams({
			action: 'notion_sync_database',
			database_id: databaseId,
			nonce: notionSyncAdmin.nonce,
		}),
	})
		.then((response) => response.json())
		.then((data) => {
			if (data.success) {
				// Show success message.
				showAdminNotice('info', notionSyncAdmin.i18n.databaseSyncStarted);

				// Create progress bar.
				const progressContainer = createProgressBar(data.data.batch_id);

				// Start polling for progress.
				pollBatchProgress(
					data.data.batch_id,
					progressContainer,
					button,
					originalText
				);
			} else {
				// Re-enable button.
				button.disabled = false;
				button.textContent = originalText;

				// Show error notice.
				showAdminNotice(
					'error',
					data.data.message ||
						'Failed to start database sync. Please try again.'
				);
			}
		})
		.catch((error) => {
			// Re-enable button.
			button.disabled = false;
			button.textContent = originalText;

			// Show error notice.
			showAdminNotice(
				'error',
				'Network error starting database sync. Please try again.'
			);
			console.error('Database sync error:', error);
		});
}

/**
 * Create progress bar UI
 *
 * @param {string} batchId - Batch ID for tracking
 * @return {HTMLElement} Progress container element
 */
function createProgressBar(batchId) {
	// Get messages container.
	const messagesContainer = document.getElementById('notion-sync-messages');
	if (!messagesContainer) {
		return null;
	}

	// Create progress container.
	const progressContainer = document.createElement('div');
	progressContainer.className = 'notice notice-info';
	progressContainer.id = `batch-progress-${batchId}`;
	progressContainer.innerHTML = `
		<p>
			<strong>Syncing database...</strong>
			<span class="progress-text">0%</span>
		</p>
		<div class="progress-bar-wrapper" style="background: #ddd; height: 20px; border-radius: 3px; overflow: hidden; margin: 10px 0;">
			<div class="progress-bar-fill" style="background: #2271b1; height: 100%; width: 0%; transition: width 0.3s ease;"></div>
		</div>
		<p>
			<button type="button" class="button cancel-batch" data-batch-id="${escapeHtml(
				batchId
			)}">Cancel Sync</button>
		</p>
	`;

	// Add to messages container.
	messagesContainer.innerHTML = '';
	messagesContainer.appendChild(progressContainer);

	return progressContainer;
}

/**
 * Poll for batch progress updates
 *
 * @param {string}      batchId           - Batch ID to poll
 * @param {HTMLElement} progressContainer - Progress bar container
 * @param {HTMLElement} syncButton        - Original sync button
 * @param {string}      originalText      - Original button text
 */
function pollBatchProgress(
	batchId,
	progressContainer,
	syncButton,
	originalText
) {
	if (!progressContainer) {
		return;
	}

	const pollInterval = setInterval(() => {
		fetch(notionSyncAdmin.ajaxUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
			},
			body: new URLSearchParams({
				action: 'notion_sync_batch_progress',
				batch_id: batchId,
				nonce: notionSyncAdmin.nonce,
			}),
		})
			.then((response) => response.json())
			.then((data) => {
				if (data.success) {
					const progress = data.data;

					// Update progress bar.
					const progressText = progressContainer.querySelector(
						'.progress-text'
					);
					const progressFill = progressContainer.querySelector(
						'.progress-bar-fill'
					);

					if (progressText && progressFill) {
						const percentage = progress.percentage || 0;
						progressText.textContent = `${percentage}% (${progress.completed || 0}/${progress.total_entries || 0})`;
						progressFill.style.width = `${percentage}%`;
					}

					// Check if complete.
					if (
						progress.status === 'completed' ||
						progress.status === 'cancelled'
					) {
						clearInterval(pollInterval);

						// Remove progress bar.
						setTimeout(() => {
							progressContainer.remove();
						}, 2000);

						// Show completion message.
						if (progress.status === 'completed') {
							showAdminNotice(
								'success',
								notionSyncAdmin.i18n.databaseSyncComplete
							);

							// Reload page to update table.
							setTimeout(() => {
								window.location.reload();
							}, 2000);
						} else {
							showAdminNotice('warning', 'Database sync cancelled.');
						}

						// Re-enable button.
						syncButton.disabled = false;
						syncButton.textContent = originalText;
					}
				}
			})
			.catch((error) => {
				console.error('Progress polling error:', error);
				// Continue polling despite errors.
			});
	}, 2000); // Poll every 2 seconds.
}

/**
 * Handle cancel batch button click
 *
 * @param {HTMLElement} button - Cancel button
 */
export function handleCancelBatch(button) {
	const { batchId } = button.dataset;

	if (!batchId) {
		return;
	}

	// Confirm cancel action.
	if (!confirm('Are you sure you want to cancel this sync?')) {
		return;
	}

	// Disable button.
	button.disabled = true;
	button.textContent = 'Cancelling...';

	// Make AJAX request to cancel batch.
	fetch(notionSyncAdmin.ajaxUrl, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
		},
		body: new URLSearchParams({
			action: 'notion_sync_cancel_batch',
			batch_id: batchId,
			nonce: notionSyncAdmin.nonce,
		}),
	})
		.then((response) => response.json())
		.then((data) => {
			if (data.success) {
				showAdminNotice('info', 'Batch sync cancelled.');
			} else {
				showAdminNotice(
					'error',
					data.data.message || 'Failed to cancel batch.'
				);
				button.disabled = false;
				button.textContent = 'Cancel Sync';
			}
		})
		.catch((error) => {
			showAdminNotice('error', 'Network error cancelling batch.');
			console.error('Cancel batch error:', error);
			button.disabled = false;
			button.textContent = 'Cancel Sync';
		});
}
