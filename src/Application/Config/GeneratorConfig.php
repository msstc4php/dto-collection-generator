<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Application\Config;

use MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic\Location;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Spec\CollectionSpec;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Target\PhpVersion;

final class GeneratorConfig
{
    /**
     * @param string $path absolute path of the config file
     * @param string $directory the config file's directory; relative paths resolve against it
     * @param PhpVersion|null $php null for "auto": the lower bound of composer.json's require.php
     * @param list<string> $sources absolute directories scanned for #[TypedCollection]
     * @param list<CollectionSpec> $collections the specs written in the config file
     * @param array<string, string> $paths namespace prefix (ending in "\") => absolute directory
     */
    public function __construct(
        public readonly string $path,
        public readonly string $directory,
        public readonly ?PhpVersion $php,
        public readonly array $sources,
        public readonly array $collections,
        public readonly array $paths,
    ) {
    }

    public function location(string $pointer = ''): Location
    {
        return Location::inConfig($this->path, $pointer);
    }
}
