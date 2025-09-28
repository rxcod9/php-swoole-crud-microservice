<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests'])
    ->name('*.php')
    ->exclude(['vendor', 'build'])
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new Config())
    ->setRiskyAllowed(true)
    ->setUsingCache(true)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache')
    ->setFinder($finder)
    ->setRules([
        '@PSR12' => true,
        '@PHP84Migration' => true,
        '@DoctrineAnnotation' => true,

        // Strictness
        'strict_comparison' => true,
        'strict_param' => true,
        'declare_strict_types' => true,

        // Imports
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'global_namespace_import' => [
            'import_constants' => true,
            'import_functions' => true,
            'import_classes' => true,
        ],

        // Arrays & Lists
        'array_syntax' => ['syntax' => 'short'],
        'list_syntax' => ['syntax' => 'short'],
        'trim_array_spaces' => true,
        'no_whitespace_before_comma_in_array' => true,
        'whitespace_after_comma_in_array' => true,
        'no_trailing_comma_in_singleline_array' => true, // aligns with PSR12

        // Functions
        'nullable_type_declaration_for_default_null_value' => true,
        'no_unreachable_default_argument_value' => true,
        'native_function_invocation' => [
            'include' => ['@compiler_optimized'],
            'scope' => 'all'
        ],
        'return_type_declaration' => ['space_before' => 'none'],

        // Classes
        'class_attributes_separation' => ['elements' => ['method' => 'one']],
        'visibility_required' => ['elements' => ['property', 'method', 'const']],
        'self_accessor' => false,
        'no_null_property_initialization' => true,

        // Operators & spacing
        'concat_space' => ['spacing' => 'one'],
        'binary_operator_spaces' => [
            'default' => 'single_space',
            'operators' => [
                '='  => 'align_single_space_minimal',
                '=>' => 'align_single_space_minimal',
            ],
        ],
        'ternary_operator_spaces' => true,

        // Strings
        'single_quote' => true,
        'escape_implicit_backslashes' => true,
        'string_implicit_backslashes' => ['single_quoted' => 'ignore'],

        // Comments & Docs
        'phpdoc_align' => ['align' => 'left'],
        'phpdoc_to_comment' => false,
        'phpdoc_trim_consecutive_blank_line_separation' => true,
        'phpdoc_summary' => false,

        // Misc
        'no_superfluous_phpdoc_tags' => ['allow_mixed' => true],
        'fully_qualified_strict_types' => true,
    ]);
