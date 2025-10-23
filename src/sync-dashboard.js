/**
 * Simple Sync Dashboard - Preact Component
 *
 * Clean, minimal implementation without WordPress bloat
 *
 * @package NotionSync
 * @since 0.3.0
 */

import { h, render } from 'preact';
import { useState, useEffect } from 'preact/hooks';

function SyncDashboard({ batchId: initialBatchId }) {
	const [batch, setBatch] = useState(null);
	const [batchId, setBatchId] = useState(initialBatchId);

	// Poll for status
	useEffect(() => {
		if (!batchId) return;

		const poll = async () => {
			try {
				const res = await fetch(
					`${window.notionSyncAdmin.restUrl}&batch_id=${batchId}`,
					{
						headers: {
							'X-WP-Nonce': window.notionSyncAdmin.restNonce,
						},
					}
				);
				const data = await res.json();
				if (data.batch) {
					setBatch(data.batch);
					// Stop polling when complete
					if (
						data.batch.status === 'completed' ||
						data.batch.status === 'failed'
					) {
						return;
					}
				}
			} catch (error) {
				console.error('Polling error:', error);
			}
		};

		// Poll every 3 seconds
		const interval = setInterval(poll, 3000);
		poll(); // Initial poll

		return () => clearInterval(interval);
	}, [batchId]);

	if (!batch) return null;

	const progress = batch.percentage || 0;
	const isComplete =
		batch.status === 'completed' || batch.status === 'failed';
	const isSuccess = batch.status === 'completed';

	return h(
		'div',
		{
			className: 'notion-sync-dashboard',
			style: {
				background: '#fff',
				border: '2px solid #2271b1',
				borderRadius: '4px',
				padding: '16px',
				margin: '20px 0',
				boxShadow: '0 2px 8px rgba(0,0,0,.1)',
			},
		},
		[
			// Header
			h(
				'div',
				{
					style: {
						display: 'flex',
						justifyContent: 'space-between',
						marginBottom: '16px',
					},
				},
				[
					h(
						'strong',
						null,
						isComplete
							? isSuccess
								? '✓ Sync Complete'
								: '✗ Sync Failed'
							: '⟳ Syncing...'
					),
					isComplete &&
						h(
							'button',
							{
								className: 'button',
								onClick: () => {
									setBatchId(null);
									setBatch(null);
								},
							},
							'Close'
						),
				]
			),

			// Progress bar
			h(
				'div',
				{
					style: {
						background: '#f0f0f1',
						height: '24px',
						borderRadius: '12px',
						overflow: 'hidden',
						marginBottom: '16px',
					},
				},
				h('div', {
					style: {
						background: 'linear-gradient(90deg, #2271b1, #135e96)',
						height: '100%',
						width: `${progress}%`,
						transition: 'width 0.3s',
					},
				})
			),

			// Stats
			h(
				'div',
				{
					style: {
						display: 'flex',
						gap: '16px',
						marginBottom: '12px',
					},
				},
				[
					h('div', null, [
						'Total: ',
						h('strong', null, batch.total || 0),
					]),
					h('div', { style: { color: '#00a32a' } }, [
						'✓ ',
						h('strong', null, batch.successful || 0),
					]),
					h('div', { style: { color: '#d63638' } }, [
						'✗ ',
						h('strong', null, batch.failed || 0),
					]),
				]
			),

			// Status message
			h(
				'div',
				{ style: { fontSize: '13px', color: '#646970' } },
				batch.status === 'processing'
					? 'Processing pages...'
					: isSuccess
						? 'All pages synced successfully!'
						: batch.status === 'failed'
							? 'Sync failed. Check error log.'
							: ''
			),
		]
	);
}

// Mount to DOM and expose global function
const container = document.getElementById('notion-sync-dashboard');
if (container) {
	// Listen for batch watch events
	window.startSyncDashboard = (batchId) => {
		render(h(SyncDashboard, { batchId }), container);
	};
}
