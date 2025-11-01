<?php
/**
 * Tests for PropertyFormatter
 *
 * @package NotionSync
 */

namespace NotionSync\Tests\Unit\Database;

use Brain\Monkey\Functions;
use NotionSync\Database\PropertyFormatter;
use NotionSync\Database\RichTextConverter;
use NotionWP\Tests\Unit\BaseTestCase;

/**
 * Class PropertyFormatterTest
 *
 * @covers \NotionSync\Database\PropertyFormatter
 */
class PropertyFormatterTest extends BaseTestCase {

	/**
	 * Formatter instance.
	 *
	 * @var PropertyFormatter
	 */
	private $formatter;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->formatter = new PropertyFormatter();

		// Add mocks for functions not in BaseTestCase
		Functions\stubs(
			array(
				'date_i18n'            => function ( $format, $timestamp = null ) {
					return gmdate( $format ?: 'M d, Y', $timestamp ?: time() );
				},
				'_n'                   => function ( $single, $plural, $number ) {
					return $number === 1 ? $single : $plural;
				},
				'is_email'             => function ( $email ) {
					return filter_var( $email, FILTER_VALIDATE_EMAIL ) !== false;
				},
				'sanitize_html_class'  => function ( $class ) {
					return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $class ) );
				},
			)
		);
	}

	/**
	 * Test format returns null for empty values.
	 */
	public function test_format_null_value(): void {
		$result = $this->formatter->format( 'text', null );
		$this->assertNull( $result );

		$result = $this->formatter->format( 'text', '' );
		$this->assertNull( $result );
	}

	/**
	 * Test format_text with plain string.
	 */
	public function test_format_text(): void {
		$result = $this->formatter->format( 'text', 'Plain text' );
		$this->assertSame( 'Plain text', $result );
	}

	/**
	 * Test format_number with integer.
	 */
	public function test_format_number_integer(): void {
		$result = $this->formatter->format( 'number', 1234 );
		$this->assertSame( '1,234', $result );
	}

	/**
	 * Test format_number with float.
	 */
	public function test_format_number_float(): void {
		$result = $this->formatter->format( 'number', 1234.56 );
		$this->assertSame( '1,234.56', $result );
	}

	/**
	 * Test format_select with color.
	 */
	public function test_format_select(): void {
		$value  = array(
			'name'  => 'In Progress',
			'color' => 'blue',
		);
		$result = $this->formatter->format( 'select', $value );

		$this->assertStringContainsString( 'notion-select', $result );
		$this->assertStringContainsString( 'notion-blue', $result );
		$this->assertStringContainsString( 'In Progress', $result );
	}

	/**
	 * Test format_multi_select with multiple items.
	 */
	public function test_format_multi_select(): void {
		$value = array(
			array(
				'name'  => 'Tag1',
				'color' => 'blue',
			),
			array(
				'name'  => 'Tag2',
				'color' => 'green',
			),
		);

		$result = $this->formatter->format( 'multi_select', $value );

		$this->assertStringContainsString( 'Tag1', $result );
		$this->assertStringContainsString( 'Tag2', $result );
		$this->assertStringContainsString( 'notion-blue', $result );
		$this->assertStringContainsString( 'notion-green', $result );
	}

	/**
	 * Test format_status.
	 */
	public function test_format_status(): void {
		$value  = array(
			'name'  => 'Done',
			'color' => 'green',
		);
		$result = $this->formatter->format( 'status', $value );

		$this->assertStringContainsString( 'notion-status', $result );
		$this->assertStringContainsString( 'notion-green', $result );
		$this->assertStringContainsString( 'Done', $result );
	}

	/**
	 * Test format_checkbox.
	 */
	public function test_format_checkbox(): void {
		$result = $this->formatter->format( 'checkbox', true );
		$this->assertTrue( $result );

		$result = $this->formatter->format( 'checkbox', false );
		$this->assertFalse( $result );
	}

	/**
	 * Test format_date with start only.
	 */
	public function test_format_date_start_only(): void {
		$value = array(
			'start' => '2025-11-15',
		);

		$result = $this->formatter->format( 'date', $value );
		$this->assertNotEmpty( $result );
	}

	/**
	 * Test format_date with date range.
	 */
	public function test_format_date_range(): void {
		$value = array(
			'start' => '2025-11-15',
			'end'   => '2025-11-20',
		);

		$result = $this->formatter->format( 'date', $value );
		$this->assertNotEmpty( $result );
		$this->assertStringContainsString( '→', $result );
	}

	/**
	 * Test format_date with datetime.
	 */
	public function test_format_date_with_time(): void {
		$value = array(
			'start' => '2025-11-15T15:45:00.000Z',
		);

		$result = $this->formatter->format( 'date', $value );
		$this->assertNotEmpty( $result );
	}

	/**
	 * Test format_timestamp (created_time, last_edited_time).
	 */
	public function test_format_timestamp(): void {
		$result = $this->formatter->format( 'created_time', '2025-11-15T15:45:00.000Z' );
		$this->assertNotEmpty( $result );
	}

	/**
	 * Test format_url.
	 */
	public function test_format_url(): void {
		$result = $this->formatter->format( 'url', 'https://example.com' );

		$this->assertStringContainsString( '<a href=', $result );
		$this->assertStringContainsString( 'https://example.com', $result );
		$this->assertStringContainsString( 'notion-url', $result );
	}

	/**
	 * Test format_email.
	 */
	public function test_format_email(): void {
		$result = $this->formatter->format( 'email', 'test@example.com' );

		$this->assertStringContainsString( 'mailto:', $result );
		$this->assertStringContainsString( 'test@example.com', $result );
		$this->assertStringContainsString( 'notion-email', $result );
	}

	/**
	 * Test format_phone.
	 */
	public function test_format_phone(): void {
		$result = $this->formatter->format( 'phone_number', '+1 (123) 456-7890' );

		$this->assertStringContainsString( 'tel:', $result );
		$this->assertStringContainsString( 'notion-phone', $result );
	}

	/**
	 * Test format_people with single person.
	 */
	public function test_format_people_single(): void {
		$value = array(
			array(
				'name' => 'John Doe',
			),
		);

		$result = $this->formatter->format( 'people', $value );
		$this->assertStringContainsString( 'John Doe', $result );
	}

	/**
	 * Test format_people with avatar.
	 */
	public function test_format_people_with_avatar(): void {
		$value = array(
			array(
				'name'       => 'John Doe',
				'avatar_url' => 'https://example.com/avatar.jpg',
			),
		);

		$result = $this->formatter->format( 'people', $value );

		$this->assertStringContainsString( 'John Doe', $result );
		$this->assertStringContainsString( '<img', $result );
		$this->assertStringContainsString( 'notion-avatar', $result );
	}

	/**
	 * Test format_files with single file.
	 */
	public function test_format_files(): void {
		$value = array(
			array(
				'name' => 'document.pdf',
				'url'  => 'https://example.com/document.pdf',
			),
		);

		$result = $this->formatter->format( 'files', $value );

		$this->assertStringContainsString( 'document.pdf', $result );
		$this->assertStringContainsString( '<a href=', $result );
		$this->assertStringContainsString( 'notion-file', $result );
	}

	/**
	 * Test format_relation.
	 */
	public function test_format_relation(): void {
		$value = array(
			array( 'id' => 'page-1' ),
			array( 'id' => 'page-2' ),
			array( 'id' => 'page-3' ),
		);

		$result = $this->formatter->format( 'relation', $value );

		$this->assertStringContainsString( '3', $result );
		$this->assertStringContainsString( 'relations', $result );
		$this->assertStringContainsString( 'notion-relation', $result );
	}

	/**
	 * Test format_rollup with number.
	 */
	public function test_format_rollup_number(): void {
		$value = array(
			'type'   => 'number',
			'number' => 42.5,
		);

		$result = $this->formatter->format( 'rollup', $value );
		$this->assertSame( '42.50', $result );
	}

	/**
	 * Test format_rollup with date.
	 */
	public function test_format_rollup_date(): void {
		$value = array(
			'type' => 'date',
			'date' => array(
				'start' => '2025-11-15',
			),
		);

		$result = $this->formatter->format( 'rollup', $value );
		$this->assertNotEmpty( $result );
	}

	/**
	 * Test format_rollup with array.
	 */
	public function test_format_rollup_array(): void {
		$value = array(
			'type'  => 'array',
			'array' => array( 'A', 'B', 'C' ),
		);

		$result = $this->formatter->format( 'rollup', $value );
		$this->assertSame( 'A, B, C', $result );
	}

	/**
	 * Test format_formula with string.
	 */
	public function test_format_formula_string(): void {
		$value = array(
			'type'   => 'string',
			'string' => 'Result',
		);

		$result = $this->formatter->format( 'formula', $value );
		$this->assertSame( 'Result', $result );
	}

	/**
	 * Test format_formula with number.
	 */
	public function test_format_formula_number(): void {
		$value = array(
			'type'   => 'number',
			'number' => 123.45,
		);

		$result = $this->formatter->format( 'formula', $value );
		$this->assertSame( '123.45', $result );
	}

	/**
	 * Test format_formula with boolean.
	 */
	public function test_format_formula_boolean(): void {
		$value = array(
			'type'    => 'boolean',
			'boolean' => true,
		);

		$result = $this->formatter->format( 'formula', $value );
		$this->assertSame( '✓', $result );

		$value = array(
			'type'    => 'boolean',
			'boolean' => false,
		);

		$result = $this->formatter->format( 'formula', $value );
		$this->assertSame( '✗', $result );
	}

	/**
	 * Test get_column_config for text type.
	 */
	public function test_get_column_config_text(): void {
		$config = $this->formatter->get_column_config( 'text', 'properties.Name', 'Name' );

		$this->assertSame( 'properties.Name', $config['field'] );
		$this->assertSame( 'Name', $config['title'] );
		$this->assertSame( 250, $config['width'] );
		$this->assertSame( 'html', $config['formatter'] );
	}

	/**
	 * Test get_column_config for number type.
	 */
	public function test_get_column_config_number(): void {
		$config = $this->formatter->get_column_config( 'number', 'properties.Amount', 'Amount' );

		$this->assertSame( 'properties.Amount', $config['field'] );
		$this->assertSame( 'Amount', $config['title'] );
		$this->assertSame( 120, $config['width'] );
		$this->assertSame( 'number', $config['sorter'] );
		$this->assertSame( 'right', $config['hozAlign'] );
		$this->assertSame( 'money', $config['formatter'] );
	}

	/**
	 * Test get_column_config for select type.
	 */
	public function test_get_column_config_select(): void {
		$config = $this->formatter->get_column_config( 'select', 'properties.Status', 'Status' );

		$this->assertSame( 'properties.Status', $config['field'] );
		$this->assertSame( 'Status', $config['title'] );
		$this->assertSame( 150, $config['width'] );
		$this->assertSame( 'html', $config['formatter'] );
		$this->assertSame( 'list', $config['headerFilter'] );
	}

	/**
	 * Test get_column_config for checkbox type.
	 */
	public function test_get_column_config_checkbox(): void {
		$config = $this->formatter->get_column_config( 'checkbox', 'properties.Done', 'Done' );

		$this->assertSame( 'properties.Done', $config['field'] );
		$this->assertSame( 'Done', $config['title'] );
		$this->assertSame( 100, $config['width'] );
		$this->assertSame( 'tickCross', $config['formatter'] );
		$this->assertSame( 'center', $config['hozAlign'] );
	}

	/**
	 * Test get_column_config for date type.
	 */
	public function test_get_column_config_date(): void {
		$config = $this->formatter->get_column_config( 'date', 'properties.DueDate', 'Due Date' );

		$this->assertSame( 'properties.DueDate', $config['field'] );
		$this->assertSame( 'Due Date', $config['title'] );
		$this->assertSame( 160, $config['width'] );
		$this->assertSame( 'datetime', $config['sorter'] );
		$this->assertSame( 'datetime', $config['formatter'] );
	}

	/**
	 * Test format with unsupported type returns as-is.
	 */
	public function test_format_unsupported_type(): void {
		$result = $this->formatter->format( 'unknown_type', 'some value' );
		$this->assertSame( 'some value', $result );
	}

	/**
	 * Test format escapes HTML in text.
	 */
	public function test_format_escapes_malicious_text(): void {
		$result = $this->formatter->format( 'text', '<script>alert("XSS")</script>' );
		$this->assertStringNotContainsString( '<script>', $result );
	}
}
