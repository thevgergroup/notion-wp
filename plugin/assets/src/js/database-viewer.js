/**
 * Database Viewer with Tabulator.js
 *
 * Displays Notion database data with interactive sorting, filtering, and export.
 *
 * @package
 * @since 1.0.0
 */

(function () {
	'use strict';

	// Wait for DOM and Tabulator to be ready.
	document.addEventListener('DOMContentLoaded', () => {
		initDatabaseViewer();
	});

	/**
	 * Initialize database viewer with Tabulator.
	 */
	function initDatabaseViewer() {
		const { postId, restUrl, nonce, i18n } = window.notionDatabaseViewer;

		if (!postId) {
			showError('Invalid database ID');
			return;
		}

		// Fetch schema first to build column definitions.
		fetchSchema(postId, restUrl, nonce)
			.then((schema) => {
				// Initialize Tabulator with schema.
				initializeTable(schema, postId, restUrl, nonce, i18n);
			})
			.catch((error) => {
				showError(error.message || i18n.error);
			});
	}

	/**
	 * Fetch database schema from REST API.
	 *
	 * @param {number} postId  Database post ID.
	 * @param {string} restUrl REST API base URL.
	 * @param {string} nonce   REST API nonce.
	 * @return {Promise<Object>} Schema data.
	 */
	async function fetchSchema(postId, restUrl, nonce) {
		const response = await fetch(`${restUrl}/databases/${postId}/schema`, {
			method: 'GET',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': nonce,
			},
		});

		if (!response.ok) {
			throw new Error(`HTTP ${response.status}: ${response.statusText}`);
		}

		return await response.json();
	}

	/**
	 * Initialize Tabulator table.
	 *
	 * @param {Object} schema  Schema data from API.
	 * @param {number} postId  Database post ID.
	 * @param {string} restUrl REST API base URL.
	 * @param {string} nonce   REST API nonce.
	 * @param {Object} i18n    Internationalization strings.
	 */
	function initializeTable(schema, postId, restUrl, nonce, i18n) {
		// Build columns from schema.
		const columns = buildColumns(schema.columns);

		// Initialize Tabulator.
		const table = new Tabulator('#database-table', {
			layout: 'fitDataStretch',
			height: '600px',
			pagination: true,
			paginationMode: 'remote',
			paginationSize: 50,
			paginationSizeSelector: [25, 50, 100, 200],
			ajaxURL: `${restUrl}/databases/${postId}/rows`,
			ajaxConfig: {
				method: 'GET',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': nonce,
				},
			},
			ajaxResponse(url, params, response) {
				// Transform API response for Tabulator.
				return {
					last_page: response.pagination.total_pages,
					data: transformRows(response.rows),
				};
			},
			columns,
			placeholder: i18n.noData,
			initialSort: [{ column: 'last_edited_time', dir: 'desc' }],
		});

		// Hide loading indicator.
		document.getElementById('table-loading').style.display = 'none';

		// Wire up action buttons.
		wireUpActions(table, i18n);
	}

	/**
	 * Build Tabulator columns from schema.
	 *
	 * @param {Array} schemaColumns Column definitions from API.
	 * @return {Array} Tabulator column configuration.
	 */
	function buildColumns(schemaColumns) {
		return schemaColumns.map((col) => {
			const column = {
				title: col.title,
				field: col.field,
				width: col.width || 150,
				headerFilter: col.headerFilter || false,
				sorter: col.sorter || 'string',
			};

			// Handle frozen columns.
			if (col.frozen) {
				column.frozen = true;
			}

			// Handle formatters.
			if (col.formatter === 'html') {
				column.formatter = function (cell) {
					const value = cell.getValue();
					if (Array.isArray(value)) {
						// Multi-select or array values.
						return value
							.map(
								(v) =>
									`<span class="tag">${escapeHtml(v)}</span>`
							)
							.join(' ');
					}
					return value;
				};
			} else if (col.formatter === 'tickCross') {
				column.formatter = 'tickCross';
				column.hozAlign = 'center';
			}

			// DateTime formatter.
			if (col.sorter === 'datetime') {
				column.formatter = function (cell) {
					const value = cell.getValue();
					if (!value) {
						return '';
					}
					try {
						return new Date(value).toLocaleString();
					} catch (e) {
						return value;
					}
				};
			}

			return column;
		});
	}

	/**
	 * Transform row data for Tabulator.
	 *
	 * Flattens nested properties structure.
	 *
	 * @param {Array} rows Raw rows from API.
	 * @return {Array} Transformed rows.
	 */
	function transformRows(rows) {
		return rows.map((row) => {
			// Parse properties JSON if string.
			const properties =
				typeof row.properties === 'string'
					? JSON.parse(row.properties)
					: row.properties;

			return {
				notion_id: row.notion_id,
				title: row.title,
				created_time: row.created_time,
				last_edited_time: row.last_edited_time,
				properties,
			};
		});
	}

	/**
	 * Wire up action buttons.
	 *
	 * @param {Tabulator} table Tabulator instance.
	 * @param {Object}    _i18n Internationalization strings (unused).
	 */
	function wireUpActions(table, _i18n) {
		// Reset filters.
		document
			.getElementById('reset-filters')
			.addEventListener('click', () => {
				table.clearHeaderFilter();
			});

		// Export CSV.
		document.getElementById('export-csv').addEventListener('click', () => {
			table.download('csv', `database-${Date.now()}.csv`);
		});

		// Export JSON.
		document.getElementById('export-json').addEventListener('click', () => {
			table.download('json', `database-${Date.now()}.json`);
		});
	}

	/**
	 * Show error message.
	 *
	 * @param {string} message Error message.
	 */
	function showError(message) {
		document.getElementById('table-loading').style.display = 'none';
		document.getElementById('error-message').textContent = message;
		document.getElementById('table-error').style.display = 'block';
	}

	/**
	 * Escape HTML to prevent XSS.
	 *
	 * @param {string} text Text to escape.
	 * @return {string} Escaped text.
	 */
	function escapeHtml(text) {
		const div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}
})();
