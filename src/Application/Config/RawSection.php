<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Application\Config;

use MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic\Diagnostics;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic\Location;

/**
 * Typed, located reads from one decoded config object. A wrong value becomes a diagnostic and the default is
 * used, so every mistake in the file is reported in one run.
 *
 * @internal
 */
final class RawSection
{
    /**
     * @param array<array-key, mixed> $values
     */
    public function __construct(
        private readonly array $values,
        private readonly string $file,
        private readonly string $pointer,
        private readonly Diagnostics $diagnostics,
    ) {
    }

    /**
     * @param list<string> $known
     */
    public function rejectUnknownKeys(array $known): void
    {
        foreach (array_keys($this->values) as $key) {
            if (!in_array((string) $key, $known, true)) {
                $this->report(sprintf('Unknown key "%s"; expected one of: %s.', $key, implode(', ', $known)), (string) $key);
            }
        }
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function value(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    public function requiredString(string $key): ?string
    {
        if (!$this->has($key)) {
            $this->report(sprintf('"%s" is required.', $key));

            return null;
        }
        $value = $this->values[$key];
        if (!is_string($value) || $value === '') {
            $this->error($key, 'must be a non-empty string');

            return null;
        }

        return $value;
    }

    /**
     * @template TChoice of string
     *
     * @param list<TChoice> $allowed
     *
     * @return TChoice|null null when missing or invalid; a missing one is reported when required
     */
    public function choice(string $key, array $allowed, bool $required): ?string
    {
        if (!$this->has($key)) {
            if ($required) {
                $this->report(sprintf('"%s" is required.', $key));
            }

            return null;
        }
        foreach ($allowed as $choice) {
            if ($choice === $this->values[$key]) {
                return $choice;
            }
        }
        $this->error($key, sprintf('must be one of: %s', implode(', ', $allowed)));

        return null;
    }

    public function bool(string $key, bool $default): bool
    {
        if (!$this->has($key)) {
            return $default;
        }
        $value = $this->values[$key];
        if (!is_bool($value)) {
            $this->error($key, 'must be true or false');

            return $default;
        }

        return $value;
    }

    /**
     * @return array<non-negative-int, string> by their index in the list, so a problem with one entry points at it
     */
    public function stringList(string $key): array
    {
        $value = $this->values[$key] ?? [];
        if (!is_array($value) || !array_is_list($value)) {
            $this->error($key, 'must be a list of strings');

            return [];
        }
        $strings = [];
        foreach ($value as $index => $item) {
            if (!is_string($item) || $item === '') {
                $this->report('Expected a non-empty string.', $key, (string) $index);

                continue;
            }
            $strings[$index] = $item;
        }

        return $strings;
    }

    /**
     * @return array<array-key, string> a numeric key comes back as an int, as PHP stores it
     */
    public function stringMap(string $key): array
    {
        $value = $this->section($key);
        $strings = [];
        foreach ($value->values as $entry => $item) {
            if (!is_string($item) || $item === '') {
                $value->report('Expected a non-empty string.', (string) $entry);

                continue;
            }
            $strings[$entry] = $item;
        }

        return $strings;
    }

    public function section(string $key): self
    {
        $value = $this->values[$key] ?? [];
        if (!is_array($value) || ($value !== [] && array_is_list($value))) {
            $this->error($key, 'must be an object');
            $value = [];
        }

        return new self($value, $this->file, $this->child($key), $this->diagnostics);
    }

    /**
     * @return list<self>
     */
    public function sectionList(string $key): array
    {
        $value = $this->values[$key] ?? [];
        if (!is_array($value) || !array_is_list($value)) {
            $this->error($key, 'must be a list');

            return [];
        }
        $sections = [];
        foreach ($value as $index => $item) {
            if (!is_array($item) || ($item !== [] && array_is_list($item))) {
                $this->report('Expected an object.', $key, (string) $index);

                continue;
            }
            $sections[] = new self($item, $this->file, $this->child($key, (string) $index), $this->diagnostics);
        }

        return $sections;
    }

    public function error(string $key, string $problem): void
    {
        $this->report(sprintf('"%s" %s.', $key, $problem), $key);
    }

    public function location(string ...$path): Location
    {
        return Location::inConfig($this->file, $this->child(...$path));
    }

    public function warn(string $message, string ...$path): void
    {
        $this->diagnostics->warning($message, $this->location(...$path));
    }

    public function report(string $message, string ...$path): void
    {
        $this->diagnostics->error($message, $this->location(...$path));
    }

    private function child(string ...$path): string
    {
        $pointer = $this->pointer;
        foreach ($path as $segment) {
            $pointer .= '/' . str_replace(['~', '/'], ['~0', '~1'], $segment);
        }

        return $pointer;
    }
}
