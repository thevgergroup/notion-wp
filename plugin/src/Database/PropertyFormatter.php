<?php
/**
 * Property Formatter
 *
 * Transforms Notion property values into display-ready formats for frontend rendering.
 * Supports all Notion property types with appropriate formatting and Tabulator column configs.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Database;

/**
 * Class PropertyFormatter
 *
 * Formats Notion properties for display in Tabulator.js tables.
 *
 * @since 1.0.0
 */
class PropertyFormatter {

	/**
	 * Rich text converter instance.
	 *
	 * @var RichTextConverter
	 */
	private $rich_text_converter;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->rich_text_converter = new RichTextConverter();
	}

	/**
	 * Format a Notion property value for display.
	 *
	 * Handles all Notion property types and returns JSON-serializable data
	 * ready for frontend consumption.
	 *
	 * @since 1.0.0
	 *
	 * @param string $property_type Notion property type.
	 * @param mixed  $value         Property value from Notion.
	 * @return mixed Formatted value (string, array, bool, etc.).
	 */
	public function format( string $property_type, $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}

		// Route to specific formatter based on type.
		switch ( $property_type ) {
			case 'title':
				return $this->format_title( $value );

			case 'rich_text':
				return $this->format_rich_text( $value );

			case 'text':
				return $this->format_text( $value );

			case 'number':
				return $this->format_number( $value );

			case 'select':
				return $this->format_select( $value );

			case 'multi_select':
				return $this->format_multi_select( $value );

			case 'status':
				return $this->format_status( $value );

			case 'checkbox':
				return $this->format_checkbox( $value );

			case 'date':
				return $this->format_date( $value );

			case 'created_time':
			case 'last_edited_time':
				return $this->format_timestamp( $value );

			case 'people':
			case 'created_by':
			case 'last_edited_by':
				return $this->format_people( $value );

			case 'files':
				return $this->format_files( $value );

			case 'url':
				return $this->format_url( $value );

			case 'email':
				return $this->format_email( $value );

			case 'phone_number':
				return $this->format_phone( $value );

			case 'relation':
				return $this->format_relation( $value );

			case 'rollup':
				return $this->format_rollup( $value );

			case 'formula':
				return $this->format_formula( $value );

			default:
				// Unsupported type - return as-is or null.
				return is_string( $value ) ? esc_html( $value ) : $value;
		}
	}

	/**
	 * Get Tabulator column configuration for property type.
	 *
	 * Returns column definition suitable for Tabulator.js including
	 * formatter, sorter, filter settings, and display options.
	 *
	 * @since 1.0.0
	 *
	 * @param string $property_type Notion property type.
	 * @param string $field_name    Column field name (e.g., 'properties.Status').
	 * @param string $title         Column display title.
	 * @return array Tabulator column configuration.
	 */
	public function get_column_config( string $property_type, string $field_name, string $title ): array {
		// Base configuration.
		$config = array(
			'field'        => $field_name,
			'title'        => $title,
			'headerFilter' => 'input',
		);

		// Type-specific configuration.
		switch ( $property_type ) {
			case 'title':
			case 'rich_text':
			case 'text':
				$config['width']     = 250;
				$config['formatter'] = 'html';
				break;

			case 'number':
				$config['width']           = 120;
				$config['sorter']          = 'number';
				$config['hozAlign']        = 'right';
				$config['formatter']       = 'money';
				$config['formatterParams'] = array(
					'thousand'    => ',',
					'precision'   => false,
					'symbol'      => '',
					'symbolAfter' => false,
				);
				break;

			case 'select':
			case 'status':
				$config['width']              = 150;
				$config['formatter']          = 'html';
				$config['headerFilter']       = 'list';
				$config['headerFilterParams'] = array( 'valuesLookup' => true );
				break;

			case 'multi_select':
				$config['width']     = 200;
				$config['formatter'] = 'html';
				break;

			case 'checkbox':
				$config['width']              = 100;
				$config['formatter']          = 'tickCross';
				$config['hozAlign']           = 'center';
				$config['headerFilter']       = 'tickCross';
				$config['headerFilterParams'] = array( 'tristate' => true );
				break;

			case 'date':
			case 'created_time':
			case 'last_edited_time':
				$config['width']           = 160;
				$config['sorter']          = 'datetime';
				$config['formatter']       = 'datetime';
				$config['formatterParams'] = array(
					'outputFormat' => 'MMM DD, YYYY',
				);
				break;

			case 'people':
			case 'created_by':
			case 'last_edited_by':
				$config['width']     = 180;
				$config['formatter'] = 'html';
				break;

			case 'url':
			case 'email':
			case 'phone_number':
				$config['width']     = 200;
				$config['formatter'] = 'html';
				break;

			case 'files':
				$config['width']     = 150;
				$config['formatter'] = 'html';
				break;

			case 'relation':
				$config['width']     = 180;
				$config['formatter'] = 'html';
				break;

			case 'rollup':
			case 'formula':
				$config['width']     = 150;
				$config['formatter'] = 'html';
				break;

			default:
				$config['width'] = 180;
				break;
		}

		return $config;
	}

	/**
	 * Format title property.
	 *
	 * Title is an array of rich_text objects.
	 *
	 * @since 1.0.0
	 *
	 * @param array $value Title property value.
	 * @return string Formatted HTML.
	 */
	private function format_title( array $value ): string {
		if ( empty( $value ) ) {
			return '';
		}

		// Title is rich text with bold for first item.
		$html = $this->rich_text_converter->to_html( $value );

		// Wrap in strong tag for emphasis.
		return '<strong>' . $html . '</strong>';
	}

	/**
	 * Format rich_text property.
	 *
	 * @since 1.0.0
	 *
	 * @param array $value Rich text array.
	 * @return string Formatted HTML.
	 */
	private function format_rich_text( array $value ): string {
		return $this->rich_text_converter->to_html( $value );
	}

	/**
	 * Format text property.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value Plain text value.
	 * @return string Escaped text.
	 */
	private function format_text( string $value ): string {
		return esc_html( $value );
	}

	/**
	 * Format number property.
	 *
	 * Formats with locale (commas for thousands).
	 *
	 * @since 1.0.0
	 *
	 * @param float|int $value Number value.
	 * @return string Formatted number.
	 */
	private function format_number( $value ): string {
		if ( ! is_numeric( $value ) ) {
			return '';
		}

		// Determine decimal places.
		$decimals = ( floor( $value ) !== (float) $value ) ? 2 : 0;

		return number_format( (float) $value, $decimals, '.', ',' );
	}

	/**
	 * Format select property.
	 *
	 * Returns HTML badge with color.
	 *
	 * @since 1.0.0
	 *
	 * @param array $value Select property value with name and color.
	 * @return string HTML badge.
	 */
	private function format_select( array $value ): string {
		if ( empty( $value['name'] ) ) {
			return '';
		}

		$name  = esc_html( $value['name'] );
		$color = ! empty( $value['color'] ) ? sanitize_html_class( $value['color'] ) : 'default';

		return sprintf(
			'<span class="notion-select notion-%s">%s</span>',
			$color,
			$name
		);
	}

	/**
	 * Format multi_select property.
	 *
	 * Returns multiple HTML badges.
	 *
	 * @since 1.0.0
	 *
	 * @param array $value Array of select objects.
	 * @return string HTML badges.
	 */
	private function format_multi_select( array $value ): string {
		if ( empty( $value ) ) {
			return '';
		}

		$badges = array();

		foreach ( $value as $item ) {
			if ( ! empty( $item['name'] ) ) {
				$badges[] = $this->format_select( $item );
			}
		}

		return implode( ' ', $badges );
	}

	/**
	 * Format status property.
	 *
	 * Similar to select but with status styling.
	 *
	 * @since 1.0.0
	 *
	 * @param array $value Status property value.
	 * @return string HTML status badge.
	 */
	private function format_status( array $value ): string {
		if ( empty( $value['name'] ) ) {
			return '';
		}

		$name  = esc_html( $value['name'] );
		$color = ! empty( $value['color'] ) ? sanitize_html_class( $value['color'] ) : 'default';

		return sprintf(
			'<span class="notion-status notion-%s">%s</span>',
			$color,
			$name
		);
	}

	/**
	 * Format checkbox property.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $value Checkbox value.
	 * @return bool Boolean value for Tabulator tickCross formatter.
	 */
	private function format_checkbox( bool $value ): bool {
		return $value;
	}

	/**
	 * Format date property.
	 *
	 * Notion dates can have start, end, and timezone.
	 *
	 * @since 1.0.0
	 *
	 * @param array $value Date property value.
	 * @return string Formatted date string.
	 */
	private function format_date( array $value ): string {
		if ( empty( $value['start'] ) ) {
			return '';
		}

		$start    = $value['start'];
		$end      = $value['end'] ?? null;
		$has_time = strpos( $start, 'T' ) !== false;

		// Format start date.
		$start_formatted = $this->format_date_string( $start, $has_time );

		// If there's an end date, format as range.
		if ( $end ) {
			$end_formatted = $this->format_date_string( $end, $has_time );
			return sprintf( '%s → %s', $start_formatted, $end_formatted );
		}

		return $start_formatted;
	}

	/**
	 * Format timestamp (created_time, last_edited_time).
	 *
	 * @since 1.0.0
	 *
	 * @param string $value ISO 8601 timestamp.
	 * @return string Formatted datetime string.
	 */
	private function format_timestamp( string $value ): string {
		return $this->format_date_string( $value, true );
	}

	/**
	 * Format date string with locale-aware formatting.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date_string Date/datetime string.
	 * @param bool   $has_time    Whether to include time.
	 * @return string Formatted date.
	 */
	private function format_date_string( string $date_string, bool $has_time ): string {
		$timestamp = strtotime( $date_string );

		if ( false === $timestamp ) {
			return esc_html( $date_string );
		}

		if ( $has_time ) {
			// Format: Nov 15, 2025 3:45 PM.
			return date_i18n( 'M j, Y g:i A', $timestamp );
		}

		// Format: Nov 15, 2025.
		return date_i18n( 'M j, Y', $timestamp );
	}

	/**
	 * Format people property.
	 *
	 * Returns user names or avatars.
	 *
	 * @since 1.0.0
	 *
	 * @param array $value Array of people objects.
	 * @return string HTML with user names.
	 */
	private function format_people( array $value ): string {
		if ( empty( $value ) ) {
			return '';
		}

		$names = array();

		foreach ( $value as $person ) {
			if ( ! empty( $person['name'] ) ) {
				$name = esc_html( $person['name'] );

				// Optionally include avatar if available.
				if ( ! empty( $person['avatar_url'] ) ) {
					$avatar  = sprintf(
						'<img src="%s" alt="%s" class="notion-avatar" width="20" height="20">',
						esc_url( $person['avatar_url'] ),
						esc_attr( $name )
					);
					$names[] = $avatar . ' ' . $name;
				} else {
					$names[] = $name;
				}
			}
		}

		return implode( ', ', $names );
	}

	/**
	 * Format files property.
	 *
	 * Returns file download links.
	 *
	 * @since 1.0.0
	 *
	 * @param array $value Array of file objects.
	 * @return string HTML with file links.
	 */
	private function format_files( array $value ): string {
		if ( empty( $value ) ) {
			return '';
		}

		$links = array();

		foreach ( $value as $file ) {
			$name = ! empty( $file['name'] ) ? esc_html( $file['name'] ) : 'File';
			$url  = $file['url'] ?? $file['file']['url'] ?? $file['external']['url'] ?? '';

			if ( $url ) {
				$links[] = sprintf(
					'<a href="%s" target="_blank" rel="noopener noreferrer" class="notion-file">%s</a>',
					esc_url( $url ),
					$name
				);
			}
		}

		return implode( ', ', $links );
	}

	/**
	 * Format URL property.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value URL string.
	 * @return string HTML link.
	 */
	private function format_url( string $value ): string {
		if ( empty( $value ) ) {
			return '';
		}

		return sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer" class="notion-url">%s</a>',
			esc_url( $value ),
			esc_html( $value )
		);
	}

	/**
	 * Format email property.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value Email address.
	 * @return string HTML mailto link.
	 */
	private function format_email( string $value ): string {
		if ( empty( $value ) || ! is_email( $value ) ) {
			return esc_html( $value );
		}

		return sprintf(
			'<a href="mailto:%s" class="notion-email">%s</a>',
			esc_attr( $value ),
			esc_html( $value )
		);
	}

	/**
	 * Format phone property.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value Phone number.
	 * @return string HTML tel link.
	 */
	private function format_phone( string $value ): string {
		if ( empty( $value ) ) {
			return '';
		}

		// Remove non-numeric characters for tel: link.
		$clean_phone = preg_replace( '/[^0-9+]/', '', $value );

		return sprintf(
			'<a href="tel:%s" class="notion-phone">%s</a>',
			esc_attr( $clean_phone ),
			esc_html( $value )
		);
	}

	/**
	 * Format relation property.
	 *
	 * Returns links to related pages.
	 *
	 * @since 1.0.0
	 *
	 * @param array $value Array of relation objects with IDs.
	 * @return string HTML with relation count or links.
	 */
	private function format_relation( array $value ): string {
		if ( empty( $value ) ) {
			return '';
		}

		$count = count( $value );

		// For now, just show count. In future, could link to related pages.
		return sprintf(
			'<span class="notion-relation">%d %s</span>',
			$count,
			_n( 'relation', 'relations', $count, 'notion-wp' )
		);
	}

	/**
	 * Format rollup property.
	 *
	 * Rollup displays aggregated value from relations.
	 *
	 * @since 1.0.0
	 *
	 * @param array $value Rollup property value.
	 * @return string Formatted rollup value.
	 */
	private function format_rollup( array $value ): string {
		if ( empty( $value ) ) {
			return '';
		}

		// Rollup has type (number, date, array) and value.
		$type = $value['type'] ?? 'number';

		switch ( $type ) {
			case 'number':
				return $this->format_number( $value['number'] ?? 0 );

			case 'date':
				return ! empty( $value['date'] ) ? $this->format_date( $value['date'] ) : '';

			case 'array':
				// Array of values.
				$items = $value['array'] ?? array();
				return esc_html( implode( ', ', $items ) );

			default:
				return '';
		}
	}

	/**
	 * Format formula property.
	 *
	 * Formula displays computed result.
	 *
	 * @since 1.0.0
	 *
	 * @param array $value Formula property value.
	 * @return string Formatted formula result.
	 */
	private function format_formula( array $value ): string {
		if ( empty( $value ) ) {
			return '';
		}

		// Formula has type (string, number, boolean, date) and value.
		$type = $value['type'] ?? 'string';

		switch ( $type ) {
			case 'string':
				return esc_html( $value['string'] ?? '' );

			case 'number':
				return $this->format_number( $value['number'] ?? 0 );

			case 'boolean':
				return $value['boolean'] ? '✓' : '✗';

			case 'date':
				return ! empty( $value['date'] ) ? $this->format_date( $value['date'] ) : '';

			default:
				return '';
		}
	}
}
