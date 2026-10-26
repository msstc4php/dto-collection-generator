<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Infrastructure\Document;

use JsonException;
use MSSTC4PHP\DtoCollectionGenerator\Application\Port\DocumentLoader;
use MSSTC4PHP\DtoCollectionGenerator\Application\Port\DocumentNotLoaded;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 */
final class FileDocumentLoader implements DocumentLoader
{
    public function load(string $path): array
    {
        if (!is_file($path)) {
            throw new DocumentNotLoaded($path . ' does not exist');
        }
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($extension, ['yaml', 'yml', 'json'], true)) {
            throw new DocumentNotLoaded($path . ' must be a .yaml, .yml or .json file');
        }
        $content = is_readable($path) ? file_get_contents($path) : false;
        if ($content === false) {
            throw new DocumentNotLoaded($path . ' cannot be read');
        }
        $decoded = $extension === 'json' ? $this->json($path, $content) : $this->yaml($path, $content);
        if (!is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
            throw new DocumentNotLoaded($path . ' must contain an object');
        }

        return $decoded;
    }

    private function json(string $path, string $content): mixed
    {
        try {
            return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new DocumentNotLoaded($path . ' is not valid JSON: ' . $exception->getMessage(), 0, $exception);
        }
    }

    private function yaml(string $path, string $content): mixed
    {
        try {
            return Yaml::parse($content, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
        } catch (ParseException $exception) {
            throw new DocumentNotLoaded($path . ' is not valid YAML: ' . $exception->getMessage(), 0, $exception);
        }
    }
}
