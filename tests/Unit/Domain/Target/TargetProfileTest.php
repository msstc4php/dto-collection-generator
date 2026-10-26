<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Unit\Domain\Target;

use MSSTC4PHP\DtoCollectionGenerator\Domain\Target\Capability;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Target\PhpVersion;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Target\TargetProfile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TargetProfileTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function versions(): iterable
    {
        yield '8.1' => ['8.1', false, false];
        yield '8.2' => ['8.2', true, false];
        yield '8.3' => ['8.3', true, true];
        yield '8.4' => ['8.4', true, true];
        yield '8.5' => ['8.5', true, true];
    }

    #[DataProvider('versions')]
    public function testCapabilities(string $version, bool $dnfTypes, bool $overrideAttribute): void
    {
        $target = new TargetProfile(PhpVersion::fromString($version));

        self::assertSame($version, (string) $target->php);
        self::assertSame($dnfTypes, $target->supports(Capability::DnfTypes));
        self::assertSame($overrideAttribute, $target->supports(Capability::OverrideAttribute));
    }

    public function testMinimumVersions(): void
    {
        self::assertSame('8.2', (string) Capability::DnfTypes->minimumVersion());
        self::assertSame('8.3', (string) Capability::OverrideAttribute->minimumVersion());
    }
}
