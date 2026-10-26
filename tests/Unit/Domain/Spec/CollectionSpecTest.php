<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Unit\Domain\Spec;

use MSSTC4PHP\DtoCollection\Attribute\TypedCollection;
use MSSTC4PHP\DtoCollection\Attribute\Variant;
use MSSTC4PHP\DtoCollection\ElementType;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic\Location;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Spec\ClassName;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Spec\CollectionSpec;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Cat;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Dog;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Item;
use PHPUnit\Framework\TestCase;

final class CollectionSpecTest extends TestCase
{
    public function testTheMarkedClassIsTheDefaultElementType(): void
    {
        $origin = Location::inFile('/project/src/Item.php', 9);
        $spec = CollectionSpec::fromAttribute(
            new TypedCollection('App\ItemList', Variant::Mutable),
            ClassName::fromString(Item::class),
            $origin,
        );

        self::assertSame('App\ItemList', $spec->class->fullName());
        self::assertSame(Variant::Mutable, $spec->variant);
        self::assertTrue($spec->elementType->equals(ElementType::ofClass(Item::class)));
        self::assertSame(Item::class, $spec->markedClass?->fullName());
        self::assertSame($origin, $spec->origin);
    }

    public function testDeclaredElementTypeAndNullable(): void
    {
        $spec = CollectionSpec::fromAttribute(
            new TypedCollection('\App\AnimalList', Variant::Immutable, nullable: true, elementType: Cat::class . '|' . Dog::class),
            ClassName::fromString(Item::class),
            Location::inFile('/project/src/Item.php', 9),
        );

        self::assertSame('App\AnimalList', $spec->class->fullName());
        self::assertSame(Cat::class . '|' . Dog::class . '|null', $spec->elementType->describe());
    }

    public function testScalarFromConfig(): void
    {
        $spec = CollectionSpec::fromAttribute(
            new TypedCollection('App\IntegerList', Variant::Immutable, elementType: 'int'),
            null,
            Location::inConfig('/project/dto-collection-generator.yaml', '/collections/0'),
        );

        self::assertTrue($spec->elementType->equals(ElementType::integer()));
        self::assertNull($spec->markedClass);
    }

    public function testAlreadyNullableStaysNullable(): void
    {
        $spec = CollectionSpec::fromAttribute(
            new TypedCollection('App\IntegerList', Variant::Immutable, nullable: true, elementType: '?int'),
            null,
            Location::inConfig('/project/dto-collection-generator.yaml', '/collections/0'),
        );

        self::assertTrue($spec->elementType->equals(ElementType::integer()->nullable()));
    }
}
