<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

// Scan src/tests for PHP files, exclude vendor/build/node_modules
$finder = Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests'])
    ->name('*.php')
    ->exclude(['vendor', 'build', 'node_modules'])
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new Config())
    ->setRiskyAllowed(true)
    ->setUsingCache(true)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache')
    ->setFinder($finder)
    ->setRules([
        // ===================================
        // Base rules / PSR12 / PHP84 migration
        // ===================================
        '@PSR12' => true,
        'phpdoc_types' => ['groups' => []], // optional, keeps types short
        '@PHP84Migration' => true,
        '@DoctrineAnnotation' => true,

        // Strictness
        'strict_comparison' => true,
        'strict_param' => true,
        'declare_strict_types' => true,

        // Imports
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,

        // Arrays & lists
        'array_syntax' => ['syntax' => 'short'],
        'list_syntax' => ['syntax' => 'short'],
        'trim_array_spaces' => true,
        'no_whitespace_before_comma_in_array' => true,
        'whitespace_after_comma_in_array' => true,
        'no_trailing_comma_in_singleline_array' => true,
        'trailing_comma_in_multiline' => true,
        'nullable_type_declaration_for_default_null_value' => true,
        'no_unreachable_default_argument_value' => true,
        'return_type_declaration' => ['space_before' => 'none'],

        // Classes & methods
        'class_attributes_separation' => ['elements' => ['method' => 'one']], // one blank line between methods
        'visibility_required' => ['elements' => ['property', 'method', 'const']],
        'self_accessor' => true,
        'no_null_property_initialization' => true,

        // Operators & spacing
        'concat_space' => ['spacing' => 'one'],
        'binary_operator_spaces' => [
            'default' => 'single_space',
            'operators' => ['='=>'align_single_space_minimal','=>'=>'align_single_space_minimal']
        ],
        'ternary_operator_spaces' => true,

        // Strings
        'single_quote' => true,

        // ===================================
        // PHPDoc / Docblock alignment
        // ===================================
        // 'phpdoc_align' => ['align' => 'vertical'], // aligns all @param/@return
        // 'phpdoc_order' => true,                     // sort tags: param → return → throws
        // 'phpdoc_trim' => true,                      // remove extra spaces
        'phpdoc_no_empty_return' => false,           // remove @return void
        'phpdoc_var_without_name' => true,          // allow @var type without variable
        'phpdoc_add_missing_param_annotation' => true, // adds missing @param
        'phpdoc_scalar' => true,                    // use scalar types in docblocks
        'phpdoc_separation' => false,              // enforce no blank lines between annotations
        'no_superfluous_phpdoc_tags' => ['allow_mixed' => true], // allow mixed when required
        // 'no_blank_lines_before_namespace' => true, // ensures file doc is at top

        // Misc spacing & braces
        'no_extra_blank_lines' => ['tokens' => ['extra','curly_brace_block','square_brace_block','parenthesis_brace_block','throw','use','return']],
        'braces' => ['allow_single_line_closure' => true,'position_after_functions_and_oop_constructs' => 'same'],
    ]);
