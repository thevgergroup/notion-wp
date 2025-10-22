/**
 * Notion Link Block
 *
 * Gutenberg block for rendering Notion links with dynamic content.
 * Uses WordPress globals (wp.blocks, wp.element, wp.components, etc.)
 * No build step required for initial implementation.
 */

(function (wp) {
	const { registerBlockType } = wp.blocks;
	const { createElement: el, useState, useEffect } = wp.element;
	const { InspectorControls } = wp.blockEditor;
	const { PanelBody, ToggleControl, TextControl } = wp.components;
	const { __ } = wp.i18n;
	const { apiFetch } = wp;

	/**
	 * Register the notion-sync/notion-link block
	 */
	registerBlockType('notion-sync/notion-link', {
		title: __('Notion Link', 'notion-wp'),
		description: __('Link to a Notion page or database with always-current title', 'notion-wp'),
		icon: 'admin-links',
		category: 'embed',
		attributes: {
			notionId: {
				type: 'string',
				default: '',
			},
			showIcon: {
				type: 'boolean',
				default: true,
			},
			openInNewTab: {
				type: 'boolean',
				default: false,
			},
			customText: {
				type: 'string',
				default: '',
			},
		},
		edit: function (props) {
			const { attributes, setAttributes } = props;
			const { notionId, showIcon, openInNewTab, customText } = attributes;

			// State for link data from API
			const [linkData, setLinkData] = useState(null);
			const [loading, setLoading] = useState(false);
			const [error, setError] = useState(null);

			// Fetch link data when notionId changes
			useEffect(() => {
				if (!notionId) {
					setLinkData(null);
					return;
				}

				setLoading(true);
				setError(null);

				apiFetch({
					path: `/notion-sync/v1/links/${notionId}`,
				})
					.then((data) => {
						setLinkData(data);
						setLoading(false);
					})
					.catch((err) => {
						setError(err.message || 'Failed to fetch link data');
						setLoading(false);
					});
			}, [notionId]);

			// Render editor view
			return el(
				'div',
				{ className: 'wp-block-notion-sync-notion-link' },
				[
					// Inspector Controls (sidebar)
					el(
						InspectorControls,
						{ key: 'inspector' },
						el(
							PanelBody,
							{ title: __('Link Settings', 'notion-wp') },
							[
								el(TextControl, {
									key: 'notion-id',
									label: __('Notion ID', 'notion-wp'),
									value: notionId,
									onChange: (value) => setAttributes({ notionId: value }),
									help: __(
										'Enter the Notion page or database ID',
										'notion-wp'
									),
								}),
								el(ToggleControl, {
									key: 'show-icon',
									label: __('Show Icon', 'notion-wp'),
									checked: showIcon,
									onChange: (value) => setAttributes({ showIcon: value }),
								}),
								el(ToggleControl, {
									key: 'open-new-tab',
									label: __('Open in New Tab', 'notion-wp'),
									checked: openInNewTab,
									onChange: (value) => setAttributes({ openInNewTab: value }),
								}),
								el(TextControl, {
									key: 'custom-text',
									label: __('Custom Link Text (optional)', 'notion-wp'),
									value: customText,
									onChange: (value) => setAttributes({ customText: value }),
									help: __(
										'Leave empty to use the current Notion title',
										'notion-wp'
									),
								}),
							]
						)
					),

					// Block content
					el('div', { key: 'content', className: 'notion-link-block-editor' }, [
						// Loading state
						loading &&
							el(
								'p',
								{ key: 'loading' },
								el('span', { className: 'spinner is-active' }),
								' Loading...'
							),

						// Error state
						!loading &&
							error &&
							el(
								'div',
								{ key: 'error', className: 'notice notice-error' },
								el('p', {}, '⚠️ ' + error)
							),

						// Empty state
						!loading &&
							!error &&
							!notionId &&
							el(
								'div',
								{ key: 'empty', className: 'notice notice-info' },
								el('p', {}, __('Enter a Notion ID in the sidebar →', 'notion-wp'))
							),

						// Link preview
						!loading &&
							!error &&
							linkData &&
							el('div', { key: 'preview', className: 'notion-link-preview' }, [
								el(
									'a',
									{
										key: 'link',
										href: linkData.url,
										className: 'notion-link notion-link--' + linkData.type,
										target: openInNewTab ? '_blank' : '_self',
										rel: openInNewTab ? 'noopener noreferrer' : '',
										onClick: (e) => e.preventDefault(), // Prevent clicking in editor
									},
									[
										showIcon &&
											el(
												'span',
												{ key: 'icon' },
												linkData.type === 'database' ? '📊 ' : '📄 '
											),
										el(
											'span',
											{ key: 'text' },
											customText || linkData.title
										),
									]
								),
								el(
									'small',
									{ key: 'meta', style: { display: 'block', marginTop: '5px' } },
									`Type: ${linkData.type} • Status: ${linkData.sync_status} • Slug: ${linkData.slug}`
								),
							]),
					]),
				]
			);
		},

		save: function () {
			// Return null because this is a dynamic block (rendered in PHP)
			return null;
		},
	});
})(window.wp);
