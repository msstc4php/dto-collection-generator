<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Application\Config;

use MSSTC4PHP\DtoCollection\Attribute\TypedCollection;
use MSSTC4PHP\DtoCollection\Attribute\Variant;
use MSSTC4PHP\DtoCollection\Exception\InvalidArgument;
use MSSTC4PHP\DtoCollection\Exception\InvalidElementType;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic\Diagnostics;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Exception\InvalidClassName;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Exception\UnsupportedPhpVersion;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Shared\Path;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Spec\ClassName;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Spec\CollectionSpec;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Target\PhpVersion;
use Throwable;

/**
 * @internal
 */
final class ConfigFactory
{
    private const NAMESPACE_PREFIX = '/^(?:[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*\\\\)+$/';

    private function __construct()
    {
    }

    /**
     * @param array<array-key, mixed> $raw the decoded config file
     * @param string $path absolute path of the config file
     *
     * @return GeneratorConfig|null null when the file has an error; the errors are in $diagnostics
     */
    public static function create(array $raw, string $path, Diagnostics $diagnostics): ?GeneratorConfig
    {
        $errors = count($diagnostics->errors());
        $directory = Path::directory($path);
        $root = new RawSection($raw, $path, '', $diagnostics);
        $root->rejectUnknownKeys(['version', 'target', 'sources', 'collections', 'paths']);
        self::checkVersion($root);
        $config = new GeneratorConfig(
            $path,
            $directory,
            self::php($root->section('target')),
            self::sources($root, $directory),
            self::collections($root),
            self::paths($root, $directory),
        );

        return count($diagnostics->errors()) > $errors ? null : $config;
    }

    private static function checkVersion(RawSection $root): void
    {
        if (!$root->has('version')) {
            $root->report('"version" is required.');
        } elseif ($root->value('version') !== 1) {
            $root->error('version', 'must be 1');
        }
    }

    private static function php(RawSection $target): ?PhpVersion
    {
        $target->rejectUnknownKeys(['php']);
        $php = $target->value('php') ?? 'auto';
        if (!is_string($php)) {
            $target->error('php', 'must be "auto" or a version in quotes, such as \'8.3\'');

            return null;
        }
        if ($php === 'auto') {
            return null;
        }

        try {
            return PhpVersion::fromString($php);
        } catch (UnsupportedPhpVersion $exception) {
            $target->report($exception->getMessage() . '.', 'php');

            return null;
        }
    }

    /**
     * @return list<string>
     */
    private static function sources(RawSection $root, string $directory): array
    {
        $sources = [];
        foreach ($root->stringList('sources') as $index => $source) {
            $absolute = Path::resolve($directory, $source);
            if (!is_dir($absolute)) {
                $root->warn(sprintf('Directory "%s" does not exist; nothing is scanned there.', $absolute), 'sources', (string) $index);
            }
            $sources[] = $absolute;
        }

        return $sources;
    }

    /**
     * @return list<CollectionSpec>
     */
    private static function collections(RawSection $root): array
    {
        $specs = [];
        foreach ($root->sectionList('collections') as $section) {
            $spec = self::collection($section);
            if ($spec instanceof CollectionSpec) {
                $specs[] = $spec;
            }
        }

        return $specs;
    }

    private static function collection(RawSection $section): ?CollectionSpec
    {
        $section->rejectUnknownKeys(['class', 'variant', 'elementType', 'nullable']);
        $class = $section->requiredString('class');
        $variant = $section->choice('variant', array_map(static fn (Variant $case): string => $case->value, Variant::cases()), true);
        $elementType = $section->requiredString('elementType');
        $nullable = $section->bool('nullable', false);
        if ($class === null || $variant === null || $elementType === null) {
            return null;
        }

        try {
            ClassName::fromString($class);
        } catch (InvalidClassName $exception) {
            $section->report($exception->getMessage(), 'class');

            return null;
        }

        try {
            $attribute = new TypedCollection($class, Variant::from($variant), $nullable, $elementType);
        } catch (InvalidElementType $exception) {
            $section->report($exception->getMessage(), 'elementType');

            return null;
        } catch (InvalidArgument $exception) {
            $section->report($exception->getMessage());

            return null;
        } catch (Throwable $exception) {
            // Autoloading a broken class file throws an Error, not an InvalidElementType.
            $section->report('Cannot load the element type: ' . $exception->getMessage(), 'elementType');

            return null;
        }

        return CollectionSpec::fromAttribute($attribute, null, $section->location());
    }

    /**
     * @return array<string, string>
     */
    private static function paths(RawSection $root, string $directory): array
    {
        $paths = [];
        $section = $root->section('paths');
        foreach ($root->stringMap('paths') as $prefix => $path) {
            if (!str_ends_with($prefix, '\\')) {
                $section->report(sprintf('A namespace prefix ends with "\"; write "%s\".', $prefix), $prefix);

                continue;
            }
            if (preg_match(self::NAMESPACE_PREFIX, $prefix) !== 1) {
                $section->report(sprintf('"%s" is not a namespace prefix.', $prefix), $prefix);

                continue;
            }
            $paths[$prefix] = Path::resolve($directory, $path);
        }

        return $paths;
    }
}
