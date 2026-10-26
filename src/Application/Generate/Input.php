<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Application\Generate;

final class Input
{
    /**
     * @param string $configPath absolute path of dto-collection-generator.yaml (or .json)
     */
    public function __construct(public readonly string $configPath, public readonly Mode $mode = Mode::Write)
    {
    }
}
