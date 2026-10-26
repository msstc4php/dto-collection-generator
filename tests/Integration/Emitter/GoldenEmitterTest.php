<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Integration\Emitter;

use Countable;
use MSSTC4PHP\DtoCollection\Attribute\Variant;
use MSSTC4PHP\DtoCollection\ElementType;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic\Location;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Spec\ClassName;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Spec\CollectionSpec;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Target\PhpVersion;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Target\TargetProfile;
use MSSTC4PHP\DtoCollectionGenerator\Infrastructure\Emitter\StringCodeEmitter;
use MSSTC4PHP\DtoCollectionGenerator\Infrastructure\Reflection\ReflectionClassInspector;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Cat;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Closure;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Dog;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Item;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Pet;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Traversable;

/**
 * Golden files: tests/Fixtures/Emitter/<php>/<class>.php.golden. UPDATE_SNAPSHOTS=1 rewrites them; review the diff.
 */
final class GoldenEmitterTest extends TestCase
{
    private const DIRECTORY = __DIR__ . '/../../Fixtures/Emitter';

    private const PROFILES = ['8.1', '8.2', '8.3'];

    /**
     * @return iterable<string, array{string, CollectionSpec}>
     */
    public static function cases(): iterable
    {
        foreach (self::PROFILES as $php) {
            foreach (self::specs($php) as $spec) {
                yield $php . ' ' . $spec->class->shortName() => [$php, $spec];
            }
        }
    }

    #[DataProvider('cases')]
    public function testEmitsTheGoldenFile(string $php, CollectionSpec $spec): void
    {
        $code = (new StringCodeEmitter())->emit($spec, new TargetProfile(PhpVersion::fromString($php)), new ReflectionClassInspector());
        $golden = sprintf('%s/%s/%s.php.golden', self::DIRECTORY, $php, $spec->class->shortName());
        if (getenv('UPDATE_SNAPSHOTS') === '1') {
            if (!is_dir(dirname($golden))) {
                mkdir(dirname($golden), 0o777, true);
            }
            file_put_contents($golden, $code);
        }

        self::assertStringEqualsFile($golden, $code);
    }

    public function testEveryGoldenFileHasACase(): void
    {
        $expected = [];
        foreach (self::PROFILES as $php) {
            foreach (self::specs($php) as $spec) {
                $expected[] = $php . '/' . $spec->class->shortName() . '.php.golden';
            }
        }
        $actual = [];
        $files = glob(self::DIRECTORY . '/*/*.php.golden');
        self::assertIsArray($files);
        foreach ($files as $file) {
            $actual[] = basename(dirname($file)) . '/' . basename($file);
        }
        sort($expected);
        sort($actual);

        self::assertSame($expected, $actual);
    }

    /**
     * @return list<CollectionSpec>
     */
    private static function specs(string $php): array
    {
        $specs = [
            self::spec('ItemList', Variant::Immutable, ElementType::ofClass(Item::class)),
            self::spec('MutableItemList', Variant::Mutable, ElementType::ofClass(Item::class)),
            self::spec('PetCollection', Variant::Odm, ElementType::ofClass(Pet::class)),
            self::spec('IntegerList', Variant::Immutable, ElementType::integer()),
            self::spec('FloatList', Variant::Immutable, ElementType::float()),
            self::spec('MutableStringList', Variant::Mutable, ElementType::string()),
            self::spec('BooleanList', Variant::Immutable, ElementType::boolean()),
            self::spec('NullableTagList', Variant::Mutable, ElementType::ofClass(Tag::class)->nullable()),
            self::spec('AnimalList', Variant::Immutable, ElementType::union(ElementType::ofClass(Cat::class), ElementType::ofClass(Dog::class))->nullable()),
            self::spec('ClosureHolderList', Variant::Immutable, ElementType::ofClass(Closure::class)),
        ];
        if ($php !== '8.1') {
            $specs[] = self::spec('CountableTraversableList', Variant::Mutable, ElementType::intersection(Countable::class, Traversable::class));
        }

        return $specs;
    }

    /**
     * @param ElementType<mixed> $type
     */
    private static function spec(string $shortName, Variant $variant, ElementType $type): CollectionSpec
    {
        return new CollectionSpec(ClassName::fromString('App\Collection\\' . $shortName), $variant, $type, null, Location::inFile('/project/config.yaml'));
    }
}
