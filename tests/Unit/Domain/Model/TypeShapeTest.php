<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Unit\Domain\Model;

use Closure;
use Countable;
use MSSTC4PHP\DtoCollection\ElementType;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Model\TypeShape;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Cat;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Dog;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Item;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Traversable;

final class TypeShapeTest extends TestCase
{
    /**
     * @return iterable<string, array{ElementType<mixed>, string, string, string, string, list<string>}>
     */
    public static function shapes(): iterable
    {
        // type, native T, native ?T, PHPDoc T, PHPDoc ?T, classes
        yield 'int' => [ElementType::integer(), 'int', '?int', 'int', 'int|null', []];
        yield 'bool' => [ElementType::boolean(), 'bool', '?bool', 'bool', 'bool|null', []];
        yield 'nullable int' => [ElementType::integer()->nullable(), '?int', '?int', 'int|null', 'int|null', []];
        yield 'float|int' => [ElementType::parse('float|int'), 'float|int', 'float|int|null', 'float|int', 'float|int|null', []];
        yield 'class' => [ElementType::ofClass(Item::class), '~Item', '?~Item', '~Item', '~Item|null', [Item::class]];
        yield 'nullable class' => [ElementType::ofClass(Item::class)->nullable(), '?~Item', '?~Item', '~Item|null', '~Item|null', [Item::class]];
        yield 'union' => [
            ElementType::union(ElementType::ofClass(Cat::class), ElementType::ofClass(Dog::class)),
            '~Cat|~Dog', '~Cat|~Dog|null', '~Cat|~Dog', '~Cat|~Dog|null', [Cat::class, Dog::class],
        ];
        yield 'nullable union' => [
            ElementType::parse(Cat::class . '|' . Dog::class . '|null'),
            '~Cat|~Dog|null', '~Cat|~Dog|null', '~Cat|~Dog|null', '~Cat|~Dog|null', [Cat::class, Dog::class],
        ];
        yield 'scalar and class' => [
            ElementType::parse('int|' . Item::class),
            'int|~Item', 'int|~Item|null', 'int|~Item', 'int|~Item|null', [Item::class],
        ];
        yield 'intersection' => [
            ElementType::intersection(Countable::class, Traversable::class),
            '~Countable&~Traversable', '(~Countable&~Traversable)|null', '~Countable&~Traversable', '(~Countable&~Traversable)|null', [Countable::class, Traversable::class],
        ];
        yield 'nullable intersection' => [
            ElementType::intersection(Countable::class, Traversable::class)->nullable(),
            '(~Countable&~Traversable)|null', '(~Countable&~Traversable)|null',
            '(~Countable&~Traversable)|null', '(~Countable&~Traversable)|null', [Countable::class, Traversable::class],
        ];
    }

    /**
     * @param ElementType<mixed> $type
     * @param list<string> $classes
     */
    #[DataProvider('shapes')]
    public function testRendering(ElementType $type, string $native, string $nullable, string $phpDoc, string $nullablePhpDoc, array $classes): void
    {
        $shape = TypeShape::of($type);
        $name = $this->shortName();

        self::assertSame($native, $shape->native($name));
        self::assertSame($nullable, $shape->nullableNative($name));
        self::assertSame($phpDoc, $shape->phpDoc($name));
        self::assertSame($nullablePhpDoc, $shape->nullablePhpDoc($name));
        self::assertSame($classes, $shape->classes());
        self::assertSame($type->isNullable(), $shape->nullable);
    }

    public function testIntersectionFlag(): void
    {
        self::assertTrue(TypeShape::of(ElementType::intersection(Countable::class, Traversable::class))->hasIntersection());
        self::assertTrue(TypeShape::of(ElementType::parse('int|' . Item::class))->hasScalar());
        self::assertFalse(TypeShape::of(ElementType::ofClass(Item::class))->hasIntersection());
        self::assertFalse(TypeShape::of(ElementType::ofClass(Item::class))->hasScalar());
    }

    public function testBranches(): void
    {
        self::assertSame(
            ['int', [Item::class], [Countable::class, Traversable::class]],
            TypeShape::of(ElementType::union(ElementType::integer(), ElementType::ofClass(Item::class), ElementType::intersection(Countable::class, Traversable::class)))->branches,
        );
    }

    /**
     * @return Closure(string): string
     */
    private function shortName(): Closure
    {
        return static fn (string $class): string => '~' . substr($class, (int) strrpos('\\' . $class, '\\'));
    }
}
