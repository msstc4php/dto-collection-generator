<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Application\Port;

use MSSTC4PHP\DtoCollectionGenerator\Domain\Validation\ClassFacts;

/**
 * Answers about the project's classes; the adapter loads them through the project's autoloader.
 */
interface ClassInspector extends ClassFacts
{
}
