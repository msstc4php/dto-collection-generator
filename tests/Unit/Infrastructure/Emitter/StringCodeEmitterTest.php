<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Unit\Infrastructure\Emitter;

use LogicException;
use MSSTC4PHP\DtoCollection\Attribute\Variant;
use MSSTC4PHP\DtoCollection\ElementType;
use MSSTC4PHP\DtoCollection\Equatable;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic\Location;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Spec\ClassKind;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Spec\ClassName;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Spec\CollectionSpec;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Target\PhpVersion;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Target\TargetProfile;
use MSSTC4PHP\DtoCollectionGenerator\Infrastructure\Emitter\StringCodeEmitter;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Animal;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Cat;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Dog;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Item;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Pet;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Support\InMemoryClassInspector;
use PHPUnit\Framework\TestCase;

final class StringCodeEmitterTest extends TestCase
{
    private const IGNORE = '// @phpstan-ignore return.type, assign.propertyType (ElementType::intersection() is typed ElementType<object>: PHPDoc cannot name the intersection of variadic class-strings)';

    public function testIntersectionElementTypeCarriesANarrowIgnore(): void
    {
        $code = $this->emit('App\Collection\SomeList', ElementType::intersection(Animal::class, Equatable::class));

        self::assertStringContainsString('        return self::$type ??= ElementType::intersection(Animal::class, Equatable::class); ' . self::IGNORE . "\n", $code);
    }

    public function testOtherElementTypesHaveNoIgnore(): void
    {
        self::assertStringNotContainsString('@phpstan-ignore', $this->emit('App\Collection\SomeList', ElementType::union(ElementType::ofClass(Item::class), ElementType::integer())->nullable()));
    }

    public function testEveryEquatableBranchGetsAComparator(): void
    {
        $code = $this->emit('App\Collection\MixedList', ElementType::union(ElementType::ofClass(Dog::class), ElementType::ofClass(Item::class), ElementType::ofClass(Cat::class)));

        self::assertStringContainsString(
            'return self::$sameness ??= ComparatorEquality::of(Item::class, static fn (Item $left, Item $right): bool => $left->equals($right))'
            . '->with(Cat::class, static fn (Cat $left, Cat $right): bool => $left->equals($right));',
            $code,
        );
    }

    public function testAGlobalCollectionIsALogicError(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('ItemList: a collection needs a namespace; the validator rejects it');

        $this->emit('ItemList', ElementType::ofClass(Item::class));
    }

    public function testCollectionNamedLikeAFixedImport(): void
    {
        $collection = $this->emit('App\Collection\Collection', ElementType::ofClass(Pet::class), Variant::Odm);
        self::assertStringContainsString('class Collection implements \Doctrine\Common\Collections\Collection, Selectable, MutableList, TypedList', $collection);
        self::assertStringContainsString(' * @implements \Doctrine\Common\Collections\Collection<int, Pet>', $collection);
        self::assertStringNotContainsString('use Doctrine\Common\Collections\Collection;', $collection);

        $closure = $this->emit('App\Collection\Closure', ElementType::ofClass(Item::class));
        self::assertStringContainsString('public function find(\Closure $predicate): ?Item', $closure);
        self::assertStringNotContainsString('use Closure;', $closure);
    }

    /**
     * @param ElementType<mixed> $type
     */
    private function emit(string $class, ElementType $type, Variant $variant = Variant::Immutable): string
    {
        $spec = new CollectionSpec(ClassName::fromString($class), $variant, $type, null, Location::inFile('/project/config.yaml'));
        $facts = new InMemoryClassInspector([
            Item::class => ['kind' => ClassKind::OrdinaryClass, 'equatable' => true],
            Cat::class => ['kind' => ClassKind::OrdinaryClass, 'equatable' => true],
        ]);

        return (new StringCodeEmitter())->emit($spec, new TargetProfile(PhpVersion::fromString('8.2')), $facts);
    }
}
