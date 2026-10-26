<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Application\Port;

use MSSTC4PHP\DtoCollectionGenerator\Application\Generate\GeneratedFile;
use MSSTC4PHP\DtoCollectionGenerator\Application\Generate\WritePlan;

interface FileWriter
{
    /**
     * Compares the files with the disk and with the manifest of the previous run; changes nothing.
     *
     * @param list<GeneratedFile> $files
     */
    public function plan(string $manifestPath, array $files): WritePlan;

    /**
     * @param list<GeneratedFile> $files the files the plan was made for
     *
     * @throws WriteFailed
     */
    public function apply(WritePlan $plan, string $manifestPath, array $files): void;
}
