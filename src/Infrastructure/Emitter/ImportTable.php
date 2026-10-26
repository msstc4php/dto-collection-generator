<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Infrastructure\Emitter;

use MSSTC4PHP\DtoCollectionGenerator\Domain\Spec\ClassName;

/**
 * Decides how the emitted file refers to each class: by an imported or same-namespace short name, or fully
 * qualified when a short name would be ambiguous.
 */
final class ImportTable
{
    /** @var array<string, string> lower-case full name => how the file refers to it */
    private array $names = [];

    /** @var list<string> */
    private readonly array $imports;

    /**
     * @param list<string> $fixed the imports every file of this shape has
     * @param list<string> $elements the classes of the element type
     */
    public function __construct(ClassName $collection, array $fixed, array $elements)
    {
        $imports = $fixed;
        $reserved = [strtolower($collection->shortName()) => true];
        foreach ($fixed as $class) {
            $reserved[strtolower(self::shortName($class))] = true;
            $this->names[strtolower($class)] = self::shortName($class);
        }
        $shortNames = array_count_values(array_map(static fn (string $class): string => strtolower(self::shortName($class)), array_values(array_unique(array_map(strtolower(...), $elements)))));
        foreach ($elements as $class) {
            $key = strtolower($class);
            if (isset($this->names[$key])) {
                continue;
            }
            $short = self::shortName($class);
            $ambiguous = isset($reserved[strtolower($short)]) || $shortNames[strtolower($short)] > 1;
            if (!str_contains($class, '\\') || $ambiguous) {
                $this->names[$key] = '\\' . $class;
            } elseif (strcasecmp($this->namespaceOf($class), $collection->namespace()) === 0) {
                $this->names[$key] = $short;
            } else {
                $this->names[$key] = $short;
                $imports[] = $class;
            }
        }
        usort($imports, strcasecmp(...));
        $this->imports = $imports;
    }

    public function name(string $class): string
    {
        return $this->names[strtolower($class)] ?? '\\' . $class;
    }

    /**
     * @return list<string> sorted as PHP-CS-Fixer's ordered_imports does
     */
    public function imports(): array
    {
        return $this->imports;
    }

    private static function shortName(string $class): string
    {
        $separator = strrpos($class, '\\');

        return $separator === false ? $class : substr($class, $separator + 1);
    }

    private function namespaceOf(string $class): string
    {
        $separator = strrpos($class, '\\');

        return $separator === false ? '' : substr($class, 0, $separator);
    }
}
