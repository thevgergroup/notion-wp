/**
 * Notion Image Block - Client-side Registration
 *
 * Registers the notion-sync/notion-image block for the WordPress block editor.
 * This is a dynamic block - rendering is handled server-side via PHP render_callback.
 *
 * @param {Object} blocks      WordPress blocks API
 * @param {Object} element     WordPress element API (React createElement)
 * @param {Object} blockEditor WordPress block editor components
 * @param {Object} i18n        WordPress internationalization functions
 * @package
 */

(function (blocks, element, blockEditor, i18n) {
	'use strict';

	const el = element.createElement;
	const { __ } = i18n;
	const { registerBlockType } = blocks;

	/**
	 * Register the Notion Image dynamic block.
	 *
	 * This registration makes the block available in the editor.
	 * Actual rendering is handled server-side by NotionImageBlock::render().
	 */
	registerBlockType('notion-sync/notion-image', {
		title: __('Notion Image', 'notion-wp'),
		description: __(
			'Display an image from Notion with automatic media library management',
			'notion-wp'
		),
		icon: 'format-image',
		category: 'media',
		keywords: [
			__('notion', 'notion-wp'),
			__('image', 'notion-wp'),
			__('picture', 'notion-wp'),
		],

		/**
		 * Block attributes.
		 *
		 * Must match the attributes used in PHP registration and render_callback.
		 */
		attributes: {
			/**
			 * Notion block ID - unique identifier from Notion API.
			 */
			blockId: {
				type: 'string',
				default: '',
			},

			/**
			 * Original Notion image URL (typically time-limited S3 URL).
			 */
			notionUrl: {
				type: 'string',
				default: '',
			},

			/**
			 * Image caption text from Notion.
			 */
			caption: {
				type: 'string',
				default: '',
			},

			/**
			 * Alt text for accessibility.
			 */
			altText: {
				type: 'string',
				default: '',
			},
		},

		/**
		 * Supports configuration.
		 *
		 * Disable features since this is a server-rendered block.
		 */
		supports: {
			align: true,
			html: false,
			customClassName: false,
		},

		/**
		 * Edit function - displays placeholder in block editor.
		 *
		 * Shows a simple placeholder with status information.
		 * Actual rendering happens server-side.
		 *
		 * @param {Object} props Block properties.
		 * @return {Element} React element.
		 */
		edit(props) {
			const { attributes } = props;
			const className = props.className || '';

			const caption = attributes.caption || '';
			const blockId = attributes.blockId || '';

			// Build informative placeholder content.
			const placeholderContent = [
				el(
					'div',
					{
						key: 'icon',
						className: 'notion-image-placeholder__icon',
						style: {
							fontSize: '48px',
							textAlign: 'center',
							marginBottom: '16px',
							color: '#555d66',
						},
					},
					'🖼️'
				),
				el(
					'p',
					{
						key: 'title',
						style: {
							margin: '0 0 8px 0',
							fontWeight: '600',
							fontSize: '14px',
							textAlign: 'center',
						},
					},
					__('Notion Image', 'notion-wp')
				),
				el(
					'p',
					{
						key: 'description',
						style: {
							margin: '0 0 12px 0',
							fontSize: '12px',
							color: '#555d66',
							textAlign: 'center',
						},
					},
					__(
						'Rendered server-side with automatic media sync',
						'notion-wp'
					)
				),
			];

			// Add caption preview if available.
			if (caption) {
				placeholderContent.push(
					el(
						'p',
						{
							key: 'caption',
							style: {
								margin: '0',
								fontSize: '12px',
								fontStyle: 'italic',
								color: '#777',
								textAlign: 'center',
							},
						},
						`${__('Caption:', 'notion-wp')} ${caption}`
					)
				);
			}

			// Add block ID info for debugging.
			if (blockId) {
				placeholderContent.push(
					el(
						'p',
						{
							key: 'blockId',
							style: {
								margin: '8px 0 0 0',
								fontSize: '11px',
								color: '#999',
								fontFamily: 'monospace',
								textAlign: 'center',
							},
						},
						`Block ID: ${blockId}`
					)
				);
			}

			return el(
				'div',
				{
					className: `${className} notion-image-placeholder`,
					style: {
						border: '2px dashed #ccc',
						borderRadius: '4px',
						padding: '24px',
						backgroundColor: '#f8f9fa',
						minHeight: '150px',
						display: 'flex',
						flexDirection: 'column',
						justifyContent: 'center',
					},
				},
				placeholderContent
			);
		},

		/**
		 * Save function - returns null for server-side rendering.
		 *
		 * The block is rendered dynamically via PHP render_callback,
		 * so we don't save any static HTML to the database.
		 *
		 * @return {null} Null to indicate server-side rendering.
		 */
		save() {
			// Dynamic block - rendering handled by PHP.
			return null;
		},
	});
})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.i18n);
