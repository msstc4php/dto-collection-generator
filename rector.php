<?php

declare(strict_types=1);

use Rector\CodingStyle\Rector\Catch_\CatchExceptionNameMatchingTypeRector;
use Rector\CodingStyle\Rector\Stmt\NewlineAfterStatementRector;
use Rector\Config\RectorConfig;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withAutoloadPaths([__DIR__ . '/vendor/autoload.php'])
    ->withoutParallel()
    // The generator runs on 8.1: Rector must never upgrade sources past it.
    ->withPhpVersion(PhpVersion::PHP_81)
    ->withPhpSets(php81: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        privatization: true,
        instanceOf: true,
        earlyReturn: true,
    )
    ->withImportNames(removeUnusedImports: true)
    ->withSkip([
        // Deliberately broken fixtures: the generator must report them.
        __DIR__ . '/tests/Fixtures/Model/Broken.php',
        __DIR__ . '/tests/Fixtures/Projects/Scan/Syntax.php',
        __DIR__ . '/tests/Fixtures/Projects/Scan/Unloadable.php',
        NewlineAfterStatementRector::class,
        CatchExceptionNameMatchingTypeRector::class,
    ])
;
