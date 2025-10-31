<?php
/**
 * Mock WP_REST_Request class for unit testing
 *
 * @package NotionWP\Tests\Mocks
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
/**
 * Mock WP_REST_Request class
 */
class WP_REST_Request {
	/**
	 * Request method
	 *
	 * @var string
	 */
	private $method;

	/**
	 * Request route
	 *
	 * @var string
	 */
	private $route;

	/**
	 * Request parameters
	 *
	 * @var array
	 */
	private $params = array();

	/**
	 * Constructor
	 *
	 * @param string $method Request method.
	 * @param string $route  Request route.
	 */
	public function __construct( string $method = 'GET', string $route = '' ) {
		$this->method = $method;
		$this->route  = $route;
	}

	/**
	 * Set parameter
	 *
	 * @param string $key   Parameter key.
	 * @param mixed  $value Parameter value.
	 */
	public function set_param( string $key, $value ): void {
		$this->params[ $key ] = $value;
	}

	/**
	 * Get parameter
	 *
	 * @param string $key Parameter key.
	 * @return mixed Parameter value or null.
	 */
	public function get_param( string $key ) {
		return $this->params[ $key ] ?? null;
	}

	/**
	 * Get all parameters
	 *
	 * @return array All parameters.
	 */
	public function get_params(): array {
		return $this->params;
	}

	/**
	 * Get method
	 *
	 * @return string Request method.
	 */
	public function get_method(): string {
		return $this->method;
	}

	/**
	 * Get route
	 *
	 * @return string Request route.
	 */
	public function get_route(): string {
		return $this->route;
	}
}
