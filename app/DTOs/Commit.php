<?php

namespace App\DTOs;

use Carbon\CarbonImmutable;

/**
 * Represents a single git commit.
 */
final readonly class Commit
{
    /**
     * @param string        $hash       Full SHA hash
     * @param string        $shortHash  Abbreviated hash
     * @param list<string>  $parents    Parent commit hashes (empty for root, 2+ for merge)
     * @param string        $author     Author name
     * @param string        $email      Author email
     * @param CarbonImmutable $date     Author date
     * @param string        $message    Subject line
     * @param list<string>  $refs       Ref decorations (branch names, tags, HEAD)
     */
    public function __construct(
        public string $hash,
        public string $shortHash,
        public array $parents,
        public string $author,
        public string $email,
        public CarbonImmutable $date,
        public string $message,
        public array $refs = [],
    ) {}

    /**
     * Is this a merge commit (2+ parents)?
     */
    public function isMerge(): bool
    {
        return count($this->parents) > 1;
    }

    /**
     * Is this a root commit (no parents)?
     */
    public function isRoot(): bool
    {
        return count($this->parents) === 0;
    }
}
