<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Application\Port;

interface ProjectPhpConstraint
{
    /**
     * Reads require.php of the nearest composer.json at or above the directory.
     */
    public function find(string $directory): PhpRequirement;
}
