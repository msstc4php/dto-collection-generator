<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Support;

use MSSTC4PHP\DtoCollectionGenerator\Application\Port\DocumentLoader;
use MSSTC4PHP\DtoCollectionGenerator\Application\Port\DocumentNotLoaded;

final class InMemoryDocumentLoader implements DocumentLoader
{
    /**
     * @param array<string, array<array-key, mixed>> $documents
     */
    public function __construct(private readonly array $documents)
    {
    }

    public function load(string $path): array
    {
        return $this->documents[$path] ?? throw new DocumentNotLoaded($path . ' does not exist');
    }
}
