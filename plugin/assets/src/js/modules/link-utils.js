/**
 * Link Utilities Module
 *
 * Handles link-related functionality including copying Notion IDs
 * and updating links in posts.
 *
 * @package NotionSync
 */

/**
 * Internal dependencies
 */
import { showAdminNotice } from './admin-ui.js';
import { escapeHtml } from './table-ui.js';

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
 * Handle update links button click
 *
 * Updates all Notion links in synced posts to WordPress permalinks.
 * This includes both page links and database links.
 */
export function handleUpdateLinks() {
	const button = document.getElementById('update-links-btn');
	const spinner = document.getElementById('link-update-spinner');
	const messagesContainer = document.getElementById('link-update-messages');

	if (!button || !messagesContainer) {
		return;
	}

	// Disable button and show loading state.
	button.disabled = true;
	if (spinner) {
		spinner.style.display = 'inline-block';
	}

	// Clear previous messages.
	messagesContainer.innerHTML = '';

	// Make AJAX request.
	fetch(notionSyncAdmin.ajaxUrl, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
		},
		body: new URLSearchParams({
			action: 'notion_sync_update_links',
			nonce: notionSyncAdmin.nonce,
		}),
	})
		.then((response) => response.json())
		.then((data) => {
			// Re-enable button and hide spinner.
			button.disabled = false;
			if (spinner) {
				spinner.style.display = 'none';
			}

			if (data.success) {
				// Show success message with statistics.
				const message = document.createElement('div');
				message.className = 'notice notice-success';
				message.innerHTML = `<p><strong>Link Update Complete</strong></p>
					<ul style="margin-left: 20px;">
						<li>Posts checked: ${data.data.posts_checked}</li>
						<li>Posts updated: ${data.data.posts_updated}</li>
						<li>Links rewritten: ${data.data.links_rewritten}</li>
					</ul>`;
				messagesContainer.appendChild(message);
			} else {
				// Show error message.
				const message = document.createElement('div');
				message.className = 'notice notice-error';
				message.innerHTML = `<p>${escapeHtml(
					data.data || 'Failed to update links. Please try again.'
				)}</p>`;
				messagesContainer.appendChild(message);
			}
		})
		.catch((error) => {
			// Re-enable button and hide spinner.
			button.disabled = false;
			if (spinner) {
				spinner.style.display = 'none';
			}

			// Show error message.
			const message = document.createElement('div');
			message.className = 'notice notice-error';
			message.innerHTML =
				'<p>Network error updating links. Please try again.</p>';
			messagesContainer.appendChild(message);
			console.error('Link update error:', error);
		});
}
