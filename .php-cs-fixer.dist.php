<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests', __DIR__ . '/examples', __DIR__ . '/tools'])
    ->append([__FILE__]);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache')
    ->setRules([
        '@PER-CS2.0' => true,
        '@PER-CS2.0:risky' => true,
        'declare_strict_types' => true,
        'strict_comparison' => true,
        'strict_param' => true,
        'final_class' => true,
        'no_unused_imports' => true,
        'ordered_imports' => ['imports_order' => ['class', 'function', 'const'], 'sort_algorithm' => 'alpha'],
        'global_namespace_import' => ['import_classes' => false, 'import_constants' => false, 'import_functions' => false],
        'native_function_invocation' => ['include' => ['@all'], 'scope' => 'namespaced', 'strict' => true],
        'no_superfluous_phpdoc_tags' => ['allow_mixed' => true, 'remove_inheritdoc' => false],
        'phpdoc_align' => ['align' => 'left'],
        'phpdoc_trim' => true,
        'single_quote' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arrays', 'arguments', 'parameters', 'match']],
        'void_return' => true,
    ])
    ->setFinder($finder);
