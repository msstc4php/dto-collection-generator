<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Support;

use LogicException;
use MSSTC4PHP\DtoCollectionGenerator\Application\Port\PhpRequirement;
use MSSTC4PHP\DtoCollectionGenerator\Application\Port\ProjectPhpConstraint;

final class FixedPhpConstraint implements ProjectPhpConstraint
{
    public int $calls = 0;

    public function __construct(private readonly ?PhpRequirement $requirement)
    {
    }

    public function find(string $directory): PhpRequirement
    {
        $this->calls++;

        return $this->requirement ?? throw new LogicException('The target is explicit; composer.json must not be read');
    }
}
