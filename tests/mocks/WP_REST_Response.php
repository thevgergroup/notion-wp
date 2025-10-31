<?php
/**
 * Mock WP_REST_Response class for unit testing
 *
 * @package NotionWP\Tests\Mocks
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
/**
 * Mock WP_REST_Response class
 */
class WP_REST_Response {
	/**
	 * Response data
	 *
	 * @var mixed
	 */
	private $data;

	/**
	 * Response status code
	 *
	 * @var int
	 */
	private $status;

	/**
	 * Response headers
	 *
	 * @var array
	 */
	private $headers = array();

	/**
	 * Constructor
	 *
	 * @param mixed $data   Response data.
	 * @param int   $status HTTP status code.
	 */
	public function __construct( $data = null, int $status = 200 ) {
		$this->data   = $data;
		$this->status = $status;
	}

	/**
	 * Get response data
	 *
	 * @return mixed Response data.
	 */
	public function get_data() {
		return $this->data;
	}

	/**
	 * Set response data
	 *
	 * @param mixed $data Response data.
	 */
	public function set_data( $data ): void {
		$this->data = $data;
	}

	/**
	 * Get status code
	 *
	 * @return int HTTP status code.
	 */
	public function get_status(): int {
		return $this->status;
	}

	/**
	 * Set status code
	 *
	 * @param int $status HTTP status code.
	 */
	public function set_status( int $status ): void {
		$this->status = $status;
	}

	/**
	 * Set header
	 *
	 * @param string $key   Header name.
	 * @param string $value Header value.
	 */
	public function header( string $key, $value ): void {
		$this->headers[ $key ] = $value;
	}

	/**
	 * Get headers
	 *
	 * @return array All headers.
	 */
	public function get_headers(): array {
		return $this->headers;
	}

	/**
	 * Get specific header
	 *
	 * @param string $key Header name.
	 * @return mixed Header value or null.
	 */
	public function get_header( string $key ) {
		return $this->headers[ $key ] ?? null;
	}
}
