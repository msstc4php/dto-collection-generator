<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Application\Generate;

enum Mode: string
{
    case Write = 'write';

    /** Writes nothing; an out-of-date output is a failure (for CI). */
    case Check = 'check';

    /** Writes nothing; reports what a write would change. */
    case DryRun = 'dry-run';
}
