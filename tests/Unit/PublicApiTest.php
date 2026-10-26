<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Unit;

use FilesystemIterator;
use MSSTC4PHP\DtoCollectionGenerator\Application\Generate\ChangeKind;
use MSSTC4PHP\DtoCollectionGenerator\Application\Generate\FileChange;
use MSSTC4PHP\DtoCollectionGenerator\Application\Generate\Generate;
use MSSTC4PHP\DtoCollectionGenerator\Application\Generate\GeneratedFile;
use MSSTC4PHP\DtoCollectionGenerator\Application\Generate\Input;
use MSSTC4PHP\DtoCollectionGenerator\Application\Generate\Mode;
use MSSTC4PHP\DtoCollectionGenerator\Application\Generate\Output;
use MSSTC4PHP\DtoCollectionGenerator\Application\Generate\Status;
use MSSTC4PHP\DtoCollectionGenerator\Application\Generate\WritePlan;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic\Diagnostic;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic\Diagnostics;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic\Location;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic\Severity;
use MSSTC4PHP\DtoCollectionGenerator\DtoCollectionGenerator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

/**
 * The public API is what .roave-backward-compatibility-check.xml keeps stable; everything else says @internal.
 */
final class PublicApiTest extends TestCase
{
    private const PUBLIC = [
        ChangeKind::class,
        FileChange::class,
        Generate::class,
        GeneratedFile::class,
        Input::class,
        Mode::class,
        Output::class,
        Status::class,
        WritePlan::class,
        Diagnostic::class,
        Diagnostics::class,
        Location::class,
        Severity::class,
        DtoCollectionGenerator::class,
    ];

    public function testOnlyThePublicApiLacksInternal(): void
    {
        $public = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/src', FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            assert($file instanceof SplFileInfo);
            $relative = substr($file->getPathname(), strlen(dirname(__DIR__, 2) . '/src/'), -4);
            $class = 'MSSTC4PHP\DtoCollectionGenerator\\' . str_replace('/', '\\', $relative);
            self::assertTrue(class_exists($class) || interface_exists($class) || enum_exists($class), $class);
            $docComment = (new ReflectionClass($class))->getDocComment();
            if ($docComment === false || !str_contains($docComment, '@internal')) {
                $public[] = $class;
            }
        }
        sort($public);

        self::assertSame(self::PUBLIC, $public);
    }
}
