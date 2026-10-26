<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Presentation\Cli;

use MSSTC4PHP\DtoCollectionGenerator\Application\Generate\ChangeKind;
use MSSTC4PHP\DtoCollectionGenerator\Application\Generate\FileChange;
use MSSTC4PHP\DtoCollectionGenerator\Application\Generate\Generate;
use MSSTC4PHP\DtoCollectionGenerator\Application\Generate\Input;
use MSSTC4PHP\DtoCollectionGenerator\Application\Generate\Mode;
use MSSTC4PHP\DtoCollectionGenerator\Application\Generate\Output;
use MSSTC4PHP\DtoCollectionGenerator\Application\Generate\Status;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic\Diagnostic;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Shared\Path;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `dto-collection-generator generate [--config=] [--check] [--dry-run] [--format=text|json]`. Exit codes: 0 done,
 * 1 out of date (--check), 2 generation failed, 3 config or usage error. In text mode, diagnostics go to stderr.
 *
 * @phpstan-import-type ChangeShape from JsonReport
 * @phpstan-import-type DiagnosticShape from JsonReport
 *
 * @internal
 */
final class GenerateCommand extends Command
{
    private const DEFAULT_CONFIGS = ['dto-collection-generator.yaml', 'dto-collection-generator.json'];

    private readonly string $workingDirectory;

    public function __construct(private readonly Generate $generate, string $workingDirectory)
    {
        $this->workingDirectory = Path::normalize($workingDirectory);
        parent::__construct('generate');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Generates the typed collection classes of #[TypedCollection] attributes and the config file.')
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Config file (default: dto-collection-generator.yaml, then dto-collection-generator.json)')
            ->addOption('check', null, InputOption::VALUE_NONE, 'Write nothing; exit 1 when the output is out of date')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Write nothing; show what would change')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'text (default) or json')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // getOptions() is typed in every supported symfony/console; getOption() returns "mixed".
        $options = $input->getOptions();
        $format = $options['format'] ?? 'text';
        if ($format !== 'text' && $format !== 'json') {
            return $this->usageError($output, false, '--format must be "text" or "json".');
        }
        $json = $format === 'json';
        $check = $options['check'] === true;
        $dryRun = $options['dry-run'] === true;
        if ($check && $dryRun) {
            return $this->usageError($output, $json, '--check and --dry-run cannot be combined.');
        }
        $config = $this->configPath($options['config'] ?? null);
        if ($config === null) {
            return $this->usageError($output, $json, 'No dto-collection-generator.yaml or dto-collection-generator.json here; pass --config.');
        }
        $mode = $check ? Mode::Check : ($dryRun ? Mode::DryRun : Mode::Write);
        $result = ($this->generate)(new Input($config, $mode));
        if ($json) {
            // Raw: the console formatter would strip anything that looks like a <tag> from the JSON.
            $output->writeln(JsonReport::encode(['status' => $result->status->value, 'diagnostics' => $this->diagnostics($result), 'changes' => $this->changes($result)]), OutputInterface::OUTPUT_RAW);
        } else {
            $this->text($result, $mode, $output);
        }

        return $result->status->exitCode();
    }

    private function configPath(mixed $option): ?string
    {
        if (is_string($option) && $option !== '') {
            return Path::resolve($this->workingDirectory, $option);
        }
        foreach (self::DEFAULT_CONFIGS as $name) {
            $path = Path::resolve($this->workingDirectory, $name);
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function usageError(OutputInterface $output, bool $json, string $message): int
    {
        if ($json) {
            $output->writeln(JsonReport::failure($message), OutputInterface::OUTPUT_RAW);
        } else {
            ErrorOutput::of($output)->writeln('error: ' . $message);
        }

        return Status::ConfigFailed->exitCode();
    }

    private function text(Output $result, Mode $mode, OutputInterface $output): void
    {
        $errors = ErrorOutput::of($output);
        foreach ($result->diagnostics as $diagnostic) {
            $errors->writeln(sprintf('%s %s: %s', $diagnostic->severity->value, $diagnostic->location->render($this->workingDirectory), $diagnostic->message), OutputInterface::OUTPUT_RAW);
        }
        if ($result->status === Status::ConfigFailed || $result->status === Status::GenerationFailed) {
            $errors->writeln(sprintf(
                '%s failed: %d error(s).',
                $result->status === Status::ConfigFailed ? 'Configuration' : 'Generation',
                count($result->diagnostics->errors()),
            ));

            return;
        }
        $lines = array_map(static fn (array $change): string => $change['kind'] . ' ' . $change['path'], $this->changes($result));
        if ($mode === Mode::Check) {
            $output->writeln($result->status === Status::OutOfDate ? 'Out of date:' : 'Up to date.');
            foreach ($lines as $line) {
                $output->writeln('  ' . $line, OutputInterface::OUTPUT_RAW);
            }

            return;
        }
        if ($mode === Mode::DryRun) {
            foreach ($lines as $line) {
                $output->writeln($line, OutputInterface::OUTPUT_RAW);
            }
        }
        $output->writeln(sprintf(
            $mode === Mode::DryRun ? 'Would write: %d, delete: %d, unchanged: %d.' : 'Written: %d, deleted: %d, unchanged: %d.',
            $this->count($result, ChangeKind::Create, ChangeKind::Update),
            $this->count($result, ChangeKind::Delete),
            $this->count($result, ChangeKind::Unchanged),
        ));
    }

    private function count(Output $result, ChangeKind ...$kinds): int
    {
        return count(array_filter($result->plan->changes ?? [], static fn (FileChange $change): bool => in_array($change->kind, $kinds, true)));
    }

    /**
     * The files that change, then the manifest; unchanged files are left out.
     *
     * @return list<ChangeShape>
     */
    private function changes(Output $result): array
    {
        $changes = [];
        foreach ($result->plan->changes ?? [] as $change) {
            if ($change->kind !== ChangeKind::Unchanged) {
                $changes[] = ['kind' => $change->kind->value, 'path' => Path::within($this->workingDirectory, $change->path) ?? $change->path];
            }
        }
        if ($result->plan?->manifestOutdated === true) {
            $manifest = $result->directory . '/' . Generate::MANIFEST;
            $changes[] = ['kind' => is_file($manifest) ? 'update' : 'create', 'path' => Path::within($this->workingDirectory, $manifest) ?? $manifest];
        }

        return $changes;
    }

    /**
     * @return list<DiagnosticShape>
     */
    private function diagnostics(Output $result): array
    {
        return array_map(fn (Diagnostic $diagnostic): array => [
            'severity' => $diagnostic->severity->value,
            'location' => $diagnostic->location->render($this->workingDirectory),
            'message' => $diagnostic->message,
        ], $result->diagnostics->all());
    }
}
