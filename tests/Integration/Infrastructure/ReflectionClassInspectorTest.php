<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Integration\Infrastructure;

use Countable;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Spec\ClassKind;
use MSSTC4PHP\DtoCollectionGenerator\Infrastructure\Reflection\ReflectionClassInspector;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Animal;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Broken;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Cat;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Colour;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Dog;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Item;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Labelled;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Pet;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Tag;
use PHPUnit\Framework\TestCase;

final class ReflectionClassInspectorTest extends TestCase
{
    public function testKinds(): void
    {
        $inspector = new ReflectionClassInspector();

        self::assertSame(ClassKind::OrdinaryClass, $inspector->kind(Item::class));
        self::assertSame(ClassKind::Interface, $inspector->kind(Animal::class));
        self::assertSame(ClassKind::Trait, $inspector->kind(Labelled::class));
        self::assertSame(ClassKind::Enum, $inspector->kind(Colour::class));
        self::assertSame(ClassKind::Interface, $inspector->kind(Countable::class));
        self::assertNull($inspector->kind('Nope\Missing'));
        self::assertNull($inspector->kind(Broken::class));
    }

    public function testHierarchy(): void
    {
        $inspector = new ReflectionClassInspector();

        self::assertTrue($inspector->isA(Cat::class, Animal::class));
        self::assertTrue($inspector->isA(Cat::class, Cat::class));
        self::assertFalse($inspector->isA(Tag::class, Animal::class));
        self::assertFalse($inspector->isA('Nope\Missing', Animal::class));
        self::assertTrue($inspector->isEquatable(Item::class));
        self::assertFalse($inspector->isEquatable(Tag::class));
        self::assertFalse($inspector->isEquatable(Dog::class));
    }

    public function testOdm(): void
    {
        $inspector = new ReflectionClassInspector();

        self::assertTrue($inspector->isOdmInstalled());
        self::assertTrue($inspector->hasOdmMapping(Pet::class));
        self::assertFalse($inspector->hasOdmMapping(Tag::class));
        self::assertFalse($inspector->hasOdmMapping('Nope\Missing'));
    }

    public function testFiles(): void
    {
        $inspector = new ReflectionClassInspector();

        self::assertSame(dirname(__DIR__, 2) . '/Fixtures/Model/Item.php', $inspector->fileOf(Item::class));
        self::assertNull($inspector->fileOf(Countable::class));
        self::assertNull($inspector->fileOf('Nope\Missing'));
    }
}
