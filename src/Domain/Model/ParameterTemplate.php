<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Domain\Model;

final class ParameterTemplate
{
    /**
     * @param 'T'|'int'|'array'|'iterable'|'Closure' $type T is the element type
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly bool $variadic = false,
    ) {
    }
}
