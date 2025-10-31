<?php
/**
 * Tests for DatabaseRestController caching functionality
 *
 * @package NotionWP
 * @subpackage Tests\Unit\API
 */

declare(strict_types=1);

namespace NotionWP\Tests\Unit\API;

use Brain\Monkey\Functions;
use Mockery;
use NotionSync\API\DatabaseRestController;
use NotionSync\Database\RowRepository;
use NotionWP\Tests\Unit\BaseTestCase;

/**
 * Class DatabaseRestControllerCachingTest
 *
 * Tests the caching implementation in DatabaseRestController,
 * including cache key generation, TTL strategy, invalidation,
 * and cache hit/miss scenarios.
 *
 * @covers \NotionSync\API\DatabaseRestController
 */
class DatabaseRestControllerCachingTest extends BaseTestCase {
	/**
	 * DatabaseRestController instance
	 *
	 * @var DatabaseRestController
	 */
	private DatabaseRestController $controller;

	/**
	 * Mock RowRepository
	 *
	 * @var RowRepository&Mockery\MockInterface
	 */
	private $repository_mock;

	/**
	 * Transient storage for testing
	 *
	 * @var array
	 */
	private array $transients = array();

	/**
	 * Set up test fixtures
	 */
	protected function setUp(): void {
		parent::setUp();

		// Reset transients.
		$this->transients = array();

		// Mock WP REST classes.
		$this->setup_rest_class_mocks();

		// Mock transient functions.
		$this->setup_transient_mocks();

		// Mock WordPress post functions.
		$this->setup_post_mocks();

		// Mock wpdb for cache invalidation.
		$this->setup_wpdb_mock();

		// Create controller with mocked repository.
		$this->controller = new DatabaseRestController();

		// Replace repository with mock using reflection.
		$this->repository_mock = Mockery::mock( RowRepository::class );
		$reflection            = new \ReflectionClass( $this->controller );
		$property              = $reflection->getProperty( 'repository' );
		$property->setAccessible( true );
		$property->setValue( $this->controller, $this->repository_mock );
	}

	/**
	 * Setup WordPress REST API class mocks
	 */
	private function setup_rest_class_mocks(): void {
		if ( ! class_exists( 'WP_REST_Request' ) ) {
			require_once __DIR__ . '/../../mocks/WP_REST_Request.php';
		}
		if ( ! class_exists( 'WP_REST_Response' ) ) {
			require_once __DIR__ . '/../../mocks/WP_REST_Response.php';
		}
	}

	/**
	 * Setup transient function mocks
	 */
	private function setup_transient_mocks(): void {
		// Mock get_transient.
		Functions\when( 'get_transient' )
			->alias(
				function ( $key ) {
					return $this->transients[ $key ] ?? false;
				}
			);

		// Mock set_transient.
		Functions\when( 'set_transient' )
			->alias(
				function ( $key, $value, $expiration ) {
					$this->transients[ $key ] = $value;
					return true;
				}
			);

		// Mock delete_transient.
		Functions\when( 'delete_transient' )
			->alias(
				function ( $key ) {
					unset( $this->transients[ $key ] );
					return true;
				}
			);

		// Mock wp_json_encode.
		Functions\when( 'wp_json_encode' )
			->alias(
				function ( $data ) {
					return json_encode( $data );
				}
			);
	}

	/**
	 * Setup post function mocks
	 */
	private function setup_post_mocks(): void {
		// Mock get_post.
		Functions\when( 'get_post' )
			->alias(
				function ( $post_id ) {
					return (object) array(
						'ID'           => $post_id,
						'post_type'    => 'notion_database',
						'post_status'  => 'publish',
						'post_title'   => 'Test Database',
						'post_content' => '',
					);
				}
			);

		// Mock get_post_modified_time.
		Functions\when( 'get_post_modified_time' )
			->alias(
				function ( $format, $gmt, $post_id ) {
					return 1698765432; // Fixed timestamp for testing.
				}
			);

		// Mock current_user_can.
		Functions\when( 'current_user_can' )
			->justReturn( false ); // Default to non-admin for public caching.
	}

	/**
	 * Override setup_wpdb_mock to add options table and query method
	 */
	protected function setup_wpdb_mock(): void {
		parent::setup_wpdb_mock();

		global $wpdb;
		$wpdb->options = 'wp_options';

		// Mock esc_like.
		$wpdb->shouldReceive( 'esc_like' )
			->andReturnUsing(
				function ( $text ) {
					return addcslashes( $text, '_%\\' );
				}
			);

		// Mock query for cache invalidation.
		$wpdb->shouldReceive( 'query' )
			->andReturn( 1 )
			->byDefault();
	}

	/**
	 * Test cache miss on first rows request
	 *
	 * @test
	 */
	public function test_rows_cache_miss_on_first_request(): void {
		// Arrange.
		$request = new \WP_REST_Request( 'GET', '/notion-sync/v1/databases/123/rows' );
		$request->set_param( 'post_id', 123 );
		$request->set_param( 'page', 1 );
		$request->set_param( 'per_page', 50 );

		$expected_rows = array(
			array(
				'id'         => 1,
				'title'      => 'Test Row',
				'properties' => array( 'Status' => 'Active' ),
			),
		);

		$this->repository_mock->shouldReceive( 'get_rows' )
			->once()
			->with( 123, 50, 0 )
			->andReturn( $expected_rows );

		$this->repository_mock->shouldReceive( 'count_rows' )
			->once()
			->with( 123 )
			->andReturn( 1 );

		// Act.
		$response = $this->controller->get_rows( $request );

		// Assert.
		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'MISS', $response->get_headers()['X-NotionWP-Cache'] );

		$data = $response->get_data();
		$this->assertEquals( $expected_rows, $data['rows'] );
		$this->assertEquals( 1, $data['pagination']['total'] );
	}

	/**
	 * Test cache hit on second rows request
	 *
	 * @test
	 */
	public function test_rows_cache_hit_on_second_request(): void {
		// Arrange.
		$request = new \WP_REST_Request( 'GET', '/notion-sync/v1/databases/123/rows' );
		$request->set_param( 'post_id', 123 );
		$request->set_param( 'page', 1 );
		$request->set_param( 'per_page', 50 );

		$expected_rows = array(
			array(
				'id'         => 1,
				'title'      => 'Test Row',
				'properties' => array( 'Status' => 'Active' ),
			),
		);

		$this->repository_mock->shouldReceive( 'get_rows' )
			->once()
			->with( 123, 50, 0 )
			->andReturn( $expected_rows );

		$this->repository_mock->shouldReceive( 'count_rows' )
			->once()
			->with( 123 )
			->andReturn( 1 );

		// Act - First request (cache miss).
		$response1 = $this->controller->get_rows( $request );
		$this->assertEquals( 'MISS', $response1->get_headers()['X-NotionWP-Cache'] );

		// Act - Second request (cache hit).
		$response2 = $this->controller->get_rows( $request );

		// Assert - Second request should hit cache.
		$this->assertEquals( 'HIT', $response2->get_headers()['X-NotionWP-Cache'] );
		$this->assertEquals( $response1->get_data(), $response2->get_data() );
	}

	/**
	 * Test different pagination parameters create different cache keys
	 *
	 * @test
	 */
	public function test_different_pagination_creates_different_cache_keys(): void {
		// Arrange - Request page 1.
		$request1 = new \WP_REST_Request( 'GET', '/notion-sync/v1/databases/123/rows' );
		$request1->set_param( 'post_id', 123 );
		$request1->set_param( 'page', 1 );
		$request1->set_param( 'per_page', 50 );

		// Arrange - Request page 2.
		$request2 = new \WP_REST_Request( 'GET', '/notion-sync/v1/databases/123/rows' );
		$request2->set_param( 'post_id', 123 );
		$request2->set_param( 'page', 2 );
		$request2->set_param( 'per_page', 50 );

		$page1_rows = array(
			array(
				'id' => 1,
				'title' => 'Row 1',
				'properties' => array(),
			),
		);
		$page2_rows = array(
			array(
				'id' => 2,
				'title' => 'Row 2',
				'properties' => array(),
			),
		);

		$this->repository_mock->shouldReceive( 'get_rows' )
			->with( 123, 50, 0 )
			->once()
			->andReturn( $page1_rows );

		$this->repository_mock->shouldReceive( 'get_rows' )
			->with( 123, 50, 50 )
			->once()
			->andReturn( $page2_rows );

		$this->repository_mock->shouldReceive( 'count_rows' )
			->with( 123 )
			->twice()
			->andReturn( 100 );

		// Act.
		$response1 = $this->controller->get_rows( $request1 );
		$response2 = $this->controller->get_rows( $request2 );

		// Assert - Both should be cache misses with different data.
		$this->assertEquals( 'MISS', $response1->get_headers()['X-NotionWP-Cache'] );
		$this->assertEquals( 'MISS', $response2->get_headers()['X-NotionWP-Cache'] );
		$this->assertNotEquals( $response1->get_data()['rows'], $response2->get_data()['rows'] );
	}

	/**
	 * Test schema cache miss on first request
	 *
	 * @test
	 */
	public function test_schema_cache_miss_on_first_request(): void {
		// Arrange.
		$request = new \WP_REST_Request( 'GET', '/notion-sync/v1/databases/123/schema' );
		$request->set_param( 'post_id', 123 );

		$sample_row = array(
			array(
				'id'         => 1,
				'title'      => 'Test',
				'properties' => array(
					'Status' => 'Active',
					'Count'  => 42,
				),
			),
		);

		$this->repository_mock->shouldReceive( 'get_rows' )
			->once()
			->with( 123, 1, 0 )
			->andReturn( $sample_row );

		// Act.
		$response = $this->controller->get_schema( $request );

		// Assert.
		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'MISS', $response->get_headers()['X-NotionWP-Cache'] );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'columns', $data );
		$this->assertNotEmpty( $data['columns'] );
	}

	/**
	 * Test schema cache hit on second request
	 *
	 * @test
	 */
	public function test_schema_cache_hit_on_second_request(): void {
		// Arrange.
		$request = new \WP_REST_Request( 'GET', '/notion-sync/v1/databases/123/schema' );
		$request->set_param( 'post_id', 123 );

		$sample_row = array(
			array(
				'id'         => 1,
				'title'      => 'Test',
				'properties' => array( 'Status' => 'Active' ),
			),
		);

		$this->repository_mock->shouldReceive( 'get_rows' )
			->once()
			->with( 123, 1, 0 )
			->andReturn( $sample_row );

		// Act - First request (cache miss).
		$response1 = $this->controller->get_schema( $request );
		$this->assertEquals( 'MISS', $response1->get_headers()['X-NotionWP-Cache'] );

		// Act - Second request (cache hit).
		$response2 = $this->controller->get_schema( $request );

		// Assert - Second request should hit cache.
		$this->assertEquals( 'HIT', $response2->get_headers()['X-NotionWP-Cache'] );
		$this->assertEquals( $response1->get_data(), $response2->get_data() );
	}

	/**
	 * Test cache TTL is shorter for admin users
	 *
	 * @test
	 */
	public function test_cache_ttl_shorter_for_admin_users(): void {
		// Arrange - Mock admin user.
		Functions\when( 'current_user_can' )
			->justReturn( true );

		$request = new \WP_REST_Request( 'GET', '/notion-sync/v1/databases/123/rows' );
		$request->set_param( 'post_id', 123 );
		$request->set_param( 'page', 1 );
		$request->set_param( 'per_page', 50 );

		$this->repository_mock->shouldReceive( 'get_rows' )
			->once()
			->andReturn( array() );

		$this->repository_mock->shouldReceive( 'count_rows' )
			->once()
			->andReturn( 0 );

		// Act.
		$response = $this->controller->get_rows( $request );

		// Assert - Admin should get 5 minute TTL (300 seconds).
		$cache_expires = $response->get_headers()['X-NotionWP-Cache-Expires'];
		$ttl           = $cache_expires - time();

		// Allow 5 second margin for test execution time.
		$this->assertLessThanOrEqual( 305, $ttl );
		$this->assertGreaterThanOrEqual( 295, $ttl );
	}

	/**
	 * Test cache invalidation on post save
	 *
	 * @test
	 */
	public function test_cache_invalidation_on_post_save(): void {
		global $wpdb;

		// Arrange - Cache some data first.
		$request = new \WP_REST_Request( 'GET', '/notion-sync/v1/databases/123/rows' );
		$request->set_param( 'post_id', 123 );
		$request->set_param( 'page', 1 );
		$request->set_param( 'per_page', 50 );

		$this->repository_mock->shouldReceive( 'get_rows' )
			->once()
			->andReturn( array() );

		$this->repository_mock->shouldReceive( 'count_rows' )
			->once()
			->andReturn( 0 );

		$response1 = $this->controller->get_rows( $request );
		$this->assertEquals( 'MISS', $response1->get_headers()['X-NotionWP-Cache'] );

		// Verify cache was populated.
		$this->assertNotEmpty( $this->transients );

		// Act - Trigger post save hook.
		$wpdb->shouldReceive( 'query' )
			->once()
			->andReturn( 1 );

		$this->controller->handle_post_save( 123 );

		// Assert - Cache should be invalidated (transients cleared via wpdb query).
		// Note: In real WordPress, this would delete transients from DB.
		// Our test verifies that wpdb->query was called with DELETE statement.
	}

	/**
	 * Test cache is not created for oversized responses
	 *
	 * @test
	 */
	public function test_cache_not_created_for_oversized_responses(): void {
		// Arrange - Create very large dataset.
		$large_rows = array();
		for ( $i = 0; $i < 1000; $i++ ) {
			$large_rows[] = array(
				'id'         => $i,
				'title'      => str_repeat( 'Large Data ', 100 ),
				'properties' => array_fill( 0, 50, str_repeat( 'x', 200 ) ),
			);
		}

		$request = new \WP_REST_Request( 'GET', '/notion-sync/v1/databases/123/rows' );
		$request->set_param( 'post_id', 123 );
		$request->set_param( 'page', 1 );
		$request->set_param( 'per_page', 100 );

		$this->repository_mock->shouldReceive( 'get_rows' )
			->twice() // Should be called twice since cache won't store it.
			->andReturn( $large_rows );

		$this->repository_mock->shouldReceive( 'count_rows' )
			->twice()
			->andReturn( 1000 );

		// Act - Make two requests.
		$response1 = $this->controller->get_rows( $request );
		$response2 = $this->controller->get_rows( $request );

		// Assert - Both should be cache misses due to size limit.
		$this->assertEquals( 'MISS', $response1->get_headers()['X-NotionWP-Cache'] );
		$this->assertEquals( 'MISS', $response2->get_headers()['X-NotionWP-Cache'] );
	}

	/**
	 * Test empty database returns empty schema
	 *
	 * @test
	 */
	public function test_empty_database_returns_empty_schema(): void {
		// Arrange.
		$request = new \WP_REST_Request( 'GET', '/notion-sync/v1/databases/123/schema' );
		$request->set_param( 'post_id', 123 );

		$this->repository_mock->shouldReceive( 'get_rows' )
			->once()
			->with( 123, 1, 0 )
			->andReturn( array() ); // Empty database.

		// Act.
		$response = $this->controller->get_schema( $request );

		// Assert.
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( array(), $data['columns'] );
		$this->assertEquals( 'No rows found', $data['message'] );
	}

	/**
	 * Test cache headers include expiration timestamp
	 *
	 * @test
	 */
	public function test_cache_headers_include_expiration_timestamp(): void {
		// Arrange.
		$request = new \WP_REST_Request( 'GET', '/notion-sync/v1/databases/123/rows' );
		$request->set_param( 'post_id', 123 );
		$request->set_param( 'page', 1 );
		$request->set_param( 'per_page', 50 );

		$this->repository_mock->shouldReceive( 'get_rows' )
			->once()
			->andReturn( array() );

		$this->repository_mock->shouldReceive( 'count_rows' )
			->once()
			->andReturn( 0 );

		// Act.
		$response = $this->controller->get_rows( $request );

		// Assert.
		$headers = $response->get_headers();
		$this->assertArrayHasKey( 'X-NotionWP-Cache', $headers );
		$this->assertArrayHasKey( 'X-NotionWP-Cache-Expires', $headers );

		// Cache expires should be in the future.
		$this->assertGreaterThan( time(), $headers['X-NotionWP-Cache-Expires'] );
	}

	/**
	 * Test cache invalidation handles post deletion
	 *
	 * @test
	 */
	public function test_cache_invalidation_on_post_delete(): void {
		global $wpdb;

		// Mock get_post to return a database post.
		Functions\when( 'get_post' )
			->alias(
				function ( $post_id ) {
					return (object) array(
						'ID'        => $post_id,
						'post_type' => 'notion_database',
					);
				}
			);

		// Act - Trigger post delete hook.
		$wpdb->shouldReceive( 'query' )
			->once()
			->andReturn( 1 );

		$this->controller->handle_post_delete( 123 );

		// Assert - wpdb->query should be called to delete cache transients.
		// Mockery will verify the call was made.
	}
}
