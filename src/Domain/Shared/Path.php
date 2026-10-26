<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Domain\Shared;

/**
 * Lexical path handling; never touches the filesystem, so symlinks are not resolved.
 *
 * @api
 *
 * @internal
 */
final class Path
{
    // Server and share are both required, so a POSIX "//x" stays an ordinary absolute path.
    private const UNC_ROOT = '#^//(?!\\.\\.?/)[^/]+/(?!\\.\\.?(?:/|\\z))[^/]+#';

    private function __construct()
    {
    }

    public static function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1;
    }

    public static function resolve(string $baseDir, string $path): string
    {
        // A root such as "/" or "C:/" must not double its slash: "//x/y" would read as a UNC share.
        return self::normalize(self::isAbsolute($path) ? $path : rtrim(str_replace('\\', '/', $baseDir), '/') . '/' . $path);
    }

    public static function normalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        // A drive letter is case-insensitive, so one spelling is kept.
        if (preg_match('#^[a-z]:#', $path) === 1) {
            $path = ucfirst($path);
        }

        // "//server/share" is a UNC root: kept as is and never climbed above.
        if (preg_match(self::UNC_ROOT, $path, $matches) === 1) {
            $segments = self::segments(substr($path, strlen($matches[0])), true);

            return $segments === [] ? $matches[0] : $matches[0] . '/' . implode('/', $segments);
        }

        $prefix = preg_match('#^(?:[A-Za-z]:)?/#', $path, $matches) === 1 ? $matches[0] : '';

        return $prefix . implode('/', self::segments(substr($path, strlen($prefix)), $prefix !== ''));
    }

    /**
     * The path of $path inside $directory, "" for the directory itself; null when it lies elsewhere. The paths are
     * compared as written, so a symbolic link or another case of a case-insensitive file system counts as elsewhere.
     */
    public static function within(string $directory, string $path): ?string
    {
        if (self::isAbsolute($directory) !== self::isAbsolute($path)) {
            return null;
        }

        $directory = self::normalize($directory);
        $path = self::normalize($path);
        if ($path === $directory) {
            return '';
        }

        $prefix = rtrim($directory, '/') . '/';

        return str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : null;
    }

    /**
     * The path of $to as seen from the directory $from, with "../" where needed.
     *
     * @return string|null null when they share no root (another drive, another UNC share, only one of them relative) or a
     *                     relative one climbs above its start
     */
    public static function relative(string $from, string $to): ?string
    {
        $from = self::normalize($from);
        $to = self::normalize($to);
        $root = self::root($from);
        if ($root !== self::root($to)) {
            return null;
        }
        $fromSegments = self::below($root, $from);
        $toSegments = self::below($root, $to);
        // Normalisation keeps the leading ".." of a relative path; what lies above it is unknown.
        if (in_array('..', [...$fromSegments, ...$toSegments], true)) {
            return null;
        }
        $common = 0;
        while (isset($fromSegments[$common], $toSegments[$common]) && $fromSegments[$common] === $toSegments[$common]) {
            $common++;
        }

        return rtrim(str_repeat('../', count($fromSegments) - $common) . implode('/', array_slice($toSegments, $common)), '/');
    }

    public static function directory(string $path): string
    {
        $normalized = self::normalize($path);
        if (preg_match(self::UNC_ROOT, $normalized, $matches) === 1 && $matches[0] === $normalized) {
            return $normalized;
        }
        $position = strrpos($normalized, '/');
        if ($position === false) {
            return '.';
        }

        $directory = substr($normalized, 0, $position);

        return $directory === '' || preg_match('#^[A-Za-z]:\z#', $directory) === 1 ? $directory . '/' : $directory;
    }

    /**
     * @return list<string>
     */
    private static function below(string $root, string $normalized): array
    {
        // A UNC root comes without its trailing slash.
        $rest = ltrim(substr($normalized, strlen($root)), '/');

        return $rest === '' ? [] : explode('/', $rest);
    }

    /**
     * @return string "//server/share", "C:/", "/", or "" for a relative path
     */
    private static function root(string $normalized): string
    {
        if (preg_match(self::UNC_ROOT, $normalized, $matches) === 1) {
            return $matches[0];
        }

        return preg_match('#^(?:[A-Za-z]:)?/#', $normalized, $matches) === 1 ? $matches[0] : '';
    }

    /**
     * @return list<string> the segments with "." dropped and ".." applied; a rooted path cannot climb above its root
     */
    private static function segments(string $path, bool $rooted): array
    {
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                if ($segments !== [] && $segments[count($segments) - 1] !== '..') {
                    array_pop($segments);

                    continue;
                }

                if ($rooted) {
                    continue;
                }
            }

            $segments[] = $segment;
        }

        return $segments;
    }
}
