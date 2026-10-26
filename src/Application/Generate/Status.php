<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Application\Generate;

enum Status: string
{
    case Ok = 'ok';

    /** Check mode found files to create, update or delete. */
    case OutOfDate = 'out-of-date';

    case GenerationFailed = 'generation-failed';
    case ConfigFailed = 'config-failed';

    public function exitCode(): int
    {
        return match ($this) {
            self::Ok => 0,
            self::OutOfDate => 1,
            self::GenerationFailed => 2,
            self::ConfigFailed => 3,
        };
    }
}
