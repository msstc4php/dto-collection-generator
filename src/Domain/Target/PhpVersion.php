<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Domain\Target;

use MSSTC4PHP\DtoCollectionGenerator\Domain\Exception\UnsupportedPhpVersion;
use Stringable;

/**
 * A target PHP minor version. Below 8.1 dto-collection does not run.
 */
final class PhpVersion implements Stringable
{
    public const SUPPORTED = ['8.1', '8.2', '8.3', '8.4', '8.5'];

    /**
     * @param value-of<self::SUPPORTED> $version
     */
    private function __construct(private readonly string $version)
    {
    }

    public static function fromString(string $version): self
    {
        foreach (self::SUPPORTED as $supported) {
            if ($supported === $version) {
                return new self($supported);
            }
        }

        throw new UnsupportedPhpVersion(sprintf('PHP "%s" is not a supported target; use one of %s', $version, implode(', ', self::SUPPORTED)));
    }

    public static function oldest(): self
    {
        return new self(self::SUPPORTED[0]);
    }

    public static function newest(): self
    {
        return new self(self::SUPPORTED[count(self::SUPPORTED) - 1]);
    }

    /**
     * @return positive-int the PHP_VERSION_ID of the minor's first release
     */
    public function id(): int
    {
        [$major, $minor] = explode('.', $this->version);

        return max(1, (int) $major * 10000 + (int) $minor * 100);
    }

    public function isAtLeast(self $other): bool
    {
        return $this->id() >= $other->id();
    }

    public function __toString(): string
    {
        return $this->version;
    }
}
