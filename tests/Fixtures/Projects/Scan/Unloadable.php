<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Projects\Scan;

use MSSTC4PHP\DtoCollection\Attribute\TypedCollection;
use MSSTC4PHP\DtoCollection\Attribute\Variant;

#[TypedCollection(class: 'App\Collection\UnloadableList', variant: Variant::Immutable)]
final class Unloadable extends MissingParent
{
}
