<?php

namespace App\DTOs;

/**
 * Represents a git branch (local or remote).
 */
final readonly class Branch
{
    /**
     * @param string  $name       Short ref name (e.g., "main", "origin/main")
     * @param string  $hash       Abbreviated commit hash the branch points to
     * @param bool    $isCurrent  Whether this is the currently checked-out branch
     * @param bool    $isRemote   Whether this is a remote-tracking branch
     * @param string|null $upstream  Upstream tracking branch (e.g., "origin/main")
     * @param int|null $ahead     Commits ahead of upstream
     * @param int|null $behind    Commits behind upstream
     */
    public function __construct(
        public string $name,
        public string $hash,
        public bool $isCurrent = false,
        public bool $isRemote = false,
        public ?string $upstream = null,
        public ?int $ahead = null,
        public ?int $behind = null,
    ) {}
}
