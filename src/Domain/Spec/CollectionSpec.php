<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Domain\Spec;

use InvalidArgumentException;
use MSSTC4PHP\DtoCollection\Attribute\TypedCollection;
use MSSTC4PHP\DtoCollection\Attribute\Variant;
use MSSTC4PHP\DtoCollection\ElementType;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic\Location;

/**
 * One collection class to generate, from an attribute on a DTO or from the config file.
 *
 * @internal
 */
final class CollectionSpec
{
    /**
     * @param ElementType<mixed> $elementType with the spec's nullable flag already applied
     * @param ClassName|null $markedClass the class carrying the attribute; null for a config spec
     */
    public function __construct(
        public readonly ClassName $class,
        public readonly Variant $variant,
        public readonly ElementType $elementType,
        public readonly ?ClassName $markedClass,
        public readonly Location $origin,
    ) {
    }

    /**
     * @throws InvalidArgumentException when the class name is invalid, or neither an element type nor a marked class is given
     */
    public static function fromAttribute(TypedCollection $attribute, ?ClassName $markedClass, Location $origin): self
    {
        $declaration = $attribute->elementType ?? $markedClass?->fullName();
        if ($declaration === null) {
            throw new InvalidArgumentException('A collection spec without a marked class needs an element type');
        }
        $type = ElementType::parse($declaration);

        return new self(
            ClassName::fromString($attribute->class),
            $attribute->variant,
            $attribute->nullable ? $type->nullable() : $type,
            $markedClass,
            $origin,
        );
    }
}
