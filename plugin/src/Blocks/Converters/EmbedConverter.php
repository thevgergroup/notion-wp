<?php
/**
 * Embed Block Converter
 *
 * Converts Notion embed blocks to WordPress embed blocks.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Blocks\Converters;

use NotionSync\Blocks\BlockConverterInterface;

/**
 * Converts Notion embed blocks to WordPress oEmbed blocks
 *
 * Handles various embed types including video, bookmark, pdf, and generic embeds.
 * Uses WordPress's built-in oEmbed support where possible.
 *
 * @since 1.0.0
 */
class EmbedConverter implements BlockConverterInterface {

	/**
	 * Check if this converter supports the given block type
	 *
	 * @param array $notion_block The Notion block data.
	 * @return bool True if this converter handles this block type.
	 */
	public function supports( array $notion_block ): bool {
		$type = $notion_block['type'] ?? '';
		return in_array(
			$type,
			array( 'embed', 'video', 'bookmark', 'pdf', 'link_preview' ),
			true
		);
	}

	/**
	 * Convert Notion embed block to WordPress embed block
	 *
	 * @param array $notion_block The Notion block data.
	 * @return string Gutenberg embed block HTML.
	 */
	public function convert( array $notion_block ): string {
		$type       = $notion_block['type'] ?? '';
		$block_data = $notion_block[ $type ] ?? array();
		$url        = $block_data['url'] ?? '';

		if ( empty( $url ) ) {
			return '';
		}

		// Handle bookmark and PDF types specially (not oEmbed).
		if ( in_array( $type, array( 'bookmark', 'pdf' ), true ) ) {
			return $this->convert_link_preview( $url, $type );
		}

		// Determine embed provider from URL.
		$provider = $this->detect_provider( $url );

		// Handle different embed types.
		switch ( $provider ) {
			case 'youtube':
			case 'vimeo':
			case 'twitter':
			case 'instagram':
			case 'spotify':
			case 'soundcloud':
				return $this->convert_oembed( $url, $provider );

			default:
				return $this->convert_generic_embed( $url );
		}
	}

	/**
	 * Convert to WordPress oEmbed block
	 *
	 * @param string $url      Embed URL.
	 * @param string $provider Provider name.
	 * @return string WordPress embed block HTML.
	 */
	private function convert_oembed( string $url, string $provider ): string {
		// Use WordPress core embed block for oEmbed providers.
		return sprintf(
			"<!-- wp:embed {\"url\":\"%s\",\"type\":\"video\",\"providerNameSlug\":\"%s\",\"responsive\":true,\"className\":\"wp-embed-aspect-16-9 wp-has-aspect-ratio\"} -->\n<figure class=\"wp-block-embed is-type-video is-provider-%s wp-block-embed-%s wp-embed-aspect-16-9 wp-has-aspect-ratio\"><div class=\"wp-block-embed__wrapper\">\n%s\n</div></figure>\n<!-- /wp:embed -->\n\n",
			esc_url( $url ),
			esc_attr( $provider ),
			esc_attr( $provider ),
			esc_attr( $provider ),
			esc_url( $url )
		);
	}

	/**
	 * Convert bookmark/link preview to HTML
	 *
	 * @param string $url  URL to preview.
	 * @param string $type Block type (bookmark, pdf, etc).
	 * @return string HTML for link preview.
	 */
	private function convert_link_preview( string $url, string $type ): string {
		return sprintf(
			"<!-- wp:html -->\n<div class=\"notion-bookmark\">\n\t<a href=\"%s\" target=\"_blank\" rel=\"noopener noreferrer\" class=\"notion-bookmark-link\">\n\t\t<div class=\"notion-bookmark-title\">%s</div>\n\t\t<div class=\"notion-bookmark-url\">%s</div>\n\t</a>\n</div>\n<!-- /wp:html -->\n\n",
			esc_url( $url ),
			esc_html( $this->get_url_title( $url ) ),
			esc_html( $url )
		);
	}

	/**
	 * Convert generic embed to HTML
	 *
	 * @param string $url Embed URL.
	 * @return string HTML for generic embed.
	 */
	private function convert_generic_embed( string $url ): string {
		// Validate URL scheme to prevent XSS (only allow http/https).
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		if ( ! in_array( strtolower( $scheme ), array( 'http', 'https' ), true ) ) {
			// Return a safe fallback for invalid schemes (javascript:, data:, etc).
			return sprintf(
				"<!-- wp:paragraph -->\n<p><em>Invalid embed URL (unsupported scheme)</em></p>\n<!-- /wp:paragraph -->\n\n"
			);
		}

		// For unknown providers, output iframe with sandbox for security.
		// sandbox="allow-scripts allow-same-origin" allows typical embed functionality
		// while preventing top-level navigation and other dangerous behaviors.
		return sprintf(
			"<!-- wp:html -->\n<div class=\"notion-embed\">\n\t<iframe src=\"%s\" width=\"100%%\" height=\"500\" frameborder=\"0\" allowfullscreen sandbox=\"allow-scripts allow-same-origin allow-presentation\"></iframe>\n</div>\n<!-- /wp:html -->\n\n",
			esc_url( $url )
		);
	}

	/**
	 * Detect embed provider from URL
	 *
	 * @param string $url Embed URL.
	 * @return string Provider name.
	 */
	private function detect_provider( string $url ): string {
		$host = wp_parse_url( $url, PHP_URL_HOST );

		if ( ! $host ) {
			return 'generic';
		}

		$host = strtolower( $host );

		// Remove www. prefix.
		$host = preg_replace( '/^www\./', '', $host );

		$providers = array(
			'youtube.com'    => 'youtube',
			'youtu.be'       => 'youtube',
			'vimeo.com'      => 'vimeo',
			'twitter.com'    => 'twitter',
			'x.com'          => 'twitter',
			'instagram.com'  => 'instagram',
			'spotify.com'    => 'spotify',
			'soundcloud.com' => 'soundcloud',
		);

		foreach ( $providers as $domain => $provider ) {
			if ( str_contains( $host, $domain ) ) {
				return $provider;
			}
		}

		return 'generic';
	}

	/**
	 * Get title from URL
	 *
	 * Extracts a readable title from the URL.
	 *
	 * @param string $url URL to extract title from.
	 * @return string Title extracted from URL.
	 */
	private function get_url_title( string $url ): string {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		$host = wp_parse_url( $url, PHP_URL_HOST );

		if ( $path && '/' !== $path ) {
			// Get last path segment.
			$segments = explode( '/', trim( $path, '/' ) );
			$title    = end( $segments );
			// Remove file extension.
			$title = preg_replace( '/\.[^.]+$/', '', $title );
			// Replace dashes and underscores with spaces.
			$title = str_replace( array( '-', '_' ), ' ', $title );
			return ucwords( $title );
		}

		return $host ?? 'Link';
	}
}
