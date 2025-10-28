<?php
/**
 * Tests for Table Block Converter
 *
 * Tests conversion of Notion table blocks to WordPress Gutenberg table blocks.
 * Covers column headers, row headers, rich text formatting, and edge cases.
 *
 * @package NotionWP
 * @since 1.0.0
 */

namespace NotionWP\Tests\Unit\Blocks\Converters;

use NotionSync\Blocks\Converters\TableConverter;
use Brain\Monkey\Functions;

/**
 * Test TableConverter functionality
 */
class TableConverterTest extends BaseConverterTestCase {

	/**
	 * Table converter instance
	 *
	 * @var TableConverter
	 */
	private TableConverter $converter;

	/**
	 * Set up test environment
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->converter = new TableConverter();
	}

	/**
	 * Test supports method returns true for table blocks
	 */
	public function test_supports_table_block_type(): void {
		$table_block = array( 'type' => 'table' );
		$this->assertTrue( $this->converter->supports( $table_block ) );

		$paragraph_block = array( 'type' => 'paragraph' );
		$this->assertFalse( $this->converter->supports( $paragraph_block ) );
	}

	/**
	 * Test converting simple table with no headers
	 */
	public function test_converts_simple_table_without_headers(): void {
		$notion_block = array(
			'type'  => 'table',
			'table' => array(
				'has_column_header' => false,
				'has_row_header'    => false,
			),
			'children' => array(
				array(
					'type'      => 'table_row',
					'table_row' => array(
						'cells' => array(
							array(
								array(
									'type'       => 'text',
									'plain_text' => 'Cell 1',
									'annotations' => array(),
								),
							),
							array(
								array(
									'type'       => 'text',
									'plain_text' => 'Cell 2',
									'annotations' => array(),
								),
							),
						),
					),
				),
				array(
					'type'      => 'table_row',
					'table_row' => array(
						'cells' => array(
							array(
								array(
									'type'       => 'text',
									'plain_text' => 'Cell 3',
									'annotations' => array(),
								),
							),
							array(
								array(
									'type'       => 'text',
									'plain_text' => 'Cell 4',
									'annotations' => array(),
								),
							),
						),
					),
				),
			),
		);

		$result = $this->converter->convert( $notion_block );

		// Should contain table block wrapper
		$this->assertStringContainsString( '<!-- wp:table -->', $result );
		$this->assertStringContainsString( '<!-- /wp:table -->', $result );

		// Should contain table HTML
		$this->assertStringContainsString( '<figure class="wp-block-table">', $result );
		$this->assertStringContainsString( '<table>', $result );
		$this->assertStringContainsString( '</table></figure>', $result );

		// Should contain all cell content
		$this->assertStringContainsString( 'Cell 1', $result );
		$this->assertStringContainsString( 'Cell 2', $result );
		$this->assertStringContainsString( 'Cell 3', $result );
		$this->assertStringContainsString( 'Cell 4', $result );

		// Should use <td> tags (not <th>) since no headers
		$this->assertStringContainsString( '<td>Cell 1</td>', $result );
	}

	/**
	 * Test converting table with column header
	 */
	public function test_converts_table_with_column_header(): void {
		$notion_block = array(
			'type'  => 'table',
			'table' => array(
				'has_column_header' => true,
				'has_row_header'    => false,
			),
			'children' => array(
				array(
					'type'      => 'table_row',
					'table_row' => array(
						'cells' => array(
							array(
								array(
									'type'       => 'text',
									'plain_text' => 'Header 1',
									'annotations' => array(),
								),
							),
							array(
								array(
									'type'       => 'text',
									'plain_text' => 'Header 2',
									'annotations' => array(),
								),
							),
						),
					),
				),
				array(
					'type'      => 'table_row',
					'table_row' => array(
						'cells' => array(
							array(
								array(
									'type'       => 'text',
									'plain_text' => 'Cell 1',
									'annotations' => array(),
								),
							),
							array(
								array(
									'type'       => 'text',
									'plain_text' => 'Cell 2',
									'annotations' => array(),
								),
							),
						),
					),
				),
			),
		);

		$result = $this->converter->convert( $notion_block );

		// Should contain thead for header row
		$this->assertStringContainsString( '<thead>', $result );
		$this->assertStringContainsString( '</thead>', $result );

		// Should contain tbody for data rows
		$this->assertStringContainsString( '<tbody>', $result );
		$this->assertStringContainsString( '</tbody>', $result );

		// Header cells should use <th>
		$this->assertStringContainsString( '<th>Header 1</th>', $result );
		$this->assertStringContainsString( '<th>Header 2</th>', $result );

		// Data cells should use <td>
		$this->assertStringContainsString( '<td>Cell 1</td>', $result );
		$this->assertStringContainsString( '<td>Cell 2</td>', $result );
	}

	/**
	 * Test converting table with row header
	 */
	public function test_converts_table_with_row_header(): void {
		$notion_block = array(
			'type'  => 'table',
			'table' => array(
				'has_column_header' => false,
				'has_row_header'    => true,
			),
			'children' => array(
				array(
					'type'      => 'table_row',
					'table_row' => array(
						'cells' => array(
							array(
								array(
									'type'       => 'text',
									'plain_text' => 'Row 1 Header',
									'annotations' => array(),
								),
							),
							array(
								array(
									'type'       => 'text',
									'plain_text' => 'Cell 1',
									'annotations' => array(),
								),
							),
							array(
								array(
									'type'       => 'text',
									'plain_text' => 'Cell 2',
									'annotations' => array(),
								),
							),
						),
					),
				),
			),
		);

		$result = $this->converter->convert( $notion_block );

		// First cell should be <th> (row header)
		$this->assertStringContainsString( '<th>Row 1 Header</th>', $result );

		// Other cells should be <th> as well (due to has_row_header)
		$this->assertStringContainsString( '<th>Cell 1</th>', $result );
		$this->assertStringContainsString( '<th>Cell 2</th>', $result );
	}

	/**
	 * Test converting table with both column and row headers
	 */
	public function test_converts_table_with_both_headers(): void {
		$notion_block = array(
			'type'  => 'table',
			'table' => array(
				'has_column_header' => true,
				'has_row_header'    => true,
			),
			'children' => array(
				array(
					'type'      => 'table_row',
					'table_row' => array(
						'cells' => array(
							array(
								array(
									'type'       => 'text',
									'plain_text' => 'Corner',
									'annotations' => array(),
								),
							),
							array(
								array(
									'type'       => 'text',
									'plain_text' => 'Col 1',
									'annotations' => array(),
								),
							),
						),
					),
				),
				array(
					'type'      => 'table_row',
					'table_row' => array(
						'cells' => array(
							array(
								array(
									'type'       => 'text',
									'plain_text' => 'Row 1',
									'annotations' => array(),
								),
							),
							array(
								array(
									'type'       => 'text',
									'plain_text' => 'Data',
									'annotations' => array(),
								),
							),
						),
					),
				),
			),
		);

		$result = $this->converter->convert( $notion_block );

		// Should contain thead
		$this->assertStringContainsString( '<thead>', $result );

		// All header row cells should be <th>
		$this->assertStringContainsString( '<th>Corner</th>', $result );
		$this->assertStringContainsString( '<th>Col 1</th>', $result );

		// First cell of body row should be <th> (row header)
		$this->assertStringContainsString( '<th>Row 1</th>', $result );

		// Other cells in body should be <th> due to has_row_header
		$this->assertStringContainsString( '<th>Data</th>', $result );
	}

	/**
	 * Test converting table with formatted text
	 */
	public function test_converts_table_with_formatted_text(): void {
		$notion_block = array(
			'type'  => 'table',
			'table' => array(
				'has_column_header' => false,
				'has_row_header'    => false,
			),
			'children' => array(
				array(
					'type'      => 'table_row',
					'table_row' => array(
						'cells' => array(
							array(
								array(
									'type'        => 'text',
									'plain_text'  => 'Bold text',
									'annotations' => array( 'bold' => true ),
								),
							),
							array(
								array(
									'type'        => 'text',
									'plain_text'  => 'Italic text',
									'annotations' => array( 'italic' => true ),
								),
							),
						),
					),
				),
			),
		);

		$result = $this->converter->convert( $notion_block );

		// Should contain formatted text
		$this->assertStringContainsString( '<strong>Bold text</strong>', $result );
		$this->assertStringContainsString( '<em>Italic text</em>', $result );
	}

	/**
	 * Test converting table with links
	 */
	public function test_converts_table_with_links(): void {
		$notion_block = array(
			'type'  => 'table',
			'table' => array(
				'has_column_header' => false,
				'has_row_header'    => false,
			),
			'children' => array(
				array(
					'type'      => 'table_row',
					'table_row' => array(
						'cells' => array(
							array(
								array(
									'type'        => 'text',
									'plain_text'  => 'Click here',
									'href'        => 'https://example.com',
									'annotations' => array(),
								),
							),
						),
					),
				),
			),
		);

		Functions\expect( 'esc_url' )->andReturnUsing( fn( $url ) => $url );

		$result = $this->converter->convert( $notion_block );

		// Should contain link
		$this->assertStringContainsString( '<a href="https://example.com">Click here</a>', $result );
	}

	/**
	 * Test empty table returns placeholder
	 */
	public function test_empty_table_returns_placeholder(): void {
		$notion_block = array(
			'type'  => 'table',
			'table' => array(
				'has_column_header' => false,
				'has_row_header'    => false,
			),
			'children' => array(), // No rows
		);

		$result = $this->converter->convert( $notion_block );

		// Should return placeholder paragraph
		$this->assertStringContainsString( '<!-- wp:paragraph -->', $result );
		$this->assertStringContainsString( '[Table with no content]', $result );
	}

	/**
	 * Test table escapes HTML in cell content
	 */
	public function test_escapes_html_in_table_cells(): void {
		$notion_block = array(
			'type'  => 'table',
			'table' => array(
				'has_column_header' => false,
				'has_row_header'    => false,
			),
			'children' => array(
				array(
					'type'      => 'table_row',
					'table_row' => array(
						'cells' => array(
							array(
								array(
									'type'        => 'text',
									'plain_text'  => '<script>alert("xss")</script>',
									'annotations' => array(),
								),
							),
						),
					),
				),
			),
		);

		$result = $this->converter->convert( $notion_block );

		// Should NOT contain unescaped script tag
		$this->assertStringNotContainsString( '<script>alert("xss")</script>', $result );

		// Should contain escaped content
		$this->assertStringContainsString( '&lt;script&gt;', $result );
	}

	/**
	 * Test table with multiple rich text segments in cell
	 */
	public function test_converts_cell_with_multiple_rich_text_segments(): void {
		$notion_block = array(
			'type'  => 'table',
			'table' => array(
				'has_column_header' => false,
				'has_row_header'    => false,
			),
			'children' => array(
				array(
					'type'      => 'table_row',
					'table_row' => array(
						'cells' => array(
							array(
								array(
									'type'        => 'text',
									'plain_text'  => 'Normal ',
									'annotations' => array(),
								),
								array(
									'type'        => 'text',
									'plain_text'  => 'bold ',
									'annotations' => array( 'bold' => true ),
								),
								array(
									'type'        => 'text',
									'plain_text'  => 'italic',
									'annotations' => array( 'italic' => true ),
								),
							),
						),
					),
				),
			),
		);

		$result = $this->converter->convert( $notion_block );

		// Should contain all segments with proper formatting
		$this->assertStringContainsString( 'Normal', $result );
		$this->assertStringContainsString( '<strong>bold</strong>', $result );
		$this->assertStringContainsString( '<em>italic</em>', $result );
	}

	/**
	 * Test table ignores non-table_row children
	 */
	public function test_ignores_non_table_row_children(): void {
		$notion_block = array(
			'type'  => 'table',
			'table' => array(
				'has_column_header' => false,
				'has_row_header'    => false,
			),
			'children' => array(
				array(
					'type' => 'paragraph', // Wrong type
				),
				array(
					'type'      => 'table_row',
					'table_row' => array(
						'cells' => array(
							array(
								array(
									'type'       => 'text',
									'plain_text' => 'Valid cell',
									'annotations' => array(),
								),
							),
						),
					),
				),
			),
		);

		$result = $this->converter->convert( $notion_block );

		// Should only contain valid table row
		$this->assertStringContainsString( 'Valid cell', $result );

		// Should only have one row
		$this->assertEquals( 1, substr_count( $result, '<tr>' ) );
	}

	/**
	 * Test table with strikethrough and code formatting
	 */
	public function test_converts_table_with_strikethrough_and_code(): void {
		$notion_block = array(
			'type'  => 'table',
			'table' => array(
				'has_column_header' => false,
				'has_row_header'    => false,
			),
			'children' => array(
				array(
					'type'      => 'table_row',
					'table_row' => array(
						'cells' => array(
							array(
								array(
									'type'        => 'text',
									'plain_text'  => 'deleted',
									'annotations' => array( 'strikethrough' => true ),
								),
							),
							array(
								array(
									'type'        => 'text',
									'plain_text'  => 'code snippet',
									'annotations' => array( 'code' => true ),
								),
							),
						),
					),
				),
			),
		);

		$result = $this->converter->convert( $notion_block );

		// Should contain strikethrough formatting
		$this->assertStringContainsString( '<s>deleted</s>', $result );

		// Should contain code formatting
		$this->assertStringContainsString( '<code>code snippet</code>', $result );
	}

	/**
	 * Test table row with empty cells array
	 */
	public function test_skips_table_row_with_empty_cells(): void {
		$notion_block = array(
			'type'  => 'table',
			'table' => array(
				'has_column_header' => false,
				'has_row_header'    => false,
			),
			'children' => array(
				array(
					'type'      => 'table_row',
					'table_row' => array(
						'cells' => array(), // Empty cells
					),
				),
				array(
					'type'      => 'table_row',
					'table_row' => array(
						'cells' => array(
							array(
								array(
									'type'       => 'text',
									'plain_text' => 'Valid',
									'annotations' => array(),
								),
							),
						),
					),
				),
			),
		);

		$result = $this->converter->convert( $notion_block );

		// Should only contain one valid row
		$this->assertEquals( 1, substr_count( $result, '<tr>' ) );
		$this->assertStringContainsString( 'Valid', $result );
	}
}
