<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Integration\Infrastructure;

use MSSTC4PHP\DtoCollection\Attribute\Variant;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic\Diagnostic;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic\Diagnostics;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Spec\CollectionSpec;
use MSSTC4PHP\DtoCollectionGenerator\Infrastructure\Scanner\AttributeSpecSource;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Projects\Scan\Nested\Shape;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Projects\Scan\Order;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Projects\Scan\Thrower;
use PHPUnit\Framework\TestCase;

final class AttributeSpecSourceTest extends TestCase
{
    private const SCAN = __DIR__ . '/../../Fixtures/Projects/Scan';

    public function testFindsSpecsAndReportsBrokenOnes(): void
    {
        $root = (string) realpath(self::SCAN);
        $diagnostics = new Diagnostics();

        $specs = (new AttributeSpecSource())->specs([$root], $diagnostics);

        self::assertSame([
            ['App\Collection\ShapeList', Variant::Immutable, Shape::class, Shape::class, 'Nested/Shape.php:10'],
            ['App\Collection\OrderList', Variant::Immutable, Order::class, Order::class, 'Order.php:10'],
            ['MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Projects\Scan\MutableOrderList', Variant::Mutable, Order::class . '|null', Order::class, 'Order.php:11'],
            ['App\Collection\ThrowerList', Variant::Immutable, Thrower::class, Thrower::class, 'Thrower.php:10'],
        ], array_map(static fn (CollectionSpec $spec): array => [
            $spec->class->fullName(),
            $spec->variant,
            $spec->elementType->describe(),
            $spec->markedClass?->fullName(),
            $spec->origin->render($root),
        ], $specs));
        self::assertSame([
            'Labelled.php:10: Invalid element type "MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Projects\Scan\Labelled": class or interface "MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Projects\Scan\Labelled" does not exist',
            "Syntax.php:10: Cannot parse this file: Syntax error, unexpected T_CLASS, expecting ':'",
            'Thrower.php:11: The collection class name must not be empty',
            'Unloadable.php:10: Cannot load MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Projects\Scan\Unloadable: Class "MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Projects\Scan\MissingParent" not found',
        ], array_map(
            static fn (Diagnostic $diagnostic): string => $diagnostic->location->render($root) . ': ' . $diagnostic->message,
            $diagnostics->all(),
        ));
        self::assertFalse(array_filter($diagnostics->all(), static fn (Diagnostic $diagnostic): bool => $diagnostic->severity->value !== 'error') !== []);
    }

    public function testNotAutoloadableClass(): void
    {
        $directory = sys_get_temp_dir() . '/dto-collection-generator-scan-' . bin2hex(random_bytes(4));
        mkdir($directory);
        $file = $directory . '/Elsewhere.php';
        file_put_contents($file, '<?php
namespace Outside;

#[\MSSTC4PHP\DtoCollection\Attribute\TypedCollection(class: \'A\\\\B\', variant: ' . Variant::class . '::Immutable)]
final class Elsewhere {}
');
        $diagnostics = new Diagnostics();

        try {
            self::assertSame([], (new AttributeSpecSource())->specs([$directory], $diagnostics));
        } finally {
            unlink($file);
            rmdir($directory);
        }

        self::assertCount(1, $diagnostics);
        self::assertSame(
            'Elsewhere.php:4: Outside\Elsewhere cannot be autoloaded; add its directory to the autoload section of composer.json',
            $diagnostics->all()[0]->location->render($directory) . ': ' . $diagnostics->all()[0]->message,
        );
    }

    public function testEveryDirectoryIsScannedOnce(): void
    {
        $root = (string) realpath(self::SCAN);
        $once = (new AttributeSpecSource())->specs([$root], new Diagnostics());

        self::assertCount(4, $once);
        self::assertEquals($once, (new AttributeSpecSource())->specs([$root . '/missing', $root, $root . '/Nested'], new Diagnostics()));
    }

    public function testMissingDirectoryGivesNothing(): void
    {
        $diagnostics = new Diagnostics();

        self::assertSame([], (new AttributeSpecSource())->specs([self::SCAN . '/missing'], $diagnostics));
        self::assertCount(0, $diagnostics);
    }
}
