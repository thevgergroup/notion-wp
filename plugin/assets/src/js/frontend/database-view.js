/**
 * Database View Frontend JavaScript
 *
 * Initializes Tabulator tables for Notion database views.
 * Depends on Tabulator library being loaded first.
 */

(function () {
	'use strict';

	/**
	 * Initialize a database view table
	 *
	 * @param {HTMLElement} container - The container element with data attributes
	 */
	function initDatabaseView(container) {
		const { databaseId } = container.dataset;
		const showExport = container.dataset.showExport === '1';
		const pageSize = parseInt(container.dataset.pageSize, 10) || 50;

		// eslint-disable-next-line no-console
		console.log(
			'[DatabaseView] Initializing view for database ID:',
			databaseId
		);

		// Find the table element within the container
		const tableElement = container.querySelector(
			'.notion-wp-database-view__table'
		);
		if (!tableElement) {
			console.error(
				'[DatabaseView] Table element not found in container'
			);
			return;
		}

		// Show loading state
		container.classList.add('notion-wp-database-view--loading');

		// Fetch database data from REST API
		const { restUrl, nonce } = window.notionWpDatabaseViewFrontend || {};
		if (!restUrl) {
			console.error('[DatabaseView] REST API URL not available');
			return;
		}

		// Fetch both schema and rows
		Promise.all([
			fetch(`${restUrl}notion-sync/v1/databases/${databaseId}/schema`, {
				headers: { 'X-WP-Nonce': nonce },
			}).then((r) =>
				r.ok
					? r.json()
					: Promise.reject(`Schema fetch failed: ${r.status}`)
			),
			fetch(`${restUrl}notion-sync/v1/databases/${databaseId}/rows`, {
				headers: { 'X-WP-Nonce': nonce },
			}).then((r) =>
				r.ok
					? r.json()
					: Promise.reject(`Rows fetch failed: ${r.status}`)
			),
		])
			.then(([schema, rowsData]) => {
				// eslint-disable-next-line no-console
				console.log('[DatabaseView] Fetched schema:', schema);
				// eslint-disable-next-line no-console
				console.log('[DatabaseView] Fetched rows:', rowsData);

				// Remove loading state
				container.classList.remove('notion-wp-database-view--loading');

				// Initialize Tabulator with schema columns and row data
				initTabulator(
					tableElement,
					{
						columns: schema.columns || [],
						rows: rowsData.rows || [],
					},
					{
						pageSize,
						showExport,
					}
				);
			})
			.catch((error) => {
				console.error(
					'[DatabaseView] Error fetching database data:',
					error
				);
				container.classList.remove('notion-wp-database-view--loading');
				container.classList.add('notion-wp-database-view--error');

				// Show error message
				const errorDiv = document.createElement('div');
				errorDiv.className = 'notion-wp-database-view__error';
				errorDiv.innerHTML =
					'<p>⚠️ Failed to load database. Please try again later.</p>';
				tableElement.parentNode.replaceChild(errorDiv, tableElement);
			});
	}

	/**
	 * Custom formatter for pill/tag display
	 * Converts comma-separated values into styled pill elements
	 *
	 * @param {Object} cell - Tabulator cell object
	 * @return {HTMLElement|string} Container with pill elements or empty string
	 */
	function pillFormatter(cell) {
		const value = cell.getValue();
		if (!value) {
			return '';
		}

		// Ensure value is a string
		const stringValue = typeof value === 'string' ? value : String(value);

		const container = document.createElement('div');
		container.className = 'notion-wp-pills';

		// Split by comma and create pill for each value
		const items = stringValue
			.split(',')
			.map((item) => item.trim())
			.filter(Boolean);

		items.forEach((item) => {
			const pill = document.createElement('span');
			pill.className = 'notion-wp-pill';
			pill.textContent = item;
			container.appendChild(pill);
		});

		return container;
	}

	/**
	 * Custom formatter for URL display
	 * Converts URLs into clickable links
	 *
	 * @param {Object} cell - Tabulator cell object
	 * @return {HTMLElement|string} Link element or empty string
	 */
	function urlFormatter(cell) {
		const value = cell.getValue();
		if (!value) {
			return '';
		}

		// Ensure value is a string
		const stringValue = typeof value === 'string' ? value : String(value);

		const link = document.createElement('a');
		link.href = stringValue;
		link.target = '_blank';
		link.rel = 'noopener noreferrer';
		link.className = 'notion-wp-url';
		link.textContent = '🔗 Link';
		link.title = stringValue;

		return link;
	}

	/**
	 * Initialize Tabulator table
	 *
	 * @param {HTMLElement} element - Table element
	 * @param {Object}      data    - Database data with columns and rows
	 * @param {Object}      options - Display options
	 */
	function initTabulator(element, data, options) {
		const { pageSize, showExport } = options;

		// Detect which columns contain array/tag data by sampling first row
		const firstRow = data.rows?.[0];
		const arrayColumns = new Set();

		if (firstRow) {
			Object.keys(firstRow.properties || {}).forEach((propName) => {
				const value = firstRow.properties[propName];
				// Check if value is an array or comma-separated string with multiple items
				if (
					Array.isArray(value) ||
					(typeof value === 'string' && value.includes(','))
				) {
					arrayColumns.add(propName);
				}
			});
		}

		// Schema provides column definitions - Tabulator will handle nested properties automatically
		const columns = (data.columns || []).map((col) => {
			// Copy column definition
			const column = { ...col };

			// For array-valued properties, add accessor to join values
			if (column.field && column.field.startsWith('properties.')) {
				const propName = column.field.replace('properties.', '');

				// Remove 'html' formatter since we're using custom formatters
				if (column.formatter === 'html') {
					delete column.formatter;
				}

				// Detect if this is a URL column
				const isUrlColumn =
					propName.toLowerCase() === 'url' ||
					propName.toLowerCase() === 'link' ||
					column.title?.toLowerCase() === 'url' ||
					column.title?.toLowerCase() === 'link';

				// Detect if this is a tag/array column (Tags, Grade Level, Subject Area, etc.)
				const isArrayColumn = arrayColumns.has(propName);

				// Add accessor to handle arrays and format values
				column.accessor = (value, rowData) => {
					// Get the actual value from the nested path
					const actualValue = rowData.properties?.[propName];

					// Always ensure we return a string for display
					if (actualValue === null || actualValue === undefined) {
						return '';
					}

					// Handle arrays - join with commas
					if (Array.isArray(actualValue)) {
						return actualValue.join(', ');
					}

					// Handle booleans
					if (typeof actualValue === 'boolean') {
						return actualValue ? '✓' : '';
					}

					// Convert everything else to string
					return String(actualValue);
				};

				// Apply appropriate formatter
				if (isUrlColumn) {
					column.formatter = urlFormatter;
					column.width = 100; // URLs don't need much width with icon display
				} else if (isArrayColumn) {
					column.formatter = pillFormatter;
					column.width = 250; // Give more space for pills
				}
			}

			// Ensure responsive priority for key columns
			if (column.field === 'notion_id' || column.field === 'title') {
				column.responsive = 0; // Always visible
			}

			return column;
		});

		// Initialize Tabulator with proper configuration
		const table = new Tabulator(element, {
			data: data.rows,
			columns,
			layout: 'fitColumns', // Fit columns to container width
			pagination: true,
			paginationSize: pageSize,
			paginationSizeSelector: [10, 25, 50, 100, 200],
			movableColumns: true,
			resizableColumns: true,
			responsiveLayout: false, // Disable responsive collapse - show horizontal scrollbar instead
			nestedFieldSeparator: '.', // Enable nested field access with dot notation
		});

		// Add export buttons if enabled
		if (showExport) {
			addExportButtons(element.parentElement, table);
		}

		// eslint-disable-next-line no-console
		console.log(
			'[DatabaseView] Tabulator initialized successfully with',
			data.rows.length,
			'rows and',
			columns.length,
			'columns'
		);
	}

	/**
	 * Add export buttons to the view
	 *
	 * @param {HTMLElement} container - Container element
	 * @param {Tabulator}   table     - Tabulator instance
	 */
	function addExportButtons(container, table) {
		const header = container.querySelector(
			'.notion-wp-database-view__header'
		);
		if (!header) {
			return;
		}

		const exportContainer = document.createElement('div');
		exportContainer.className = 'notion-wp-database-view__export';

		const csvButton = document.createElement('button');
		csvButton.className = 'notion-wp-database-view__export-btn';
		csvButton.textContent = 'Export CSV';
		csvButton.addEventListener('click', () => {
			table.download('csv', 'database-export.csv');
		});

		const jsonButton = document.createElement('button');
		jsonButton.className = 'notion-wp-database-view__export-btn';
		jsonButton.textContent = 'Export JSON';
		jsonButton.addEventListener('click', () => {
			table.download('json', 'database-export.json');
		});

		exportContainer.appendChild(csvButton);
		exportContainer.appendChild(jsonButton);
		header.appendChild(exportContainer);
	}

	/**
	 * Initialize all database views on the page
	 */
	function initAll() {
		const containers = document.querySelectorAll(
			'.notion-wp-database-view[data-database-id]'
		);
		// eslint-disable-next-line no-console
		console.log(
			'[DatabaseView] Found',
			containers.length,
			'database view(s)'
		);

		containers.forEach((container) => {
			initDatabaseView(container);
		});
	}

	// Initialize when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAll);
	} else {
		initAll();
	}
})();
