<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class TemporaryDirectory
{
    public readonly string $path;

    public function __construct()
    {
        $path = sys_get_temp_dir() . '/dto-collection-generator-test-' . bin2hex(random_bytes(6));
        if (!mkdir($path, 0o777, true)) {
            throw new RuntimeException('Cannot create ' . $path);
        }
        $this->path = $path;
    }

    public function write(string $relativePath, string $content): string
    {
        $path = $this->path . '/' . $relativePath;
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0o777, true)) {
            throw new RuntimeException('Cannot create ' . $directory);
        }
        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException('Cannot write ' . $path);
        }

        return $path;
    }

    public function remove(): void
    {
        if (!is_dir($this->path)) {
            return;
        }
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $file) {
            assert($file instanceof SplFileInfo);
            $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->path);
    }
}
