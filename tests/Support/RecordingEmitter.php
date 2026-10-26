<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Support;

use MSSTC4PHP\DtoCollectionGenerator\Application\Port\CodeEmitter;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Spec\CollectionSpec;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Target\TargetProfile;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Validation\ClassFacts;

final class RecordingEmitter implements CodeEmitter
{
    /** @var list<string> */
    public array $emitted = [];

    public function emit(CollectionSpec $spec, TargetProfile $target, ClassFacts $facts): string
    {
        $this->emitted[] = $spec->class->fullName();

        return sprintf("<?php // %s for PHP %s\n", $spec->class->fullName(), $target->php);
    }
}
