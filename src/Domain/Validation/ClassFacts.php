<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Domain\Validation;

use MSSTC4PHP\DtoCollectionGenerator\Domain\Spec\ClassKind;

/**
 * What the generator needs to know about the classes a spec names.
 *
 * @internal
 */
interface ClassFacts
{
    /**
     * @return ClassKind|null null when the class does not exist or cannot be loaded
     */
    public function kind(string $class): ?ClassKind;

    public function isA(string $class, string $parent): bool;

    public function isEquatable(string $class): bool;

    /**
     * Whether the class carries an ODM document attribute; a mapping in XML is not seen.
     */
    public function hasOdmMapping(string $class): bool;

    public function isOdmInstalled(): bool;

    /**
     * @return string|null null for internal classes and classes that do not exist
     */
    public function fileOf(string $class): ?string;
}
