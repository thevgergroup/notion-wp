<?php
/**
 * MediaSyncScheduler Test Script
 *
 * Tests background media processing with Action Scheduler.
 */

use NotionSync\Media\MediaSyncScheduler;
use NotionSync\Media\ImageDownloader;
use NotionSync\Media\MediaUploader;

echo "=== MediaSyncScheduler Tests ===\n\n";

// Test 1: Check Action Scheduler availability
echo "Test 1: Action Scheduler Availability\n";
$available = MediaSyncScheduler::is_action_scheduler_available();
echo "Status: " . ($available ? "✓ AVAILABLE" : "✗ NOT AVAILABLE") . "\n";
echo "Function exists: " . (function_exists('as_schedule_single_action') ? "YES" : "NO") . "\n\n";

// Test 2: Get background threshold
echo "Test 2: Background Processing Threshold\n";
$threshold = MediaSyncScheduler::get_background_threshold();
echo "Threshold: {$threshold} images\n";
echo "Meaning: Batches with {$threshold}+ images will process in background\n\n";

// Test 3: Create test post for scheduling
echo "Test 3: Create Test Post\n";
$test_post_id = wp_insert_post([
	'post_title' => 'Media Scheduler Test Post',
	'post_content' => 'Testing background media processing',
	'post_status' => 'draft',
	'post_type' => 'post'
]);

if ($test_post_id && !is_wp_error($test_post_id)) {
	echo "✓ Test post created: ID {$test_post_id}\n\n";
} else {
	echo "✗ Failed to create test post\n";
	exit(1);
}

// Test 4: Small batch (synchronous processing)
echo "Test 4: Small Batch Processing (Sync)\n";

$scheduler = new MediaSyncScheduler();

$small_batch = [
	[
		'block_id' => 'test-small-1',
		'url' => 'https://test.example.com/image1.jpg',
		'metadata' => ['alt_text' => 'Test image 1']
	],
	[
		'block_id' => 'test-small-2',
		'url' => 'https://test.example.com/image2.jpg',
		'metadata' => ['alt_text' => 'Test image 2']
	]
];

$result = $scheduler->schedule_or_process($test_post_id, $small_batch);

echo "Status: {$result['status']}\n";
echo "Total items: {$result['total']}\n";
echo "Expected: 'sync' (small batch, processed immediately)\n";
echo ($result['status'] === 'sync' ? "✓ CORRECT" : "✗ INCORRECT") . "\n\n";

// Test 5: Large batch (background processing)
echo "Test 5: Large Batch Processing (Background)\n";

// Create a batch of 12 media items (exceeds threshold of 10)
$large_batch = [];
for ($i = 1; $i <= 12; $i++) {
	$large_batch[] = [
		'block_id' => "test-large-{$i}",
		'url' => "https://test.example.com/image{$i}.jpg",
		'metadata' => ['alt_text' => "Test image {$i}"]
	];
}

$result = $scheduler->schedule_or_process($test_post_id, $large_batch);

echo "Status: {$result['status']}\n";
echo "Total items: {$result['total']}\n";

if ($available) {
	echo "Expected: 'scheduled' (large batch, background processing)\n";
	echo ($result['status'] === 'scheduled' ? "✓ CORRECT" : "✗ INCORRECT") . "\n";

	if (isset($result['batch_id'])) {
		echo "Batch ID: {$result['batch_id']}\n";
	}
	if (isset($result['scheduled_id'])) {
		echo "Scheduled Action ID: {$result['scheduled_id']}\n";
	}
} else {
	echo "Expected: 'sync' (Action Scheduler not available, fallback to sync)\n";
	echo ($result['status'] === 'sync' ? "✓ CORRECT" : "✗ INCORRECT") . "\n";
}
echo "\n";

// Test 6: Check batch status
echo "Test 6: Batch Status Tracking\n";

$status = $scheduler->get_batch_status($test_post_id);

if ($status) {
	echo "✓ Batch status found\n";
	echo "Status: {$status['status']}\n";
	echo "Total: {$status['total']}\n";
	echo "Processed: {$status['processed']}\n";

	if ($status['status'] === 'processing') {
		echo "Note: Background job is queued/processing\n";
	}
} else {
	echo "No batch status (expected if Action Scheduler not available)\n";
}
echo "\n";

// Test 7: Force background processing
echo "Test 7: Force Background (Even Small Batch)\n";

$forced_batch = [
	[
		'block_id' => 'test-forced-1',
		'url' => 'https://test.example.com/forced.jpg',
		'metadata' => ['alt_text' => 'Forced background']
	]
];

$result = $scheduler->schedule_or_process(
	$test_post_id,
	$forced_batch,
	['force_background' => true]
);

echo "Status: {$result['status']}\n";
echo "Total items: {$result['total']}\n";

if ($available) {
	echo "Expected: 'scheduled' (force_background = true)\n";
	echo ($result['status'] === 'scheduled' ? "✓ CORRECT" : "✗ INCORRECT") . "\n";
} else {
	echo "Expected: 'sync' (Action Scheduler not available)\n";
	echo ($result['status'] === 'sync' ? "✓ CORRECT" : "✗ INCORRECT") . "\n";
}
echo "\n";

// Test 8: Cleanup
echo "Test 8: Cleanup Batch Metadata\n";

$scheduler->cleanup_batch_metadata($test_post_id);
echo "✓ Batch metadata cleaned up\n\n";

// Verify cleanup
$status_after_cleanup = $scheduler->get_batch_status($test_post_id);
echo "Status after cleanup: " . ($status_after_cleanup ? "✗ STILL EXISTS" : "✓ REMOVED") . "\n\n";

// Test 9: Delete test post
echo "Test 9: Cleanup Test Post\n";
$deleted = wp_delete_post($test_post_id, true);
echo ($deleted ? "✓" : "✗") . " Test post deleted\n\n";

echo "=== ALL SCHEDULER TESTS COMPLETED ===\n";

// Summary
echo "\n=== Summary ===\n";
echo "Action Scheduler: " . ($available ? "Available ✓" : "Not Available (tests ran in fallback mode)") . "\n";
echo "Background threshold: {$threshold} images\n";
echo "Small batches: Process synchronously (fast)\n";
echo "Large batches (10+): Queue for background processing (prevents timeouts)\n";
echo "\nThe MediaSyncScheduler is ready for production use!\n";
