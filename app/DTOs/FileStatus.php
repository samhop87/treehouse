<?php

namespace App\DTOs;

/**
 * Represents the status of a single file in the working tree.
 *
 * Based on `git status --porcelain=v2` output format.
 */
final readonly class FileStatus
{
    /**
     * @param string $path         File path relative to repo root
     * @param string $indexStatus  Status in the index (staging area): M, A, D, R, C, ?, !,  (space = unchanged)
     * @param string $workStatus   Status in the working tree: M, D, ?, !, (space = unchanged)
     * @param string|null $origPath Original path if renamed/copied
     */
    public function __construct(
        public string $path,
        public string $indexStatus,
        public string $workStatus,
        public ?string $origPath = null,
    ) {}

    /**
     * Is this file untracked?
     */
    public function isUntracked(): bool
    {
        return $this->indexStatus === '?' && $this->workStatus === '?';
    }

    /**
     * Is this file staged (has changes in index)?
     */
    public function isStaged(): bool
    {
        return $this->indexStatus !== ' ' && $this->indexStatus !== '?';
    }

    /**
     * Has this file been modified in the working tree?
     */
    public function isModified(): bool
    {
        return $this->workStatus === 'M' || $this->workStatus === 'D';
    }

    /**
     * Is this a rename?
     */
    public function isRenamed(): bool
    {
        return $this->indexStatus === 'R';
    }

    /**
     * Is this file in a conflicted (unmerged) state?
     */
    public function isConflicted(): bool
    {
        return $this->indexStatus === 'u';
    }

    /**
     * Human-readable description of the file's status.
     */
    public function label(): string
    {
        if ($this->isConflicted()) {
            return 'Conflicted';
        }
        if ($this->isUntracked()) {
            return 'Untracked';
        }
        if ($this->isRenamed()) {
            return 'Renamed';
        }

        return match ($this->indexStatus !== ' ' ? $this->indexStatus : $this->workStatus) {
            'M' => 'Modified',
            'A' => 'Added',
            'D' => 'Deleted',
            'C' => 'Copied',
            default => 'Changed',
        };
    }
}
