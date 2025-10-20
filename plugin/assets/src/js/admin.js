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

	// Initialize when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
		document.addEventListener('DOMContentLoaded', initAdminNotices);
		document.addEventListener('DOMContentLoaded', initCopyButtons);
	} else {
		init();
		initAdminNotices();
		initCopyButtons();
	}
})();
