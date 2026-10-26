<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Application\Port;

/**
 * What the nearest composer.json says about PHP: a constraint, nothing, or why the file could not be used.
 *
 * @internal
 */
final class PhpRequirement
{
    private function __construct(
        public readonly ?string $file,
        public readonly ?string $constraint,
        public readonly ?string $problem,
    ) {
    }

    public static function none(): self
    {
        return new self(null, null, null);
    }

    public static function found(string $file, ?string $constraint): self
    {
        return new self($file, $constraint, null);
    }

    public static function unusable(string $file, string $problem): self
    {
        return new self($file, null, $problem);
    }
}
