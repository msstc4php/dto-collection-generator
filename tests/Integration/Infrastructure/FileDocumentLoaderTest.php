<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Integration\Infrastructure;

use MSSTC4PHP\DtoCollectionGenerator\Application\Port\DocumentNotLoaded;
use MSSTC4PHP\DtoCollectionGenerator\Infrastructure\Document\FileDocumentLoader;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FileDocumentLoaderTest extends TestCase
{
    private TemporaryDirectory $directory;

    protected function setUp(): void
    {
        $this->directory = new TemporaryDirectory();
    }

    protected function tearDown(): void
    {
        $this->directory->remove();
    }

    public function testLoadsYaml(): void
    {
        $path = $this->directory->write('dto-collection-generator.yaml', "version: 1\ntarget:\n  php: '8.2'\nsources: [src]\n");

        self::assertSame(['version' => 1, 'target' => ['php' => '8.2'], 'sources' => ['src']], (new FileDocumentLoader())->load($path));
    }

    public function testLoadsJson(): void
    {
        $path = $this->directory->write('dto-collection-generator.json', '{"version": 1, "paths": {"App\\\\": "src"}}');

        self::assertSame(['version' => 1, 'paths' => ['App\\' => 'src']], (new FileDocumentLoader())->load($path));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function failures(): iterable
    {
        yield 'malformed yaml' => ['config.yaml', "version: [1\n", 'is not valid YAML: Malformed inline YAML string'];
        yield 'malformed json' => ['config.json', '{"version": ', 'is not valid JSON: Syntax error'];
        yield 'yaml scalar' => ['config.yaml', "just text\n", 'must contain an object'];
        yield 'json list' => ['config.json', '[1, 2]', 'must contain an object'];
        yield 'empty yaml' => ['config.yaml', '', 'must contain an object'];
        yield 'other extension' => ['config.ini', 'version = 1', 'must be a .yaml, .yml or .json file'];
    }

    #[DataProvider('failures')]
    public function testFailures(string $name, string $content, string $problem): void
    {
        $path = $this->directory->write($name, $content);

        $this->expectException(DocumentNotLoaded::class);
        $this->expectExceptionMessage($path . ' ' . $problem);

        (new FileDocumentLoader())->load($path);
    }

    public function testExtensionIgnoresCase(): void
    {
        $path = $this->directory->write('CONFIG.YAML', "version: 1\n");

        self::assertSame(['version' => 1], (new FileDocumentLoader())->load($path));
    }

    public function testUnreadableFile(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            self::markTestSkipped('root can read any file');
        }
        $path = $this->directory->write('config.yaml', "version: 1\n");
        chmod($path, 0o000);

        try {
            $this->expectException(DocumentNotLoaded::class);
            $this->expectExceptionMessage($path . ' cannot be read');
            (new FileDocumentLoader())->load($path);
        } finally {
            chmod($path, 0o644);
        }
    }

    public function testMissingFile(): void
    {
        $this->expectException(DocumentNotLoaded::class);
        $this->expectExceptionMessage($this->directory->path . '/missing.yaml does not exist');

        (new FileDocumentLoader())->load($this->directory->path . '/missing.yaml');
    }
}
