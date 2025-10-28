<?php
/**
 * Code Block Converter
 *
 * Converts Notion code blocks to WordPress code blocks.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Blocks\Converters;

use NotionSync\Blocks\BlockConverterInterface;

/**
 * Converts Notion code blocks to WordPress code blocks
 *
 * Preserves syntax highlighting language and caption information.
 *
 * @since 1.0.0
 */
class CodeConverter implements BlockConverterInterface {

	/**
	 * Check if this converter supports the given block type
	 *
	 * @param array $notion_block The Notion block data.
	 * @return bool True if this converter handles this block type.
	 */
	public function supports( array $notion_block ): bool {
		return isset( $notion_block['type'] ) && 'code' === $notion_block['type'];
	}

	/**
	 * Convert Notion code block to WordPress code block
	 *
	 * @param array $notion_block The Notion block data.
	 * @return string Gutenberg code block HTML.
	 */
	public function convert( array $notion_block ): string {
		$block_data = $notion_block['code'] ?? array();
		$rich_text  = $block_data['rich_text'] ?? array();
		$language   = $block_data['language'] ?? 'plaintext';
		$caption    = $block_data['caption'] ?? array();

		// Extract code content.
		$code = $this->extract_code_content( $rich_text );

		if ( empty( $code ) ) {
			return '';
		}

		// Map Notion language to Gutenberg-compatible language.
		$gutenberg_language = $this->map_language( $language );

		// Extract caption if present.
		$caption_text = $this->extract_caption( $caption );

		// Build block attributes.
		$block_attrs = array();
		if ( 'plaintext' !== $gutenberg_language ) {
			$block_attrs['language'] = $gutenberg_language;
		}

		// Convert attributes to JSON format for Gutenberg.
		$attrs_json = ! empty( $block_attrs ) ? wp_json_encode( $block_attrs ) : '';

		// Return Gutenberg code block.
		$output = sprintf(
			"<!-- wp:code %s-->\n<pre class=\"wp-block-code\"><code>%s</code></pre>\n<!-- /wp:code -->\n\n",
			$attrs_json ? $attrs_json . ' ' : '',
			esc_html( $code )
		);

		// Add caption as a paragraph if present.
		if ( ! empty( $caption_text ) ) {
			$output .= sprintf(
				"<!-- wp:paragraph -->\n<p class=\"code-caption\"><em>%s</em></p>\n<!-- /wp:paragraph -->\n\n",
				wp_kses_post( $caption_text )
			);
		}

		return $output;
	}

	/**
	 * Extract code content from rich text array
	 *
	 * @param array $rich_text Array of rich text objects.
	 * @return string Code content.
	 */
	private function extract_code_content( array $rich_text ): string {
		$code = '';

		foreach ( $rich_text as $text_obj ) {
			$code .= $text_obj['plain_text'] ?? '';
		}

		return $code;
	}

	/**
	 * Extract caption text from caption array
	 *
	 * @param array $caption Array of caption rich text objects.
	 * @return string Caption text.
	 */
	private function extract_caption( array $caption ): string {
		if ( empty( $caption ) ) {
			return '';
		}

		$text = '';
		foreach ( $caption as $caption_obj ) {
			$text .= $caption_obj['plain_text'] ?? '';
		}

		return $text;
	}

	/**
	 * Map Notion language to Gutenberg language
	 *
	 * Maps Notion's language identifiers to Prism.js/Gutenberg language codes.
	 *
	 * @param string $notion_language Notion language identifier.
	 * @return string Gutenberg/Prism.js language code.
	 */
	private function map_language( string $notion_language ): string {
		// Direct mappings for common languages.
		$language_map = array(
			'abap'                     => 'abap',
			'arduino'                  => 'arduino',
			'bash'                     => 'bash',
			'basic'                    => 'basic',
			'c'                        => 'c',
			'clojure'                  => 'clojure',
			'coffeescript'             => 'coffeescript',
			'c++'                      => 'cpp',
			'c#'                       => 'csharp',
			'css'                      => 'css',
			'dart'                     => 'dart',
			'diff'                     => 'diff',
			'docker'                   => 'docker',
			'elixir'                   => 'elixir',
			'elm'                      => 'elm',
			'erlang'                   => 'erlang',
			'flow'                     => 'flow',
			'fortran'                  => 'fortran',
			'f#'                       => 'fsharp',
			'gherkin'                  => 'gherkin',
			'glsl'                     => 'glsl',
			'go'                       => 'go',
			'graphql'                  => 'graphql',
			'groovy'                   => 'groovy',
			'haskell'                  => 'haskell',
			'html'                     => 'markup',
			'java'                     => 'java',
			'javascript'               => 'javascript',
			'json'                     => 'json',
			'julia'                    => 'julia',
			'kotlin'                   => 'kotlin',
			'latex'                    => 'latex',
			'less'                     => 'less',
			'lisp'                     => 'lisp',
			'livescript'               => 'livescript',
			'lua'                      => 'lua',
			'makefile'                 => 'makefile',
			'markdown'                 => 'markdown',
			'markup'                   => 'markup',
			'matlab'                   => 'matlab',
			'mermaid'                  => 'mermaid',
			'nix'                      => 'nix',
			'objective-c'              => 'objectivec',
			'ocaml'                    => 'ocaml',
			'pascal'                   => 'pascal',
			'perl'                     => 'perl',
			'php'                      => 'php',
			'plain text'               => 'plaintext',
			'powershell'               => 'powershell',
			'prolog'                   => 'prolog',
			'protobuf'                 => 'protobuf',
			'python'                   => 'python',
			'r'                        => 'r',
			'reason'                   => 'reason',
			'ruby'                     => 'ruby',
			'rust'                     => 'rust',
			'sass'                     => 'sass',
			'scala'                    => 'scala',
			'scheme'                   => 'scheme',
			'scss'                     => 'scss',
			'shell'                    => 'shell',
			'sql'                      => 'sql',
			'swift'                    => 'swift',
			'typescript'               => 'typescript',
			'vb.net'                   => 'vbnet',
			'verilog'                  => 'verilog',
			'vhdl'                     => 'vhdl',
			'visual basic'             => 'vbnet',
			'webassembly'              => 'wasm',
			'xml'                      => 'markup',
			'yaml'                     => 'yaml',
			'java/c/c++/c#'            => 'clike',
		);

		$lower_language = strtolower( $notion_language );

		return $language_map[ $lower_language ] ?? 'plaintext';
	}
}
