/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
	useBlockProps,
	BlockControls,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	ToggleControl,
	Placeholder,
	ToolbarGroup,
	ToolbarButton,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { database as databaseIcon } from '@wordpress/icons';

/**
 * Edit component for the database-view block.
 *
 * @param {Object}   props               Component props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to update block attributes.
 *
 * @return {Element} The edit component.
 */
export default function Edit({ attributes, setAttributes }) {
	const { databaseId, viewType, showFilters, showExport } = attributes;

	const blockProps = useBlockProps({
		className: 'notion-wp-database-view-editor',
	});

	// Get database posts from localized data
	const databases = window.notionWpDatabaseView?.databases || [];

	// Find selected database
	const selectedDatabase = databases.find((db) => db.id === databaseId);

	// Build options for SelectControl
	const databaseOptions = [
		{ value: 0, label: __('Select a database…', 'notion-wp') },
		...databases.map((db) => ({
			value: db.id,
			label: `${db.title} (${db.rowCount} rows)`,
		})),
	];

	// View type options - mark non-table views as coming soon
	const viewTypeOptions = [
		{ value: 'table', label: __('Table', 'notion-wp') },
		{
			value: 'board',
			label: __('Board (Coming Soon)', 'notion-wp'),
			disabled: true,
		},
		{
			value: 'gallery',
			label: __('Gallery (Coming Soon)', 'notion-wp'),
			disabled: true,
		},
		{
			value: 'timeline',
			label: __('Timeline (Coming Soon)', 'notion-wp'),
			disabled: true,
		},
		{
			value: 'calendar',
			label: __('Calendar (Coming Soon)', 'notion-wp'),
			disabled: true,
		},
	];

	// Show placeholder if no database is selected
	if (!databaseId) {
		return (
			<div {...blockProps}>
				<Placeholder
					icon={databaseIcon}
					label={__('Notion Database View', 'notion-wp')}
					instructions={__(
						'Select a Notion database to display.',
						'notion-wp'
					)}
				>
					<SelectControl
						label={__('Database', 'notion-wp')}
						value={databaseId}
						options={databaseOptions}
						onChange={(value) =>
							setAttributes({ databaseId: parseInt(value, 10) })
						}
					/>
				</Placeholder>
			</div>
		);
	}

	return (
		<>
			<BlockControls>
				<ToolbarGroup>
					<ToolbarButton
						icon={databaseIcon}
						label={__('Change Database', 'notion-wp')}
						onClick={() => setAttributes({ databaseId: 0 })}
					/>
				</ToolbarGroup>
			</BlockControls>

			<InspectorControls>
				<PanelBody
					title={__('Database Settings', 'notion-wp')}
					initialOpen={true}
				>
					<SelectControl
						label={__('Database', 'notion-wp')}
						value={databaseId}
						options={databaseOptions}
						onChange={(value) =>
							setAttributes({ databaseId: parseInt(value, 10) })
						}
						help={
							selectedDatabase
								? `${selectedDatabase.rowCount} ${__('rows', 'notion-wp')}`
								: ''
						}
					/>

					<SelectControl
						label={__('View Type', 'notion-wp')}
						value={viewType}
						options={viewTypeOptions}
						onChange={(value) => setAttributes({ viewType: value })}
						help={__(
							'Only Table view is available in this release.',
							'notion-wp'
						)}
					/>
				</PanelBody>

				<PanelBody
					title={__('Display Options', 'notion-wp')}
					initialOpen={false}
				>
					<ToggleControl
						label={__('Show Filters', 'notion-wp')}
						checked={showFilters}
						onChange={(value) =>
							setAttributes({ showFilters: value })
						}
						help={__(
							'Allow users to filter the data.',
							'notion-wp'
						)}
					/>

					<ToggleControl
						label={__('Show Export', 'notion-wp')}
						checked={showExport}
						onChange={(value) =>
							setAttributes({ showExport: value })
						}
						help={__(
							'Allow users to export data to CSV.',
							'notion-wp'
						)}
					/>
				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
				<ServerSideRender
					block="notion-wp/database-view"
					attributes={attributes}
					LoadingResponsePlaceholder={() => (
						<Placeholder
							icon={databaseIcon}
							label={__('Loading Database…', 'notion-wp')}
						/>
					)}
					ErrorResponsePlaceholder={({ response }) => (
						<Placeholder
							icon={databaseIcon}
							label={__('Error Loading Database', 'notion-wp')}
						>
							<p>
								{response.message ||
									__('Unknown error', 'notion-wp')}
							</p>
						</Placeholder>
					)}
				/>
			</div>
		</>
	);
}
