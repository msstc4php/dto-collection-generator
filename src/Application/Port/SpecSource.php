<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Application\Port;

use MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic\Diagnostics;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Spec\CollectionSpec;

/**
 * @internal
 */
interface SpecSource
{
    /**
     * @param list<string> $directories absolute directories to scan
     *
     * @return list<CollectionSpec> in file path order, then declaration order
     */
    public function specs(array $directories, Diagnostics $diagnostics): array;
}
