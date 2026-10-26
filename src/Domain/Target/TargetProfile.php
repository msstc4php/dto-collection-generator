<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Domain\Target;

final class TargetProfile
{
    public function __construct(public readonly PhpVersion $php)
    {
    }

    public function supports(Capability $capability): bool
    {
        return $this->php->isAtLeast($capability->minimumVersion());
    }
}
