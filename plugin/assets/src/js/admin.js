/**
 * Admin JavaScript for Notion Sync Settings Page
 *
 * Handles:
 * - Form submission with loading states
 * - Token validation
 * - Disconnect confirmation
 * - Keyboard navigation
 * - Accessibility enhancements
 *
 * @package NotionSync
 */

(function () {
	'use strict';

	/**
	 * Initialize admin functionality when DOM is ready
	 */
	function init() {
		const connectionForm = document.getElementById('notion-sync-connection-form');
		const disconnectButton = document.getElementById('notion-sync-disconnect');

		if (connectionForm) {
			handleConnectionForm(connectionForm);
		}

		if (disconnectButton) {
			handleDisconnectButton(disconnectButton);
		}

		// Enhance keyboard navigation
		enhanceKeyboardNavigation();
	}

	/**
	 * Handle connection form submission
	 *
	 * @param {HTMLFormElement} form - The connection form element
	 */
	function handleConnectionForm(form) {
		const tokenInput = form.querySelector('#notion_token');
		const submitButton = form.querySelector('button[type="submit"]');

		if (!tokenInput || !submitButton) {
			return;
		}

		// Validate token format on input (basic check)
		tokenInput.addEventListener('input', function () {
			validateTokenFormat(this, submitButton);
		});

		// Handle form submission
		form.addEventListener('submit', function (event) {
			const token = tokenInput.value.trim();

			// Basic validation
			if (!isValidTokenFormat(token)) {
				event.preventDefault();
				showInlineError(
					tokenInput,
					'Token appears to be invalid. It should start with "secret_" or "ntn_" and contain alphanumeric characters.'
				);
				return;
			}

			// Show loading state
			showLoadingState(submitButton);

			// Form will submit naturally - server handles the rest
		});
	}

	/**
	 * Validate token format and update button state
	 *
	 * @param {HTMLInputElement} input - Token input element
	 * @param {HTMLButtonElement} button - Submit button element
	 */
	function validateTokenFormat(input, button) {
		const token = input.value.trim();
		const isValid = isValidTokenFormat(token);

		// Update button state
		button.disabled = !isValid;

		// Clear any previous inline errors
		clearInlineError(input);

		// Update ARIA attributes
		input.setAttribute('aria-invalid', !isValid);
	}

	/**
	 * Check if token format appears valid (basic check)
	 *
	 * @param {string} token - The token to validate
	 * @return {boolean} True if format appears valid
	 */
	function isValidTokenFormat(token) {
		if (!token || token.length < 10) {
			return false;
		}

		// Notion integration tokens start with "secret_" or "ntn_"
		if (!token.startsWith('secret_') && !token.startsWith('ntn_')) {
			return false;
		}

		// Should contain only alphanumeric and underscores after prefix
		const prefix = token.startsWith('secret_') ? 7 : 4;
		const tokenBody = token.substring(prefix);
		return /^[a-zA-Z0-9_]+$/.test(tokenBody);
	}

	/**
	 * Show loading state on button
	 *
	 * @param {HTMLButtonElement} button - Button element
	 */
	function showLoadingState(button) {
		// Store original text
		button.dataset.originalText = button.textContent;

		// Disable button
		button.disabled = true;

		// Create spinner element
		const spinner = document.createElement('span');
		spinner.className = 'spinner is-active';
		spinner.setAttribute('aria-hidden', 'true');

		// Update button content
		button.innerHTML = '';
		button.appendChild(spinner);
		button.appendChild(document.createTextNode('Connecting...'));

		// Update ARIA attributes
		button.setAttribute('aria-busy', 'true');
		button.setAttribute('aria-live', 'polite');
	}

	/**
	 * Handle disconnect button
	 *
	 * @param {HTMLButtonElement} button - Disconnect button element
	 */
	function handleDisconnectButton(button) {
		button.addEventListener('click', function (event) {
			// Show confirmation dialog
			const confirmed = confirm(
				'Are you sure you want to disconnect from Notion?\n\nYour settings will be removed and you will need to reconnect.'
			);

			if (!confirmed) {
				event.preventDefault();
				return;
			}

			// Show loading state
			showLoadingState(button);

			// Form will submit naturally
		});
	}

	/**
	 * Show inline error message below input
	 *
	 * @param {HTMLInputElement} input - Input element
	 * @param {string} message - Error message
	 */
	function showInlineError(input, message) {
		// Clear any existing error
		clearInlineError(input);

		// Create error element
		const errorEl = document.createElement('p');
		errorEl.className = 'notion-sync-inline-error';
		errorEl.style.color = '#d63638';
		errorEl.style.marginTop = '5px';
		errorEl.style.fontSize = '13px';
		errorEl.setAttribute('role', 'alert');
		errorEl.setAttribute('aria-live', 'assertive');
		errorEl.textContent = message;

		// Insert after input
		input.parentNode.insertBefore(errorEl, input.nextSibling);

		// Update ARIA attributes
		input.setAttribute('aria-invalid', 'true');
		input.setAttribute('aria-describedby', 'notion-sync-token-error');
		errorEl.id = 'notion-sync-token-error';

		// Focus input for accessibility
		input.focus();
	}

	/**
	 * Clear inline error message
	 *
	 * @param {HTMLInputElement} input - Input element
	 */
	function clearInlineError(input) {
		const existingError = input.parentNode.querySelector('.notion-sync-inline-error');
		if (existingError) {
			existingError.remove();
		}

		// Reset ARIA attributes
		input.removeAttribute('aria-describedby');
	}

	/**
	 * Enhance keyboard navigation for accessibility
	 */
	function enhanceKeyboardNavigation() {
		// Add visible focus indicators to all interactive elements
		const interactiveElements = document.querySelectorAll(
			'.notion-sync-settings a, .notion-sync-settings button, .notion-sync-settings input, .notion-sync-settings select'
		);

		interactiveElements.forEach(function (element) {
			// Ensure tab index is set appropriately
			if (element.tabIndex < 0 && !element.disabled) {
				element.tabIndex = 0;
			}

			// Add keyboard event handlers
			element.addEventListener('keydown', function (event) {
				// Handle Enter and Space for buttons styled as links
				if (
					(event.key === 'Enter' || event.key === ' ') &&
					element.getAttribute('role') === 'button'
				) {
					event.preventDefault();
					element.click();
				}
			});
		});

		// Add escape key handler for modals/dialogs (future use)
		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				closeAllModals();
			}
		});
	}

	/**
	 * Close all open modals (placeholder for future functionality)
	 */
	function closeAllModals() {
		// Future: Close any open modal dialogs
		// Currently a placeholder for extensibility
	}

	/**
	 * Auto-dismiss dismissible notices after delay
	 */
	function handleDismissibleNotices() {
		const notices = document.querySelectorAll('.notice.is-dismissible.notion-sync-notice');

		notices.forEach(function (notice) {
			// Auto-dismiss success notices after 5 seconds
			if (notice.classList.contains('notice-success')) {
				setTimeout(function () {
					if (notice.querySelector('.notice-dismiss')) {
						notice.querySelector('.notice-dismiss').click();
					}
				}, 5000);
			}
		});
	}

	/**
	 * Handle admin notices
	 */
	function initAdminNotices() {
		handleDismissibleNotices();

		// Make notices keyboard accessible
		const dismissButtons = document.querySelectorAll('.notice-dismiss');
		dismissButtons.forEach(function (button) {
			button.setAttribute('aria-label', 'Dismiss this notice');

			// Ensure keyboard accessibility
			button.addEventListener('keydown', function (event) {
				if (event.key === 'Enter' || event.key === ' ') {
					event.preventDefault();
					this.click();
				}
			});
		});
	}

	/**
	 * Initialize copy functionality for debugging info
	 */
	function initCopyButtons() {
		const copyButtons = document.querySelectorAll('.notion-sync-copy-button');

		copyButtons.forEach(function (button) {
			button.addEventListener('click', function () {
				const textToCopy = this.dataset.copy || this.previousElementSibling.textContent;

				// Use modern clipboard API with fallback
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard
						.writeText(textToCopy)
						.then(function () {
							showCopySuccess(button);
						})
						.catch(function () {
							// Fallback for older browsers
							fallbackCopy(textToCopy, button);
						});
				} else {
					fallbackCopy(textToCopy, button);
				}
			});
		});
	}

	/**
	 * Show copy success feedback
	 *
	 * @param {HTMLElement} button - Copy button element
	 */
	function showCopySuccess(button) {
		const originalText = button.textContent;
		button.textContent = 'Copied!';
		button.classList.add('copied');

		setTimeout(function () {
			button.textContent = originalText;
			button.classList.remove('copied');
		}, 2000);
	}

	/**
	 * Fallback copy method for older browsers
	 *
	 * @param {string} text - Text to copy
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
			showCopySuccess(button);
		} catch (err) {
			// Silently fail - not critical functionality
		}

		document.body.removeChild(textarea);
	}

	/**
	 * Initialize sync functionality for pages list table
	 */
	function initSyncFunctionality() {
		// Handle "Sync Now" button clicks (individual page sync)
		document.addEventListener('click', function (event) {
			if (event.target.classList.contains('notion-sync-now')) {
				event.preventDefault();
				handleSyncNow(event.target);
			}
		});

		// Handle bulk sync form submission
		const bulkForm = document.getElementById('notion-pages-form');
		if (bulkForm) {
			handleBulkActions(bulkForm);
		}

		// Handle copy Notion ID buttons
		document.addEventListener('click', function (event) {
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
		const pageId = button.dataset.pageId;
		const pageTitle = button.dataset.pageTitle || 'this page';
		const row = button.closest('tr');

		if (!pageId) {
			showAdminNotice('error', 'Page ID is missing. Cannot sync page.');
			return;
		}

		// Disable button and show loading state
		button.disabled = true;
		const originalText = button.textContent;
		button.textContent = notionSyncAdmin.i18n.syncing;

		// Update status badge
		const statusBadge = row.querySelector('.notion-sync-badge');
		if (statusBadge) {
			updateStatusBadge(statusBadge, 'syncing', notionSyncAdmin.i18n.syncing);
		}

		// Make AJAX request
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
				// Re-enable button
				button.disabled = false;
				button.textContent = originalText;

				if (data.success) {
					// Update status badge to synced
					if (statusBadge) {
						updateStatusBadge(statusBadge, 'synced', notionSyncAdmin.i18n.synced);
					}

					// Update WordPress post column
					updateWpPostColumn(row, data.data.post_id, data.data.edit_url);

					// Update last synced column
					updateLastSyncedColumn(row, 'Just now');

					// Show success notice
					showAdminNotice(
						'success',
						data.data.message || 'Page synced successfully!'
					);

					// Update row actions to include edit/view links
					updateRowActions(button, data.data.edit_url, data.data.view_url);
				} else {
					// Update status badge to error
					if (statusBadge) {
						updateStatusBadge(statusBadge, 'error', notionSyncAdmin.i18n.syncError);
					}

					// Show error notice
					showAdminNotice(
						'error',
						data.data.message || 'Failed to sync page. Please try again.'
					);
				}
			})
			.catch((error) => {
				// Re-enable button
				button.disabled = false;
				button.textContent = originalText;

				// Update status badge to error
				if (statusBadge) {
					updateStatusBadge(statusBadge, 'error', notionSyncAdmin.i18n.syncError);
				}

				// Show error notice
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
		form.addEventListener('submit', function (event) {
			// Get selected action
			const actionSelect = form.querySelector('select[name="action"]');
			const actionSelect2 = form.querySelector('select[name="action2"]');
			const action = actionSelect?.value || actionSelect2?.value;

			// Only handle our bulk sync action
			if (action !== 'bulk_sync') {
				return;
			}

			// Prevent default form submission
			event.preventDefault();

			// Get selected page IDs
			const checkboxes = form.querySelectorAll('input[name="page_ids[]"]:checked');
			const pageIds = Array.from(checkboxes).map((cb) => cb.value);

			if (pageIds.length === 0) {
				showAdminNotice('warning', notionSyncAdmin.i18n.selectPages);
				return;
			}

			// Confirm bulk action
			if (!confirm(notionSyncAdmin.i18n.confirmBulkSync)) {
				return;
			}

			// Disable all checkboxes and form controls
			const formControls = form.querySelectorAll('input, select, button');
			formControls.forEach((control) => (control.disabled = true));

			// Show loading notice
			showAdminNotice('info', 'Syncing ' + pageIds.length + ' pages...');

		// Build form body to properly send array as PHP expects
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
					// Re-enable form controls
					formControls.forEach((control) => (control.disabled = false));

					if (data.success) {
						// Show success message
						showAdminNotice('success', data.data.message);

						// Reload page to show updated sync status
						setTimeout(() => {
							window.location.reload();
						}, 1500);
					} else {
						showAdminNotice(
							'error',
							data.data.message || 'Bulk sync failed. Please try again.'
						);
					}
				})
				.catch((error) => {
					// Re-enable form controls
					formControls.forEach((control) => (control.disabled = false));

					showAdminNotice('error', 'Network error during bulk sync.');
					console.error('Bulk sync error:', error);
				});
		});
	}

	/**
	 * Update status badge appearance
	 *
	 * @param {HTMLElement} badge - Status badge element
	 * @param {string} status - Status: 'syncing', 'synced', 'error', 'not-synced'
	 * @param {string} text - Badge text
	 */
	function updateStatusBadge(badge, status, text) {
		// Remove all status classes
		badge.classList.remove(
			'notion-sync-badge-synced',
			'notion-sync-badge-syncing',
			'notion-sync-badge-error',
			'notion-sync-badge-not-synced'
		);

		// Add new status class
		badge.classList.add('notion-sync-badge-' + status);

		// Update icon
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

		// Update text (preserve existing structure)
		const textNodes = Array.from(badge.childNodes).filter(
			(node) => node.nodeType === Node.TEXT_NODE
		);
		if (textNodes.length > 0) {
			textNodes[0].textContent = ' ' + text;
		}
	}

	/**
	 * Update WordPress post column with link
	 *
	 * @param {HTMLElement} row - Table row element
	 * @param {number} postId - WordPress post ID
	 * @param {string} editUrl - Edit post URL
	 */
	function updateWpPostColumn(row, postId, editUrl) {
		const wpPostCell = row.querySelector('.column-wp_post');
		if (wpPostCell && postId && editUrl) {
			wpPostCell.innerHTML =
				'<a href="' +
				escapeHtml(editUrl) +
				'">#' +
				escapeHtml(postId.toString()) +
				'</a>';
		}
	}

	/**
	 * Update last synced column
	 *
	 * @param {HTMLElement} row - Table row element
	 * @param {string} timeText - Human-readable time text
	 */
	function updateLastSyncedColumn(row, timeText) {
		const lastSyncedCell = row.querySelector('.column-last_synced');
		if (lastSyncedCell) {
			lastSyncedCell.textContent = timeText;
		}
	}

	/**
	 * Update row actions to include edit/view links
	 *
	 * @param {HTMLElement} syncButton - Sync Now button
	 * @param {string} editUrl - Edit post URL
	 * @param {string} viewUrl - View post URL
	 */
	function updateRowActions(syncButton, editUrl, viewUrl) {
		const actionsDiv = syncButton.closest('.row-actions');
		if (!actionsDiv) return;

		// Check if edit action already exists
		if (!actionsDiv.querySelector('.edit') && editUrl) {
			const editLink = document.createElement('span');
			editLink.className = 'edit';
			editLink.innerHTML =
				' | <a href="' + escapeHtml(editUrl) + '">Edit Post</a>';
			actionsDiv.appendChild(editLink);
		}

		// Check if view action already exists
		if (!actionsDiv.querySelector('.view') && viewUrl) {
			const viewLink = document.createElement('span');
			viewLink.className = 'view';
			viewLink.innerHTML =
				' | <a href="' +
				escapeHtml(viewUrl) +
				'" target="_blank" rel="noopener noreferrer">View Post</a>';
			actionsDiv.appendChild(viewLink);
		}
	}

	/**
	 * Handle copy Notion ID button
	 *
	 * @param {HTMLElement} button - Copy button element
	 */
	function handleCopyNotionId(button) {
		const textToCopy = button.dataset.copy;

		if (!textToCopy) {
			return;
		}

		// Use modern clipboard API
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
	 * Show admin notice message
	 *
	 * @param {string} type - Notice type: 'success', 'error', 'warning', 'info'
	 * @param {string} message - Notice message
	 */
	function showAdminNotice(type, message) {
		const container = document.getElementById('notion-sync-messages');
		if (!container) {
			// Fallback to alert if container not found
			alert(message);
			return;
		}

		// Create notice element
		const notice = document.createElement('div');
		notice.className = 'notice notice-' + type + ' is-dismissible';
		notice.innerHTML = '<p>' + escapeHtml(message) + '</p>';

		// Add dismiss button
		const dismissButton = document.createElement('button');
		dismissButton.type = 'button';
		dismissButton.className = 'notice-dismiss';
		dismissButton.innerHTML = '<span class="screen-reader-text">Dismiss this notice.</span>';
		dismissButton.addEventListener('click', () => {
			notice.remove();
		});
		notice.appendChild(dismissButton);

		// Clear previous notices of same type
		const existingNotices = container.querySelectorAll('.notice-' + type);
		existingNotices.forEach((n) => n.remove());

		// Add to container
		container.appendChild(notice);

		// Auto-dismiss success notices
		if (type === 'success') {
			setTimeout(() => {
				notice.remove();
			}, 5000);
		}

		// Scroll to notice
		notice.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
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

	// Initialize when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
		document.addEventListener('DOMContentLoaded', initAdminNotices);
		document.addEventListener('DOMContentLoaded', initCopyButtons);
		document.addEventListener('DOMContentLoaded', initSyncFunctionality);
	} else {
		init();
		initAdminNotices();
		initCopyButtons();
		initSyncFunctionality();
	}
})();
