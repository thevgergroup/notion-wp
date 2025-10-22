/**
 * Table UI Updates Module
 *
 * Handles updating table UI elements (badges, columns, row actions).
 *
 * @package NotionSync
 */

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
