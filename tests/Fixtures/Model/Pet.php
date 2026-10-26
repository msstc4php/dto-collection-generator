<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use MSSTC4PHP\DtoCollection\Equatable;

#[ODM\EmbeddedDocument]
class Pet implements Equatable
{
    public function __construct(
        #[ODM\Field]
        public string $name = 'Rex',
    ) {
    }

    public function equals(object $other): bool
    {
        return $other instanceof self && $other->name === $this->name;
    }
}
