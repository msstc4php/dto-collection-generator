<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Support;

use MSSTC4PHP\DtoCollectionGenerator\Application\Port\ClassInspector;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Spec\ClassKind;

/**
 * @phpstan-type ClassShape array{kind: ClassKind, parents?: list<string>, equatable?: bool, odm?: bool, file?: string}
 */
final class InMemoryClassInspector implements ClassInspector
{
    /**
     * @param array<string, ClassShape> $classes keys compared case-insensitively, as PHP does
     */
    public function __construct(private readonly array $classes, private readonly bool $odmInstalled = true)
    {
    }

    public function kind(string $class): ?ClassKind
    {
        return $this->find($class)['kind'] ?? null;
    }

    public function isA(string $class, string $parent): bool
    {
        $shape = $this->find($class);
        if ($shape === null) {
            return false;
        }
        if (strtolower($class) === strtolower($parent)) {
            return true;
        }
        foreach ($shape['parents'] ?? [] as $ancestor) {
            if (strtolower($ancestor) === strtolower($parent)) {
                return true;
            }
        }

        return false;
    }

    public function isEquatable(string $class): bool
    {
        return $this->find($class)['equatable'] ?? false;
    }

    public function hasOdmMapping(string $class): bool
    {
        return $this->odmInstalled && ($this->find($class)['odm'] ?? false);
    }

    public function isOdmInstalled(): bool
    {
        return $this->odmInstalled;
    }

    public function fileOf(string $class): ?string
    {
        return $this->find($class)['file'] ?? null;
    }

    /**
     * @return ClassShape|null
     */
    private function find(string $class): ?array
    {
        foreach ($this->classes as $name => $shape) {
            if (strtolower($name) === strtolower(ltrim($class, '\\'))) {
                return $shape;
            }
        }

        return null;
    }
}
