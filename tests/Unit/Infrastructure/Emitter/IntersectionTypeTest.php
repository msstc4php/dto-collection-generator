<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Unit\Infrastructure\Emitter;

use MSSTC4PHP\DtoCollection\Attribute\Variant;
use MSSTC4PHP\DtoCollection\ElementType;
use MSSTC4PHP\DtoCollection\Equatable;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic\Location;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Spec\ClassName;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Spec\CollectionSpec;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Target\PhpVersion;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Target\TargetProfile;
use MSSTC4PHP\DtoCollectionGenerator\Infrastructure\Emitter\StringCodeEmitter;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Animal;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Item;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Support\InMemoryClassInspector;
use PHPUnit\Framework\TestCase;

final class IntersectionTypeTest extends TestCase
{
    private const IGNORE = '// @phpstan-ignore return.type, assign.propertyType (ElementType::intersection() is typed ElementType<object>: PHPDoc cannot name the intersection of variadic class-strings)';

    public function testIntersectionElementTypeCarriesANarrowIgnore(): void
    {
        $code = $this->emit(ElementType::intersection(Animal::class, Equatable::class));

        self::assertStringContainsString('        return self::$type ??= ElementType::intersection(Animal::class, Equatable::class); ' . self::IGNORE . "\n", $code);
    }

    public function testOtherElementTypesHaveNoIgnore(): void
    {
        self::assertStringNotContainsString('@phpstan-ignore', $this->emit(ElementType::union(ElementType::ofClass(Item::class), ElementType::integer())->nullable()));
    }

    /**
     * @param ElementType<mixed> $type
     */
    private function emit(ElementType $type): string
    {
        $spec = new CollectionSpec(ClassName::fromString('App\Collection\SomeList'), Variant::Immutable, $type, null, Location::inFile('/project/config.yaml'));

        return (new StringCodeEmitter())->emit($spec, new TargetProfile(PhpVersion::fromString('8.2')), new InMemoryClassInspector([]));
    }
}
