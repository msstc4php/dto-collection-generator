<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model;

use MSSTC4PHP\DtoCollection\Equatable;

final class Item implements Equatable
{
    public function __construct(public readonly string $sku)
    {
    }

    public function equals(object $other): bool
    {
        return $other instanceof self && $other->sku === $this->sku;
    }
}
