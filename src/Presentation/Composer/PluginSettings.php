<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Presentation\Composer;

use LogicException;

/**
 * `extra.dto-collection-generator` of the root package: the config to generate from, or what is wrong with it.
 *
 * @internal
 */
final class PluginSettings
{
    /**
     * @param non-empty-string|null $config
     * @param non-empty-string|null $problem
     */
    private function __construct(private readonly ?string $config, private readonly bool $failOnError, private readonly ?string $problem)
    {
    }

    /**
     * @param array<array-key, mixed> $extra the root package's extra, as Composer decodes it
     *
     * @return self|null null when the package does not set extra.dto-collection-generator
     */
    public static function fromExtra(array $extra): ?self
    {
        if (!array_key_exists('dto-collection-generator', $extra)) {
            return null;
        }

        $settings = $extra['dto-collection-generator'];
        if (!is_array($settings) || ($settings !== [] && array_values($settings) === $settings)) {
            return new self(null, false, 'extra.dto-collection-generator must be an object with "config", the path of the config file.');
        }

        $failOnError = $settings['failOnError'] ?? false;
        if (!is_bool($failOnError)) {
            return new self(null, false, 'extra.dto-collection-generator.failOnError must be true or false.');
        }

        if (!array_key_exists('config', $settings)) {
            return new self(null, $failOnError, 'extra.dto-collection-generator has no "config", so nothing is generated.');
        }

        $config = $settings['config'];
        if (!is_string($config) || $config === '') {
            return new self(null, $failOnError, 'extra.dto-collection-generator.config must be the path of the config file.');
        }

        return new self($config, $failOnError, null);
    }

    /**
     * @return non-empty-string
     *
     * @throws LogicException when the settings have a problem instead
     */
    public function config(): string
    {
        if ($this->config === null) {
            throw new LogicException('Settings with a problem have no config.');
        }

        return $this->config;
    }

    public function failOnError(): bool
    {
        return $this->failOnError;
    }

    /**
     * @return non-empty-string|null
     */
    public function problem(): ?string
    {
        return $this->problem;
    }
}
