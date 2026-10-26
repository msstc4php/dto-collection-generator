<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Projects\Scan;

use MSSTC4PHP\DtoCollection\Attribute\TypedCollection as Collect;
use MSSTC4PHP\DtoCollection\Attribute\Variant;

#[Collect(class: 'App\Collection\OrderList', variant: Variant::Immutable)]
#[Collect(class: MutableOrderList::class, variant: Variant::Mutable, nullable: true)]
final class Order
{
}
