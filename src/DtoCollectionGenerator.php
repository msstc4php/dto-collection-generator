<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator;

use Composer\InstalledVersions;
use MSSTC4PHP\DtoCollectionGenerator\Application\Generate\Generate;
use MSSTC4PHP\DtoCollectionGenerator\Infrastructure\Document\FileDocumentLoader;
use MSSTC4PHP\DtoCollectionGenerator\Infrastructure\Emitter\StringCodeEmitter;
use MSSTC4PHP\DtoCollectionGenerator\Infrastructure\Environment\ComposerJsonPhpConstraint;
use MSSTC4PHP\DtoCollectionGenerator\Infrastructure\Environment\ComposerProjectLayout;
use MSSTC4PHP\DtoCollectionGenerator\Infrastructure\Reflection\ReflectionClassInspector;
use MSSTC4PHP\DtoCollectionGenerator\Infrastructure\Scanner\AttributeSpecSource;
use MSSTC4PHP\DtoCollectionGenerator\Infrastructure\Writer\FilesystemWriter;
use MSSTC4PHP\DtoCollectionGenerator\Presentation\Cli\ErrorOutput;
use MSSTC4PHP\DtoCollectionGenerator\Presentation\Cli\GenerateCommand;
use MSSTC4PHP\DtoCollectionGenerator\Presentation\Cli\JsonReport;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\CommandLoader\FactoryCommandLoader;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * The composition root and the PHP API: `DtoCollectionGenerator::generator()(new Input($config, Mode::Write))`.
 * It loads the project's classes through the autoloader of the process, so it runs inside the project.
 *
 * @api
 */
final class DtoCollectionGenerator
{
    private const PACKAGE = 'msstc4php/dto-collection-generator';

    private function __construct()
    {
    }

    public static function generator(): Generate
    {
        return new Generate(
            new FileDocumentLoader(),
            new ComposerJsonPhpConstraint(),
            new AttributeSpecSource(),
            new ReflectionClassInspector(),
            new StringCodeEmitter(),
            new ComposerProjectLayout(),
            new FilesystemWriter(),
        );
    }

    /**
     * @param string|null $workingDirectory where the default config is looked up and paths are shown from; the process's by default
     */
    public static function console(?string $workingDirectory = null): Application
    {
        $application = new Application('dto-collection-generator', InstalledVersions::getPrettyVersion(self::PACKAGE) ?? 'dev');
        $directory = $workingDirectory ?? (string) getcwd();
        // A command loader works the same on symfony/console 5.4 to 8, where add() gave way to addCommand().
        $application->setCommandLoader(new FactoryCommandLoader([
            'generate' => static fn (): GenerateCommand => new GenerateCommand(self::generator(), $directory),
        ]));

        return $application;
    }

    /**
     * Runs the console so that exit code 1 keeps meaning "out of date": Symfony itself answers 1 to a mistyped
     * option or an uncaught exception, which here become 3 and 2.
     */
    public static function run(?InputInterface $input = null, ?OutputInterface $output = null, ?Application $application = null): int
    {
        $input ??= new ArgvInput();
        $output ??= new ConsoleOutput();
        $application ??= self::console();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        try {
            return $application->run($input, $output);
        } catch (ExceptionInterface $exception) {
            if ($input->getParameterOption('--format') === 'json') {
                $output->writeln(JsonReport::failure($exception->getMessage()), OutputInterface::OUTPUT_RAW);
            } else {
                ErrorOutput::of($output)->writeln('error: ' . $exception->getMessage(), OutputInterface::OUTPUT_RAW);
            }

            return 3;
        } catch (Throwable $exception) {
            $errors = ErrorOutput::of($output);
            $errors->writeln('error: ' . $exception->getMessage(), OutputInterface::OUTPUT_RAW);
            $errors->writeln(sprintf('%s in %s:%d', $exception::class, $exception->getFile(), $exception->getLine()), OutputInterface::VERBOSITY_VERBOSE | OutputInterface::OUTPUT_RAW);

            return 2;
        }
    }
}
