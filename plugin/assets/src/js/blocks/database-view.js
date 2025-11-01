/**
 * Database View Block
 *
 * Gutenberg block for rendering Notion database views.
 * Uses WordPress globals (wp.blocks, wp.element, wp.components, etc.)
 * No build step required for initial implementation.
 *
 * @param {Object} wp - WordPress global object
 */

(function (wp) {
	const { registerBlockType } = wp.blocks;
	const { createElement: el } = wp.element;
	const { InspectorControls } = wp.blockEditor;
	const { PanelBody, SelectControl, ToggleControl, RangeControl } =
		wp.components;
	const { __ } = wp.i18n;

	/**
	 * DatabaseViewEdit Component - Edit interface for the Database View block
	 *
	 * @param {Object} props - Component props
	 * @return {Object} React element
	 */
	function DatabaseViewEdit(props) {
		const { attributes, setAttributes } = props;
		const { databaseId, viewType, showFilters, showExport, pageSize } =
			attributes;

		// Get databases from localized script data
		const databases = window.notionWpDatabaseView?.databases || [];

		// Prepare database options for select control
		const databaseOptions = [
			{ label: __('Select a database…', 'notion-wp'), value: 0 },
			...databases.map((db) => ({
				label: db.title || __('(no title)', 'notion-wp'),
				value: db.id,
			})),
		];

		// Get selected database info
		const selectedDatabase = databases.find((db) => db.id === databaseId);

		// Render editor view
		return el('div', { className: 'wp-block-notion-wp-database-view' }, [
			// Inspector Controls (sidebar)
			el(InspectorControls, { key: 'inspector' }, [
				el(
					PanelBody,
					{
						key: 'database',
						title: __('Database Settings', 'notion-wp'),
						initialOpen: true,
					},
					[
						el(SelectControl, {
							key: 'db-select',
							label: __('Database', 'notion-wp'),
							value: databaseId,
							options: databaseOptions,
							onChange: (value) =>
								setAttributes({
									databaseId: parseInt(value, 10),
								}),
							help: __(
								'Select the Notion database to display.',
								'notion-wp'
							),
						}),
						el(SelectControl, {
							key: 'view-type',
							label: __('View Type', 'notion-wp'),
							value: viewType,
							options: [
								{
									label: __('Table', 'notion-wp'),
									value: 'table',
								},
								{
									label: __(
										'Board (Coming Soon)',
										'notion-wp'
									),
									value: 'board',
									disabled: true,
								},
								{
									label: __(
										'Gallery (Coming Soon)',
										'notion-wp'
									),
									value: 'gallery',
									disabled: true,
								},
								{
									label: __(
										'Timeline (Coming Soon)',
										'notion-wp'
									),
									value: 'timeline',
									disabled: true,
								},
							],
							onChange: (value) =>
								setAttributes({ viewType: value }),
							help: __(
								'Choose how to display the database.',
								'notion-wp'
							),
						}),
					]
				),
				el(
					PanelBody,
					{
						key: 'display',
						title: __('Display Options', 'notion-wp'),
						initialOpen: false,
					},
					[
						el(ToggleControl, {
							key: 'filters',
							label: __('Show Filters', 'notion-wp'),
							checked: showFilters,
							onChange: (value) =>
								setAttributes({ showFilters: value }),
							help: __(
								'Allow users to filter table columns.',
								'notion-wp'
							),
						}),
						el(ToggleControl, {
							key: 'export',
							label: __('Show Export Buttons', 'notion-wp'),
							checked: showExport,
							onChange: (value) =>
								setAttributes({ showExport: value }),
							help: __(
								'Allow users to export data as CSV or JSON.',
								'notion-wp'
							),
						}),
						el(RangeControl, {
							key: 'page-size',
							label: __('Page Size', 'notion-wp'),
							value: pageSize,
							onChange: (value) =>
								setAttributes({ pageSize: value }),
							min: 10,
							max: 200,
							step: 10,
							help: __(
								'Number of rows to display per page.',
								'notion-wp'
							),
						}),
					]
				),
			]),

			// Block content
			el(
				'div',
				{
					key: 'content',
					className: 'notion-wp-database-view-editor',
				},
				!databaseId || databaseId === 0
					? // Empty state - no database selected
						el(
							'div',
							{
								key: 'empty',
								className:
									'notion-wp-database-view-placeholder',
							},
							[
								el(
									'p',
									{ key: 'message' },
									__(
										'Select a Notion database to display from the sidebar →',
										'notion-wp'
									)
								),
							]
						)
					: // Database selected - show preview
						[
							el(
								'div',
								{
									key: 'header',
									className: 'notion-wp-database-view-header',
								},
								[
									el(
										'div',
										{
											key: 'icon',
											className:
												'notion-wp-database-icon',
										},
										'📊'
									),
									el(
										'div',
										{
											key: 'info',
											className:
												'notion-wp-database-info',
										},
										[
											el(
												'h3',
												{ key: 'title' },
												selectedDatabase
													? selectedDatabase.title
													: __(
															'Unknown Database',
															'notion-wp'
														)
											),
											el(
												'p',
												{ key: 'desc' },
												selectedDatabase &&
													selectedDatabase.rowCount
													? `${
															selectedDatabase.rowCount
														} rows • This database will be displayed as an interactive table on the frontend.`
													: __(
															'This database will be displayed as an interactive table on the frontend.',
															'notion-wp'
														)
											),
										]
									),
								]
							),
							el(
								'div',
								{
									key: 'settings',
									className:
										'notion-wp-database-view-settings',
								},
								[
									el('ul', { key: 'list' }, [
										el('li', { key: 'view' }, [
											el(
												'strong',
												{},
												__('View Type: ', 'notion-wp')
											),
											viewType,
										]),
										el('li', { key: 'size' }, [
											el(
												'strong',
												{},
												__('Page Size: ', 'notion-wp')
											),
											`${pageSize} ${__('rows', 'notion-wp')}`,
										]),
										el('li', { key: 'filters' }, [
											el(
												'strong',
												{},
												__('Filters: ', 'notion-wp')
											),
											showFilters
												? __('Enabled', 'notion-wp')
												: __('Disabled', 'notion-wp'),
										]),
										el('li', { key: 'export' }, [
											el(
												'strong',
												{},
												__('Export: ', 'notion-wp')
											),
											showExport
												? __('Enabled', 'notion-wp')
												: __('Disabled', 'notion-wp'),
										]),
									]),
								]
							),
						]
			),
		]);
	}

	/**
	 * Register the notion-wp/database-view block
	 */
	registerBlockType('notion-wp/database-view', {
		title: __('Notion Database View', 'notion-wp'),
		description: __(
			'Display an embedded Notion database with interactive filtering, sorting, and pagination.',
			'notion-wp'
		),
		icon: 'database',
		category: 'embed',
		attributes: {
			databaseId: {
				type: 'number',
				default: 0,
			},
			viewType: {
				type: 'string',
				default: 'table',
			},
			showFilters: {
				type: 'boolean',
				default: true,
			},
			showExport: {
				type: 'boolean',
				default: true,
			},
			pageSize: {
				type: 'number',
				default: 50,
			},
		},
		edit: DatabaseViewEdit,

		save() {
			// Return null because this is a dynamic block (rendered in PHP)
			return null;
		},
	});
})(window.wp);
