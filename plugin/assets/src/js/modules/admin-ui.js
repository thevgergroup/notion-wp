/**
 * UI Utilities Module
 *
 * Provides reusable UI components and utilities:
 * - Loading states
 * - Error displays
 * - Admin notices
 * - Copy functionality
 * - Keyboard navigation
 *
 * @package
 */

/**
 * Show loading state on button
 *
 * @param {HTMLButtonElement} button - Button element
 */
export function showLoadingState(button) {
	// Store original text.
	button.dataset.originalText = button.textContent;

	// Disable button.
	button.disabled = true;

	// Create spinner element.
	const spinner = document.createElement('span');
	spinner.className = 'spinner is-active';
	spinner.setAttribute('aria-hidden', 'true');

	// Update button content.
	button.innerHTML = '';
	button.appendChild(spinner);
	button.appendChild(document.createTextNode('Connecting...'));

	// Update ARIA attributes.
	button.setAttribute('aria-busy', 'true');
	button.setAttribute('aria-live', 'polite');
}

/**
 * Show inline error message below input
 *
 * @param {HTMLInputElement} input   - Input element
 * @param {string}           message - Error message
 */
export function showInlineError(input, message) {
	// Clear any existing error.
	clearInlineError(input);

	// Create error element.
	const errorEl = document.createElement('p');
	errorEl.className = 'notion-sync-inline-error';
	errorEl.style.color = '#d63638';
	errorEl.style.marginTop = '5px';
	errorEl.style.fontSize = '13px';
	errorEl.setAttribute('role', 'alert');
	errorEl.setAttribute('aria-live', 'assertive');
	errorEl.textContent = message;

	// Insert after input.
	input.parentNode.insertBefore(errorEl, input.nextSibling);

	// Update ARIA attributes.
	input.setAttribute('aria-invalid', 'true');
	input.setAttribute('aria-describedby', 'notion-sync-token-error');
	errorEl.id = 'notion-sync-token-error';

	// Focus input for accessibility.
	input.focus();
}

/**
 * Clear inline error message
 *
 * @param {HTMLInputElement} input - Input element
 */
export function clearInlineError(input) {
	const existingError = input.parentNode.querySelector(
		'.notion-sync-inline-error'
	);
	if (existingError) {
		existingError.remove();
	}

	// Reset ARIA attributes.
	input.removeAttribute('aria-describedby');
}

/**
 * Enhance keyboard navigation for accessibility
 */
export function enhanceKeyboardNavigation() {
	// Add visible focus indicators to all interactive elements.
	const interactiveElements = document.querySelectorAll(
		'.notion-sync-settings a, .notion-sync-settings button, .notion-sync-settings input, .notion-sync-settings select'
	);

	interactiveElements.forEach((element) => {
		// Ensure tab index is set appropriately.
		if (element.tabIndex < 0 && !element.disabled) {
			element.tabIndex = 0;
		}

		// Add keyboard event handlers.
		element.addEventListener('keydown', (event) => {
			// Handle Enter and Space for buttons styled as links.
			if (
				(event.key === 'Enter' || event.key === ' ') &&
				element.getAttribute('role') === 'button'
			) {
				event.preventDefault();
				element.click();
			}
		});
	});

	// Add escape key handler for modals/dialogs (future use).
	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape') {
			closeAllModals();
		}
	});
}

/**
 * Close all open modals (placeholder for future functionality)
 */
export function closeAllModals() {
	// Future: Close any open modal dialogs.
	// Currently a placeholder for extensibility.
}

/**
 * Auto-dismiss dismissible notices after delay
 */
function handleDismissibleNotices() {
	const notices = document.querySelectorAll(
		'.notice.is-dismissible.notion-sync-notice'
	);

	notices.forEach((notice) => {
		// Auto-dismiss success notices after 5 seconds.
		if (notice.classList.contains('notice-success')) {
			setTimeout(() => {
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
export function initAdminNotices() {
	handleDismissibleNotices();

	// Make notices keyboard accessible.
	const dismissButtons = document.querySelectorAll('.notice-dismiss');
	dismissButtons.forEach((button) => {
		button.setAttribute('aria-label', 'Dismiss this notice');

		// Ensure keyboard accessibility.
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
export function initCopyButtons() {
	const copyButtons = document.querySelectorAll('.notion-sync-copy-button');

	copyButtons.forEach((button) => {
		button.addEventListener('click', function () {
			const textToCopy =
				this.dataset.copy || this.previousElementSibling.textContent;

			// Use modern clipboard API with fallback.
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard
					.writeText(textToCopy)
					.then(() => {
						showCopySuccess(button);
					})
					.catch(() => {
						// Fallback for older browsers.
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
export function showCopySuccess(button) {
	const originalText = button.textContent;
	button.textContent = 'Copied!';
	button.classList.add('copied');

	setTimeout(() => {
		button.textContent = originalText;
		button.classList.remove('copied');
	}, 2000);
}

/**
 * Fallback copy method for older browsers
 *
 * @param {string}      text   - Text to copy
 * @param {HTMLElement} button - Copy button element
 */
export function fallbackCopy(text, button) {
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
		// Silently fail - not critical functionality.
	}

	document.body.removeChild(textarea);
}

/**
 * Show admin notice message
 *
 * @param {string} type    - Notice type: 'success', 'error', 'warning', 'info'
 * @param {string} message - Notice message
 */
export function showAdminNotice(type, message) {
	const container = document.getElementById('notion-sync-messages');
	if (!container) {
		// Fallback to alert if container not found.
		alert(message);
		return;
	}

	// Create notice element.
	const notice = document.createElement('div');
	notice.className = `notice notice-${type} is-dismissible`;
	notice.innerHTML = `<p>${escapeHtml(message)}</p>`;

	// Add dismiss button.
	const dismissButton = document.createElement('button');
	dismissButton.type = 'button';
	dismissButton.className = 'notice-dismiss';
	dismissButton.innerHTML =
		'<span class="screen-reader-text">Dismiss this notice.</span>';
	dismissButton.addEventListener('click', () => {
		notice.remove();
	});
	notice.appendChild(dismissButton);

	// Clear previous notices of same type.
	const existingNotices = container.querySelectorAll(`.notice-${type}`);
	existingNotices.forEach((n) => n.remove());

	// Add to container.
	container.appendChild(notice);

	// Auto-dismiss success notices.
	if (type === 'success') {
		setTimeout(() => {
			notice.remove();
		}, 5000);
	}

	// Scroll to notice.
	notice.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

/**
 * Escape HTML to prevent XSS
 *
 * @param {string} text - Text to escape
 * @return {string} Escaped text
 */
export function escapeHtml(text) {
	const div = document.createElement('div');
	div.textContent = text;
	return div.innerHTML;
}
