<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Application\Generate;

use MSSTC4PHP\DtoCollectionGenerator\Application\Config\ConfigFactory;
use MSSTC4PHP\DtoCollectionGenerator\Application\Config\GeneratorConfig;
use MSSTC4PHP\DtoCollectionGenerator\Application\Config\TargetResolver;
use MSSTC4PHP\DtoCollectionGenerator\Application\Port\ClassInspector;
use MSSTC4PHP\DtoCollectionGenerator\Application\Port\CodeEmitter;
use MSSTC4PHP\DtoCollectionGenerator\Application\Port\DocumentLoader;
use MSSTC4PHP\DtoCollectionGenerator\Application\Port\DocumentNotLoaded;
use MSSTC4PHP\DtoCollectionGenerator\Application\Port\FileWriter;
use MSSTC4PHP\DtoCollectionGenerator\Application\Port\ProjectLayout;
use MSSTC4PHP\DtoCollectionGenerator\Application\Port\ProjectPhpConstraint;
use MSSTC4PHP\DtoCollectionGenerator\Application\Port\SpecSource;
use MSSTC4PHP\DtoCollectionGenerator\Application\Port\WriteFailed;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic\Diagnostics;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic\Location;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Shared\Path;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Target\TargetProfile;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Validation\SpecValidator;

/**
 * The generator's one use case: config → specs → checks → classes → files.
 */
final class Generate
{
    public const MANIFEST = '.dto-collection-generator.manifest.json';

    public function __construct(
        private readonly DocumentLoader $loader,
        private readonly ProjectPhpConstraint $constraints,
        private readonly SpecSource $specSource,
        private readonly ClassInspector $inspector,
        private readonly CodeEmitter $emitter,
        private readonly ProjectLayout $layout,
        private readonly FileWriter $writer,
    ) {
    }

    public function __invoke(Input $input): Output
    {
        $diagnostics = new Diagnostics();
        $directory = Path::directory($input->configPath);
        $config = $this->config($input->configPath, $diagnostics);
        $target = $config instanceof GeneratorConfig ? (new TargetResolver($this->constraints))->resolve($config, $diagnostics) : null;
        if (!$config instanceof GeneratorConfig || !$target instanceof TargetProfile) {
            return new Output(Status::ConfigFailed, $diagnostics, $directory);
        }

        $files = $this->files($config, $target, $diagnostics);
        if ($files === null) {
            return new Output(Status::GenerationFailed, $diagnostics, $directory);
        }
        $manifest = $config->directory . '/' . self::MANIFEST;
        $plan = $this->writer->plan($manifest, $files);
        foreach ($plan->changes as $change) {
            if ($change->kind === ChangeKind::Conflict) {
                $diagnostics->error('Cannot write this file: ' . $change->reason, Location::inFile($change->path));
            }
        }
        if ($plan->hasConflicts()) {
            return new Output(Status::GenerationFailed, $diagnostics, $directory, $plan);
        }

        return match ($input->mode) {
            Mode::Check => new Output($plan->isUpToDate() ? Status::Ok : Status::OutOfDate, $diagnostics, $directory, $plan),
            Mode::DryRun => new Output(Status::Ok, $diagnostics, $directory, $plan),
            Mode::Write => $this->write($plan, $manifest, $files, $config, $diagnostics),
        };
    }

    private function config(string $path, Diagnostics $diagnostics): ?GeneratorConfig
    {
        try {
            $raw = $this->loader->load($path);
        } catch (DocumentNotLoaded $exception) {
            $diagnostics->error($exception->getMessage(), Location::inConfig($path, ''));

            return null;
        }

        return ConfigFactory::create($raw, $path, $diagnostics);
    }

    /**
     * @return list<GeneratedFile>|null null when a spec has an error
     */
    private function files(GeneratorConfig $config, TargetProfile $target, Diagnostics $diagnostics): ?array
    {
        $specs = [...$this->specSource->specs($config->sources, $diagnostics), ...$config->collections];
        $valid = (new SpecValidator())->validate($specs, $target, $this->inspector, $diagnostics, $this->writer->isGenerated(...));
        if ($diagnostics->hasErrors()) {
            return null;
        }

        $files = [];
        foreach ($valid as $spec) {
            $path = $this->layout->pathFor($spec->class, $config);
            if ($path === null) {
                $diagnostics->error(sprintf('%s: no namespace prefix in "paths" or in the PSR-4 autoload of composer.json covers it; add one to "paths"', $spec->class->fullName()), $spec->origin);

                continue;
            }
            $files[] = new GeneratedFile($path, $this->emitter->emit($spec, $target, $this->inspector));
        }

        return $diagnostics->hasErrors() ? null : $files;
    }

    /**
     * @param list<GeneratedFile> $files
     */
    private function write(WritePlan $plan, string $manifest, array $files, GeneratorConfig $config, Diagnostics $diagnostics): Output
    {
        try {
            $this->writer->apply($plan, $manifest, $files);
        } catch (WriteFailed $exception) {
            $diagnostics->error($exception->getMessage(), $config->location());

            return new Output(Status::GenerationFailed, $diagnostics, $config->directory, $plan);
        }

        return new Output(Status::Ok, $diagnostics, $config->directory, $plan);
    }
}
