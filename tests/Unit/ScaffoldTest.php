<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Unit;

use MSSTC4PHP\DtoCollection\Attribute\Variant;
use PHPUnit\Framework\TestCase;

final class ScaffoldTest extends TestCase
{
    public function testDtoCollectionIsInstalled(): void
    {
        self::assertTrue(enum_exists(Variant::class));
    }
}
