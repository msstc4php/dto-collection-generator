<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Application\Generate;

enum ChangeKind: string
{
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case Unchanged = 'unchanged';

    /** A file the generator must not touch stands in the way; nothing is written. */
    case Conflict = 'conflict';
}
