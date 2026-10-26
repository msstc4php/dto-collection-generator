<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Functional\Cli;

use MSSTC4PHP\DtoCollectionGenerator\DtoCollectionGenerator;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Support\DemoProject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;

final class GenerateCommandTest extends TestCase
{
    private const SEPARATE_STDERR = ['capture_stderr_separately' => true];

    private DemoProject $project;

    protected function setUp(): void
    {
        $this->project = new DemoProject();
    }

    protected function tearDown(): void
    {
        $this->project->remove();
    }

    public function testWritesThenChecks(): void
    {
        $tester = $this->tester();

        self::assertSame(0, $tester->execute([], self::SEPARATE_STDERR));
        self::assertSame("Written: 3, deleted: 0, unchanged: 0.\n", $tester->getDisplay());
        self::assertSame('', $tester->getErrorOutput());
        foreach (['OrderLineList', 'MutableOrderLineList', 'IntegerList'] as $class) {
            self::assertFileExists($this->project->path('generated/' . $class . '.php'));
        }
        self::assertStringContainsString('#[Override]', (string) file_get_contents($this->project->path('generated/OrderLineList.php')));
        self::assertFileExists($this->project->path('.dto-collection-generator.manifest.json'));

        $check = $this->tester();
        self::assertSame(0, $check->execute(['--check' => true], self::SEPARATE_STDERR));
        self::assertSame("Up to date.\n", $check->getDisplay());

        $again = $this->tester();
        self::assertSame(0, $again->execute([], self::SEPARATE_STDERR));
        self::assertSame("Written: 0, deleted: 0, unchanged: 3.\n", $again->getDisplay());
    }

    public function testCheckFindsAHandEdit(): void
    {
        $this->tester()->execute([], self::SEPARATE_STDERR);
        file_put_contents($this->project->path('generated/IntegerList.php'), "\n// edited\n", FILE_APPEND);

        $check = $this->tester();
        self::assertSame(1, $check->execute(['--check' => true], self::SEPARATE_STDERR));
        self::assertSame("Out of date:\n  update generated/IntegerList.php\n", $check->getDisplay());
    }

    public function testDryRun(): void
    {
        $tester = $this->tester();

        self::assertSame(0, $tester->execute(['--dry-run' => true], self::SEPARATE_STDERR));
        self::assertSame("create generated/OrderLineList.php\ncreate generated/MutableOrderLineList.php\ncreate generated/IntegerList.php\nWould write: 3, delete: 0, unchanged: 0.\n", $tester->getDisplay());
        self::assertDirectoryDoesNotExist($this->project->path('generated'));
    }

    public function testRemovedSpecDeletesItsClass(): void
    {
        $this->tester()->execute([], self::SEPARATE_STDERR);
        file_put_contents($this->project->path('dto-collection-generator.yaml'), "version: 1\nsources: [Dto]\n");

        $tester = $this->tester();
        self::assertSame(0, $tester->execute([], self::SEPARATE_STDERR));
        self::assertSame("Written: 0, deleted: 1, unchanged: 2.\n", $tester->getDisplay());
        self::assertFileDoesNotExist($this->project->path('generated/IntegerList.php'));
    }

    public function testJsonReport(): void
    {
        $tester = $this->tester();

        self::assertSame(0, $tester->execute(['--format' => 'json', '--dry-run' => true], self::SEPARATE_STDERR));
        self::assertSame([
            'status' => 'ok',
            'diagnostics' => [],
            'changes' => [
                ['kind' => 'create', 'path' => 'generated/OrderLineList.php'],
                ['kind' => 'create', 'path' => 'generated/MutableOrderLineList.php'],
                ['kind' => 'create', 'path' => 'generated/IntegerList.php'],
            ],
        ], json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testJsonReportOfAFailure(): void
    {
        file_put_contents($this->project->path('dto-collection-generator.yaml'), "version: 2\n");
        $tester = $this->tester();

        self::assertSame(3, $tester->execute(['--format' => 'json'], self::SEPARATE_STDERR));
        self::assertSame(
            ['status' => 'config-failed', 'diagnostics' => [['severity' => 'error', 'location' => 'dto-collection-generator.yaml#/version', 'message' => '"version" must be 1.']], 'changes' => []],
            json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR),
        );
        self::assertSame('', $tester->getErrorOutput());
    }

    public function testBrokenConfig(): void
    {
        file_put_contents($this->project->path('dto-collection-generator.yaml'), "version: 2\n");
        $tester = $this->tester();

        self::assertSame(3, $tester->execute([], self::SEPARATE_STDERR));
        self::assertSame("error dto-collection-generator.yaml#/version: \"version\" must be 1.\nConfiguration failed: 1 error(s).\n", $tester->getErrorOutput());
        self::assertSame('', $tester->getDisplay());
    }

    public function testInvalidSpec(): void
    {
        file_put_contents($this->project->path('dto-collection-generator.yaml'), "version: 1\ncollections:\n  - {class: Elsewhere\\IntegerList, variant: immutable, elementType: int}\n");
        $tester = $this->tester();

        self::assertSame(2, $tester->execute([], self::SEPARATE_STDERR));
        self::assertSame(
            "error dto-collection-generator.yaml#/collections/0: Elsewhere\\IntegerList: no namespace prefix in \"paths\" or in the PSR-4 autoload of composer.json covers it; add one to \"paths\"\nGeneration failed: 1 error(s).\n",
            $tester->getErrorOutput(),
        );
    }

    public function testUsageErrors(): void
    {
        $tester = $this->tester();
        self::assertSame(3, $tester->execute(['--check' => true, '--dry-run' => true], self::SEPARATE_STDERR));
        self::assertSame("error: --check and --dry-run cannot be combined.\n", $tester->getErrorOutput());

        $tester = $this->tester();
        self::assertSame(3, $tester->execute(['--format' => 'xml'], self::SEPARATE_STDERR));
        self::assertSame("error: --format must be \"text\" or \"json\".\n", $tester->getErrorOutput());

        $tester = $this->tester();
        self::assertSame(3, $tester->execute(['--format' => 'json', '--check' => true, '--dry-run' => true], self::SEPARATE_STDERR));
        self::assertSame(
            ['status' => 'config-failed', 'diagnostics' => [['severity' => 'error', 'location' => '', 'message' => '--check and --dry-run cannot be combined.']], 'changes' => []],
            json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR),
        );
    }

    public function testFindsTheJsonConfigAndExplainsAMissingOne(): void
    {
        rename($this->project->path('dto-collection-generator.yaml'), $this->project->path('dto-collection-generator.json'));
        file_put_contents($this->project->path('dto-collection-generator.json'), '{"version": 1, "sources": ["Dto"]}');
        $tester = $this->tester();
        self::assertSame(0, $tester->execute([], self::SEPARATE_STDERR));
        self::assertFileExists($this->project->path('generated/OrderLineList.php'));

        unlink($this->project->path('dto-collection-generator.json'));
        $tester = $this->tester();
        self::assertSame(3, $tester->execute([], self::SEPARATE_STDERR));
        self::assertSame("error: No dto-collection-generator.yaml or dto-collection-generator.json here; pass --config.\n", $tester->getErrorOutput());

        $tester = $this->tester();
        self::assertSame(3, $tester->execute(['--config' => 'elsewhere.yaml'], self::SEPARATE_STDERR));
        self::assertStringStartsWith('error elsewhere.yaml: ', $tester->getErrorOutput());
    }

    public function testRunMapsConsoleFailuresToTheExitCodes(): void
    {
        $output = new BufferedOutput();
        self::assertSame(3, DtoCollectionGenerator::run(new ArrayInput(['command' => 'generate', '--unknown' => true]), $output, DtoCollectionGenerator::console($this->project->path())));
        self::assertStringStartsWith('error: The "--unknown" option does not exist.', $output->fetch());

        self::assertSame(3, DtoCollectionGenerator::run(new ArrayInput(['command' => 'generate', '--unknown' => true, '--format' => 'json']), $output, DtoCollectionGenerator::console($this->project->path())));
        self::assertSame('config-failed', $this->reportStatus($output->fetch()));

        self::assertSame(0, DtoCollectionGenerator::run(new ArrayInput(['command' => 'generate']), $output, DtoCollectionGenerator::console($this->project->path())));
        self::assertSame("Written: 3, deleted: 0, unchanged: 0.\n", $output->fetch());
    }

    private function reportStatus(string $json): mixed
    {
        $report = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return is_array($report) ? $report['status'] ?? null : null;
    }

    private function tester(): CommandTester
    {
        return new CommandTester(DtoCollectionGenerator::console($this->project->path())->find('generate'));
    }
}
