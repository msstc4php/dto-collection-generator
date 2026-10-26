<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Support;

use MSSTC4PHP\DtoCollectionGenerator\Application\Port\SpecSource;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic\Diagnostics;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic\Location;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Spec\CollectionSpec;

final class InMemorySpecSource implements SpecSource
{
    /** @var list<list<string>> */
    public array $scanned = [];

    /**
     * @param list<CollectionSpec> $specs
     * @param list<string> $errors reported at /project/src/Broken.php
     */
    public function __construct(private readonly array $specs, private readonly array $errors = [])
    {
    }

    public function specs(array $directories, Diagnostics $diagnostics): array
    {
        $this->scanned[] = $directories;
        foreach ($this->errors as $error) {
            $diagnostics->error($error, Location::inFile('/project/src/Broken.php', 1));
        }

        return $this->specs;
    }
}
