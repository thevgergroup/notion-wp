/**
 * Status Badge Module
 *
 * Renders icon-based status badges for sync status display.
 * Replaces text-based "Synced/Not Synced" with visual indicators.
 *
 * @package
 * @since 0.3.0
 */

/**
 * Status badge configuration
 *
 * Maps status types to their visual representation using WordPress Dashicons
 */
const STATUS_CONFIG = {
	not_synced: {
		icon: 'dashicons-minus',
		color: '#8c8f94',
		bgColor: '#f0f0f1',
		label: 'Not Synced',
		tooltip: 'This page has not been synced to WordPress yet.',
		animated: false,
	},
	synced: {
		icon: 'dashicons-yes-alt',
		color: '#00a32a',
		bgColor: '#e7f5ec',
		label: 'Synced',
		tooltip: 'Successfully synced. WordPress post is up-to-date.',
		animated: false,
	},
	outdated: {
		icon: 'dashicons-warning',
		color: '#dba617',
		bgColor: '#fcf9e8',
		label: 'Outdated',
		tooltip:
			'Content has been modified in Notion since last sync. Re-sync to update.',
		animated: false,
	},
	syncing: {
		icon: 'dashicons-update',
		color: '#2271b1',
		bgColor: '#f0f6fc',
		label: 'Syncing',
		tooltip: 'Sync in progress...',
		animated: true,
	},
	failed: {
		icon: 'dashicons-warning',
		color: '#d63638',
		bgColor: '#fcf0f1',
		label: 'Failed',
		tooltip: 'Sync failed. Click for details.',
		animated: false,
	},
};

/**
 * Create a status badge element
 *
 * @param {Object}  statusData        Status data from API
 * @param {Object}  options           Additional options
 * @param {boolean} options.showLabel Whether to show text label
 * @param {boolean} options.compact   Compact mode (icon only)
 * @return {HTMLElement} Badge element
 */
export function createStatusBadge(statusData, options = {}) {
	const { showLabel = true, compact = false } = options;

	// Get status config (fallback to not_synced)
	const status = statusData.status || 'not_synced';
	const config = STATUS_CONFIG[status] || STATUS_CONFIG.not_synced;

	// Override with server-provided config if available
	const icon = statusData.icon || config.icon;
	const color = statusData.color || config.color;
	const bgColor = statusData.bg || config.bgColor;
	const label = statusData.label || config.label;
	const tooltip = statusData.tooltip || config.tooltip;
	const animated =
		statusData.animated !== undefined
			? statusData.animated
			: config.animated;

	// Create badge container
	const badge = document.createElement('span');
	badge.className = 'notion-status-badge';
	badge.setAttribute('data-status', status);
	badge.setAttribute('title', tooltip);
	badge.setAttribute('role', 'status');
	badge.setAttribute('aria-label', tooltip);

	// Apply styles
	badge.style.display = 'inline-flex';
	badge.style.alignItems = 'center';
	badge.style.gap = '4px';
	badge.style.padding = compact ? '2px 6px' : '4px 8px';
	badge.style.borderRadius = '3px';
	badge.style.backgroundColor = bgColor;
	badge.style.fontSize = '12px';
	badge.style.lineHeight = '1';
	badge.style.whiteSpace = 'nowrap';

	// Create icon element
	const iconEl = document.createElement('span');
	iconEl.className = `dashicons ${icon}`;
	iconEl.style.color = color;
	iconEl.style.fontSize = '16px';
	iconEl.style.width = '16px';
	iconEl.style.height = '16px';
	iconEl.setAttribute('aria-hidden', 'true');

	// Add animation for syncing state
	if (animated) {
		iconEl.style.animation = 'notion-spin 1s linear infinite';
		badge.classList.add('is-syncing');
	}

	badge.appendChild(iconEl);

	// Add label if requested
	if (showLabel && !compact) {
		const labelEl = document.createElement('span');
		labelEl.className = 'notion-status-label';
		labelEl.textContent = label;
		labelEl.style.color = color;
		labelEl.style.fontWeight = '500';
		badge.appendChild(labelEl);
	}

	// Add error indicator if failed
	if (status === 'failed' && statusData.error) {
		badge.style.cursor = 'help';
		badge.setAttribute('data-error', statusData.error);
	}

	return badge;
}

/**
 * Update an existing status badge
 *
 * @param {HTMLElement} badgeElement Existing badge element
 * @param {Object}      statusData   New status data
 */
export function updateStatusBadge(badgeElement, statusData) {
	if (
		!badgeElement ||
		!badgeElement.classList.contains('notion-status-badge')
	) {
		console.warn('Invalid badge element provided to updateStatusBadge');
		return;
	}

	// Get status config
	const status = statusData.status || 'not_synced';
	const config = STATUS_CONFIG[status] || STATUS_CONFIG.not_synced;

	// Override with server-provided config
	const icon = statusData.icon || config.icon;
	const color = statusData.color || config.color;
	const bgColor = statusData.bg || config.bgColor;
	const label = statusData.label || config.label;
	const tooltip = statusData.tooltip || config.tooltip;
	const animated =
		statusData.animated !== undefined
			? statusData.animated
			: config.animated;

	// Update attributes
	badgeElement.setAttribute('data-status', status);
	badgeElement.setAttribute('title', tooltip);
	badgeElement.setAttribute('aria-label', tooltip);
	badgeElement.style.backgroundColor = bgColor;

	// Update icon
	const iconEl = badgeElement.querySelector('.dashicons');
	if (iconEl) {
		iconEl.className = `dashicons ${icon}`;
		iconEl.style.color = color;

		// Handle animation
		if (animated) {
			iconEl.style.animation = 'notion-spin 1s linear infinite';
			badgeElement.classList.add('is-syncing');
		} else {
			iconEl.style.animation = '';
			badgeElement.classList.remove('is-syncing');
		}
	}

	// Update label
	const labelEl = badgeElement.querySelector('.notion-status-label');
	if (labelEl) {
		labelEl.textContent = label;
		labelEl.style.color = color;
	}

	// Handle error state
	if (status === 'failed' && statusData.error) {
		badgeElement.style.cursor = 'help';
		badgeElement.setAttribute('data-error', statusData.error);
	} else {
		badgeElement.style.cursor = '';
		badgeElement.removeAttribute('data-error');
	}
}

/**
 * Replace text-based status with icon badge
 *
 * Finds text like "Synced" or "Not Synced" and replaces with badge
 *
 * @param {HTMLElement} container Container element to search within
 */
export function replaceTextStatusWithBadge(container) {
	if (!container) {
		return;
	}

	// Find all status cells (adjust selector based on actual HTML structure)
	const statusCells = container.querySelectorAll('.column-status');

	statusCells.forEach((cell) => {
		const text = cell.textContent.trim().toLowerCase();

		// Determine status from text
		let status = 'not_synced';
		if (text.includes('synced') && !text.includes('not')) {
			status = 'synced';
		} else if (text.includes('syncing')) {
			status = 'syncing';
		} else if (text.includes('failed') || text.includes('error')) {
			status = 'failed';
		} else if (text.includes('outdated')) {
			status = 'outdated';
		}

		// Create and insert badge
		const badge = createStatusBadge({ status });
		cell.innerHTML = '';
		cell.appendChild(badge);
	});
}

/**
 * Initialize status badges for a table
 *
 * Replaces existing text statuses with badges and sets up observers
 *
 * @param {HTMLElement} tableElement Table element
 */
export function initStatusBadges(tableElement) {
	if (!tableElement) {
		console.warn('No table element provided to initStatusBadges');
		return;
	}

	// Replace existing text statuses
	replaceTextStatusWithBadge(tableElement);

	// Set up mutation observer to handle dynamically added rows
	const observer = new MutationObserver((mutations) => {
		mutations.forEach((mutation) => {
			mutation.addedNodes.forEach((node) => {
				if (
					node.nodeType === 1 &&
					node.classList?.contains('column-status')
				) {
					replaceTextStatusWithBadge(node.parentElement);
				}
			});
		});
	});

	observer.observe(tableElement, {
		childList: true,
		subtree: true,
	});

	// Store observer reference for cleanup
	tableElement._statusBadgeObserver = observer;
}

/**
 * Get status badge element for a page ID
 *
 * @param {string}      pageId    Page ID
 * @param {HTMLElement} container Container to search within (defaults to document)
 * @return {HTMLElement|null} Badge element or null
 */
export function getStatusBadgeForPage(pageId, container = document) {
	// Try to find the row for this page ID
	const row = container.querySelector(`tr[data-page-id="${pageId}"]`);
	if (!row) {
		return null;
	}

	// Find the status badge within the row
	return row.querySelector('.notion-status-badge');
}

/**
 * Inject CSS for status badge animations
 *
 * Should be called once on page load
 */
export function injectStatusBadgeStyles() {
	// Check if already injected
	if (document.getElementById('notion-status-badge-styles')) {
		return;
	}

	const style = document.createElement('style');
	style.id = 'notion-status-badge-styles';
	style.textContent = `
		@keyframes notion-spin {
			from {
				transform: rotate(0deg);
			}
			to {
				transform: rotate(360deg);
			}
		}

		.notion-status-badge {
			transition: background-color 0.2s ease, color 0.2s ease;
		}

		.notion-status-badge:hover {
			filter: brightness(0.95);
		}

		.notion-status-badge[data-status="failed"] {
			cursor: help;
		}

		.notion-status-badge.is-syncing .dashicons {
			transform-origin: center;
		}

		/* Accessibility: ensure badges are keyboard accessible when they have actions */
		.notion-status-badge[data-status="failed"]:focus {
			outline: 2px solid #2271b1;
			outline-offset: 2px;
		}
	`;

	document.head.appendChild(style);
}

/**
 * Initialize status badge system
 *
 * Call this once on page load to set up styles and replace existing badges
 */
export function initStatusBadgeSystem() {
	// Inject CSS
	injectStatusBadgeStyles();

	// Find and initialize tables
	const tables = document.querySelectorAll('.wp-list-table');
	tables.forEach((table) => {
		initStatusBadges(table);
	});
}
