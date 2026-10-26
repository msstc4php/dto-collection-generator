<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Integration\Infrastructure;

use MSSTC4PHP\DtoCollectionGenerator\Application\Config\GeneratorConfig;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Spec\ClassName;
use MSSTC4PHP\DtoCollectionGenerator\Infrastructure\Environment\ComposerProjectLayout;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\TestCase;

final class ComposerProjectLayoutTest extends TestCase
{
    private TemporaryDirectory $directory;

    protected function setUp(): void
    {
        $this->directory = new TemporaryDirectory();
        $this->directory->write('composer.json', json_encode([
            'autoload' => ['psr-4' => ['App\\' => 'src/', 'App\\Sub\\' => ['lib/sub', 'other'], 'Lib\\' => 'library', '1' => 'numeric', 'Broken\\' => 5]],
            'autoload-dev' => ['psr-4' => ['App\\Tests\\' => 'tests/']],
        ], JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        $this->directory->remove();
    }

    public function testPsr4(): void
    {
        $root = $this->directory->path;
        $layout = new ComposerProjectLayout();
        $config = $this->config($root, []);

        self::assertSame($root . '/src/Collection/ItemList.php', $layout->pathFor(ClassName::fromString('App\Collection\ItemList'), $config));
        self::assertSame($root . '/lib/sub/ItemList.php', $layout->pathFor(ClassName::fromString('App\Sub\ItemList'), $config));
        self::assertSame($root . '/library/Deep/ItemList.php', $layout->pathFor(ClassName::fromString('Lib\Deep\ItemList'), $config));
        self::assertSame($root . '/tests/ItemList.php', $layout->pathFor(ClassName::fromString('App\Tests\ItemList'), $config));
        self::assertNull($layout->pathFor(ClassName::fromString('Other\ItemList'), $config));
        self::assertNull($layout->pathFor(ClassName::fromString('app\Collection\ItemList'), $config));
    }

    public function testConfigPathsComeFirst(): void
    {
        $root = $this->directory->path;
        $config = $this->config($root, ['App\Collection\\' => $root . '/generated', 'Other\\' => '/elsewhere']);
        $layout = new ComposerProjectLayout();

        self::assertSame($root . '/generated/ItemList.php', $layout->pathFor(ClassName::fromString('App\Collection\ItemList'), $config));
        self::assertSame($root . '/generated/Deep/ItemList.php', $layout->pathFor(ClassName::fromString('App\Collection\Deep\ItemList'), $config));
        self::assertSame('/elsewhere/ItemList.php', $layout->pathFor(ClassName::fromString('Other\ItemList'), $config));
        self::assertSame($root . '/src/ItemList.php', $layout->pathFor(ClassName::fromString('App\ItemList'), $config));
    }

    public function testComposerJsonAboveTheConfig(): void
    {
        $root = $this->directory->path;
        mkdir($root . '/config');

        self::assertSame(
            $root . '/src/ItemList.php',
            (new ComposerProjectLayout())->pathFor(ClassName::fromString('App\ItemList'), $this->config($root . '/config', [])),
        );
    }

    public function testUnreadableComposerJson(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            self::markTestSkipped('root can read any file');
        }
        chmod($this->directory->path . '/composer.json', 0o000);

        try {
            self::assertNull((new ComposerProjectLayout())->pathFor(ClassName::fromString('App\ItemList'), $this->config($this->directory->path, [])));
        } finally {
            chmod($this->directory->path . '/composer.json', 0o644);
        }
    }

    public function testInvalidComposerJson(): void
    {
        file_put_contents($this->directory->path . '/composer.json', '{"autoload": ');

        self::assertNull((new ComposerProjectLayout())->pathFor(ClassName::fromString('App\ItemList'), $this->config($this->directory->path, [])));
    }

    public function testComposerJsonEditedBetweenCalls(): void
    {
        $layout = new ComposerProjectLayout();
        $config = $this->config($this->directory->path, []);
        self::assertNotNull($layout->pathFor(ClassName::fromString('App\ItemList'), $config));

        file_put_contents($this->directory->path . '/composer.json', '{}');
        self::assertNull($layout->pathFor(ClassName::fromString('App\ItemList'), $config));
    }

    public function testWithoutComposerJson(): void
    {
        unlink($this->directory->path . '/composer.json');
        $config = $this->config($this->directory->path, ['App\\' => $this->directory->path . '/src']);

        self::assertSame($this->directory->path . '/src/ItemList.php', (new ComposerProjectLayout())->pathFor(ClassName::fromString('App\ItemList'), $config));
    }

    /**
     * @param array<string, string> $paths
     */
    private function config(string $directory, array $paths): GeneratorConfig
    {
        return new GeneratorConfig($directory . '/dto-collection-generator.yaml', $directory, null, [], [], $paths);
    }
}
