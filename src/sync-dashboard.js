/**
 * Simple Sync Dashboard - Preact Component
 *
 * Clean, minimal implementation without WordPress bloat
 *
 * @package
 * @since 0.3.0
 */

/**
 * External dependencies
 */
import { h, render } from 'preact';
import { useState, useEffect } from 'preact/hooks';

function SyncDashboard({ batchId: initialBatchId, totalPages = 0 }) {
	const [batch, setBatch] = useState(null);
	const [batchId, setBatchId] = useState(initialBatchId);
	const [isQueuing, setIsQueuing] = useState(true);
	const [queueWaitTime, setQueueWaitTime] = useState(0);
	const [mediaQueue, setMediaQueue] = useState(null);

	// Poll for status
	useEffect(() => {
		if (!batchId) {
			return;
		}

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
					setIsQueuing(false); // Got first response

					// Calculate queue wait time (time between scheduled and first processing)
					if (
						data.batch.started_at &&
						data.batch.status === 'queued'
					) {
						const startedTime = new Date(
							`${data.batch.started_at} UTC`
						).getTime();
						const now = Date.now();
						const waitSeconds = Math.floor(
							(now - startedTime) / 1000
						);
						setQueueWaitTime(waitSeconds);
					} else {
						setQueueWaitTime(0);
					}

					// Update media queue stats
					if (data.media_queue) {
						setMediaQueue(data.media_queue);
					}

					// Stop polling when complete
					if (
						data.batch.status === 'completed' ||
						data.batch.status === 'failed'
					) {
					}
				}
			} catch (error) {
				// Silently handle polling errors to avoid console spam
				// Error details available in network tab if needed
			}
		};

		// Poll every 2 seconds for more responsive updates
		const interval = setInterval(poll, 2000);
		poll(); // Initial poll

		return () => clearInterval(interval);
	}, [batchId]);

	// Show queuing state immediately
	if (!batch && isQueuing) {
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
				h(
					'div',
					{
						style: {
							display: 'flex',
							alignItems: 'center',
							gap: '12px',
						},
					},
					[
						h('div', {
							style: {
								width: '20px',
								height: '20px',
								border: '3px solid #f0f0f1',
								borderTopColor: '#2271b1',
								borderRadius: '50%',
								animation: 'spin 1s linear infinite',
							},
						}),
						h('strong', null, 'Queuing sync...'),
					]
				),
				h(
					'div',
					{
						style: {
							fontSize: '13px',
							color: '#646970',
							marginTop: '12px',
						},
					},
					`Preparing to sync ${totalPages} page${totalPages !== 1 ? 's' : ''}...`
				),
				// Add spinner animation
				h(
					'style',
					null,
					`
					@keyframes spin {
						to { transform: rotate(360deg); }
					}
				`
				),
			]
		);
	}

	if (!batch) {
		return null;
	}

	const progress = batch.percentage || 0;
	const isComplete =
		batch.status === 'completed' || batch.status === 'failed';
	const isSuccess = batch.status === 'completed';
	const pageStatuses = batch.page_statuses || {};
	const pageIds = batch.page_ids || [];
	const results = batch.results || {};

	// Helper to format status badge
	const getStatusBadge = (status) => {
		const badges = {
			queued: { icon: '○', color: '#8c8f94', label: 'Queued' },
			processing: { icon: '⟳', color: '#2271b1', label: 'Processing' },
			completed: { icon: '✓', color: '#00a32a', label: 'Completed' },
			failed: { icon: '✗', color: '#d63638', label: 'Failed' },
		};
		const badge = badges[status] || badges.queued;
		return h(
			'span',
			{
				style: {
					display: 'inline-flex',
					alignItems: 'center',
					gap: '4px',
					color: badge.color,
					fontWeight: '500',
				},
			},
			[badge.icon, ' ', badge.label]
		);
	};

	// Helper to format wait time
	const formatWaitTime = (seconds) => {
		if (seconds < 60) {
			return `${seconds}s`;
		}
		const mins = Math.floor(seconds / 60);
		const secs = seconds % 60;
		return `${mins}m ${secs}s`;
	};

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
						alignItems: 'center',
						marginBottom: '16px',
					},
				},
				[
					h('div', null, [
						h(
							'strong',
							{ style: { fontSize: '16px' } },
							(() => {
								if (isComplete) {
									return isSuccess
										? '✓ Sync Complete'
										: '✗ Sync Failed';
								}
								return '⟳ Syncing...';
							})()
						),
						// Show queue wait time
						queueWaitTime > 0 &&
							h(
								'div',
								{
									style: {
										fontSize: '12px',
										color: '#646970',
										marginTop: '4px',
									},
								},
								`Waiting in queue for ${formatWaitTime(queueWaitTime)}`
							),
					]),
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
						position: 'relative',
					},
				},
				[
					h('div', {
						style: {
							background:
								'linear-gradient(90deg, #2271b1, #135e96)',
							height: '100%',
							width: `${progress}%`,
							transition: 'width 0.3s',
						},
					}),
					h(
						'div',
						{
							style: {
								position: 'absolute',
								top: '50%',
								left: '50%',
								transform: 'translate(-50%, -50%)',
								fontSize: '12px',
								fontWeight: '600',
								color: progress > 50 ? '#fff' : '#2c3338',
							},
						},
						`${batch.processed || 0} / ${batch.total || 0} pages (${progress}%)`
					),
				]
			),

			// Summary Stats
			h('div', null, [
				h(
					'h4',
					{
						style: {
							margin: '0 0 8px 0',
							fontSize: '14px',
							fontWeight: '600',
						},
					},
					'Page Sync Progress'
				),
				h(
					'div',
					{
						style: {
							display: 'grid',
							gridTemplateColumns:
								'repeat(auto-fit, minmax(120px, 1fr))',
							gap: '12px',
							marginBottom: '16px',
						},
					},
					[
						h(
							'div',
							{
								style: {
									padding: '8px',
									background: '#f9fafb',
									borderRadius: '4px',
									textAlign: 'center',
								},
							},
							[
								h(
									'div',
									{
										style: {
											fontSize: '24px',
											fontWeight: '600',
										},
									},
									batch.total || 0
								),
								h(
									'div',
									{
										style: {
											fontSize: '12px',
											color: '#646970',
										},
									},
									'Total'
								),
							]
						),
						h(
							'div',
							{
								style: {
									padding: '8px',
									background: '#e7f5ec',
									borderRadius: '4px',
									textAlign: 'center',
								},
							},
							[
								h(
									'div',
									{
										style: {
											fontSize: '24px',
											fontWeight: '600',
											color: '#00a32a',
										},
									},
									batch.successful || 0
								),
								h(
									'div',
									{
										style: {
											fontSize: '12px',
											color: '#00a32a',
										},
									},
									'Successful'
								),
							]
						),
						h(
							'div',
							{
								style: {
									padding: '8px',
									background: '#fcf0f1',
									borderRadius: '4px',
									textAlign: 'center',
								},
							},
							[
								h(
									'div',
									{
										style: {
											fontSize: '24px',
											fontWeight: '600',
											color: '#d63638',
										},
									},
									batch.failed || 0
								),
								h(
									'div',
									{
										style: {
											fontSize: '12px',
											color: '#d63638',
										},
									},
									'Failed'
								),
							]
						),
					]
				),
			]),

			// Media Queue Stats (if available)
			mediaQueue &&
				mediaQueue.total > 0 &&
				h(
					'div',
					{
						style: {
							marginTop: '8px',
							padding: '12px',
							background: '#f9fafb',
							borderRadius: '4px',
							border: '1px solid #dcdcde',
						},
					},
					[
						h(
							'div',
							{
								style: {
									display: 'flex',
									alignItems: 'center',
									justifyContent: 'space-between',
									marginBottom: '8px',
								},
							},
							[
								h(
									'h4',
									{
										style: {
											margin: 0,
											fontSize: '13px',
											fontWeight: '600',
											color: '#2c3338',
										},
									},
									'📥 Image Downloads'
								),
								h(
									'span',
									{
										style: {
											fontSize: '12px',
											color: '#646970',
											fontWeight: '500',
										},
									},
									`${mediaQueue.total} total`
								),
							]
						),
						h(
							'div',
							{
								style: {
									display: 'grid',
									gridTemplateColumns:
										'repeat(auto-fit, minmax(80px, 1fr))',
									gap: '8px',
									fontSize: '12px',
								},
							},
							[
								h(
									'div',
									{
										style: {
											display: 'flex',
											alignItems: 'center',
											gap: '4px',
											color: '#8c8f94',
										},
									},
									[
										h('span', null, '○'),
										h('span', null, mediaQueue.pending),
										h(
											'span',
											{ style: { fontSize: '11px' } },
											'Queued'
										),
									]
								),
								h(
									'div',
									{
										style: {
											display: 'flex',
											alignItems: 'center',
											gap: '4px',
											color: '#2271b1',
										},
									},
									[
										h('span', null, '⟳'),
										h('span', null, mediaQueue.in_progress),
										h(
											'span',
											{ style: { fontSize: '11px' } },
											'Processing'
										),
									]
								),
								h(
									'div',
									{
										style: {
											display: 'flex',
											alignItems: 'center',
											gap: '4px',
											color: '#00a32a',
										},
									},
									[
										h('span', null, '✓'),
										h('span', null, mediaQueue.completed),
										h(
											'span',
											{ style: { fontSize: '11px' } },
											'Done'
										),
									]
								),
								mediaQueue.failed > 0 &&
									h(
										'div',
										{
											style: {
												display: 'flex',
												alignItems: 'center',
												gap: '4px',
												color: '#d63638',
											},
										},
										[
											h('span', null, '✗'),
											h('span', null, mediaQueue.failed),
											h(
												'span',
												{ style: { fontSize: '11px' } },
												'Failed'
											),
										]
									),
							]
						),
					]
				),

			// Per-Page Status Table
			pageIds.length > 0 &&
				h('div', { style: { marginTop: '16px' } }, [
					h(
						'h4',
						{
							style: {
								margin: '0 0 12px 0',
								fontSize: '14px',
								fontWeight: '600',
							},
						},
						'Page Status'
					),
					h(
						'div',
						{
							style: {
								maxHeight: '300px',
								overflow: 'auto',
								border: '1px solid #dcdcde',
								borderRadius: '4px',
							},
						},
						h(
							'table',
							{
								style: {
									width: '100%',
									borderCollapse: 'collapse',
									fontSize: '13px',
								},
							},
							[
								h(
									'thead',
									null,
									h('tr', null, [
										h(
											'th',
											{
												style: {
													textAlign: 'left',
													padding: '8px',
													background: '#f9fafb',
													borderBottom:
														'1px solid #dcdcde',
													fontWeight: '600',
												},
											},
											'Page ID'
										),
										h(
											'th',
											{
												style: {
													textAlign: 'left',
													padding: '8px',
													background: '#f9fafb',
													borderBottom:
														'1px solid #dcdcde',
													fontWeight: '600',
												},
											},
											'Status'
										),
										h(
											'th',
											{
												style: {
													textAlign: 'right',
													padding: '8px',
													background: '#f9fafb',
													borderBottom:
														'1px solid #dcdcde',
													fontWeight: '600',
												},
											},
											'Details'
										),
									])
								),
								h(
									'tbody',
									null,
									pageIds.map((pageId) => {
										const status =
											pageStatuses[pageId] || 'queued';
										const result = results[pageId];
										const isCurrentPage =
											batch.current_page_id === pageId;

										return h('tr', { key: pageId }, [
											h(
												'td',
												{
													style: {
														padding: '8px',
														borderBottom:
															'1px solid #f0f0f1',
														fontFamily: 'monospace',
														fontSize: '12px',
													},
												},
												[
													pageId.substring(0, 8),
													'...',
													isCurrentPage &&
														h(
															'span',
															{
																style: {
																	marginLeft:
																		'8px',
																	padding:
																		'2px 6px',
																	background:
																		'#2271b1',
																	color: '#fff',
																	borderRadius:
																		'3px',
																	fontSize:
																		'10px',
																	fontWeight:
																		'600',
																},
															},
															'CURRENT'
														),
												]
											),
											h(
												'td',
												{
													style: {
														padding: '8px',
														borderBottom:
															'1px solid #f0f0f1',
													},
												},
												getStatusBadge(status)
											),
											h(
												'td',
												{
													style: {
														padding: '8px',
														borderBottom:
															'1px solid #f0f0f1',
														textAlign: 'right',
														fontSize: '12px',
														color: '#646970',
													},
												},
												(() => {
													if (!result) {
														return '—';
													}
													if (result.success) {
														return result.duration
															? `${result.duration.toFixed(2)}s`
															: 'Success';
													}
													return (
														result.error || 'Failed'
													);
												})()
											),
										]);
									})
								),
							]
						)
					),
				]),

			// Status message
			h(
				'div',
				{
					style: {
						fontSize: '13px',
						color: '#646970',
						marginTop: '12px',
						fontStyle: 'italic',
					},
				},
				(() => {
					if (batch.status === 'queued') {
						return 'Waiting for queue to start processing...';
					}
					if (batch.status === 'processing') {
						if (batch.current_page_id) {
							return `Processing page ${batch.current_page_id.substring(0, 8)}...`;
						}
						return 'Processing pages...';
					}
					if (isSuccess) {
						return 'All pages synced successfully!';
					}
					if (batch.status === 'failed') {
						return 'Sync failed. Check error log for details.';
					}
					return '';
				})()
			),
		]
	);
}

// Mount to DOM and expose global function
const container = document.getElementById('notion-sync-dashboard');
if (container) {
	// Listen for batch watch events
	window.startSyncDashboard = (batchId, totalPages = 0) => {
		render(h(SyncDashboard, { batchId, totalPages }), container);
	};

	// Check for active syncs on page load
	const checkForActiveSyncs = async () => {
		try {
			const res = await fetch(`${window.notionSyncAdmin.restUrl}`, {
				headers: {
					'X-WP-Nonce': window.notionSyncAdmin.restNonce,
				},
			});
			const data = await res.json();

			// If there's an active batch, show it
			if (
				data.batch &&
				(data.batch.status === 'processing' ||
					data.batch.status === 'queued')
			) {
				window.startSyncDashboard(
					data.batch.batch_id,
					data.batch.total
				);
			}
		} catch (error) {
			// Silently fail - don't block page load
			// No active syncs found
		}
	};

	// Check on page load
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', checkForActiveSyncs);
	} else {
		checkForActiveSyncs();
	}
}
