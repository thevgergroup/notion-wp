<?php
/**
 * Test Fixtures for Hierarchy and Menu Tests
 *
 * Provides reusable test data including Notion page IDs in different formats,
 * mock WordPress posts, and hierarchy structures.
 *
 * @package NotionWP\Tests\Fixtures
 */

declare(strict_types=1);

namespace NotionWP\Tests\Fixtures;

/**
 * Class HierarchyTestFixtures
 *
 * Centralized test fixtures for hierarchy and menu sync testing.
 * Includes fixtures for the ID format bug scenarios.
 */
class HierarchyTestFixtures {
	/**
	 * Get sample Notion page IDs in various formats
	 *
	 * Returns IDs in both normalized (no dashes) and UUID (with dashes) formats
	 * to test ID format compatibility.
	 *
	 * @return array<string, string> Array of page IDs by name.
	 */
	public static function get_notion_page_ids(): array {
		return array(
			// Root pages
			'root_1_normalized'   => '2634dac9b96e813da15efd85567b68ff',
			'root_1_with_dashes'  => '2634dac9-b96e-813d-a15e-fd85567b68ff',
			'root_2_normalized'   => 'abc123def456789012345678901234ab',
			'root_2_with_dashes'  => 'abc123de-f456-7890-1234-5678901234ab',

			// Child pages
			'child_1_normalized'  => '1111a222b333c444d555e666f777g888',
			'child_1_with_dashes' => '1111a222-b333-c444-d555-e666f777g888',
			'child_2_normalized'  => '9999h888i777j666k555l444m333n222',
			'child_2_with_dashes' => '9999h888-i777-j666-k555-l444m333n222',

			// Grandchild pages
			'grandchild_1_normalized'  => 'aaaabbbbccccddddeeeeffffgggghhh',
			'grandchild_1_with_dashes' => 'aaaabbbb-cccc-dddd-eeee-ffffgggghhhh',
		);
	}

	/**
	 * Create a mock WordPress post object
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $title      Post title.
	 * @param int    $menu_order Menu order.
	 * @param int    $parent_id  Parent post ID (0 for root).
	 * @return \stdClass Mock post object.
	 */
	public static function create_mock_post( int $post_id, string $title, int $menu_order = 0, int $parent_id = 0 ): \stdClass {
		return (object) array(
			'ID'          => $post_id,
			'post_title'  => $title,
			'post_type'   => 'page',
			'post_status' => 'publish',
			'menu_order'  => $menu_order,
			'post_parent' => $parent_id,
		);
	}

	/**
	 * Get single root page hierarchy (no children)
	 *
	 * @return array Hierarchy map array.
	 */
	public static function get_single_root_hierarchy(): array {
		return array(
			'2634dac9b96e813da15efd85567b68ff' => array(
				'post_id'         => 100,
				'parent_page_id'  => null,
				'parent_post_id'  => null,
				'title'           => 'Root Page',
				'order'           => 0,
				'children'        => array(),
			),
		);
	}

	/**
	 * Get two-level hierarchy (root with children)
	 *
	 * @return array Hierarchy map array.
	 */
	public static function get_two_level_hierarchy(): array {
		return array(
			'2634dac9b96e813da15efd85567b68ff' => array(
				'post_id'         => 100,
				'parent_page_id'  => null,
				'parent_post_id'  => null,
				'title'           => 'Root Page',
				'order'           => 0,
				'children'        => array(
					'1111a222b333c444d555e666f777g888',
					'9999h888i777j666k555l444m333n222',
				),
			),
			'1111a222b333c444d555e666f777g888' => array(
				'post_id'         => 101,
				'parent_page_id'  => '2634dac9b96e813da15efd85567b68ff',
				'parent_post_id'  => 100,
				'title'           => 'Child 1',
				'order'           => 0,
				'children'        => array(),
			),
			'9999h888i777j666k555l444m333n222' => array(
				'post_id'         => 102,
				'parent_page_id'  => '2634dac9b96e813da15efd85567b68ff',
				'parent_post_id'  => 100,
				'title'           => 'Child 2',
				'order'           => 1,
				'children'        => array(),
			),
		);
	}

	/**
	 * Get three-level hierarchy (root -> child -> grandchild)
	 *
	 * @return array Hierarchy map array.
	 */
	public static function get_three_level_hierarchy(): array {
		return array(
			'2634dac9b96e813da15efd85567b68ff' => array(
				'post_id'         => 100,
				'parent_page_id'  => null,
				'parent_post_id'  => null,
				'title'           => 'Root Page',
				'order'           => 0,
				'children'        => array( '1111p222q333r444s555t666u777v888' ),
			),
			'1111p222q333r444s555t666u777v888' => array(
				'post_id'         => 101,
				'parent_page_id'  => '2634dac9b96e813da15efd85567b68ff',
				'parent_post_id'  => 100,
				'title'           => 'Child Page',
				'order'           => 0,
				'children'        => array( 'aaaabbbbccccddddeeeeffffgggghhh' ),
			),
			'aaaabbbbccccddddeeeeffffgggghhh' => array(
				'post_id'         => 102,
				'parent_page_id'  => '1111p222q333r444s555t666u777v888',
				'parent_post_id'  => 101,
				'title'           => 'Grandchild Page',
				'order'           => 0,
				'children'        => array(),
			),
		);
	}

	/**
	 * Get multiple root pages hierarchy
	 *
	 * Represents multiple separate trees (e.g., different documentation sections).
	 *
	 * @return array Hierarchy map array.
	 */
	public static function get_multiple_roots_hierarchy(): array {
		return array(
			// First tree
			'2634dac9b96e813da15efd85567b68ff' => array(
				'post_id'         => 100,
				'parent_page_id'  => null,
				'parent_post_id'  => null,
				'title'           => 'Documentation',
				'order'           => 0,
				'children'        => array( 'aaaa1111bbbb2222cccc3333dddd4444' ),
			),
			'aaaa1111bbbb2222cccc3333dddd4444' => array(
				'post_id'         => 101,
				'parent_page_id'  => '2634dac9b96e813da15efd85567b68ff',
				'parent_post_id'  => 100,
				'title'           => 'Getting Started',
				'order'           => 0,
				'children'        => array(),
			),

			// Second tree
			'abc123def456789012345678901234ab' => array(
				'post_id'         => 200,
				'parent_page_id'  => null,
				'parent_post_id'  => null,
				'title'           => 'API Reference',
				'order'           => 1,
				'children'        => array( 'eeee5555ffff6666gggg7777hhhh8888' ),
			),
			'eeee5555ffff6666gggg7777hhhh8888' => array(
				'post_id'         => 201,
				'parent_page_id'  => 'abc123def456789012345678901234ab',
				'parent_post_id'  => 200,
				'title'           => 'Endpoints',
				'order'           => 0,
				'children'        => array(),
			),
		);
	}

	/**
	 * Create Notion page properties with parent information
	 *
	 * @param string $parent_page_id Parent Notion page ID.
	 * @param string $parent_type    Parent type (default: 'page_id').
	 * @return array Page properties array.
	 */
	public static function create_page_properties_with_parent( string $parent_page_id, string $parent_type = 'page_id' ): array {
		return array(
			'parent' => array(
				'type'    => $parent_type,
				$parent_type => $parent_page_id,
			),
			'title'  => array(
				array( 'plain_text' => 'Test Page' ),
			),
		);
	}

	/**
	 * Create Notion page properties without parent (root page)
	 *
	 * @return array Page properties array.
	 */
	public static function create_root_page_properties(): array {
		return array(
			'parent' => array(
				'type'         => 'workspace',
				'workspace'    => true,
			),
			'title'  => array(
				array( 'plain_text' => 'Root Page' ),
			),
		);
	}

	/**
	 * Create mock menu items
	 *
	 * @param int  $count       Number of items to create.
	 * @param bool $notion_synced Whether items are Notion-synced.
	 * @return array<\stdClass> Array of mock menu items.
	 */
	public static function create_mock_menu_items( int $count, bool $notion_synced = true ): array {
		$items = array();
		for ( $i = 1; $i <= $count; $i++ ) {
			$items[] = (object) array(
				'ID'               => 100 + $i,
				'menu_item_parent' => 0,
				'object_id'        => $i,
				'object'           => 'page',
				'type'             => 'post_type',
				'title'            => "Menu Item $i",
			);
		}
		return $items;
	}

	/**
	 * Get test scenario for ID format bug
	 *
	 * Returns test data demonstrating the bug where parent IDs stored
	 * in different formats (with/without dashes) weren't being matched.
	 *
	 * @return array Test scenario data.
	 */
	public static function get_id_format_bug_scenario(): array {
		$page_ids = self::get_notion_page_ids();

		return array(
			'description' => 'Child pages with parent IDs stored in different formats',
			'parent_id'   => $page_ids['root_1_with_dashes'], // Query uses this
			'children'    => array(
				// Child 1: parent stored WITHOUT dashes (normalized)
				array(
					'post_id'           => 101,
					'notion_page_id'    => $page_ids['child_1_normalized'],
					'parent_stored_as'  => $page_ids['root_1_normalized'], // Stored without dashes
				),
				// Child 2: parent stored WITH dashes
				array(
					'post_id'           => 102,
					'notion_page_id'    => $page_ids['child_2_normalized'],
					'parent_stored_as'  => $page_ids['root_1_with_dashes'], // Stored with dashes
				),
			),
			'expected_result' => 'Both children should be found regardless of ID format',
		);
	}

	/**
	 * Normalize Notion ID (remove dashes)
	 *
	 * @param string $notion_id Notion page ID.
	 * @return string Normalized ID without dashes.
	 */
	public static function normalize_notion_id( string $notion_id ): string {
		return str_replace( '-', '', $notion_id );
	}

	/**
	 * Format Notion ID with dashes (UUID format)
	 *
	 * @param string $notion_id Notion page ID (with or without dashes).
	 * @return string Formatted ID with dashes (8-4-4-4-12 pattern).
	 */
	public static function format_notion_id_with_dashes( string $notion_id ): string {
		$normalized = self::normalize_notion_id( $notion_id );

		if ( strlen( $normalized ) !== 32 ) {
			return $notion_id; // Invalid length, return as-is
		}

		return substr( $normalized, 0, 8 ) . '-' .
				substr( $normalized, 8, 4 ) . '-' .
				substr( $normalized, 12, 4 ) . '-' .
				substr( $normalized, 16, 4 ) . '-' .
				substr( $normalized, 20 );
	}
}
