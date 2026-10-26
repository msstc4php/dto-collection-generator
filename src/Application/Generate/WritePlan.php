<?php

declare(strict_types=1);

namespace MSSTC4PHP\DtoCollectionGenerator\Application\Generate;

final class WritePlan
{
    /**
     * @param list<FileChange> $changes
     * @param bool $manifestOutdated whether the manifest lists files other than the ones this run generates
     */
    public function __construct(public readonly array $changes, public readonly bool $manifestOutdated)
    {
    }

    public function isUpToDate(): bool
    {
        foreach ($this->changes as $change) {
            if ($change->kind !== ChangeKind::Unchanged) {
                return false;
            }
        }

        return !$this->manifestOutdated;
    }

    public function hasConflicts(): bool
    {
        return $this->count(ChangeKind::Conflict) > 0;
    }

    public function count(ChangeKind $kind): int
    {
        return count(array_filter($this->changes, static fn (FileChange $change): bool => $change->kind === $kind));
    }
}
