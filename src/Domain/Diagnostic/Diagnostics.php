<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic;

use ArrayIterator;
use Countable;
use IteratorAggregate;

/**
 * Collects every problem of a run instead of stopping at the first, so one run reports them all.
 *
 * @implements IteratorAggregate<int, Diagnostic>
 */
final class Diagnostics implements Countable, IteratorAggregate
{
    /** @var list<Diagnostic> */
    private array $items = [];

    /** @var array<string, true> */
    private array $seen = [];

    public function error(string $message, Location $location): void
    {
        $this->add(new Diagnostic(Severity::Error, $message, $location));
    }

    public function warning(string $message, Location $location): void
    {
        $this->add(new Diagnostic(Severity::Warning, $message, $location));
    }

    public function merge(self $other): void
    {
        foreach ($other->items as $diagnostic) {
            $this->add($diagnostic);
        }
    }

    public function hasErrors(): bool
    {
        return $this->errors() !== [];
    }

    /**
     * @return list<Diagnostic>
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * @return list<Diagnostic>
     */
    public function errors(): array
    {
        return array_values(array_filter(
            $this->items,
            static fn (Diagnostic $diagnostic): bool => $diagnostic->severity === Severity::Error,
        ));
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return ArrayIterator<int, Diagnostic>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    private function add(Diagnostic $diagnostic): void
    {
        $location = $diagnostic->location;
        $key = implode("\0", [$diagnostic->severity->value, $diagnostic->message, $location->path, (string) $location->line, (string) $location->pointer]);
        if (isset($this->seen[$key])) {
            return;
        }
        $this->seen[$key] = true;
        $this->items[] = $diagnostic;
    }
}
