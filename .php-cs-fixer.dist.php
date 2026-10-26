<?php declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    // A deliberately unparsable fixture: the generator must report it.
    ->notPath('Fixtures/Projects/Scan/Syntax.php')
    ->append([__FILE__, __DIR__ . '/rector.php'])
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        '@PHP81Migration' => true,
        '@PHP80Migration:risky' => true,
        'declare_strict_types' => true,
        'phpdoc_align' => ['align' => 'left'],
        'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline'],
        'phpdoc_to_comment' => false,
        'no_superfluous_phpdoc_tags' => ['allow_mixed' => true],
        'linebreak_after_opening_tag' => false,
        'blank_line_after_opening_tag' => false,
        'concat_space' => ['spacing' => 'one'],
        'increment_style' => ['style' => 'post'],
        'yoda_style' => ['equal' => false, 'identical' => false, 'less_and_greater' => false],
        'class_attributes_separation' => true,
        'multiline_whitespace_before_semicolons' => ['strategy' => 'new_line_for_chained_calls'],
        'global_namespace_import' => ['import_classes' => true],
        'blank_line_before_statement' => ['statements' => ['declare', 'return']],
    ])
    ->setFinder($finder)
    ->setParallelConfig(new PhpCsFixer\Runner\Parallel\ParallelConfig(4))
    ->setRiskyAllowed(true)
;
