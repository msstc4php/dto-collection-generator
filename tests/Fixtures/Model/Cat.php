<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model;

use MSSTC4PHP\DtoCollection\Equatable;

final class Cat implements Animal, Equatable
{
    public function __construct(public readonly string $name = 'Tom')
    {
    }

    public function equals(object $other): bool
    {
        return $other instanceof self && $other->name === $this->name;
    }
}
