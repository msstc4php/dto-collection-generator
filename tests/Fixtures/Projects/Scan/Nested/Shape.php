<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Projects\Scan\Nested;

use MSSTC4PHP\DtoCollection\Attribute\TypedCollection;
use MSSTC4PHP\DtoCollection\Attribute\Variant;

#[TypedCollection(class: 'App\Collection\ShapeList', variant: Variant::Immutable)]
interface Shape
{
}
