<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Application\Port;

interface DocumentLoader
{
    /**
     * @return array<array-key, mixed> the decoded top-level object
     *
     * @throws DocumentNotLoaded
     */
    public function load(string $path): array;
}
