<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Application\Generate;

final class GeneratedFile
{
    /**
     * @param string $path absolute
     */
    public function __construct(public readonly string $path, public readonly string $contents)
    {
    }
}
