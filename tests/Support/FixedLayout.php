<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Support;

use MSSTC4PHP\DtoCollectionGenerator\Application\Config\GeneratorConfig;
use MSSTC4PHP\DtoCollectionGenerator\Application\Port\ProjectLayout;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Spec\ClassName;

/**
 * Places classes under App\ in /project/src, nothing else.
 */
final class FixedLayout implements ProjectLayout
{
    public function pathFor(ClassName $class, GeneratorConfig $config): ?string
    {
        return str_starts_with($class->fullName(), 'App\\') ? '/project/src/' . str_replace('\\', '/', substr($class->fullName(), 4)) . '.php' : null;
    }
}
