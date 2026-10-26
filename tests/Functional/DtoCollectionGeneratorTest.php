<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Functional;

use Composer\InstalledVersions;
use MSSTC4PHP\DtoCollectionGenerator\Application\Generate\Input;
use MSSTC4PHP\DtoCollectionGenerator\Application\Generate\Mode;
use MSSTC4PHP\DtoCollectionGenerator\Application\Generate\Status;
use MSSTC4PHP\DtoCollectionGenerator\DtoCollectionGenerator;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Support\DemoProject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class DtoCollectionGeneratorTest extends TestCase
{
    public function testGeneratorIsThePhpApi(): void
    {
        $project = new DemoProject();

        try {
            $output = DtoCollectionGenerator::generator()(new Input($project->path('dto-collection-generator.yaml'), Mode::DryRun));
        } finally {
            $project->remove();
        }

        self::assertSame(Status::Ok, $output->status);
        self::assertCount(3, $output->plan->changes ?? []);
    }

    public function testConsoleNamesTheInstalledVersion(): void
    {
        self::assertSame(InstalledVersions::getPrettyVersion('msstc4php/dto-collection-generator'), DtoCollectionGenerator::console()->getVersion());
    }

    public function testAnUnexpectedFailureExitsWithTwo(): void
    {
        $application = new Application();
        $application->add(new class extends Command {
            public function __construct()
            {
                parent::__construct('explode');
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                throw new RuntimeException('Boom');
            }
        });
        $output = new BufferedOutput();
        self::assertSame(2, DtoCollectionGenerator::run(new ArrayInput(['command' => 'explode']), $output, $application));
        self::assertSame("error: Boom\n", $output->fetch());

        $verbose = new BufferedOutput(OutputInterface::VERBOSITY_VERBOSE);
        self::assertSame(2, DtoCollectionGenerator::run(new ArrayInput(['command' => 'explode']), $verbose, $application));
        self::assertStringStartsWith("error: Boom\nRuntimeException in " . __FILE__ . ':', $verbose->fetch());
    }
}
