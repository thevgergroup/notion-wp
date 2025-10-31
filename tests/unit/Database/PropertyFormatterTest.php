<?php
/**
 * Tests for PropertyFormatter
 *
 * @package NotionSync
 */

namespace NotionSync\Tests\Unit\Database;

use NotionSync\Database\PropertyFormatter;
use NotionSync\Database\RichTextConverter;
use WP_Mock\Tools\TestCase;

/**
 * Class PropertyFormatterTest
 *
 * @covers \NotionSync\Database\PropertyFormatter
 */
class PropertyFormatterTest extends TestCase {

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
		\WP_Mock::setUp();
		$this->formatter = new PropertyFormatter();
	}

	/**
	 * Tear down test environment.
	 */
	public function tearDown(): void {
		\WP_Mock::tearDown();
		parent::tearDown();
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
		\WP_Mock::userFunction( 'esc_html' )
			->once()
			->with( 'Plain text' )
			->andReturn( 'Plain text' );

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
		\WP_Mock::userFunction( 'esc_html' )
			->once()
			->with( 'In Progress' )
			->andReturn( 'In Progress' );

		\WP_Mock::userFunction( 'sanitize_html_class' )
			->once()
			->with( 'blue' )
			->andReturn( 'blue' );

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
		\WP_Mock::userFunction( 'esc_html' )
			->times( 2 )
			->andReturnUsing(
				function ( $text ) {
					return $text;
				}
			);

		\WP_Mock::userFunction( 'sanitize_html_class' )
			->times( 2 )
			->andReturnUsing(
				function ( $class ) {
					return $class;
				}
			);

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
		\WP_Mock::userFunction( 'esc_html' )
			->once()
			->with( 'Done' )
			->andReturn( 'Done' );

		\WP_Mock::userFunction( 'sanitize_html_class' )
			->once()
			->with( 'green' )
			->andReturn( 'green' );

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
		\WP_Mock::userFunction( 'date_i18n' )
			->once()
			->andReturn( 'Nov 15, 2025' );

		$value = array(
			'start' => '2025-11-15',
		);

		$result = $this->formatter->format( 'date', $value );
		$this->assertSame( 'Nov 15, 2025', $result );
	}

	/**
	 * Test format_date with date range.
	 */
	public function test_format_date_range(): void {
		\WP_Mock::userFunction( 'date_i18n' )
			->twice()
			->andReturn( 'Nov 15, 2025', 'Nov 20, 2025' );

		$value = array(
			'start' => '2025-11-15',
			'end'   => '2025-11-20',
		);

		$result = $this->formatter->format( 'date', $value );
		$this->assertStringContainsString( 'Nov 15, 2025', $result );
		$this->assertStringContainsString( 'Nov 20, 2025', $result );
		$this->assertStringContainsString( '→', $result );
	}

	/**
	 * Test format_date with datetime.
	 */
	public function test_format_date_with_time(): void {
		\WP_Mock::userFunction( 'date_i18n' )
			->once()
			->andReturn( 'Nov 15, 2025 3:45 PM' );

		$value = array(
			'start' => '2025-11-15T15:45:00.000Z',
		);

		$result = $this->formatter->format( 'date', $value );
		$this->assertStringContainsString( 'Nov 15, 2025 3:45 PM', $result );
	}

	/**
	 * Test format_timestamp (created_time, last_edited_time).
	 */
	public function test_format_timestamp(): void {
		\WP_Mock::userFunction( 'date_i18n' )
			->once()
			->andReturn( 'Nov 15, 2025 3:45 PM' );

		$result = $this->formatter->format( 'created_time', '2025-11-15T15:45:00.000Z' );
		$this->assertStringContainsString( 'Nov 15, 2025 3:45 PM', $result );
	}

	/**
	 * Test format_url.
	 */
	public function test_format_url(): void {
		\WP_Mock::userFunction( 'esc_url' )
			->once()
			->with( 'https://example.com' )
			->andReturn( 'https://example.com' );

		\WP_Mock::userFunction( 'esc_html' )
			->once()
			->with( 'https://example.com' )
			->andReturn( 'https://example.com' );

		$result = $this->formatter->format( 'url', 'https://example.com' );

		$this->assertStringContainsString( '<a href=', $result );
		$this->assertStringContainsString( 'https://example.com', $result );
		$this->assertStringContainsString( 'notion-url', $result );
	}

	/**
	 * Test format_email.
	 */
	public function test_format_email(): void {
		\WP_Mock::userFunction( 'is_email' )
			->once()
			->with( 'test@example.com' )
			->andReturn( true );

		\WP_Mock::userFunction( 'esc_attr' )
			->once()
			->with( 'test@example.com' )
			->andReturn( 'test@example.com' );

		\WP_Mock::userFunction( 'esc_html' )
			->once()
			->with( 'test@example.com' )
			->andReturn( 'test@example.com' );

		$result = $this->formatter->format( 'email', 'test@example.com' );

		$this->assertStringContainsString( 'mailto:', $result );
		$this->assertStringContainsString( 'test@example.com', $result );
		$this->assertStringContainsString( 'notion-email', $result );
	}

	/**
	 * Test format_phone.
	 */
	public function test_format_phone(): void {
		\WP_Mock::userFunction( 'esc_attr' )
			->once()
			->with( '+11234567890' )
			->andReturn( '+11234567890' );

		\WP_Mock::userFunction( 'esc_html' )
			->once()
			->with( '+1 (123) 456-7890' )
			->andReturn( '+1 (123) 456-7890' );

		$result = $this->formatter->format( 'phone_number', '+1 (123) 456-7890' );

		$this->assertStringContainsString( 'tel:', $result );
		$this->assertStringContainsString( 'notion-phone', $result );
	}

	/**
	 * Test format_people with single person.
	 */
	public function test_format_people_single(): void {
		\WP_Mock::userFunction( 'esc_html' )
			->once()
			->with( 'John Doe' )
			->andReturn( 'John Doe' );

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
		\WP_Mock::userFunction( 'esc_html' )
			->once()
			->with( 'John Doe' )
			->andReturn( 'John Doe' );

		\WP_Mock::userFunction( 'esc_url' )
			->once()
			->with( 'https://example.com/avatar.jpg' )
			->andReturn( 'https://example.com/avatar.jpg' );

		\WP_Mock::userFunction( 'esc_attr' )
			->once()
			->with( 'John Doe' )
			->andReturn( 'John Doe' );

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
		\WP_Mock::userFunction( 'esc_html' )
			->once()
			->with( 'document.pdf' )
			->andReturn( 'document.pdf' );

		\WP_Mock::userFunction( 'esc_url' )
			->once()
			->with( 'https://example.com/document.pdf' )
			->andReturn( 'https://example.com/document.pdf' );

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
		\WP_Mock::userFunction( '_n' )
			->once()
			->with( 'relation', 'relations', 3, 'notion-wp' )
			->andReturn( 'relations' );

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
		\WP_Mock::userFunction( 'date_i18n' )
			->once()
			->andReturn( 'Nov 15, 2025' );

		$value = array(
			'type' => 'date',
			'date' => array(
				'start' => '2025-11-15',
			),
		);

		$result = $this->formatter->format( 'rollup', $value );
		$this->assertStringContainsString( 'Nov 15, 2025', $result );
	}

	/**
	 * Test format_rollup with array.
	 */
	public function test_format_rollup_array(): void {
		\WP_Mock::userFunction( 'esc_html' )
			->once()
			->with( 'A, B, C' )
			->andReturn( 'A, B, C' );

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
		\WP_Mock::userFunction( 'esc_html' )
			->once()
			->with( 'Result' )
			->andReturn( 'Result' );

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
		\WP_Mock::userFunction( 'esc_html' )
			->once()
			->with( 'some value' )
			->andReturn( 'some value' );

		$result = $this->formatter->format( 'unknown_type', 'some value' );
		$this->assertSame( 'some value', $result );
	}

	/**
	 * Test format escapes HTML in text.
	 */
	public function test_format_escapes_malicious_text(): void {
		\WP_Mock::userFunction( 'esc_html' )
			->once()
			->with( '<script>alert("XSS")</script>' )
			->andReturn( '&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;' );

		$result = $this->formatter->format( 'text', '<script>alert("XSS")</script>' );
		$this->assertStringNotContainsString( '<script>', $result );
	}
}
