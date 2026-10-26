<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Projects\Demo\Dto;

use MSSTC4PHP\DtoCollection\Attribute\TypedCollection;
use MSSTC4PHP\DtoCollection\Attribute\Variant;
use MSSTC4PHP\DtoCollection\Equatable;

#[TypedCollection(class: 'Demo\Collection\OrderLineList', variant: Variant::Immutable)]
#[TypedCollection(class: 'Demo\Collection\MutableOrderLineList', variant: Variant::Mutable)]
final class OrderLine implements Equatable
{
    public function __construct(public readonly string $sku, public readonly int $quantity)
    {
    }

    public function equals(object $other): bool
    {
        return $other instanceof self && $other->sku === $this->sku && $other->quantity === $this->quantity;
    }
}
