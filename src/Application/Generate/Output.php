<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Application\Generate;

use MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic\Diagnostics;

final class Output
{
    /**
     * @param string $directory the config file's directory, which reports show paths relative to
     * @param WritePlan|null $plan null when the run stopped before planning
     */
    public function __construct(
        public readonly Status $status,
        public readonly Diagnostics $diagnostics,
        public readonly string $directory,
        public readonly ?WritePlan $plan = null,
    ) {
    }
}
