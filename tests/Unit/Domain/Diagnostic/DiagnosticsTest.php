<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Unit\Domain\Diagnostic;

use MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic\Diagnostic;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic\Diagnostics;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic\Location;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic\Severity;
use PHPUnit\Framework\TestCase;

final class DiagnosticsTest extends TestCase
{
    public function testTheSameDiagnosticCountsOnce(): void
    {
        $diagnostics = new Diagnostics();
        $diagnostics->error('Broken', Location::inFile('/project/src/Item.php', 12));
        $diagnostics->error('Broken', Location::inFile('/project/src/Item.php', 12));
        $diagnostics->warning('Broken', Location::inFile('/project/src/Item.php', 12));
        $diagnostics->error('Broken', Location::inFile('/project/src/Item.php', 13));

        self::assertCount(3, $diagnostics);
        self::assertCount(2, $diagnostics->errors());
    }

    public function testWarningsAloneAreNoErrors(): void
    {
        $diagnostics = new Diagnostics();
        self::assertFalse($diagnostics->hasErrors());

        $diagnostics->warning('Careful', Location::inConfig('/project/dto-collection-generator.yaml', '/target/php'));
        self::assertFalse($diagnostics->hasErrors());
        self::assertSame([], $diagnostics->errors());

        $diagnostics->error('Broken', Location::inConfig('/project/dto-collection-generator.yaml', '/version'));
        self::assertTrue($diagnostics->hasErrors());
    }

    public function testMergeKeepsTheOrder(): void
    {
        $first = new Diagnostics();
        $first->error('one', Location::inFile('/a.php'));

        $second = new Diagnostics();
        $second->warning('two', Location::inFile('/b.php'));
        $second->error('one', Location::inFile('/a.php'));
        $second->error('three', Location::inFile('/c.php'));

        $first->merge($second);

        self::assertSame(['one', 'two', 'three'], array_map(
            static fn (Diagnostic $diagnostic): string => $diagnostic->message,
            $first->all(),
        ));
        self::assertSame([Severity::Error, Severity::Warning, Severity::Error], array_map(
            static fn (Diagnostic $diagnostic): Severity => $diagnostic->severity,
            iterator_to_array($first, false),
        ));
    }

    public function testLocationsRenderRelativeToTheProject(): void
    {
        self::assertSame('src/Dto/Item.php:12', Location::inFile('/project/src/Dto/Item.php', 12)->render('/project'));
        self::assertSame('src/Dto/Item.php', Location::inFile('/project/src/Dto/Item.php')->render('/project/'));
        self::assertSame('/elsewhere/Item.php', Location::inFile('/elsewhere/Item.php')->render('/project'));
        self::assertSame('/projectile/Item.php', Location::inFile('/projectile/Item.php')->render('/project'));
        self::assertSame(
            'dto-collection-generator.yaml#/collections/0/class',
            Location::inConfig('/project/dto-collection-generator.yaml', '/collections/0/class')->render('/project'),
        );
        self::assertSame('dto-collection-generator.yaml', Location::inConfig('/project/dto-collection-generator.yaml', '')->render('/project'));
    }
}
