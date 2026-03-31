<?php

namespace App\DTOs;

/**
 * Represents a git stash entry.
 */
final readonly class StashEntry
{
    /**
     * @param string $ref      Stash reference (e.g., "stash@{0}")
     * @param string $hash     Full commit hash of the stash
     * @param string $message  Stash message/description
     */
    public function __construct(
        public string $ref,
        public string $hash,
        public string $message,
    ) {}

    /**
     * Extract the stash index number from the ref.
     */
    public function index(): int
    {
        if (preg_match('/\{(\d+)\}/', $this->ref, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }
}
