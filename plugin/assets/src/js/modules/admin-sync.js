/**
 * Sync Operations Module
 *
 * Handles all sync-related functionality including:
 * - Individual page sync
 * - Bulk sync operations
 * - Table updates (status badges, post links, timestamps)
 * - Notion ID copying
 *
 * @package
 */

/**
 * Internal dependencies
 */
import { showAdminNotice } from './admin-ui.js';

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

	// Handle bulk sync form submission.
	const bulkForm = document.getElementById('notion-pages-form');
	if (bulkForm) {
		handleBulkActions(bulkForm);
	}

	// Handle copy Notion ID buttons.
	document.addEventListener('click', (event) => {
		if (event.target.closest('.notion-copy-id')) {
			event.preventDefault();
			handleCopyNotionId(event.target.closest('.notion-copy-id'));
		}
	});
}

/**
 * Handle individual page sync ("Sync Now" button)
 *
 * @param {HTMLElement} button - The sync button element
 */
function handleSyncNow(button) {
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
function handleBulkActions(form) {
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

/**
 * Update status badge appearance
 *
 * @param {HTMLElement} badge  - Status badge element
 * @param {string}      status - Status: 'syncing', 'synced', 'error', 'not-synced'
 * @param {string}      text   - Badge text
 */
export function updateStatusBadge(badge, status, text) {
	// Remove all status classes.
	badge.classList.remove(
		'notion-sync-badge-synced',
		'notion-sync-badge-syncing',
		'notion-sync-badge-error',
		'notion-sync-badge-not-synced'
	);

	// Add new status class.
	badge.classList.add(`notion-sync-badge-${status}`);

	// Update icon.
	const icon = badge.querySelector('.dashicons');
	if (icon) {
		icon.className = 'dashicons';
		if (status === 'syncing') {
			icon.classList.add('dashicons-update');
			icon.style.animation = 'rotation 1s infinite linear';
		} else if (status === 'synced') {
			icon.classList.add('dashicons-yes');
		} else if (status === 'error') {
			icon.classList.add('dashicons-warning');
		} else {
			icon.classList.add('dashicons-minus');
		}
	}

	// Update text (preserve existing structure).
	const textNodes = Array.from(badge.childNodes).filter(
		(node) => node.nodeType === Node.TEXT_NODE
	);
	if (textNodes.length > 0) {
		textNodes[0].textContent = ` ${text}`;
	}
}

/**
 * Update WordPress post column with link
 *
 * @param {HTMLElement} row     - Table row element
 * @param {number}      postId  - WordPress post ID
 * @param {string}      editUrl - Edit post URL
 */
export function updateWpPostColumn(row, postId, editUrl) {
	const wpPostCell = row.querySelector('.column-wp_post');
	if (wpPostCell && postId && editUrl) {
		wpPostCell.innerHTML = `<a href="${escapeHtml(editUrl)}">#${escapeHtml(
			postId.toString()
		)}</a>`;
	}
}

/**
 * Update last synced column
 *
 * @param {HTMLElement} row      - Table row element
 * @param {string}      timeText - Human-readable time text
 */
export function updateLastSyncedColumn(row, timeText) {
	const lastSyncedCell = row.querySelector('.column-last_synced');
	if (lastSyncedCell) {
		lastSyncedCell.textContent = timeText;
	}
}

/**
 * Update row actions to include edit/view links
 *
 * @param {HTMLElement} syncButton - Sync Now button
 * @param {string}      editUrl    - Edit post URL
 * @param {string}      viewUrl    - View post URL
 */
export function updateRowActions(syncButton, editUrl, viewUrl) {
	const actionsDiv = syncButton.closest('.row-actions');
	if (!actionsDiv) {
		return;
	}

	// Check if edit action already exists.
	if (!actionsDiv.querySelector('.edit') && editUrl) {
		const editLink = document.createElement('span');
		editLink.className = 'edit';
		editLink.innerHTML = ` | <a href="${escapeHtml(editUrl)}">Edit Post</a>`;
		actionsDiv.appendChild(editLink);
	}

	// Check if view action already exists.
	if (!actionsDiv.querySelector('.view') && viewUrl) {
		const viewLink = document.createElement('span');
		viewLink.className = 'view';
		viewLink.innerHTML = ` | <a href="${escapeHtml(
			viewUrl
		)}" target="_blank" rel="noopener noreferrer">View Post</a>`;
		actionsDiv.appendChild(viewLink);
	}
}

/**
 * Handle copy Notion ID button
 *
 * @param {HTMLElement} button - Copy button element
 */
export function handleCopyNotionId(button) {
	const textToCopy = button.dataset.copy;

	if (!textToCopy) {
		return;
	}

	// Use modern clipboard API.
	if (navigator.clipboard && navigator.clipboard.writeText) {
		navigator.clipboard
			.writeText(textToCopy)
			.then(() => {
				showCopyFeedback(button);
			})
			.catch(() => {
				fallbackCopy(textToCopy, button);
			});
	} else {
		fallbackCopy(textToCopy, button);
	}
}

/**
 * Show copy success feedback
 *
 * @param {HTMLElement} button - Copy button
 */
function showCopyFeedback(button) {
	const originalTitle = button.title;
	button.title = notionSyncAdmin.i18n.copied;
	button.style.color = '#46b450';

	setTimeout(() => {
		button.title = originalTitle;
		button.style.color = '';
	}, 2000);
}

/**
 * Fallback copy method for older browsers
 *
 * @param {string}      text   - Text to copy
 * @param {HTMLElement} button - Copy button element
 */
function fallbackCopy(text, button) {
	const textarea = document.createElement('textarea');
	textarea.value = text;
	textarea.style.position = 'fixed';
	textarea.style.opacity = '0';
	document.body.appendChild(textarea);
	textarea.focus();
	textarea.select();

	try {
		document.execCommand('copy');
		showCopyFeedback(button);
	} catch (err) {
		// Silently fail - not critical functionality.
	}

	document.body.removeChild(textarea);
}

/**
 * Escape HTML to prevent XSS
 *
 * @param {string} text - Text to escape
 * @return {string} Escaped text
 */
function escapeHtml(text) {
	const div = document.createElement('div');
	div.textContent = text;
	return div.innerHTML;
}
