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
 */
final class ComposerProjectLayout implements ProjectLayout
{
    /** @var array<string, array<string, string>> config directory => PSR-4 prefix => absolute directory */
    private array $psr4 = [];

    public function pathFor(ClassName $class, GeneratorConfig $config): ?string
    {
        return $this->place($class->fullName(), $config->paths) ?? $this->place($class->fullName(), $this->psr4($config->directory));
    }

    /**
     * @param array<string, string> $prefixes
     */
    private function place(string $class, array $prefixes): ?string
    {
        $best = null;
        foreach ($prefixes as $prefix => $directory) {
            if (str_starts_with($class, $prefix) && ($best === null || strlen($prefix) > strlen($best))) {
                $best = $prefix;
            }
        }

        return $best === null ? null : rtrim($prefixes[$best], '/') . '/' . str_replace('\\', '/', substr($class, strlen($best))) . '.php';
    }

    /**
     * @return array<string, string>
     */
    private function psr4(string $directory): array
    {
        return $this->psr4[$directory] ??= $this->read($directory);
    }

    /**
     * @return array<string, string>
     */
    private function read(string $directory): array
    {
        $file = $this->find($directory);
        $content = $file === null ? false : file_get_contents($file);
        if ($file === null || $content === false) {
            return [];
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
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
                    $prefixes[(string) $prefix] = Path::resolve(Path::directory($file), $path);
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
