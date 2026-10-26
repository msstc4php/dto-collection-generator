<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Unit\Application\Config;

use MSSTC4PHP\DtoCollection\Attribute\Variant;
use MSSTC4PHP\DtoCollection\ElementType;
use MSSTC4PHP\DtoCollectionGenerator\Application\Config\ConfigFactory;
use MSSTC4PHP\DtoCollectionGenerator\Application\Config\GeneratorConfig;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic\Diagnostic;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic\Diagnostics;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Broken;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Item;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Pet;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigFactoryTest extends TestCase
{
    private const CONFIG = '/project/dto-collection-generator.yaml';

    public function testCompleteConfig(): void
    {
        $directory = dirname(__DIR__, 3) . '/Fixtures';
        $diagnostics = new Diagnostics();
        $config = ConfigFactory::create([
            'version' => 1,
            'target' => ['php' => '8.3'],
            'sources' => ['Model', './Model/../Model'],
            'collections' => [
                ['class' => 'App\Collection\IntegerList', 'variant' => 'immutable', 'elementType' => 'int'],
                ['class' => '\App\Collection\ItemList', 'variant' => 'mutable', 'elementType' => Item::class, 'nullable' => true],
            ],
            'paths' => ['App\Collection\\' => 'src/Collection', 'Other\\' => '/elsewhere/other'],
        ], $directory . '/dto-collection-generator.yaml', $diagnostics);

        self::assertSame([], $this->messages($diagnostics));
        self::assertInstanceOf(GeneratorConfig::class, $config);
        self::assertSame($directory . '/dto-collection-generator.yaml', $config->path);
        self::assertSame($directory, $config->directory);
        self::assertSame('8.3', (string) $config->php);
        self::assertSame([$directory . '/Model', $directory . '/Model'], $config->sources);
        self::assertSame(['App\Collection\\' => $directory . '/src/Collection', 'Other\\' => '/elsewhere/other'], $config->paths);
        self::assertCount(2, $config->collections);
        [$integers, $items] = $config->collections;
        self::assertSame('App\Collection\IntegerList', $integers->class->fullName());
        self::assertSame(Variant::Immutable, $integers->variant);
        self::assertTrue($integers->elementType->equals(ElementType::integer()));
        self::assertNull($integers->markedClass);
        self::assertSame('dto-collection-generator.yaml#/collections/0', $integers->origin->render($directory));
        self::assertSame(Variant::Mutable, $items->variant);
        self::assertTrue($items->elementType->equals(ElementType::ofClass(Item::class)->nullable()));
    }

    public function testDefaults(): void
    {
        $diagnostics = new Diagnostics();
        $config = ConfigFactory::create(['version' => 1], self::CONFIG, $diagnostics);

        self::assertSame([], $this->messages($diagnostics));
        self::assertInstanceOf(GeneratorConfig::class, $config);
        self::assertNull($config->php);
        self::assertSame([], $config->sources);
        self::assertSame([], $config->collections);
        self::assertSame([], $config->paths);
    }

    public function testAutoTarget(): void
    {
        $config = ConfigFactory::create(['version' => 1, 'target' => ['php' => 'auto']], self::CONFIG, new Diagnostics());

        self::assertNull($config?->php);
    }

    /**
     * @return iterable<string, array{array<mixed>, string}>
     */
    public static function mistakes(): iterable
    {
        yield 'unknown root key' => [['version' => 1, 'extra' => true], 'error #/extra: Unknown key "extra"; expected one of: version, target, sources, collections, paths.'];
        yield 'missing version' => [[], 'error : "version" is required.'];
        yield 'wrong version' => [['version' => 2], 'error #/version: "version" must be 1.'];
        yield 'target not an object' => [['version' => 1, 'target' => '8.1'], 'error #/target: "target" must be an object.'];
        yield 'unknown target key' => [['version' => 1, 'target' => ['mode' => 'x']], 'error #/target/mode: Unknown key "mode"; expected one of: php.'];
        yield 'unsupported php' => [['version' => 1, 'target' => ['php' => '8.0']], 'error #/target/php: PHP "8.0" is not a supported target; use one of 8.1, 8.2, 8.3, 8.4, 8.5.'];
        yield 'unquoted php' => [['version' => 1, 'target' => ['php' => 8.3]], 'error #/target/php: "php" must be "auto" or a version in quotes, such as \'8.3\'.'];
        yield 'sources not a list' => [['version' => 1, 'sources' => 'src'], 'error #/sources: "sources" must be a list of strings.'];
        yield 'empty source' => [['version' => 1, 'sources' => ['']], 'error #/sources/0: Expected a non-empty string.'];
        yield 'collections not a list' => [['version' => 1, 'collections' => ['class' => 'A']], 'error #/collections: "collections" must be a list.'];
        yield 'collection not an object' => [['version' => 1, 'collections' => ['A']], 'error #/collections/0: Expected an object.'];
        yield 'unknown collection key' => [['version' => 1, 'collections' => [self::collection(['size' => 1])]], 'error #/collections/0/size: Unknown key "size"; expected one of: class, variant, elementType, nullable.'];
        yield 'missing class' => [['version' => 1, 'collections' => [self::collection([], 'class')]], 'error #/collections/0: "class" is required.'];
        yield 'missing element type' => [['version' => 1, 'collections' => [self::collection([], 'elementType')]], 'error #/collections/0: "elementType" is required.'];
        yield 'missing variant' => [['version' => 1, 'collections' => [self::collection([], 'variant')]], 'error #/collections/0: "variant" is required.'];
        yield 'unknown variant' => [['version' => 1, 'collections' => [self::collection(['variant' => 'list'])]], 'error #/collections/0/variant: "variant" must be one of: immutable, mutable, odm.'];
        yield 'nullable not a bool' => [['version' => 1, 'collections' => [self::collection(['nullable' => 'yes'])]], 'error #/collections/0/nullable: "nullable" must be true or false.'];
        yield 'bad class name' => [['version' => 1, 'collections' => [self::collection(['class' => 'App\List'])]], 'error #/collections/0/class: "App\List" cannot name a class: "List" is a reserved word'];
        yield 'bad element type' => [['version' => 1, 'collections' => [self::collection(['elementType' => 'App\Missing'])]], 'error #/collections/0/elementType: Invalid element type "App\Missing": class or interface "App\Missing" does not exist'];
        yield 'unloadable element class' => [['version' => 1, 'collections' => [self::collection(['elementType' => Broken::class])]], 'error #/collections/0/elementType: Cannot load the element type: Class "MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\MissingParent" not found'];
        yield 'nullable odm' => [['version' => 1, 'collections' => [self::collection(['variant' => 'odm', 'elementType' => Pet::class, 'nullable' => true])]], 'error #/collections/0: App\IntegerList: Doctrine ODM does not store null elements, so an ODM collection cannot be nullable'];
        yield 'paths not an object' => [['version' => 1, 'paths' => ['src']], 'error #/paths: "paths" must be an object.'];
        yield 'path prefix without separator' => [['version' => 1, 'paths' => ['App' => 'src']], 'error #/paths/App: A namespace prefix ends with "\"; write "App\".'];
        yield 'path prefix not a namespace' => [['version' => 1, 'paths' => ['1App\\' => 'src']], 'error #/paths/1App\: "1App\" is not a namespace prefix.'];
        yield 'numeric path prefix' => [['version' => 1, 'paths' => ['1' => 'src']], 'error #/paths/1: A namespace prefix ends with "\"; write "1\".'];
        yield 'escaped pointer' => [['version' => 1, 'paths' => ['a/b~' => 'src']], 'error #/paths/a~1b~0: A namespace prefix ends with "\"; write "a/b~\".'];
        yield 'class not a string' => [['version' => 1, 'collections' => [self::collection(['class' => 5])]], 'error #/collections/0/class: "class" must be a non-empty string.'];
        yield 'empty class' => [['version' => 1, 'collections' => [self::collection(['class' => ''])]], 'error #/collections/0/class: "class" must be a non-empty string.'];
        yield 'collection is a list' => [['version' => 1, 'collections' => [['A', 'B']]], 'error #/collections/0: Expected an object.'];
        yield 'empty path' => [['version' => 1, 'paths' => ['App\\' => '']], 'error #/paths/App\: Expected a non-empty string.'];
    }

    /**
     * @param array<mixed> $raw
     */
    #[DataProvider('mistakes')]
    public function testMistakes(array $raw, string $expected): void
    {
        $diagnostics = new Diagnostics();

        self::assertNull(ConfigFactory::create($raw, self::CONFIG, $diagnostics));
        self::assertSame([$expected], $this->messages($diagnostics));
    }

    public function testEveryEntryOfAListIsChecked(): void
    {
        $diagnostics = new Diagnostics();
        ConfigFactory::create([
            'version' => 1,
            'sources' => ['', ''],
            'collections' => ['A', 'B', []],
            'paths' => ['App' => '', 'Other' => 'src', 'Third' => 'src', '1A\\' => 'src', '2B\\' => 'src'],
        ], self::CONFIG, $diagnostics);

        self::assertSame([
            'error #/sources/0: Expected a non-empty string.',
            'error #/sources/1: Expected a non-empty string.',
            'error #/collections/0: Expected an object.',
            'error #/collections/1: Expected an object.',
            'error #/collections/2: "class" is required.',
            'error #/collections/2: "variant" is required.',
            'error #/collections/2: "elementType" is required.',
            'error #/paths/App: Expected a non-empty string.',
            'error #/paths/Other: A namespace prefix ends with "\"; write "Other\".',
            'error #/paths/Third: A namespace prefix ends with "\"; write "Third\".',
            'error #/paths/1A\\: "1A\\" is not a namespace prefix.',
            'error #/paths/2B\\: "2B\\" is not a namespace prefix.',
        ], $this->messages($diagnostics));
    }

    public function testEveryMistakeIsReportedInOneRun(): void
    {
        $diagnostics = new Diagnostics();
        ConfigFactory::create(['version' => 2, 'extra' => 1, 'collections' => [self::collection(['variant' => 'list']), 'A']], self::CONFIG, $diagnostics);

        self::assertCount(4, $diagnostics);
    }

    public function testMissingSourceDirectoryWarns(): void
    {
        $diagnostics = new Diagnostics();
        $config = ConfigFactory::create(['version' => 1, 'sources' => ['missing']], self::CONFIG, $diagnostics);

        self::assertInstanceOf(GeneratorConfig::class, $config);
        self::assertSame(['warning #/sources/0: Directory "/project/missing" does not exist; nothing is scanned there.'], $this->messages($diagnostics));
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private static function collection(array $overrides, ?string $without = null): array
    {
        $collection = array_merge(['class' => 'App\IntegerList', 'variant' => 'immutable', 'elementType' => 'int'], $overrides);
        unset($collection[$without ?? '']);

        return $collection;
    }

    /**
     * @return list<string>
     */
    private function messages(Diagnostics $diagnostics): array
    {
        return array_map(
            static fn (Diagnostic $diagnostic): string => sprintf(
                '%s %s: %s',
                $diagnostic->severity->value,
                substr($diagnostic->location->render('/project'), strlen('dto-collection-generator.yaml')),
                $diagnostic->message,
            ),
            $diagnostics->all(),
        );
    }
}
