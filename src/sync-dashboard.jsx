/**
 * Sync Dashboard - Preact Component with JSX
 *
 * Displays real-time sync progress with per-page status and image queue tracking.
 *
 * @package
 * @since 0.3.0
 */

/* eslint-disable react/prop-types */

/**
 * External dependencies
 */
// eslint-disable-next-line import/no-unresolved
import { render } from 'preact';
// eslint-disable-next-line import/no-unresolved
import { useState, useEffect } from 'preact/hooks';

function SyncDashboard({ batchId: initialBatchId, totalPages = 0 }) {
	const [batch, setBatch] = useState(null);
	const [batchId, setBatchId] = useState(initialBatchId);
	const [isQueuing, setIsQueuing] = useState(true);
	const [queueWaitTime, setQueueWaitTime] = useState(0);
	const [mediaQueue, setMediaQueue] = useState(null);

	// Poll for status every 2 seconds
	useEffect(() => {
		if (!batchId) {
			return;
		}

		const poll = async () => {
			try {
				const res = await fetch(
					`${window.notionSyncAdmin.restUrl}?batch_id=${batchId}`,
					{
						headers: {
							'X-WP-Nonce': window.notionSyncAdmin.restNonce,
						},
					}
				);
				const data = await res.json();

				if (data.batch) {
					setBatch(data.batch);
					setIsQueuing(false);

					// Calculate queue wait time
					if (
						data.batch.started_at &&
						data.batch.status === 'queued'
					) {
						const startedTime = new Date(
							`${data.batch.started_at} UTC`
						).getTime();
						const waitSeconds = Math.floor(
							(Date.now() - startedTime) / 1000
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
				// Silently handle polling errors
			}
		};

		const interval = setInterval(poll, 2000);
		poll();
		return () => clearInterval(interval);
	}, [batchId]);

	// Helper: Format wait time
	const formatWaitTime = (seconds) => {
		if (seconds < 60) {
			return `${seconds}s`;
		}
		const mins = Math.floor(seconds / 60);
		const secs = seconds % 60;
		return `${mins}m ${secs}s`;
	};

	// Helper: Get status badge
	const StatusBadge = ({ status }) => {
		const badges = {
			queued: { icon: '○', color: '#8c8f94', label: 'Queued' },
			processing: { icon: '⟳', color: '#2271b1', label: 'Processing' },
			completed: { icon: '✓', color: '#00a32a', label: 'Completed' },
			failed: { icon: '✗', color: '#d63638', label: 'Failed' },
		};
		const badge = badges[status] || badges.queued;

		return (
			<span
				style={{
					display: 'inline-flex',
					alignItems: 'center',
					gap: '4px',
					color: badge.color,
					fontWeight: '500',
				}}
			>
				{badge.icon} {badge.label}
			</span>
		);
	};

	// Show queuing state
	if (!batch && isQueuing) {
		return (
			<div
				className="notion-sync-dashboard"
				style={{
					background: '#fff',
					border: '2px solid #2271b1',
					borderRadius: '4px',
					padding: '16px',
					margin: '20px 0',
					boxShadow: '0 2px 8px rgba(0,0,0,.1)',
				}}
			>
				<div
					style={{
						display: 'flex',
						alignItems: 'center',
						gap: '12px',
					}}
				>
					<div
						style={{
							width: '20px',
							height: '20px',
							border: '3px solid #f0f0f1',
							borderTopColor: '#2271b1',
							borderRadius: '50%',
							animation: 'spin 1s linear infinite',
						}}
					/>
					<strong>Queuing sync...</strong>
				</div>
				<div
					style={{
						fontSize: '13px',
						color: '#646970',
						marginTop: '12px',
					}}
				>
					Preparing to sync {totalPages} page
					{totalPages !== 1 ? 's' : ''}...
				</div>
				<style>
					{`
						@keyframes spin {
							to { transform: rotate(360deg); }
						}
					`}
				</style>
			</div>
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

	// Status message helper
	const getStatusMessage = () => {
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
	};

	return (
		<div
			className="notion-sync-dashboard"
			style={{
				background: '#fff',
				border: '2px solid #2271b1',
				borderRadius: '4px',
				padding: '16px',
				margin: '20px 0',
				boxShadow: '0 2px 8px rgba(0,0,0,.1)',
			}}
		>
			{/* Header */}
			<div
				style={{
					display: 'flex',
					justifyContent: 'space-between',
					alignItems: 'center',
					marginBottom: '16px',
				}}
			>
				<div>
					<strong style={{ fontSize: '16px' }}>
						{(() => {
							if (isComplete) {
								return isSuccess
									? '✓ Sync Complete'
									: '✗ Sync Failed';
							}
							return '⟳ Syncing...';
						})()}
					</strong>
					{queueWaitTime > 0 && (
						<div
							style={{
								fontSize: '12px',
								color: '#646970',
								marginTop: '4px',
							}}
						>
							Waiting in queue for {formatWaitTime(queueWaitTime)}
						</div>
					)}
				</div>
				{isComplete && (
					<button
						className="button"
						onClick={() => {
							setBatchId(null);
							setBatch(null);
						}}
					>
						Close
					</button>
				)}
			</div>

			{/* Progress Bar */}
			<div
				style={{
					background: '#f0f0f1',
					height: '24px',
					borderRadius: '12px',
					overflow: 'hidden',
					marginBottom: '16px',
					position: 'relative',
				}}
			>
				<div
					style={{
						background: 'linear-gradient(90deg, #2271b1, #135e96)',
						height: '100%',
						width: `${progress}%`,
						transition: 'width 0.3s',
					}}
				/>
				<div
					style={{
						position: 'absolute',
						top: '50%',
						left: '50%',
						transform: 'translate(-50%, -50%)',
						fontSize: '12px',
						fontWeight: '600',
						color: progress > 50 ? '#fff' : '#2c3338',
					}}
				>
					{batch.processed || 0} / {batch.total || 0} pages (
					{progress}%)
				</div>
			</div>

			{/* Page Sync Stats */}
			<div>
				<h4
					style={{
						margin: '0 0 8px 0',
						fontSize: '14px',
						fontWeight: '600',
					}}
				>
					Page Sync Progress
				</h4>
				<div
					style={{
						display: 'grid',
						gridTemplateColumns:
							'repeat(auto-fit, minmax(120px, 1fr))',
						gap: '12px',
						marginBottom: '16px',
					}}
				>
					<StatCard value={batch.total || 0} label="Total" />
					<StatCard
						value={batch.successful || 0}
						label="Successful"
						color="success"
					/>
					<StatCard
						value={batch.failed || 0}
						label="Failed"
						color="error"
					/>
				</div>
			</div>

			{/* Image Queue Stats */}
			{mediaQueue && mediaQueue.total > 0 && (
				<div
					style={{
						marginTop: '8px',
						padding: '12px',
						background: '#f9fafb',
						borderRadius: '4px',
						border: '1px solid #dcdcde',
					}}
				>
					<div
						style={{
							display: 'flex',
							alignItems: 'center',
							justifyContent: 'space-between',
							marginBottom: '8px',
						}}
					>
						<h4
							style={{
								margin: 0,
								fontSize: '13px',
								fontWeight: '600',
							}}
						>
							📥 Image Downloads
						</h4>
						<span
							style={{
								fontSize: '12px',
								color: '#646970',
								fontWeight: '500',
							}}
						>
							{mediaQueue.total} total
						</span>
					</div>
					<div
						style={{
							display: 'grid',
							gridTemplateColumns:
								'repeat(auto-fit, minmax(80px, 1fr))',
							gap: '8px',
							fontSize: '12px',
						}}
					>
						<MediaStat
							icon="○"
							count={mediaQueue.pending}
							label="Queued"
							color="#8c8f94"
						/>
						<MediaStat
							icon="⟳"
							count={mediaQueue.in_progress}
							label="Processing"
							color="#2271b1"
						/>
						<MediaStat
							icon="✓"
							count={mediaQueue.completed}
							label="Done"
							color="#00a32a"
						/>
						{mediaQueue.failed > 0 && (
							<MediaStat
								icon="✗"
								count={mediaQueue.failed}
								label="Failed"
								color="#d63638"
							/>
						)}
					</div>
				</div>
			)}

			{/* Per-Page Status Table */}
			{pageIds.length > 0 && (
				<div style={{ marginTop: '16px' }}>
					<h4
						style={{
							margin: '0 0 12px 0',
							fontSize: '14px',
							fontWeight: '600',
						}}
					>
						Page Status
					</h4>
					<div
						style={{
							maxHeight: '300px',
							overflow: 'auto',
							border: '1px solid #dcdcde',
							borderRadius: '4px',
						}}
					>
						<table
							style={{
								width: '100%',
								borderCollapse: 'collapse',
								fontSize: '13px',
							}}
						>
							<thead>
								<tr>
									<th
										style={{
											textAlign: 'left',
											padding: '8px',
											background: '#f9fafb',
											borderBottom: '1px solid #dcdcde',
											fontWeight: '600',
										}}
									>
										Page ID
									</th>
									<th
										style={{
											textAlign: 'left',
											padding: '8px',
											background: '#f9fafb',
											borderBottom: '1px solid #dcdcde',
											fontWeight: '600',
										}}
									>
										Status
									</th>
									<th
										style={{
											textAlign: 'right',
											padding: '8px',
											background: '#f9fafb',
											borderBottom: '1px solid #dcdcde',
											fontWeight: '600',
										}}
									>
										Details
									</th>
								</tr>
							</thead>
							<tbody>
								{pageIds.map((pageId) => {
									const status =
										pageStatuses[pageId] || 'queued';
									const result = results[pageId];
									const isCurrentPage =
										batch.current_page_id === pageId;

									return (
										<tr key={pageId}>
											<td
												style={{
													padding: '8px',
													borderBottom:
														'1px solid #f0f0f1',
													fontFamily: 'monospace',
													fontSize: '12px',
												}}
											>
												{pageId.substring(0, 8)}...
												{isCurrentPage && (
													<span
														style={{
															marginLeft: '8px',
															padding: '2px 6px',
															background:
																'#2271b1',
															color: '#fff',
															borderRadius: '3px',
															fontSize: '10px',
															fontWeight: '600',
														}}
													>
														CURRENT
													</span>
												)}
											</td>
											<td
												style={{
													padding: '8px',
													borderBottom:
														'1px solid #f0f0f1',
												}}
											>
												<StatusBadge status={status} />
											</td>
											<td
												style={{
													padding: '8px',
													borderBottom:
														'1px solid #f0f0f1',
													textAlign: 'right',
													fontSize: '12px',
													color: '#646970',
												}}
											>
												{(() => {
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
												})()}
											</td>
										</tr>
									);
								})}
							</tbody>
						</table>
					</div>
				</div>
			)}

			{/* Status Message */}
			<div
				style={{
					fontSize: '13px',
					color: '#646970',
					marginTop: '12px',
					fontStyle: 'italic',
				}}
			>
				{getStatusMessage()}
			</div>
		</div>
	);
}

// Helper Components
function StatCard({ value, label, color = 'neutral' }) {
	const colors = {
		neutral: { bg: '#f9fafb', text: '#2c3338' },
		success: { bg: '#e7f5ec', text: '#00a32a' },
		error: { bg: '#fcf0f1', text: '#d63638' },
	};
	const { bg, text } = colors[color];

	return (
		<div
			style={{
				padding: '8px',
				background: bg,
				borderRadius: '4px',
				textAlign: 'center',
			}}
		>
			<div
				style={{
					fontSize: '24px',
					fontWeight: '600',
					color: text,
				}}
			>
				{value}
			</div>
			<div
				style={{
					fontSize: '12px',
					color: color === 'neutral' ? '#646970' : text,
				}}
			>
				{label}
			</div>
		</div>
	);
}

function MediaStat({ icon, count, label, color }) {
	return (
		<div
			style={{
				display: 'flex',
				alignItems: 'center',
				gap: '4px',
				color,
			}}
		>
			<span>{icon}</span>
			<span>{count}</span>
			<span style={{ fontSize: '11px' }}>{label}</span>
		</div>
	);
}

// Mount to DOM and expose global function
const container = document.getElementById('notion-sync-dashboard');
if (container) {
	window.startSyncDashboard = (batchId, totalPages = 0) => {
		render(
			<SyncDashboard batchId={batchId} totalPages={totalPages} />,
			container
		);
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
		}
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', checkForActiveSyncs);
	} else {
		checkForActiveSyncs();
	}
}
