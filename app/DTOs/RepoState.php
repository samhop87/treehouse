<?php

namespace App\DTOs;

/**
 * Represents the overall state of a repository.
 * Parsed from `git status --porcelain=v2 --branch` header lines.
 */
final readonly class RepoState
{
    /**
     * @param string       $headHash     Current HEAD commit hash
     * @param string       $branch       Current branch name (or '(detached)' if detached HEAD)
     * @param string|null  $upstream     Upstream tracking branch
     * @param int          $ahead        Number of commits ahead of upstream
     * @param int          $behind       Number of commits behind upstream
     * @param bool         $isDetached   Whether HEAD is detached
     * @param list<FileStatus> $files    All file statuses
     */
    public function __construct(
        public string $headHash,
        public string $branch,
        public ?string $upstream = null,
        public int $ahead = 0,
        public int $behind = 0,
        public bool $isDetached = false,
        public array $files = [],
    ) {}

    /**
     * Files that are staged (in the index).
     *
     * @return list<FileStatus>
     */
    public function stagedFiles(): array
    {
        return array_values(array_filter($this->files, fn (FileStatus $f) => $f->isStaged()));
    }

    /**
     * Files modified in the working tree (unstaged changes).
     *
     * @return list<FileStatus>
     */
    public function unstagedFiles(): array
    {
        return array_values(array_filter($this->files, fn (FileStatus $f) => $f->isModified()));
    }

    /**
     * Untracked files.
     *
     * @return list<FileStatus>
     */
    public function untrackedFiles(): array
    {
        return array_values(array_filter($this->files, fn (FileStatus $f) => $f->isUntracked()));
    }

    /**
     * Conflicted (unmerged) files.
     *
     * @return list<FileStatus>
     */
    public function conflictedFiles(): array
    {
        return array_values(array_filter($this->files, fn (FileStatus $f) => $f->isConflicted()));
    }

    /**
     * Whether the working tree is clean (no changes at all).
     */
    public function isClean(): bool
    {
        return count($this->files) === 0;
    }

    /**
     * Whether there are merge conflicts.
     */
    public function hasConflicts(): bool
    {
        return count($this->conflictedFiles()) > 0;
    }
}
