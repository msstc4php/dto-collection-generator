<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Infrastructure\Scanner;

use FilesystemIterator;
use MSSTC4PHP\DtoCollection\Attribute\TypedCollection;
use MSSTC4PHP\DtoCollectionGenerator\Application\Port\SpecSource;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic\Diagnostics;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic\Location;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Spec\ClassName;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Spec\CollectionSpec;
use PhpParser\Error;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;
use Throwable;

/**
 * Reads #[TypedCollection] attributes: php-parser picks the classes, reflection instantiates the attributes, so
 * PHP itself evaluates the arguments and the attribute's constructor checks them.
 *
 * @phpstan-import-type Candidate from CandidateClassFinder
 *
 * @internal
 */
final class AttributeSpecSource implements SpecSource
{
    public function __construct(private readonly CandidateClassFinder $finder = new CandidateClassFinder())
    {
    }

    public function specs(array $directories, Diagnostics $diagnostics): array
    {
        $specs = [];
        foreach ($this->files($directories) as $file) {
            $code = file_get_contents($file);
            if ($code === false || !str_contains($code, 'TypedCollection')) {
                continue;
            }

            try {
                $candidates = $this->finder->find($code);
            } catch (Error $error) {
                $diagnostics->error('Cannot parse this file: ' . $error->getRawMessage(), Location::inFile($file, $error->getStartLine()));

                continue;
            }
            foreach ($candidates as $candidate) {
                array_push($specs, ...$this->read($file, $candidate, $diagnostics));
            }
        }

        return $specs;
    }

    /**
     * @param Candidate $candidate
     *
     * @return list<CollectionSpec>
     */
    private function read(string $file, array $candidate, Diagnostics $diagnostics): array
    {
        $class = $candidate['class'];
        $lines = $candidate['lines'];
        $origin = Location::inFile($file, $lines[0]);

        try {
            // class_exists() covers enums.
            $exists = class_exists($class) || interface_exists($class) || trait_exists($class);
        } catch (Throwable $exception) {
            $diagnostics->error(sprintf('Cannot load %s: %s', $class, $exception->getMessage()), $origin);

            return [];
        }
        if (!$exists) {
            $diagnostics->error(sprintf('%s cannot be autoloaded; add its directory to the autoload section of composer.json', $class), $origin);

            return [];
        }

        $specs = [];
        $marked = ClassName::fromString($class);
        foreach ((new ReflectionClass($class))->getAttributes(TypedCollection::class) as $index => $attribute) {
            // Reflection and php-parser list the attributes in the same order.
            $location = Location::inFile($file, $lines[$index] ?? $lines[0]);

            try {
                $specs[] = CollectionSpec::fromAttribute($attribute->newInstance(), $marked, $location);
            } catch (Throwable $exception) {
                $diagnostics->error($exception->getMessage(), $location);
            }
        }

        return $specs;
    }

    /**
     * @param list<string> $directories
     *
     * @return list<string> the *.php files, sorted so the output does not depend on the file system's order
     */
    private function files(array $directories): array
    {
        $files = [];
        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }
        $files = array_unique($files);
        sort($files, SORT_STRING);

        return $files;
    }
}
