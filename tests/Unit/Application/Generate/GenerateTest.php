<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Unit\Application\Generate;

use MSSTC4PHP\DtoCollection\Attribute\Variant;
use MSSTC4PHP\DtoCollection\ElementType;
use MSSTC4PHP\DtoCollectionGenerator\Application\Generate\ChangeKind;
use MSSTC4PHP\DtoCollectionGenerator\Application\Generate\Generate;
use MSSTC4PHP\DtoCollectionGenerator\Application\Generate\GeneratedFile;
use MSSTC4PHP\DtoCollectionGenerator\Application\Generate\Input;
use MSSTC4PHP\DtoCollectionGenerator\Application\Generate\Mode;
use MSSTC4PHP\DtoCollectionGenerator\Application\Generate\Output;
use MSSTC4PHP\DtoCollectionGenerator\Application\Generate\Status;
use MSSTC4PHP\DtoCollectionGenerator\Application\Port\PhpRequirement;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic\Diagnostic;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic\Location;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Spec\ClassKind;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Spec\ClassName;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Spec\CollectionSpec;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Item;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Fixtures\Model\Tag;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Support\FixedLayout;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Support\FixedPhpConstraint;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Support\InMemoryClassInspector;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Support\InMemoryDocumentLoader;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Support\InMemorySpecSource;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Support\RecordingEmitter;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Support\RecordingWriter;
use PHPUnit\Framework\TestCase;

final class GenerateTest extends TestCase
{
    private const CONFIG = '/project/dto-collection-generator.yaml';

    private RecordingEmitter $emitter;

    private RecordingWriter $writer;

    protected function setUp(): void
    {
        $this->emitter = new RecordingEmitter();
        $this->writer = new RecordingWriter();
    }

    public function testWritesAttributeSpecsThenConfigSpecs(): void
    {
        $source = new InMemorySpecSource([$this->spec('App\Collection\ItemList')]);
        $output = $this->generate($this->config(['sources' => ['src'], 'collections' => [['class' => 'App\Collection\IntegerList', 'variant' => 'immutable', 'elementType' => 'int']]]), source: $source);

        self::assertSame(Status::Ok, $output->status);
        self::assertSame(['warning /project/dto-collection-generator.yaml#/sources/0: Directory "/project/src" does not exist; nothing is scanned there.'], $this->messages($output));
        self::assertSame([['/project/src']], $source->scanned);
        self::assertSame(['App\Collection\ItemList', 'App\Collection\IntegerList'], $this->emitter->emitted);
        self::assertSame('/project/.dto-collection-generator.manifest.json', $this->writer->manifest);
        self::assertSame(
            [['/project/src/Collection/ItemList.php', "<?php // App\\Collection\\ItemList for PHP 8.2\n"], ['/project/src/Collection/IntegerList.php', "<?php // App\\Collection\\IntegerList for PHP 8.2\n"]],
            array_map(static fn (GeneratedFile $file): array => [$file->path, $file->contents], $this->writer->planned),
        );
        self::assertSame(1, $this->writer->applied);
        self::assertSame(2, $output->plan?->count(ChangeKind::Create));
        self::assertSame('/project', $output->directory);
    }

    public function testMissingConfig(): void
    {
        $output = $this->generate([], path: '/project/missing.yaml');

        self::assertSame(Status::ConfigFailed, $output->status);
        self::assertSame(['error /project/missing.yaml: /project/missing.yaml does not exist'], $this->messages($output));
        self::assertSame([], $this->emitter->emitted);
    }

    public function testInvalidConfig(): void
    {
        $output = $this->generate(['version' => 2]);

        self::assertSame(Status::ConfigFailed, $output->status);
        self::assertSame(['error /project/dto-collection-generator.yaml#/version: "version" must be 1.'], $this->messages($output));
        self::assertNull($output->plan);
    }

    public function testTargetThatDtoCollectionDoesNotSupport(): void
    {
        $output = $this->generate(['version' => 1], requirement: PhpRequirement::found('/project/composer.json', '^7.4'));

        self::assertSame(Status::ConfigFailed, $output->status);
        self::assertCount(1, $output->diagnostics->errors());
    }

    public function testInvalidSpecStopsBeforeEmitting(): void
    {
        $output = $this->generate($this->config(), source: new InMemorySpecSource([$this->spec('App\Collection\ItemList'), $this->spec('App\Collection\itemlist')]));

        self::assertSame(Status::GenerationFailed, $output->status);
        self::assertCount(1, $output->diagnostics->errors());
        self::assertSame([], $this->emitter->emitted);
        self::assertNull($output->plan);
    }

    public function testScannerErrorsFailTheRunButAreReportedWithTheRest(): void
    {
        $output = $this->generate($this->config(), source: new InMemorySpecSource([$this->spec('App\Collection\ItemList'), $this->spec('App\Collection\itemlist')], ['Cannot parse this file']));

        self::assertSame(Status::GenerationFailed, $output->status);
        self::assertCount(2, $output->diagnostics->errors());
        self::assertSame([], $this->emitter->emitted);
    }

    public function testClassOutsideEveryPrefix(): void
    {
        $output = $this->generate($this->config(), source: new InMemorySpecSource([$this->spec('Other\ItemList')]));

        self::assertSame(Status::GenerationFailed, $output->status);
        self::assertSame(['error /project/src/Item.php:5: Other\ItemList: no namespace prefix in "paths" or in the PSR-4 autoload of composer.json covers it; add one to "paths"'], $this->messages($output));
        self::assertNull($output->plan);
    }

    public function testConflictsFailWithoutWriting(): void
    {
        $this->writer = new RecordingWriter(ChangeKind::Conflict);
        $output = $this->generate($this->config(), source: new InMemorySpecSource([$this->spec('App\Collection\ItemList')]));

        self::assertSame(Status::GenerationFailed, $output->status);
        self::assertSame(['error /project/src/Collection/ItemList.php: Cannot write this file: in the way'], $this->messages($output));
        self::assertSame(0, $this->writer->applied);
        self::assertNotNull($output->plan);
    }

    public function testCheckModeNeverWrites(): void
    {
        $outdated = $this->generate($this->config(), Mode::Check, source: new InMemorySpecSource([$this->spec('App\Collection\ItemList')]));
        self::assertSame(Status::OutOfDate, $outdated->status);
        self::assertSame(0, $this->writer->applied);

        $this->writer = new RecordingWriter(ChangeKind::Unchanged);
        $upToDate = $this->generate($this->config(), Mode::Check, source: new InMemorySpecSource([$this->spec('App\Collection\ItemList')]));
        self::assertSame(Status::Ok, $upToDate->status);
        self::assertSame(0, $this->writer->applied);
    }

    public function testDryRunReportsWithoutWriting(): void
    {
        $output = $this->generate($this->config(), Mode::DryRun, source: new InMemorySpecSource([$this->spec('App\Collection\ItemList')]));

        self::assertSame(Status::Ok, $output->status);
        self::assertSame(1, $output->plan?->count(ChangeKind::Create));
        self::assertSame(0, $this->writer->applied);
    }

    public function testWarningsKeepTheRunOk(): void
    {
        $output = $this->generate(['version' => 1, 'sources' => ['missing']], requirement: PhpRequirement::none());

        self::assertSame(Status::Ok, $output->status);
        self::assertCount(2, $output->diagnostics);
        self::assertSame(1, $this->writer->applied);
    }

    public function testWriteFailure(): void
    {
        $this->writer = new RecordingWriter(fails: true);
        $output = $this->generate($this->config(), source: new InMemorySpecSource([$this->spec('App\Collection\ItemList')]));

        self::assertSame(Status::GenerationFailed, $output->status);
        self::assertSame(['error /project/dto-collection-generator.yaml: Cannot write /project/src/Collection/ItemList.php'], $this->messages($output));
    }

    public function testExistingGeneratedClassIsAskedOfTheWriter(): void
    {
        $output = $this->generate($this->config(), source: new InMemorySpecSource([$this->spec('App\Generated'), $this->spec('App\Existing')]));

        self::assertSame(Status::GenerationFailed, $output->status);
        self::assertSame(['error /project/src/Item.php:5: App\Existing already exists in /project/src/Existing.php, which dto-collection-generator did not write; choose another class name'], $this->messages($output));
    }

    public function testExitCodes(): void
    {
        self::assertSame([0, 1, 2, 3], array_map(static fn (Status $status): int => $status->exitCode(), [Status::Ok, Status::OutOfDate, Status::GenerationFailed, Status::ConfigFailed]));
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private function generate(array $config, Mode $mode = Mode::Write, ?InMemorySpecSource $source = null, ?PhpRequirement $requirement = null, string $path = self::CONFIG): Output
    {
        $generate = new Generate(
            new InMemoryDocumentLoader($path === self::CONFIG ? [self::CONFIG => $config] : []),
            new FixedPhpConstraint($requirement ?? PhpRequirement::found('/project/composer.json', '^8.2')),
            $source ?? new InMemorySpecSource([]),
            new InMemoryClassInspector([
                Item::class => ['kind' => ClassKind::OrdinaryClass],
                Tag::class => ['kind' => ClassKind::OrdinaryClass],
                'App\Generated' => ['kind' => ClassKind::OrdinaryClass, 'file' => '/project/src/Generated.php'],
                'App\Existing' => ['kind' => ClassKind::OrdinaryClass, 'file' => '/project/src/Existing.php'],
            ]),
            $this->emitter,
            new FixedLayout(),
            $this->writer,
        );

        return $generate(new Input($path, $mode));
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function config(array $overrides = []): array
    {
        return array_merge(['version' => 1], $overrides);
    }

    private function spec(string $class): CollectionSpec
    {
        return new CollectionSpec(ClassName::fromString($class), Variant::Immutable, ElementType::ofClass(Item::class), ClassName::fromString(Item::class), Location::inFile('/project/src/Item.php', 5));
    }

    /**
     * @return list<string>
     */
    private function messages(Output $output): array
    {
        return array_map(
            static fn (Diagnostic $diagnostic): string => $diagnostic->severity->value . ' ' . $diagnostic->location->absolute() . ': ' . $diagnostic->message,
            $output->diagnostics->all(),
        );
    }
}
