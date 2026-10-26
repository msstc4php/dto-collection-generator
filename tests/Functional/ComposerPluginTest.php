<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Functional;

use Composer\Composer;
use Composer\Config;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Factory;
use Composer\IO\BufferIO;
use Composer\IO\IOInterface;
use Composer\Package\RootPackage;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Util\ProcessExecutor;
use FilesystemIterator;
use LogicException;
use MSSTC4PHP\DtoCollectionGenerator\ComposerPlugin;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The plugin runs this repository's bin/dto-collection-generator, as a project runs its vendor/bin proxy.
 */
final class ComposerPluginTest extends TestCase
{
    private const CONFIG = "version: 1\ntarget: {php: '8.2'}\ncollections:\n  - {class: App\\Collection\\IntegerList, variant: immutable, elementType: int}\npaths: {'App\\Collection\\': out}\n";

    private string $root;

    private string $cwd;

    protected function setUp(): void
    {
        $this->cwd = (string) getcwd();
        $this->root = sys_get_temp_dir() . '/dto-collection-generator-plugin-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/gen', 0o777, true);
        file_put_contents($this->root . '/gen/dto-collection-generator.yaml', self::CONFIG);
        chdir($this->root);
    }

    protected function tearDown(): void
    {
        chdir($this->cwd);
        putenv('COMPOSER');
        $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($entries as $entry) {
            assert($entry instanceof SplFileInfo);
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }

        rmdir($this->root);
    }

    public function testListensToTheDumpOfTheAutoloader(): void
    {
        self::assertSame([ScriptEvents::POST_AUTOLOAD_DUMP => 'onPostAutoloadDump'], ComposerPlugin::getSubscribedEvents());
    }

    public function testDoesNothingWithoutItsExtra(): void
    {
        self::assertSame('', $this->dump([]));
        self::assertDirectoryDoesNotExist($this->root . '/gen/out');
    }

    public function testGeneratesFromAConfigRelativeToTheProject(): void
    {
        self::assertSame("dto-collection-generator: Written: 1, deleted: 0, unchanged: 0.\n", $this->dump(['dto-collection-generator' => ['config' => 'gen/dto-collection-generator.yaml']]));
        self::assertFileExists($this->root . '/gen/out/IntegerList.php');
        self::assertSame("dto-collection-generator: Written: 0, deleted: 0, unchanged: 1.\n", $this->dump(['dto-collection-generator' => ['config' => 'gen/dto-collection-generator.yaml']]));
    }

    public function testTakesTheProjectFromTheComposerVariable(): void
    {
        mkdir($this->root . '/sub');
        file_put_contents($this->root . '/sub/dto-collection-generator.yaml', self::CONFIG);
        putenv('COMPOSER=sub/composer.json');

        $this->dump(['dto-collection-generator' => ['config' => 'dto-collection-generator.yaml']]);

        self::assertFileExists($this->root . '/sub/out/IntegerList.php');
    }

    public function testPassesAbsoluteConfigPathsAsTheyAre(): void
    {
        $this->dump(['dto-collection-generator' => ['config' => $this->root . '/gen/dto-collection-generator.yaml']]);
        self::assertFileExists($this->root . '/gen/out/IntegerList.php');

        self::assertStringStartsWith('dto-collection-generator: error C:/nowhere/dto.yaml: ', $this->dump(['dto-collection-generator' => ['config' => 'C:\nowhere\dto.yaml']]));
    }

    public function testWarnsAboutFailuresUnlessToldToFail(): void
    {
        file_put_contents($this->root . '/gen/dto-collection-generator.yaml', str_replace('App\\Collection\\IntegerList', 'Elsewhere\\IntegerList', self::CONFIG));

        $output = $this->dump(['dto-collection-generator' => ['config' => 'gen/dto-collection-generator.yaml']]);

        self::assertStringContainsString('dto-collection-generator: error gen/dto-collection-generator.yaml#/collections/0: Elsewhere\IntegerList: no namespace prefix', $output);
        self::assertStringEndsWith("dto-collection-generator: Generation failed: 1 error(s).\ndto-collection-generator: the generator exited with code 2.\n", $output);
        self::assertDirectoryDoesNotExist($this->root . '/gen/out');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('dto-collection-generator: the generator exited with code 2.');
        $this->dump(['dto-collection-generator' => ['config' => 'gen/dto-collection-generator.yaml', 'failOnError' => true]]);
    }

    public function testSkipsAGeneratorRemovedByTheSameCommand(): void
    {
        // composer install --no-dev removes a require-dev generator after Composer loaded its plugin.
        $extra = ['dto-collection-generator' => ['config' => 'gen/dto-collection-generator.yaml']];
        $skipped = "dto-collection-generator: skipped, the generator is no longer installed.\n";

        self::assertSame('', $this->dump($extra, $this->root . '/gone-bin'));
        $verbose = new BufferIO('', OutputInterface::VERBOSITY_VERBOSE);
        $this->runPlugin($extra, $verbose, $this->root . '/gone-bin');
        self::assertSame($skipped, $verbose->getOutput());
        // A project that wants generation to fail loudly also hears when it did not run.
        self::assertSame($skipped, $this->dump(['dto-collection-generator' => ['config' => 'gen/dto-collection-generator.yaml', 'failOnError' => true]], $this->root . '/gone-bin'));
        self::assertDirectoryDoesNotExist($this->root . '/gen/out');
    }

    public function testWarnsAboutAGeneratorThatCannotFinishUnlessToldToFail(): void
    {
        mkdir($this->root . '/slow-bin');
        file_put_contents($this->root . '/slow-bin/dto-collection-generator', "<?php\nsleep(5);\n");
        $timeout = ProcessExecutor::getTimeout();
        ProcessExecutor::setTimeout(1);

        try {
            $output = $this->dump(['dto-collection-generator' => ['config' => 'gen/dto-collection-generator.yaml']], $this->root . '/slow-bin');
            self::assertStringStartsWith('dto-collection-generator: the generator could not run: ', $output);
            self::assertStringContainsString('exceeded the timeout of 1 seconds', $output);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('dto-collection-generator: the generator could not run: ');
            $this->dump(['dto-collection-generator' => ['config' => 'gen/dto-collection-generator.yaml', 'failOnError' => true]], $this->root . '/slow-bin');
        } finally {
            ProcessExecutor::setTimeout($timeout);
        }
    }

    public function testShowsTheOutputOfAFailureAsErrorsWhenItFailsTheCommand(): void
    {
        $io = $this->io(true);
        try {
            $this->runPlugin(['dto-collection-generator' => ['config' => 'missing.yaml', 'failOnError' => true]], $io);
            self::fail('The failure did not fail the command.');
        } catch (RuntimeException) {
            self::assertStringStartsWith("\033[37;41mdto-collection-generator: error missing.yaml: ", $io->getOutput());
        }

        $io = $this->io(true);
        $this->runPlugin(['dto-collection-generator' => ['config' => 'missing.yaml']], $io);
        self::assertStringStartsWith("\033[30;43mdto-collection-generator: error missing.yaml: ", $io->getOutput());
    }

    public function testShowsTheWarningsOfASuccessfulRunAsWarningsEvenWhenFailing(): void
    {
        file_put_contents($this->root . '/gen/dto-collection-generator.yaml', self::CONFIG . "sources: [missing]\n");
        $io = $this->io(true);

        $this->runPlugin(['dto-collection-generator' => ['config' => 'gen/dto-collection-generator.yaml', 'failOnError' => true]], $io);

        self::assertStringStartsWith("\033[30;43mdto-collection-generator: warning ", $io->getOutput());
    }

    public function testKeepsAngleBracketsOfTheOutputAsText(): void
    {
        self::assertStringContainsString('missing<b>.yaml', $this->dump(['dto-collection-generator' => ['config' => 'missing<b>.yaml']]));
    }

    public function testShowsTheWarningsOfASuccessfulRun(): void
    {
        file_put_contents($this->root . '/gen/dto-collection-generator.yaml', self::CONFIG . "sources: [missing]\n");

        $output = $this->dump(['dto-collection-generator' => ['config' => 'gen/dto-collection-generator.yaml']]);

        self::assertStringStartsWith('dto-collection-generator: warning gen/dto-collection-generator.yaml#/sources/0: Directory "', $output);
        self::assertStringEndsWith("dto-collection-generator: Written: 1, deleted: 0, unchanged: 0.\n", $output);
    }

    public function testReportsSettingsItCannotUse(): void
    {
        self::assertSame(
            "dto-collection-generator: extra.dto-collection-generator must be an object with \"config\", the path of the config file.\n",
            $this->dump(['dto-collection-generator' => 'gen/dto-collection-generator.yaml']),
        );
        self::assertSame(
            "dto-collection-generator: extra.dto-collection-generator must be an object with \"config\", the path of the config file.\n",
            $this->dump(['dto-collection-generator' => ['gen/dto-collection-generator.yaml']]),
        );
        self::assertSame(
            "dto-collection-generator: extra.dto-collection-generator has no \"config\", so nothing is generated.\n",
            $this->dump(['dto-collection-generator' => []]),
        );
        self::assertSame(
            "dto-collection-generator: extra.dto-collection-generator.config must be the path of the config file.\n",
            $this->dump(['dto-collection-generator' => ['config' => '']]),
        );
        self::assertSame(
            "dto-collection-generator: extra.dto-collection-generator.failOnError must be true or false.\n",
            $this->dump(['dto-collection-generator' => ['config' => 'gen/dto-collection-generator.yaml', 'failOnError' => 'yes']]),
        );
        self::assertDirectoryDoesNotExist($this->root . '/gen/out');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('dto-collection-generator: extra.dto-collection-generator has no "config", so nothing is generated.');
        $this->dump(['dto-collection-generator' => ['failOnError' => true]]);
    }

    public function testDoesNothingWhenComposerRunsWithoutScripts(): void
    {
        $composer = $this->composer(['dto-collection-generator' => ['config' => 'gen/dto-collection-generator.yaml']]);
        $io = $this->io(false);
        $composer->setEventDispatcher((new EventDispatcher($composer, $io))->setRunScripts(false));

        (new ComposerPlugin())->onPostAutoloadDump(new Event(ScriptEvents::POST_AUTOLOAD_DUMP, $composer, $io));

        self::assertSame('', $io->getOutput());
        self::assertDirectoryDoesNotExist($this->root . '/gen/out');
    }

    public function testRunsWhenTheScriptsFlagCannotBeRead(): void
    {
        $composer = $this->composer(['dto-collection-generator' => ['config' => 'gen/dto-collection-generator.yaml']]);
        $io = $this->io(false);
        $composer->setEventDispatcher(new class($composer, $io) extends EventDispatcher {
            public function __construct(Composer $composer, IOInterface $io)
            {
                parent::__construct($composer, $io);
                unset($this->runScripts);
            }

            /**
             * @return never
             */
            public function __get(string $name): mixed
            {
                throw new LogicException('No ' . $name . ' in this Composer.');
            }
        });

        (new ComposerPlugin())->onPostAutoloadDump(new Event(ScriptEvents::POST_AUTOLOAD_DUMP, $composer, $io));

        self::assertFileExists($this->root . '/gen/out/IntegerList.php');
    }

    /**
     * @param array<string, array<array-key, bool|int|string>|string> $extra
     */
    private function dump(array $extra, ?string $binDir = null): string
    {
        $io = $this->io(false);
        $this->runPlugin($extra, $io, $binDir);

        return $io->getOutput();
    }

    private function io(bool $decorated): BufferIO
    {
        // Composer's own styles, "warning" among them, as its console application registers them.
        return new BufferIO('', OutputInterface::VERBOSITY_NORMAL, new OutputFormatter($decorated, Factory::createAdditionalStyles()));
    }

    /**
     * @param array<string, array<array-key, bool|int|string>|string> $extra
     */
    private function composer(array $extra, ?string $binDir = null): Composer
    {
        $composer = new Composer();
        $package = new RootPackage('acme/app', '1.0.0.0', '1.0.0');
        $package->setExtra($extra);

        $composer->setPackage($package);
        $config = new Config(false, $this->root);
        $config->merge(['config' => ['bin-dir' => $binDir ?? dirname(__DIR__, 2) . '/bin']]);

        $composer->setConfig($config);

        return $composer;
    }

    /**
     * @param array<string, array<array-key, bool|int|string>|string> $extra
     */
    private function runPlugin(array $extra, BufferIO $io, ?string $binDir = null): void
    {
        $composer = $this->composer($extra, $binDir);
        $composer->setEventDispatcher(new EventDispatcher($composer, $io));

        $plugin = new ComposerPlugin();
        $plugin->activate($composer, $io);

        try {
            $plugin->onPostAutoloadDump(new Event(ScriptEvents::POST_AUTOLOAD_DUMP, $composer, $io));
        } finally {
            $plugin->deactivate($composer, $io);
            $plugin->uninstall($composer, $io);
        }
    }
}
