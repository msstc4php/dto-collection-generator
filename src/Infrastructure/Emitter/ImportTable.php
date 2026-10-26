<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Infrastructure\Emitter;

use LogicException;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Spec\ClassName;

/**
 * Decides how the emitted file refers to each class: by an imported or same-namespace short name, or fully
 * qualified when a short name would be ambiguous.
 *
 * @internal
 */
final class ImportTable
{
    /** @var array<string, string> lower-case full name => how the file refers to it */
    private array $names = [];

    /** @var list<string> */
    private array $imports = [];

    /**
     * @param list<string> $fixed the imports every file of this shape has
     * @param list<string> $elements the classes of the element type
     */
    public function __construct(ClassName $collection, array $fixed, array $elements)
    {
        $own = strtolower($collection->shortName());
        $taken = [$own];
        foreach ($fixed as $class) {
            // A class may not import a name equal to its own: the collection's name wins, the import is qualified.
            if (strtolower($this->shortName($class)) === $own) {
                $this->names[strtolower($class)] = '\\' . $class;

                continue;
            }
            $taken[] = strtolower($this->shortName($class));
            $this->names[strtolower($class)] = $this->shortName($class);
            $this->imports[] = $class;
        }
        $counts = [];
        foreach (array_unique(array_map(strtolower(...), $elements)) as $class) {
            $short = $this->shortName($class);
            $counts[$short] = ($counts[$short] ?? 0) + 1;
        }
        foreach ($elements as $class) {
            $key = strtolower($class);
            if (isset($this->names[$key])) {
                continue;
            }
            $short = $this->shortName($class);
            if (!str_contains($class, '\\') || in_array(strtolower($short), $taken, true) || $counts[strtolower($short)] > 1) {
                $this->names[$key] = '\\' . $class;
            } else {
                $this->names[$key] = $short;
                if (strcasecmp(self::namespaceOf($class), $collection->namespace()) !== 0) {
                    $this->imports[] = $class;
                }
            }
        }
        usort($this->imports, strcasecmp(...));
    }

    public function name(string $class): string
    {
        return $this->names[strtolower($class)] ?? throw new LogicException(sprintf('%s is neither imported nor an element class', $class));
    }

    /**
     * @return list<string> sorted as PHP-CS-Fixer's ordered_imports does
     */
    public function imports(): array
    {
        return $this->imports;
    }

    private function shortName(string $class): string
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
