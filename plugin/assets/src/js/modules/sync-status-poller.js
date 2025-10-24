/**
 * Sync Status Poller Module
 *
 * Manages real-time polling for sync status updates via REST API.
 * Provides adaptive polling intervals and state management for individual pages and batches.
 *
 * @package
 * @since 0.3.0
 */

/**
 * SyncStatusPoller class
 *
 * Handles periodic polling to the /wp-json/notion-sync/v1/sync-status endpoint
 * and notifies listeners when status changes occur.
 */
class SyncStatusPoller {
	/**
	 * Constructor
	 *
	 * @param {Object} options         Configuration options
	 * @param {string} options.restUrl Base REST API URL
	 * @param {string} options.nonce   REST API nonce for authentication
	 */
	constructor(options = {}) {
		this.restUrl = options.restUrl || '/wp-json/notion-sync/v1/sync-status';
		this.nonce = options.nonce || '';
		this.pollIntervalActive = 3000; // 3 seconds during active sync
		this.pollIntervalIdle = 10000; // 10 seconds when idle
		this.currentInterval = this.pollIntervalIdle;
		this.pollTimer = null;
		this.isPolling = false;
		this.watchedPageIds = new Set();
		this.watchedBatchId = null;
		this.lastResponse = null;
		this.callbacks = {
			onPageStatusChange: [],
			onBatchProgress: [],
			onBatchComplete: [],
			onError: [],
		};

		// Bind methods
		this.poll = this.poll.bind(this);
		this.handleResponse = this.handleResponse.bind(this);
		this.handleError = this.handleError.bind(this);
	}

	/**
	 * Start polling for sync status
	 *
	 * @param {Object}        options         Polling options
	 * @param {Array<string>} options.pageIds Array of Notion page IDs to watch
	 * @param {string}        options.batchId Batch ID to watch
	 */
	start(options = {}) {
		if (options.pageIds) {
			options.pageIds.forEach((id) => this.watchedPageIds.add(id));
		}

		if (options.batchId) {
			this.watchedBatchId = options.batchId;
		}

		// Clear any existing timer
		this.stop();

		// Start polling immediately
		this.isPolling = true;
		this.poll();
	}

	/**
	 * Stop polling
	 */
	stop() {
		this.isPolling = false;
		if (this.pollTimer) {
			clearTimeout(this.pollTimer);
			this.pollTimer = null;
		}
	}

	/**
	 * Add page IDs to watch list
	 *
	 * @param {Array<string>|string} pageIds Page ID(s) to watch
	 */
	watchPages(pageIds) {
		const ids = Array.isArray(pageIds) ? pageIds : [pageIds];
		ids.forEach((id) => this.watchedPageIds.add(id));

		// If not currently polling, start
		if (!this.isPolling) {
			this.start();
		}
	}

	/**
	 * Watch a batch
	 *
	 * @param {string} batchId Batch ID to watch
	 */
	watchBatch(batchId) {
		this.watchedBatchId = batchId;

		// Switch to active polling
		this.currentInterval = this.pollIntervalActive;

		// If not currently polling, start
		if (!this.isPolling) {
			this.start();
		}
	}

	/**
	 * Register a callback for page status changes
	 *
	 * @param {Function} callback Callback function(pageId, statusData)
	 */
	onPageStatusChange(callback) {
		this.callbacks.onPageStatusChange.push(callback);
	}

	/**
	 * Register a callback for batch progress updates
	 *
	 * @param {Function} callback Callback function(batchData)
	 */
	onBatchProgress(callback) {
		this.callbacks.onBatchProgress.push(callback);
	}

	/**
	 * Register a callback for batch completion
	 *
	 * @param {Function} callback Callback function(batchData)
	 */
	onBatchComplete(callback) {
		this.callbacks.onBatchComplete.push(callback);
	}

	/**
	 * Register a callback for errors
	 *
	 * @param {Function} callback Callback function(error)
	 */
	onError(callback) {
		this.callbacks.onError.push(callback);
	}

	/**
	 * Perform a single poll request
	 *
	 * @private
	 */
	async poll() {
		if (!this.isPolling) {
			return;
		}

		try {
			const params = new URLSearchParams();

			// Add watched page IDs
			if (this.watchedPageIds.size > 0) {
				Array.from(this.watchedPageIds).forEach((id) => {
					params.append('page_ids[]', id);
				});
			}

			// Add batch ID
			if (this.watchedBatchId) {
				params.append('batch_id', this.watchedBatchId);
			}

			// Build URL - use & if restUrl already has query params (starts with ?)
			const separator = this.restUrl.includes('?') ? '&' : '?';
			const url = `${this.restUrl}${separator}${params.toString()}`;

			const response = await fetch(url, {
				method: 'GET',
				headers: {
					'X-WP-Nonce':
						window.notionSyncAdmin?.restNonce || this.nonce,
					'Content-Type': 'application/json',
				},
				redirect: 'follow',
			});

			if (!response.ok) {
				throw new Error(`HTTP error! status: ${response.status}`);
			}

			const data = await response.json();
			this.handleResponse(data);
		} catch (error) {
			this.handleError(error);
		} finally {
			// Schedule next poll if still active
			if (this.isPolling) {
				this.pollTimer = setTimeout(this.poll, this.currentInterval);
			}
		}
	}

	/**
	 * Handle successful poll response
	 *
	 * @private
	 * @param {Object} data Response data
	 */
	handleResponse(data) {
		// Detect page status changes
		if (data.pages) {
			Object.entries(data.pages).forEach(([pageId, statusData]) => {
				const prevStatus = this.lastResponse?.pages?.[pageId];

				// Check if status changed
				if (!prevStatus || prevStatus.status !== statusData.status) {
					// Notify listeners
					this.callbacks.onPageStatusChange.forEach((callback) => {
						callback(pageId, statusData);
					});
				}
			});
		}

		// Handle batch progress
		if (data.batch) {
			const batchData = data.batch;

			// Notify batch progress listeners
			this.callbacks.onBatchProgress.forEach((callback) => {
				callback(batchData);
			});

			// Check if batch completed
			if (
				batchData.status === 'completed' ||
				batchData.status === 'failed'
			) {
				// Notify completion listeners
				this.callbacks.onBatchComplete.forEach((callback) => {
					callback(batchData);
				});

				// Switch to idle polling
				this.currentInterval = this.pollIntervalIdle;

				// Clear batch watch
				this.watchedBatchId = null;

				// If no pages to watch, stop polling
				if (this.watchedPageIds.size === 0) {
					this.stop();
				}
			} else if (
				batchData.status === 'processing' ||
				batchData.status === 'queued'
			) {
				// Keep active polling during batch processing
				this.currentInterval = this.pollIntervalActive;
			}
		}

		// Store response for comparison
		this.lastResponse = data;
	}

	/**
	 * Handle polling errors
	 *
	 * @private
	 * @param {Error} error Error object
	 */
	handleError(error) {
		console.error('Sync status polling error:', error);

		// Notify error listeners
		this.callbacks.onError.forEach((callback) => {
			callback(error);
		});

		// Continue polling despite errors (with idle interval)
		this.currentInterval = this.pollIntervalIdle;
	}

	/**
	 * Clear all watched items and stop polling
	 */
	reset() {
		this.watchedPageIds.clear();
		this.watchedBatchId = null;
		this.lastResponse = null;
		this.stop();
	}

	/**
	 * Get current status for a page (from last response)
	 *
	 * @param {string} pageId Page ID
	 * @return {Object|null} Status data or null if not found
	 */
	getPageStatus(pageId) {
		return this.lastResponse?.pages?.[pageId] || null;
	}

	/**
	 * Get current batch data (from last response)
	 *
	 * @return {Object|null} Batch data or null if not found
	 */
	getBatchData() {
		return this.lastResponse?.batch || null;
	}
}

/**
 * Create and export a singleton instance
 */
let pollerInstance = null;

/**
 * Get or create the sync status poller instance
 *
 * @param {Object} options Configuration options (only used on first call)
 * @return {SyncStatusPoller} Poller instance
 */
export function getSyncStatusPoller(options = {}) {
	if (!pollerInstance) {
		// Get REST URL and nonce from WordPress localized data
		const restUrl =
			window.notionSyncAdmin?.restUrl ||
			'/wp-json/notion-sync/v1/sync-status';
		const nonce = window.notionSyncAdmin?.nonce || '';

		pollerInstance = new SyncStatusPoller({
			restUrl,
			nonce,
			...options,
		});
	}

	return pollerInstance;
}

/**
 * Convenience function to start watching a batch
 *
 * @param {string} batchId   Batch ID to watch
 * @param {Object} callbacks Event callbacks
 * @return {SyncStatusPoller} Poller instance
 */
export function watchBatchStatus(batchId, callbacks = {}) {
	const poller = getSyncStatusPoller();

	// Register callbacks
	if (callbacks.onProgress) {
		poller.onBatchProgress(callbacks.onProgress);
	}
	if (callbacks.onComplete) {
		poller.onBatchComplete(callbacks.onComplete);
	}
	if (callbacks.onError) {
		poller.onError(callbacks.onError);
	}

	// Start watching batch
	poller.watchBatch(batchId);

	return poller;
}

/**
 * Convenience function to start watching page statuses
 *
 * @param {Array<string>|string} pageIds        Page ID(s) to watch
 * @param {Function}             onStatusChange Callback for status changes
 * @return {SyncStatusPoller} Poller instance
 */
export function watchPageStatus(pageIds, onStatusChange) {
	const poller = getSyncStatusPoller();

	if (onStatusChange) {
		poller.onPageStatusChange(onStatusChange);
	}

	poller.watchPages(pageIds);

	return poller;
}
