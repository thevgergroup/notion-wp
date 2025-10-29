/**
 * Admin Navigation - Menu Sync Functionality
 *
 * Handles manual menu sync button functionality.
 *
 * @package
 */

/**
 * Initialize menu sync functionality
 */
export function initNavigationSync() {
	const syncButton = document.getElementById('notion-sync-menu-button');
	if (!syncButton) {
		return;
	}

	syncButton.addEventListener('click', handleMenuSync);
}

/**
 * Handle manual menu sync
 *
 * @param {Event} event Click event
 */
async function handleMenuSync(event) {
	const button = event.target;
	const { nonce } = button.dataset;
	const messageContainer = document.getElementById(
		'notion-menu-sync-messages'
	);

	// Disable button and show loading state
	button.disabled = true;
	button.classList.add('updating-message');
	const originalText = button.textContent;
	button.textContent = 'Syncing...';

	// Clear previous messages
	messageContainer.innerHTML = '';

	try {
		const response = await fetch(
			window.ajaxurl || '/wp-admin/admin-ajax.php',
			{
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams({
					action: 'notion_sync_menu_now',
					nonce,
				}),
			}
		);

		const data = await response.json();

		if (data.success) {
			showMessage(messageContainer, 'success', data.data.message);
		} else {
			showMessage(
				messageContainer,
				'error',
				data.data?.message || 'Menu sync failed. Please try again.'
			);
		}
	} catch (error) {
		showMessage(messageContainer, 'error', `Error: ${error.message}`);
	} finally {
		// Restore button state
		button.disabled = false;
		button.classList.remove('updating-message');
		button.textContent = originalText;
	}
}

/**
 * Show message in container
 *
 * @param {HTMLElement} container Message container element
 * @param {string}      type      Message type (success, error, warning, info)
 * @param {string}      message   Message text (can contain safe HTML from server)
 */
function showMessage(container, type, message) {
	const notice = document.createElement('div');
	notice.className = `notice notice-${type} inline`;
	// Message comes from wp_send_json_success which is already escaped on the server
	notice.innerHTML = `<p>${message}</p>`;
	container.appendChild(notice);
}
