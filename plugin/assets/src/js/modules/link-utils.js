/**
 * Link Utilities Module
 *
 * Handles link-related functionality including copying Notion IDs.
 *
 * @package
 */

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
