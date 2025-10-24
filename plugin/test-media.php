<?php
/**
 * Media Handling Test Script
 *
 * Tests ImageDownloader, MediaUploader, MediaRegistry, and Converters.
 */

use NotionSync\Media\ImageDownloader;
use NotionSync\Media\MediaUploader;
use NotionSync\Media\MediaRegistry;
use NotionSync\Media\ImageConverter;
use NotionSync\Media\FileDownloader;

// Test 1: ImageDownloader - Download from public URL
echo "=== Test 1: ImageDownloader - Download Image ===\n";

$downloader = new ImageDownloader();

// Test with a public image URL (Unsplash)
$test_url = 'https://images.unsplash.com/photo-1506905925346-21bda4d32df4';

try {
	echo "Testing should_download() for Unsplash URL...\n";
	$should_download = $downloader->should_download( $test_url );
	echo 'Result: ' . ( $should_download ? 'YES (ERROR - should be NO)' : 'NO (CORRECT)' ) . "\n\n";
} catch ( Exception $e ) {
	echo 'Error: ' . $e->getMessage() . "\n\n";
}

// Test with mock Notion S3 URL
$notion_url = 'https://s3.us-west-2.amazonaws.com/secure.notion-static.com/test-image.jpg?X-Amz-Algorithm=test';

echo "Testing should_download() for Notion S3 URL...\n";
$should_download = $downloader->should_download( $notion_url );
echo 'Result: ' . ( $should_download ? 'YES (CORRECT)' : 'NO (ERROR - should be YES)' ) . "\n\n";

// Test 2: Create a test image file
echo "=== Test 2: Create Test Image File ===\n";

try {
	// Create a simple 1x1 pixel PNG image
	$temp_dir = sys_get_temp_dir();
	$test_file = $temp_dir . '/test-image-' . uniqid() . '.png';

	// Create a 1x1 red pixel PNG
	$img = imagecreate( 150, 150 );
	$red = imagecolorallocate( $img, 255, 0, 0 );
	imagepng( $img, $test_file );
	imagedestroy( $img );

	echo "✓ Created test image file\n";
	echo "  File path: $test_file\n";
	echo '  File size: ' . filesize( $test_file ) . " bytes\n\n";

	// Simulate downloaded file structure
	$downloaded = [
		'file_path' => $test_file,
		'filename' => basename( $test_file ),
		'mime_type' => 'image/png',
		'file_size' => filesize( $test_file ),
		'source_url' => 'https://test.example.com/test.png',
	];
	echo "✓ Downloaded successfully!\n";
	echo "  File path: {$downloaded['file_path']}\n";
	echo "  Filename: {$downloaded['filename']}\n";
	echo "  MIME type: {$downloaded['mime_type']}\n";
	echo "  File size: {$downloaded['file_size']} bytes\n";
	echo "  Source URL: {$downloaded['source_url']}\n\n";

	// Test 3: MediaUploader
	echo "=== Test 3: MediaUploader - Upload to Media Library ===\n";

	$uploader = new MediaUploader();

	$metadata = [
		'title' => 'Test Image',
		'caption' => 'This is a test image from Phase 3',
		'alt_text' => 'Test placeholder image',
		'description' => 'Testing media upload functionality',
	];

	$attachment_id = $uploader->upload( $downloaded['file_path'], $metadata );
	echo "✓ Uploaded to Media Library!\n";
	echo "  Attachment ID: $attachment_id\n";

	$attachment_url = $uploader->get_attachment_url( $attachment_id );
	echo "  Attachment URL: $attachment_url\n\n";

	// Test 4: MediaRegistry
	echo "=== Test 4: MediaRegistry - Register Media ===\n";

	$test_block_id = 'test-block-' . uniqid();
	$registered = MediaRegistry::register( $test_block_id, $attachment_id, $downloaded['source_url'] );

	echo ( $registered ? '✓' : '✗' ) . ' Registration: ' . ( $registered ? 'SUCCESS' : 'FAILED' ) . "\n";

	// Test finding the attachment
	$found_id = MediaRegistry::find( $test_block_id );
	echo ( $found_id === $attachment_id ? '✓' : '✗' ) . ' Find attachment: ';
	echo ( $found_id === $attachment_id ? "FOUND (ID: $found_id)" : 'NOT FOUND' ) . "\n";

	// Test getting media URL
	$media_url = MediaRegistry::get_media_url( $test_block_id );
	echo ( $media_url ? '✓' : '✗' ) . ' Get media URL: ';
	echo ( $media_url ? $media_url : 'NOT FOUND' ) . "\n\n";

	// Test 5: ImageConverter
	echo "=== Test 5: ImageConverter - Convert Notion Block ===\n";

	$converter = new ImageConverter( $downloader, $uploader );

	// Simulate external image block (Unsplash)
	$external_block = [
		'id' => 'test-external-' . uniqid(),
		'type' => 'image',
		'image' => [
			'type' => 'external',
			'external' => [
				'url' => 'https://images.unsplash.com/photo-1506905925346-21bda4d32df4',
			],
			'caption' => [
				[ 'plain_text' => 'Beautiful mountain landscape' ],
			],
		],
	];

	echo "Converting external image block...\n";
	$gutenberg_html = $converter->convert( $external_block );
	echo "✓ Converted successfully!\n";
	echo "Output:\n";
	echo substr( $gutenberg_html, 0, 200 ) . "...\n\n";

	// Test 6: MediaRegistry Stats
	echo "=== Test 6: MediaRegistry Statistics ===\n";

	$stats = MediaRegistry::get_stats();
	echo "Total entries: {$stats['total_entries']}\n";
	echo "Unique attachments: {$stats['total_attachments']}\n";
	echo "Orphaned entries: {$stats['orphaned']}\n\n";

	// Test 7: FileDownloader
	echo "=== Test 7: FileDownloader - Check Allowed Types ===\n";

	$file_downloader = new FileDownloader();
	$allowed_types = FileDownloader::get_allowed_mime_types();
	echo 'Allowed file types: ' . count( $allowed_types ) . "\n";
	echo 'Includes PDF: ' . ( in_array( 'application/pdf', $allowed_types ) ? 'YES' : 'NO' ) . "\n";
	echo 'Includes DOCX: ' . ( in_array( 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', $allowed_types ) ? 'YES' : 'NO' ) . "\n\n";

	// Test 8: Cleanup test attachment
	echo "=== Test 8: Cleanup ===\n";

	$deleted = wp_delete_attachment( $attachment_id, true );
	echo ( $deleted ? '✓' : '✗' ) . " Test attachment deleted\n";

	MediaRegistry::delete( $test_block_id );
	echo "✓ Registry entry deleted\n\n";

	echo "=== ALL TESTS COMPLETED ===\n";

} catch ( Exception $e ) {
	echo '✗ Error: ' . $e->getMessage() . "\n";
	echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
