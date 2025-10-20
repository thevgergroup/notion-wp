/**
 * Connection and Authentication Module
 *
 * Handles connection form submission, token validation, and disconnect functionality.
 *
 * @package
 */

/**
 * Internal dependencies
 */
import {
	showLoadingState,
	showInlineError,
	clearInlineError,
} from './admin-ui.js';

/**
 * Initialize connection form handling
 */
export function initConnectionForm() {
	const connectionForm = document.getElementById(
		'notion-sync-connection-form'
	);
	const disconnectButton = document.getElementById('notion-sync-disconnect');

	if (connectionForm) {
		handleConnectionForm(connectionForm);
	}

	if (disconnectButton) {
		handleDisconnectButton(disconnectButton);
	}
}

/**
 * Handle connection form submission
 *
 * @param {HTMLFormElement} form - The connection form element
 */
function handleConnectionForm(form) {
	const tokenInput = form.querySelector('#notion_token');
	const submitButton = form.querySelector('button[type="submit"]');

	if (!tokenInput || !submitButton) {
		return;
	}

	// Validate token format on input (basic check)
	tokenInput.addEventListener('input', function () {
		validateTokenFormat(this, submitButton);
	});

	// Handle form submission
	form.addEventListener('submit', (event) => {
		const token = tokenInput.value.trim();

		// Basic validation
		if (!isValidTokenFormat(token)) {
			event.preventDefault();
			showInlineError(
				tokenInput,
				'Token appears to be invalid. It should start with "secret_" or "ntn_" and contain alphanumeric characters.'
			);
			return;
		}

		// Show loading state
		showLoadingState(submitButton);

		// Form will submit naturally - server handles the rest
	});
}

/**
 * Validate token format and update button state
 *
 * @param {HTMLInputElement}  input  - Token input element
 * @param {HTMLButtonElement} button - Submit button element
 */
function validateTokenFormat(input, button) {
	const token = input.value.trim();
	const isValid = isValidTokenFormat(token);

	// Update button state
	button.disabled = !isValid;

	// Clear any previous inline errors
	clearInlineError(input);

	// Update ARIA attributes
	input.setAttribute('aria-invalid', !isValid);
}

/**
 * Check if token format appears valid (basic check)
 *
 * @param {string} token - The token to validate
 * @return {boolean} True if format appears valid
 */
function isValidTokenFormat(token) {
	if (!token || token.length < 10) {
		return false;
	}

	// Notion integration tokens start with "secret_" or "ntn_"
	if (!token.startsWith('secret_') && !token.startsWith('ntn_')) {
		return false;
	}

	// Should contain only alphanumeric and underscores after prefix
	const prefix = token.startsWith('secret_') ? 7 : 4;
	const tokenBody = token.substring(prefix);
	return /^[a-zA-Z0-9_]+$/.test(tokenBody);
}

/**
 * Handle disconnect button
 *
 * @param {HTMLButtonElement} button - Disconnect button element
 */
function handleDisconnectButton(button) {
	button.addEventListener('click', (event) => {
		// Show confirmation dialog
		const confirmed = confirm(
			'Are you sure you want to disconnect from Notion?\n\nYour settings will be removed and you will need to reconnect.'
		);

		if (!confirmed) {
			event.preventDefault();
			return;
		}

		// Show loading state
		showLoadingState(button);

		// Form will submit naturally
	});
}
