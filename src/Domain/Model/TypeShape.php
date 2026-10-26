<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Domain\Model;

use Closure;
use MSSTC4PHP\DtoCollection\ElementType;

/**
 * An element type as the pieces the emitted code prints: native hints for T and ?T, and their PHPDoc forms.
 * Class names go through a printer callback, so the emitter decides between an import and a qualified name.
 */
final class TypeShape
{
    /**
     * @param list<string|non-empty-list<string>> $branches a scalar name, or the classes of one branch (two or more: an intersection)
     */
    private function __construct(public readonly array $branches, public readonly bool $nullable)
    {
    }

    /**
     * @param ElementType<mixed> $type
     */
    public static function of(ElementType $type): self
    {
        // describe() prints the PHP declaration ElementType::parse() reads back; DNF does not occur in it.
        $branches = [];
        foreach (explode('|', $type->describe()) as $branch) {
            if ($branch !== 'null') {
                $branches[] = str_contains($branch, '&') ? explode('&', $branch) : (in_array($branch, ['int', 'float', 'string', 'bool'], true) ? $branch : [$branch]);
            }
        }

        return new self($branches, $type->isNullable());
    }

    /**
     * @param Closure(string): string $name
     */
    public function native(Closure $name): string
    {
        return $this->nullable ? $this->withNull($name, true) : $this->joined($name, false);
    }

    /**
     * @param Closure(string): string $name
     *
     * @return string|null null when ?T needs DNF and the target has none
     */
    public function nullableNative(Closure $name, bool $dnf): ?string
    {
        if (!$dnf && $this->hasIntersection()) {
            return null;
        }

        return $this->withNull($name, true);
    }

    /**
     * @param Closure(string): string $name
     */
    public function phpDoc(Closure $name): string
    {
        return $this->nullable ? $this->withNull($name, false) : $this->joined($name, false);
    }

    /**
     * @param Closure(string): string $name
     */
    public function nullablePhpDoc(Closure $name): string
    {
        return $this->withNull($name, false);
    }

    /**
     * @return list<string>
     */
    public function classes(): array
    {
        $classes = [];
        foreach ($this->branches as $branch) {
            if (is_array($branch)) {
                array_push($classes, ...$branch);
            }
        }

        return $classes;
    }

    public function hasIntersection(): bool
    {
        foreach ($this->branches as $branch) {
            if (is_array($branch) && count($branch) > 1) {
                return true;
            }
        }

        return false;
    }

    public function hasScalar(): bool
    {
        foreach ($this->branches as $branch) {
            if (is_string($branch)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Closure(string): string $name
     */
    private function withNull(Closure $name, bool $native): string
    {
        if ($native && count($this->branches) === 1 && !$this->hasIntersection()) {
            return '?' . $this->joined($name, false);
        }

        return $this->joined($name, true) . '|null';
    }

    /**
     * @param Closure(string): string $name
     * @param bool $grouped wraps an intersection in parentheses, as a union member must be
     */
    private function joined(Closure $name, bool $grouped): string
    {
        $parts = [];
        foreach ($this->branches as $branch) {
            if (is_string($branch)) {
                $parts[] = $branch;

                continue;
            }
            $intersection = implode('&', array_map($name, $branch));
            $parts[] = $grouped && count($branch) > 1 ? '(' . $intersection . ')' : $intersection;
        }

        return implode('|', $parts);
    }
}
