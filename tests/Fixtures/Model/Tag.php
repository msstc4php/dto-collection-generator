<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model;

final class Tag
{
    public function __construct(public readonly string $label = 'new')
    {
    }
}
