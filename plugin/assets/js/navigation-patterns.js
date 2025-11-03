/**
 * Collapsible Navigation Pattern Scripts
 *
 * Provides toggle functionality for Notion hierarchical navigation.
 *
 * @package
 * @since 1.0.0
 */

(function () {
	'use strict';

	/**
	 * Initialize collapsible navigation functionality.
	 */
	function initCollapsibleNavigation() {
		const navContainers = document.querySelectorAll(
			'.notion-nav-collapsible'
		);

		navContainers.forEach((container) => {
			// Find all toggle buttons
			const toggleButtons =
				container.querySelectorAll('.notion-nav-toggle');

			toggleButtons.forEach((button) => {
				button.addEventListener('click', (e) => {
					e.preventDefault();
					e.stopPropagation();

					const listItem = button.closest('li.has-children');
					const isExpanded =
						button.getAttribute('aria-expanded') === 'true';

					if (isExpanded) {
						// Collapse
						button.setAttribute('aria-expanded', 'false');
						button.setAttribute(
							'aria-label',
							button
								.getAttribute('aria-label')
								.replace('Collapse', 'Expand')
						);
						listItem.classList.remove('is-expanded');
					} else {
						// Expand
						button.setAttribute('aria-expanded', 'true');
						button.setAttribute(
							'aria-label',
							button
								.getAttribute('aria-label')
								.replace('Expand', 'Collapse')
						);
						listItem.classList.add('is-expanded');
					}
				});
			});
		});
	}

	// Initialize when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener(
			'DOMContentLoaded',
			initCollapsibleNavigation
		);
	} else {
		initCollapsibleNavigation();
	}

	// Re-initialize if content is dynamically loaded (e.g., AJAX)
	document.addEventListener('notion-nav-refresh', initCollapsibleNavigation);
})();
