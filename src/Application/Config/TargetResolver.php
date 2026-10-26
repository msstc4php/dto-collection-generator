<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Application\Config;

use MSSTC4PHP\DtoCollectionGenerator\Application\Port\ProjectPhpConstraint;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Diagnostic\Diagnostics;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Exception\UnsupportedPhpVersion;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Target\PhpConstraint;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Target\PhpVersion;
use MSSTC4PHP\DtoCollectionGenerator\Domain\Target\TargetProfile;

/**
 * @internal
 */
final class TargetResolver
{
    public function __construct(private readonly ProjectPhpConstraint $constraints)
    {
    }

    /**
     * @return TargetProfile|null null when composer.json allows a PHP that dto-collection does not run on
     */
    public function resolve(GeneratorConfig $config, Diagnostics $diagnostics): ?TargetProfile
    {
        $php = $config->php ?? $this->detect($config, $diagnostics);

        return $php instanceof PhpVersion ? new TargetProfile($php) : null;
    }

    private function detect(GeneratorConfig $config, Diagnostics $diagnostics): ?PhpVersion
    {
        $location = $config->location('/target/php');
        $oldest = PhpVersion::oldest();
        $requirement = $this->constraints->find($config->directory);
        if ($requirement->problem !== null) {
            $diagnostics->warning(sprintf('%s %s; generating for PHP %s.', $requirement->file, $requirement->problem, $oldest), $location);

            return $oldest;
        }
        $constraint = $requirement->constraint;
        if ($constraint === null) {
            $diagnostics->warning(sprintf('No composer.json names a PHP requirement; generating for PHP %s.', $oldest), $location);

            return $oldest;
        }
        $lowest = PhpConstraint::lowestMinor($constraint);
        if ($lowest === null) {
            $diagnostics->warning(sprintf('composer.json requires PHP "%s", which names no lower bound; generating for PHP %s.', $constraint, $oldest), $location);

            return $oldest;
        }

        try {
            return PhpVersion::fromString($lowest);
        } catch (UnsupportedPhpVersion) {
            if (version_compare($lowest, (string) $oldest, '<')) {
                $diagnostics->error(sprintf('composer.json requires PHP "%s", but dto-collection needs PHP %s or newer; set target.php or raise require.php.', $constraint, $oldest), $location);

                return null;
            }
            $newest = PhpVersion::newest();
            $diagnostics->warning(sprintf('composer.json requires PHP "%s"; generating for PHP %s, the newest known target.', $constraint, $newest), $location);

            return $newest;
        }
    }
}
