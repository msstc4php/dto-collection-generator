<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Unit\Domain\Target;

use MSSTC4PHP\DtoCollectionGenerator\Domain\Exception\UnsupportedPhpVersion;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Target\PhpVersion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhpVersionTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function unsupported(): iterable
    {
        yield 'below dto-collection' => ['8.0'];
        yield 'unknown future' => ['8.6'];
        yield 'patch level' => ['8.1.2'];
        yield 'not a version' => ['eight'];
        yield 'empty' => [''];
    }

    #[DataProvider('unsupported')]
    public function testRejectsUnsupportedVersions(string $version): void
    {
        $this->expectException(UnsupportedPhpVersion::class);
        $this->expectExceptionMessage(sprintf('PHP "%s" is not a supported target; use one of 8.1, 8.2, 8.3, 8.4, 8.5', $version));

        PhpVersion::fromString($version);
    }

    public function testIdUsesThePhpVersionIdEncoding(): void
    {
        self::assertSame(80100, PhpVersion::fromString('8.1')->id());
        self::assertSame(80300, PhpVersion::fromString('8.3')->id());
        self::assertSame(80500, PhpVersion::fromString('8.5')->id());
    }

    public function testRange(): void
    {
        self::assertSame('8.1', (string) PhpVersion::oldest());
        self::assertSame('8.5', (string) PhpVersion::newest());
    }

    public function testComparison(): void
    {
        $php83 = PhpVersion::fromString('8.3');

        self::assertTrue($php83->isAtLeast(PhpVersion::fromString('8.2')));
        self::assertTrue($php83->isAtLeast(PhpVersion::fromString('8.3')));
        self::assertFalse($php83->isAtLeast(PhpVersion::fromString('8.4')));
    }
}
