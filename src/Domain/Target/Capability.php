<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Domain\Target;

/**
 * A language feature the emitted code uses only when the target version has it.
 */
enum Capability: string
{
    /** `(A&B)|null` for the `?T` positions of an intersection element type. */
    case DnfTypes = 'dnf-types';

    /** `#[\Override]` on the methods that implement an interface method. */
    case OverrideAttribute = 'override-attribute';

    public function minimumVersion(): PhpVersion
    {
        return PhpVersion::fromString(match ($this) {
            self::DnfTypes => '8.2',
            self::OverrideAttribute => '8.3',
        });
    }
}
