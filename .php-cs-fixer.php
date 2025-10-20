<?php
/**
 * PHP-CS-Fixer Configuration for Notion-WP Plugin
 *
 * This configuration auto-fixes PHP code style issues to match WordPress Coding Standards.
 * Run with: composer lint:fix
 *
 * @see https://cs.symfony.com/
 * @package Notion_WP
 */

$finder = PhpCsFixer\Finder::create()
	->in(__DIR__ . '/plugin')
	->in(__DIR__ . '/tests')
	->exclude('vendor')
	->exclude('node_modules')
	->exclude('build')
	->exclude('dist')
	->name('*.php')
	->notName('*.blade.php') // Exclude any template files if present
	->ignoreDotFiles(true)
	->ignoreVCS(true);

$config = new PhpCsFixer\Config();

return $config
	->setRules([
		// PSR-12 base standard
		'@PSR12' => true,

		// WordPress-style array syntax
		// Allow short array syntax [] which is more modern
		'array_syntax' => ['syntax' => 'short'],

		// Indentation: Tabs (WordPress standard)
		'indentation_type' => true,

		// Line endings: Unix style
		'line_ending' => true,

		// No blank line after opening tag
		'blank_line_after_opening_tag' => false,

		// Blank line after namespace declaration
		'blank_line_after_namespace' => true,

		// No blank lines before namespace
		'no_blank_lines_before_namespace' => false,

		// Single blank line at end of file
		'single_blank_line_at_eof' => true,

		// No trailing whitespace
		'no_trailing_whitespace' => true,
		'no_trailing_whitespace_in_comment' => true,

		// Bracing style (WordPress uses opening brace on same line for functions)
		'braces' => [
			'allow_single_line_closure' => true,
			'position_after_functions_and_oop_constructs' => 'same',
			'position_after_control_structures' => 'same',
			'position_after_anonymous_constructs' => 'same',
		],

		// Class and method spacing
		'class_attributes_separation' => [
			'elements' => [
				'method' => 'one',
				'property' => 'one',
				'const' => 'one',
			],
		],

		// Import statements
		'no_unused_imports' => true,
		'ordered_imports' => [
			'sort_algorithm' => 'alpha',
			'imports_order' => ['class', 'function', 'const'],
		],
		'single_import_per_statement' => true,
		'single_line_after_imports' => true,

		// Comments
		'single_line_comment_style' => [
			'comment_types' => ['hash'],
		],
		'multiline_comment_opening_closing' => true,

		// Operators
		'binary_operator_spaces' => [
			'default' => 'single_space',
			'operators' => [
				'=>' => 'align_single_space_minimal',
				'=' => 'align_single_space_minimal',
			],
		],
		'concat_space' => ['spacing' => 'one'],
		'unary_operator_spaces' => true,
		'operator_linebreak' => ['only_booleans' => true],

		// Casts
		'cast_spaces' => ['space' => 'single'],
		'lowercase_cast' => true,
		'short_scalar_cast' => true,

		// Functions
		'function_declaration' => [
			'closure_function_spacing' => 'one',
		],
		'function_typehint_space' => true,
		'method_argument_space' => [
			'on_multiline' => 'ensure_fully_multiline',
			'keep_multiple_spaces_after_comma' => false,
		],
		'return_type_declaration' => ['space_before' => 'none'],
		'no_spaces_after_function_name' => true,

		// Control structures
		'control_structure_continuation_position' => ['position' => 'same_line'],
		'elseif' => true,
		'no_alternative_syntax' => false, // WordPress allows alternative syntax in templates

		// Strings
		'single_quote' => true,
		'escape_implicit_backslashes' => true,

		// PHP tags
		'full_opening_tag' => true,
		'no_closing_tag' => true,

		// Constants
		'native_constant_invocation' => false, // WordPress doesn't require \true, \false
		'native_function_invocation' => false, // WordPress doesn't require \ prefix

		// Clean up code
		'no_empty_statement' => true,
		'no_extra_blank_lines' => [
			'tokens' => [
				'curly_brace_block',
				'extra',
				'parenthesis_brace_block',
				'square_brace_block',
				'throw',
				'use',
			],
		],
		'no_leading_namespace_whitespace' => true,
		'no_multiline_whitespace_around_double_arrow' => true,
		'no_singleline_whitespace_before_semicolons' => true,
		'no_whitespace_in_blank_line' => true,

		// Yoda conditions (WordPress uses Yoda style)
		'yoda_style' => [
			'equal' => true,
			'identical' => true,
			'less_and_greater' => false,
		],

		// Strict types declaration
		'declare_strict_types' => false, // WordPress doesn't use strict types

		// Visibility modifiers
		'visibility_required' => [
			'elements' => ['property', 'method', 'const'],
		],

		// Modern PHP features
		'modernize_types_casting' => true,
		'no_alias_functions' => true,
		'no_mixed_echo_print' => ['use' => 'echo'],

		// Standardization
		'standardize_not_equals' => true,
		'ternary_operator_spaces' => true,
		'trim_array_spaces' => true,

		// Namespaces
		'no_leading_import_slash' => true,
		'single_blank_line_before_namespace' => false,

		// Type declarations
		'lowercase_keywords' => true,
		'lowercase_static_reference' => true,

		// Whitespace
		'space_after_semicolon' => ['remove_in_empty_for_expressions' => true],
		'ternary_to_null_coalescing' => true,
		'whitespace_after_comma_in_array' => true,

		// No leading slash in global namespace
		'global_namespace_import' => [
			'import_classes' => false,
			'import_constants' => false,
			'import_functions' => false,
		],
	])
	->setFinder($finder)
	->setIndent("\t") // WordPress uses tabs
	->setLineEnding("\n")
	->setRiskyAllowed(false); // Don't apply risky fixes
