<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic;

final class Diagnostic
{
    public function __construct(
        public readonly Severity $severity,
        public readonly string $message,
        public readonly Location $location,
    ) {
    }
}
