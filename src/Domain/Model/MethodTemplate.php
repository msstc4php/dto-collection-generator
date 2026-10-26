<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Domain\Model;

/**
 * One public method of a generated collection, with the element type left as T.
 */
final class MethodTemplate
{
    /**
     * @param list<ParameterTemplate> $parameters
     * @param 'T'|'?T'|'static'|'self'|'void'|'bool'|'?int' $returns
     * @param 'construct'|'delegate' $body "construct" returns a new instance from the parameters (or an empty one)
     * @param string|null $delegate the trait method a "delegate" body calls
     * @param bool $declaredByInterface whether ReadableList, MutableList or TypedList declares it, so #[\Override] applies
     * @param list<string> $phpDoc with T standing for the element type
     */
    public function __construct(
        public readonly string $name,
        public readonly bool $static,
        public readonly array $parameters,
        public readonly string $returns,
        public readonly string $body,
        public readonly ?string $delegate,
        public readonly bool $noDiscard,
        public readonly bool $declaredByInterface,
        public readonly array $phpDoc = [],
    ) {
    }
}
