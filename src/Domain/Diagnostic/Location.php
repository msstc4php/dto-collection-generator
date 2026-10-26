<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic;

/**
 * Where a diagnostic points: a PHP file (optionally a line) or a JSON pointer inside the config file.
 */
final class Location
{
    private function __construct(
        public readonly string $path,
        public readonly ?int $line,
        public readonly ?string $pointer,
    ) {
    }

    public static function inFile(string $path, ?int $line = null): self
    {
        return new self($path, $line, null);
    }

    public static function inConfig(string $path, string $pointer): self
    {
        return new self($path, null, $pointer);
    }

    public function render(string $relativeTo): string
    {
        $prefix = rtrim($relativeTo, '/') . '/';
        $path = str_starts_with($this->path, $prefix) ? substr($this->path, strlen($prefix)) : $this->path;
        if ($this->line !== null) {
            return $path . ':' . $this->line;
        }

        return $this->pointer === null || $this->pointer === '' ? $path : $path . '#' . $this->pointer;
    }
}
