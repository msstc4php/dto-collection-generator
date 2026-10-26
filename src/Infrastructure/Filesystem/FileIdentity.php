<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Infrastructure\Filesystem;

use MSSTC4PHP\DtoCollectionGenerator\Domain\Shared\Path;

/**
 * Tells whether two paths name the same file: by device and inode, else by real path.
 *
 * @internal
 */
final class FileIdentity
{
    private function __construct()
    {
    }

    public static function of(string $path): ?string
    {
        return self::fromStat(@stat($path));
    }

    /**
     * @param array{dev: int, ino: int}|false $stat as stat() returns it
     *
     * @return string|null "device:inode"; null when unknown
     */
    public static function fromStat(array|false $stat): ?string
    {
        // Some file systems and builds report inode 0 for every file: no identity then.
        if ($stat === false || $stat['ino'] === 0) {
            return null;
        }

        return $stat['dev'] . ':' . $stat['ino'];
    }

    /**
     * The real path in lower case, for a case-insensitive comparison when no identity is known; the normalised path
     * when it does not exist.
     */
    public static function spelling(string $path): string
    {
        $real = realpath($path);

        return strtolower($real === false ? Path::normalize($path) : $real);
    }

    public static function same(string $left, string $right): bool
    {
        $identity = self::of($left);
        if ($identity !== null && $identity === self::of($right)) {
            return true;
        }

        // On macOS realpath() keeps the letter case it is given; only the inode settles "Src" against "src".
        $real = static function (string $path): string {
            $resolved = realpath($path);

            return $resolved === false ? Path::normalize($path) : $resolved;
        };

        return $real($left) === $real($right);
    }
}
