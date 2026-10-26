<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Unit\Domain\Validation;

use Closure;
use MSSTC4PHP\DtoCollection\Attribute\Variant;
use MSSTC4PHP\DtoCollection\ElementType;
use MSSTC4PHP\DtoCollection\Equatable;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic\Diagnostic;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic\Diagnostics;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic\Location;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Spec\ClassKind;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Spec\ClassName;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Spec\CollectionSpec;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Target\PhpVersion;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Target\TargetProfile;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Validation\SpecValidator;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Animal;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Cat;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Dog;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Item;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Pet;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Tag;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Support\InMemoryClassInspector;
use PHPUnit\Framework\TestCase;

/**
 * The element types use the real fixture classes (ElementType checks they exist); what the validator learns about
 * them comes from the in-memory inspector.
 */
final class SpecValidatorTest extends TestCase
{
    private InMemoryClassInspector $facts;

    protected function setUp(): void
    {
        $this->facts = new InMemoryClassInspector([
            Item::class => ['kind' => ClassKind::OrdinaryClass, 'equatable' => true],
            Animal::class => ['kind' => ClassKind::Interface],
            Equatable::class => ['kind' => ClassKind::Interface],
            Cat::class => ['kind' => ClassKind::OrdinaryClass, 'parents' => [Animal::class, Equatable::class]],
            Dog::class => ['kind' => ClassKind::OrdinaryClass, 'parents' => [Animal::class]],
            Pet::class => ['kind' => ClassKind::OrdinaryClass, 'odm' => true],
            Tag::class => ['kind' => ClassKind::OrdinaryClass],
            'App\Existing' => ['kind' => ClassKind::OrdinaryClass, 'file' => '/project/src/Existing.php'],
            'App\Generated' => ['kind' => ClassKind::OrdinaryClass, 'file' => '/project/src/Generated.php'],
            'ArrayObject' => ['kind' => ClassKind::OrdinaryClass],
        ]);
    }

    public function testValidSpecsPassUnchanged(): void
    {
        $specs = [
            $this->spec('App\ItemList', ElementType::ofClass(Item::class)),
            $this->spec('App\AnimalList', ElementType::ofClass(Animal::class), marked: Cat::class),
            $this->spec('App\PetCollection', ElementType::ofClass(Pet::class), Variant::Odm, marked: Pet::class),
            $this->spec('App\IntegerList', ElementType::integer(), marked: null),
        ];
        $diagnostics = new Diagnostics();

        self::assertSame($specs, $this->validate($specs, $diagnostics));
        self::assertCount(0, $diagnostics);
    }

    public function testDuplicateClassNamesIgnoringCase(): void
    {
        $first = $this->spec('App\ItemList', ElementType::ofClass(Item::class), line: 3);
        $second = $this->spec('App\itemlist', ElementType::ofClass(Item::class), line: 7);
        $diagnostics = new Diagnostics();

        self::assertSame([$first], $this->validate([$first, $second], $diagnostics));
        self::assertSame(['error 7: App\itemlist: another spec already generates App\ItemList (/project/src/Item.php:3); PHP class names ignore case'], $this->messages($diagnostics));
    }

    public function testExistingHandWrittenClass(): void
    {
        $diagnostics = new Diagnostics();

        self::assertSame([], $this->validate([$this->spec('App\Existing', ElementType::ofClass(Item::class))], $diagnostics));
        self::assertSame(['error 1: App\Existing already exists in /project/src/Existing.php, which dto-collection-generator did not write; choose another class name'], $this->messages($diagnostics));
    }

    public function testExistingInternalClass(): void
    {
        $diagnostics = new Diagnostics();

        self::assertSame([], $this->validate([$this->spec('ArrayObject', ElementType::ofClass(Item::class))], $diagnostics));
        self::assertSame(['error 1: ArrayObject already exists, built into PHP or an extension; choose another class name'], $this->messages($diagnostics));
    }

    public function testExistingGeneratedClassIsNotACollision(): void
    {
        $diagnostics = new Diagnostics();
        $spec = $this->spec('App\Generated', ElementType::ofClass(Item::class));

        self::assertSame([$spec], $this->validate([$spec], $diagnostics));
        self::assertCount(0, $diagnostics);
    }

    public function testCollectionNamedLikeItsElement(): void
    {
        $diagnostics = new Diagnostics();
        $name = strtoupper(Item::class);

        self::assertSame([], $this->validate([$this->spec($name, ElementType::ofClass(Item::class))], $diagnostics, isGenerated: static fn (): bool => true));
        self::assertSame([sprintf('error 1: %s: a collection cannot have the name of its element class %s', $name, Item::class)], $this->messages($diagnostics));
    }

    public function testMarkedClassOutsideElementType(): void
    {
        $animalAndEquatable = ElementType::intersection(Animal::class, Equatable::class);
        $specs = [
            $this->spec('App\EquatableList', ElementType::ofClass(Equatable::class), marked: Dog::class, line: 1),
            $this->spec('App\AnimalList', ElementType::union(ElementType::ofClass(Item::class), ElementType::ofClass(Animal::class)), marked: Dog::class, line: 2),
            $this->spec('App\CatList', $animalAndEquatable, marked: Cat::class, line: 3),
            $this->spec('App\DogList', $animalAndEquatable, marked: Dog::class, line: 4),
            $this->spec('App\IntegerList', ElementType::integer()->nullable(), marked: Dog::class, line: 5),
        ];
        $diagnostics = new Diagnostics();

        self::assertSame([$specs[1], $specs[2]], $this->validate($specs, $diagnostics, '8.2'));
        self::assertSame([
            sprintf('error 1: App\EquatableList: %s, which carries the attribute, is not an %s', Dog::class, Equatable::class),
            sprintf('error 4: App\DogList: %s, which carries the attribute, is not an %s&%s', Dog::class, Animal::class, Equatable::class),
            sprintf('error 5: App\IntegerList: %s, which carries the attribute, is not an ?int', Dog::class),
        ], $this->messages($diagnostics));
    }

    public function testIntersectionNeedsPhp82(): void
    {
        $spec = $this->spec('App\AnimalList', ElementType::intersection(Animal::class, Equatable::class), marked: Cat::class);

        $diagnostics = new Diagnostics();
        self::assertSame([], $this->validate([$spec], $diagnostics, '8.1'));
        self::assertSame([sprintf(
            'error 1: App\AnimalList: an intersection element type needs target PHP 8.2 or newer, where (%s&%s)|null can be declared; the target is PHP 8.1',
            Animal::class,
            Equatable::class,
        )], $this->messages($diagnostics));

        $diagnostics = new Diagnostics();
        self::assertSame([$spec], $this->validate([$spec], $diagnostics, '8.2'));
        self::assertCount(0, $diagnostics);
    }

    public function testOdmWithScalarBranch(): void
    {
        $diagnostics = new Diagnostics();
        $type = ElementType::union(ElementType::integer(), ElementType::ofClass(Pet::class));

        self::assertSame([], $this->validate([$this->spec('App\MixedCollection', $type, Variant::Odm, marked: null)], $diagnostics));
        self::assertSame(['error 1: App\MixedCollection: an ODM collection holds documents; int is not one'], $this->messages($diagnostics));
    }

    public function testOdmWithoutOdmInstalled(): void
    {
        $facts = new InMemoryClassInspector([Pet::class => ['kind' => ClassKind::OrdinaryClass, 'odm' => true]], odmInstalled: false);
        $diagnostics = new Diagnostics();

        self::assertSame([], (new SpecValidator())->validate([$this->spec('App\PetCollection', ElementType::ofClass(Pet::class), Variant::Odm, marked: Pet::class)], $this->target('8.1'), $facts, $diagnostics, static fn (): bool => true));
        self::assertSame(['error 1: App\PetCollection: the ODM variant needs doctrine/mongodb-odm, which is not installed'], $this->messages($diagnostics));
    }

    public function testOdmWithoutMappingWarns(): void
    {
        $specs = [
            $this->spec('App\TagCollection', ElementType::ofClass(Tag::class), Variant::Odm, marked: Tag::class, line: 1),
            $this->spec('App\AnimalCollection', ElementType::ofClass(Animal::class), Variant::Odm, marked: Cat::class, line: 2),
        ];
        $diagnostics = new Diagnostics();

        self::assertSame($specs, $this->validate($specs, $diagnostics));
        self::assertSame([sprintf('warning 1: App\TagCollection: %s has no ODM document attribute; make sure it is mapped (in XML, for example)', Tag::class)], $this->messages($diagnostics));
    }

    /**
     * @param list<CollectionSpec> $specs
     * @param (Closure(string): bool)|null $isGenerated
     *
     * @return list<CollectionSpec>
     */
    private function validate(array $specs, Diagnostics $diagnostics, string $php = '8.1', ?Closure $isGenerated = null): array
    {
        return (new SpecValidator())->validate(
            $specs,
            $this->target($php),
            $this->facts,
            $diagnostics,
            $isGenerated ?? static fn (string $file): bool => $file === '/project/src/Generated.php',
        );
    }

    private function target(string $php): TargetProfile
    {
        return new TargetProfile(PhpVersion::fromString($php));
    }

    /**
     * @param ElementType<mixed> $type
     */
    private function spec(string $class, ElementType $type, Variant $variant = Variant::Immutable, ?string $marked = Item::class, int $line = 1): CollectionSpec
    {
        return new CollectionSpec(
            ClassName::fromString($class),
            $variant,
            $type,
            $marked === null ? null : ClassName::fromString($marked),
            Location::inFile('/project/src/Item.php', $line),
        );
    }

    /**
     * @return list<string>
     */
    private function messages(Diagnostics $diagnostics): array
    {
        return array_map(
            static fn (Diagnostic $diagnostic): string => sprintf('%s %d: %s', $diagnostic->severity->value, $diagnostic->location->line, $diagnostic->message),
            $diagnostics->all(),
        );
    }
}
