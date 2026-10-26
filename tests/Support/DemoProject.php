<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * A copy of tests/Fixtures/Projects/Demo in a temporary directory. Its DTO classes autoload from the original through
 * this repository's autoload-dev, so the copy is only where files are scanned and written.
 */
final class DemoProject
{
    public readonly TemporaryDirectory $directory;

    public function __construct()
    {
        $this->directory = new TemporaryDirectory();
        $source = __DIR__ . '/../Fixtures/Projects/Demo';
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            assert($file instanceof SplFileInfo);
            $this->directory->write(substr($file->getPathname(), strlen($source) + 1), (string) file_get_contents($file->getPathname()));
        }
    }

    public function path(string $relativePath = ''): string
    {
        return $relativePath === '' ? $this->directory->path : $this->directory->path . '/' . $relativePath;
    }

    public function remove(): void
    {
        $this->directory->remove();
    }
}
