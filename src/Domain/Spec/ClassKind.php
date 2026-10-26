<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Domain\Spec;

enum ClassKind
{
    case OrdinaryClass;
    case Interface;
    case Trait;
    case Enum;
}
