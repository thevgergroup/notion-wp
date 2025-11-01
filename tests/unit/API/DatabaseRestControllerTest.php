<?php
/**
 * Tests for Database REST API Controller Security
 *
 * Tests comprehensive permission system including:
 * - Published posts (public access)
 * - Password-protected posts (require password validation)
 * - Private posts (require read_private_posts capability)
 * - Draft/Pending/Future posts (require edit_posts capability)
 * - Invalid post types and non-existent posts
 * - Admin override capabilities
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionWP\Tests\Unit\API;

use Brain\Monkey\Functions;
use Mockery;
use NotionSync\API\DatabaseRestController;
use NotionSync\Database\RowRepository;
use NotionWP\Tests\Unit\BaseTestCase;
use WP_REST_Request;

/**
 * Test DatabaseRestController permission system
 */
class DatabaseRestControllerTest extends BaseTestCase {
	/**
	 * Controller instance
	 *
	 * @var DatabaseRestController
	 */
	private DatabaseRestController $controller;

	/**
	 * Set up test environment
	 */
	protected function setUp(): void {
		parent::setUp();

		// Set up wpdb mock to avoid database errors in RowRepository constructor
		$this->setup_wpdb_mock();

		// Create controller instance
		$this->controller = new DatabaseRestController();
	}

	/**
	 * Test published post allows public access
	 *
	 * Security test: Published posts without passwords should be publicly accessible
	 */
	public function test_published_post_allows_public_access(): void {
		$post_id = 123;
		$request = $this->create_request( $post_id );

		// Mock post as published database
		$post = $this->create_database_post( $post_id, 'publish', '' );

		Functions\when( 'get_post' )->justReturn( $post );

		$result = $this->controller->check_read_permission( $request );

		$this->assertTrue( $result, 'Published database posts should be publicly accessible' );
	}

	/**
	 * Test password-protected post requires password validation
	 *
	 * Security test: HIGH PRIORITY - Password-protected posts must check password status
	 */
	public function test_password_protected_post_requires_password(): void {
		$post_id = 124;
		$request = $this->create_request( $post_id );

		// Mock post as published but password-protected
		$post = $this->create_database_post( $post_id, 'publish', 'secret123' );

		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'post_password_required' )->justReturn( true );

		$result = $this->controller->check_read_permission( $request );

		$this->assertFalse( $result, 'Password-protected posts should deny access when password not provided' );
	}

	/**
	 * Test password-protected post with validated password allows access
	 *
	 * Security test: Password-protected posts should allow access when password validated
	 */
	public function test_password_protected_post_with_valid_password_allows_access(): void {
		$post_id = 125;
		$request = $this->create_request( $post_id );

		// Mock post as published but password-protected
		$post = $this->create_database_post( $post_id, 'publish', 'secret123' );

		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'post_password_required' )->justReturn( false );

		$result = $this->controller->check_read_permission( $request );

		$this->assertTrue( $result, 'Password-protected posts should allow access when password is validated' );
	}

	/**
	 * Test private post requires read_private_posts capability
	 *
	 * Security test: Private posts should only be accessible to users with appropriate capability
	 */
	public function test_private_post_requires_capability(): void {
		$post_id = 126;
		$request = $this->create_request( $post_id );

		// Mock post as private database
		$post = $this->create_database_post( $post_id, 'private', '' );

		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'current_user_can' )->justReturn( false );

		$result = $this->controller->check_read_permission( $request );

		$this->assertFalse( $result, 'Private posts should deny access to users without read_private_posts capability' );
	}

	/**
	 * Test private post allows access with read_private_posts capability
	 *
	 * Security test: Users with read_private_posts should access private databases
	 */
	public function test_private_post_allows_access_with_capability(): void {
		$post_id = 127;
		$request = $this->create_request( $post_id );

		// Mock post as private database
		$post = $this->create_database_post( $post_id, 'private', '' );

		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'current_user_can' )->justReturn( true );

		$result = $this->controller->check_read_permission( $request );

		$this->assertTrue( $result, 'Private posts should allow access to users with read_private_posts capability' );
	}

	/**
	 * Test draft post requires edit_posts capability
	 *
	 * Security test: Draft posts should require edit_posts capability
	 */
	public function test_draft_post_requires_edit_capability(): void {
		$post_id = 128;
		$request = $this->create_request( $post_id );

		// Mock post as draft database
		$post = $this->create_database_post( $post_id, 'draft', '' );

		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'current_user_can' )->justReturn( false );

		$result = $this->controller->check_read_permission( $request );

		$this->assertFalse( $result, 'Draft posts should deny access to users without edit_posts capability' );
	}

	/**
	 * Test pending post requires edit_posts capability
	 *
	 * Security test: Pending posts should require edit_posts capability
	 */
	public function test_pending_post_requires_edit_capability(): void {
		$post_id = 129;
		$request = $this->create_request( $post_id );

		// Mock post as pending database
		$post = $this->create_database_post( $post_id, 'pending', '' );

		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'current_user_can' )->justReturn( false );

		$result = $this->controller->check_read_permission( $request );

		$this->assertFalse( $result, 'Pending posts should deny access to users without edit_posts capability' );
	}

	/**
	 * Test future post requires edit_posts capability
	 *
	 * Security test: Future (scheduled) posts should require edit_posts capability
	 */
	public function test_future_post_requires_edit_capability(): void {
		$post_id = 130;
		$request = $this->create_request( $post_id );

		// Mock post as future database
		$post = $this->create_database_post( $post_id, 'future', '' );

		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'current_user_can' )->justReturn( false );

		$result = $this->controller->check_read_permission( $request );

		$this->assertFalse( $result, 'Future posts should deny access to users without edit_posts capability' );
	}

	/**
	 * Test non-existent post denies access
	 *
	 * Security test: CRITICAL - Non-existent posts should be denied
	 */
	public function test_non_existent_post_denies_access(): void {
		$post_id = 999;
		$request = $this->create_request( $post_id );

		Functions\when( 'get_post' )->justReturn( null );

		$result = $this->controller->check_read_permission( $request );

		$this->assertFalse( $result, 'Non-existent posts should always deny access' );
	}

	/**
	 * Test wrong post type denies access
	 *
	 * Security test: CRITICAL - Only notion_database post type should be accessible
	 */
	public function test_wrong_post_type_denies_access(): void {
		$post_id = 131;
		$request = $this->create_request( $post_id );

		// Mock post as wrong post type
		$post = (object) array(
			'ID'            => $post_id,
			'post_type'     => 'page', // Wrong type
			'post_status'   => 'publish',
			'post_password' => '',
		);

		Functions\when( 'get_post' )->justReturn( $post );

		$result = $this->controller->check_read_permission( $request );

		$this->assertFalse( $result, 'Posts of wrong type should be denied access' );
	}

	/**
	 * Test admin override works for any status
	 *
	 * Security test: Admins should be able to access databases in any status
	 */
	public function test_admin_override_for_trash_status(): void {
		$post_id = 132;
		$request = $this->create_request( $post_id );

		// Mock post as trashed database (unusual status)
		$post = $this->create_database_post( $post_id, 'trash', '' );

		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'current_user_can' )->justReturn( true );

		$result = $this->controller->check_read_permission( $request );

		$this->assertTrue( $result, 'Admins should be able to access databases in any status' );
	}

	/**
	 * Test draft post allows access with edit_posts capability
	 *
	 * Security test: Users with edit_posts should access draft databases
	 */
	public function test_draft_post_allows_access_with_edit_capability(): void {
		$post_id = 133;
		$request = $this->create_request( $post_id );

		// Mock post as draft database
		$post = $this->create_database_post( $post_id, 'draft', '' );

		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'current_user_can' )->justReturn( true );

		$result = $this->controller->check_read_permission( $request );

		$this->assertTrue( $result, 'Draft posts should allow access to users with edit_posts capability' );
	}

	/**
	 * Test post type validation prevents unauthorized access
	 *
	 * Security test: Ensure only notion_database posts are accessible via this endpoint
	 */
	public function test_post_type_validation_prevents_unauthorized_access(): void {
		$post_id = 134;
		$request = $this->create_request( $post_id );

		// Try various invalid post types
		$invalid_types = array( 'post', 'page', 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset' );

		foreach ( $invalid_types as $invalid_type ) {
			$post = (object) array(
				'ID'            => $post_id,
				'post_type'     => $invalid_type,
				'post_status'   => 'publish',
				'post_password' => '',
			);

			Functions\when( 'get_post' )->justReturn( $post );

			$result = $this->controller->check_read_permission( $request );

			$this->assertFalse( $result, "Posts of type '{$invalid_type}' should be denied access" );
		}
	}

	/**
	 * Helper: Create a mock WP_REST_Request
	 *
	 * @param int $post_id Post ID to include in request.
	 * @return WP_REST_Request Mock request object.
	 */
	private function create_request( int $post_id ): WP_REST_Request {
		$request = Mockery::mock( WP_REST_Request::class );
		$request->shouldReceive( 'get_param' )
			->with( 'post_id' )
			->andReturn( $post_id );
		return $request;
	}

	/**
	 * Helper: Create a mock notion_database post
	 *
	 * @param int    $post_id       Post ID.
	 * @param string $status        Post status.
	 * @param string $password      Post password (empty string for no password).
	 * @return object Mock post object.
	 */
	private function create_database_post( int $post_id, string $status, string $password ): object {
		return (object) array(
			'ID'            => $post_id,
			'post_type'     => 'notion_database',
			'post_status'   => $status,
			'post_password' => $password,
			'post_title'    => 'Test Database',
		);
	}
}
