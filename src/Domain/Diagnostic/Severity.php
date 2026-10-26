<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic;

enum Severity: string
{
    case Error = 'error';
    case Warning = 'warning';
}
