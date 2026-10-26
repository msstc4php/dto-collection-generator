<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Application\Generate;

final class FileChange
{
    /**
     * @param string $path absolute
     * @param string|null $reason why a conflict is one
     */
    public function __construct(public readonly ChangeKind $kind, public readonly string $path, public readonly ?string $reason = null)
    {
    }
}
