<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Application\Port;

use MSSTC4PHP\DtoCollectionGenerator\Application\Config\GeneratorConfig;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Spec\ClassName;

/**
 * @internal
 */
interface ProjectLayout
{
    /**
     * @return string|null the absolute file path for the class, or null when no namespace prefix covers it
     */
    public function pathFor(ClassName $class, GeneratorConfig $config): ?string;
}
