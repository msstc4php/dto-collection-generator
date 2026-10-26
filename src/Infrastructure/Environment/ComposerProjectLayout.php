<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Infrastructure\Environment;

use JsonException;
use MSSTC4PHP\DtoCollectionGenerator\Application\Config\GeneratorConfig;
use MSSTC4PHP\DtoCollectionGenerator\Application\Port\ProjectLayout;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Shared\Path;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Spec\ClassName;

/**
 * Places a class by the config's `paths`, then by the PSR-4 maps of the nearest composer.json; the longest
 * matching prefix wins, as in Composer's autoloader.
 *
 * @internal
 */
final class ComposerProjectLayout implements ProjectLayout
{
    public function pathFor(ClassName $class, GeneratorConfig $config): ?string
    {
        return $this->place($class->fullName(), $config->paths) ?? $this->place($class->fullName(), $this->psr4($config->directory));
    }

    /**
     * @param array<array-key, string> $prefixes PHP turns a numeric prefix key like "1" into an int
     */
    private function place(string $class, array $prefixes): ?string
    {
        $best = null;
        foreach ($prefixes as $key => $directory) {
            $prefix = (string) $key;
            if (str_starts_with($class, $prefix) && ($best === null || strlen($prefix) > strlen($best))) {
                $best = $prefix;
            }
        }

        // The directories come from Path::resolve(), without a trailing slash.
        return $best === null ? null : $prefixes[$best] . '/' . str_replace('\\', '/', substr($class, strlen($best))) . '.php';
    }

    /**
     * @return array<array-key, string>
     */
    private function psr4(string $directory): array
    {
        $file = $this->find($directory);
        $content = $file === null ? null : @file_get_contents($file);
        if ($file === null || !is_string($content)) {
            return [];
        }

        try {
            $data = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }
        $prefixes = [];
        foreach (['autoload', 'autoload-dev'] as $section) {
            $map = is_array($data) && is_array($data[$section] ?? null) ? ($data[$section]['psr-4'] ?? []) : [];
            foreach (is_array($map) ? $map : [] as $prefix => $paths) {
                // Composer allows a list of directories; a new class goes into the first.
                $path = is_array($paths) ? ($paths[0] ?? null) : $paths;
                if (is_string($path)) {
                    $prefixes[$prefix] = Path::resolve(Path::directory($file), $path);
                }
            }
        }

        return $prefixes;
    }

    private function find(string $directory): ?string
    {
        $current = Path::normalize($directory);
        while (true) {
            if (is_file($current . '/composer.json')) {
                return $current . '/composer.json';
            }
            $parent = Path::directory($current);
            if ($parent === $current) {
                return null;
            }
            $current = $parent;
        }
    }
}
