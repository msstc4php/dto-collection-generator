<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Tests\Support;

use MSSTC4PHP\DtoCollectionGenerator\Application\Generate\ChangeKind;
use MSSTC4PHP\DtoCollectionGenerator\Application\Generate\FileChange;
use MSSTC4PHP\DtoCollectionGenerator\Application\Generate\GeneratedFile;
use MSSTC4PHP\DtoCollectionGenerator\Application\Generate\WritePlan;
use MSSTC4PHP\DtoCollectionGenerator\Application\Port\FileWriter;
use MSSTC4PHP\DtoCollectionGenerator\Application\Port\WriteFailed;

/**
 * Plans every file with one kind of change and records what would be written.
 */
final class RecordingWriter implements FileWriter
{
    /** @var list<GeneratedFile> */
    public array $planned = [];

    public ?string $manifest = null;

    public int $applied = 0;

    public function __construct(private readonly ChangeKind $kind = ChangeKind::Create, private readonly bool $fails = false)
    {
    }

    public function plan(string $manifestPath, array $files): WritePlan
    {
        $this->manifest = $manifestPath;
        $this->planned = $files;

        return new WritePlan(array_map(
            fn (GeneratedFile $file): FileChange => new FileChange($this->kind, $file->path, $this->kind === ChangeKind::Conflict ? 'in the way' : null),
            $files,
        ), false);
    }

    public function apply(WritePlan $plan, string $manifestPath, array $files): void
    {
        if ($this->fails) {
            throw new WriteFailed('Cannot write /project/src/Collection/ItemList.php');
        }
        $this->applied++;
    }

    public function isGenerated(string $path): bool
    {
        return str_ends_with($path, 'Generated.php');
    }
}
