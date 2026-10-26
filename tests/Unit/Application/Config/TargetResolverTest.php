<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Unit\Application\Config;

use MSSTC4PHP\DtoCollectionGenerator\Application\Config\GeneratorConfig;
use MSSTC4PHP\DtoCollectionGenerator\Application\Config\TargetResolver;
use MSSTC4PHP\DtoCollectionGenerator\Application\Port\PhpRequirement;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic\Diagnostic;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic\Diagnostics;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Target\PhpVersion;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Target\TargetProfile;
use MSSTC4PHP\DtoCollectionGenerator\Tests\Support\FixedPhpConstraint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TargetResolverTest extends TestCase
{
    public function testExplicitVersionDoesNotReadComposerJson(): void
    {
        $constraint = new FixedPhpConstraint(null);
        $diagnostics = new Diagnostics();

        $target = (new TargetResolver($constraint))->resolve($this->config(PhpVersion::fromString('8.3')), $diagnostics);

        self::assertSame('8.3', (string) $target?->php);
        self::assertSame(0, $constraint->calls);
        self::assertCount(0, $diagnostics);
    }

    /**
     * @return iterable<string, array{PhpRequirement, ?string, list<string>}>
     */
    public static function detections(): iterable
    {
        $file = '/project/composer.json';
        yield 'caret' => [PhpRequirement::found($file, '^8.2'), '8.2', []];
        yield 'patch level' => [PhpRequirement::found($file, '>=8.3.4'), '8.3', []];
        yield 'alternatives' => [PhpRequirement::found($file, '^8.4 || ^8.1'), '8.1', []];
        yield 'no composer.json' => [PhpRequirement::none(), '8.1', ['warning: No composer.json names a PHP requirement; generating for PHP 8.1.']];
        yield 'no php requirement' => [PhpRequirement::found($file, null), '8.1', ['warning: No composer.json names a PHP requirement; generating for PHP 8.1.']];
        yield 'no lower bound' => [PhpRequirement::found($file, '<9'), '8.1', ['warning: composer.json requires PHP "<9", which names no lower bound; generating for PHP 8.1.']];
        yield 'unusable file' => [PhpRequirement::unusable($file, 'is not valid JSON'), '8.1', ['warning: /project/composer.json is not valid JSON; generating for PHP 8.1.']];
        yield 'below dto-collection' => [PhpRequirement::found($file, '>=7.4'), null, ['error: composer.json requires PHP ">=7.4", but dto-collection needs PHP 8.1 or newer; set target.php or raise require.php.']];
        yield 'newer than known' => [PhpRequirement::found($file, '^8.6'), '8.5', ['warning: composer.json requires PHP "^8.6"; generating for PHP 8.5, the newest known target.']];
        yield 'next major' => [PhpRequirement::found($file, '^9.0'), '8.5', ['warning: composer.json requires PHP "^9.0"; generating for PHP 8.5, the newest known target.']];
    }

    /**
     * @param list<string> $messages
     */
    #[DataProvider('detections')]
    public function testAutoReadsComposerJson(PhpRequirement $requirement, ?string $expected, array $messages): void
    {
        $diagnostics = new Diagnostics();

        $target = (new TargetResolver(new FixedPhpConstraint($requirement)))->resolve($this->config(null), $diagnostics);

        self::assertSame($expected, $target instanceof TargetProfile ? (string) $target->php : null);
        self::assertSame($messages, array_map(
            static fn (Diagnostic $diagnostic): string => $diagnostic->severity->value . ': ' . $diagnostic->message,
            $diagnostics->all(),
        ));
        foreach ($diagnostics as $diagnostic) {
            self::assertSame('dto-collection-generator.yaml#/target/php', $diagnostic->location->render('/project'));
        }
    }

    private function config(?PhpVersion $php): GeneratorConfig
    {
        return new GeneratorConfig('/project/dto-collection-generator.yaml', '/project', $php, [], [], []);
    }
}
