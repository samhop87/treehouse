<?php

namespace App\DTOs;

use Carbon\CarbonImmutable;

/**
 * Represents a git tag (lightweight or annotated).
 */
final readonly class Tag
{
    /**
     * @param string  $name           Tag name
     * @param string  $hash           Object hash the tag points to
     * @param string|null $targetHash For annotated tags, the hash of the tagged commit
     * @param bool    $isAnnotated    Whether this is an annotated tag
     * @param CarbonImmutable|null $date  Creator date
     * @param string|null $message    Tag message/subject (annotated tags only)
     */
    public function __construct(
        public string $name,
        public string $hash,
        public ?string $targetHash = null,
        public bool $isAnnotated = false,
        public ?CarbonImmutable $date = null,
        public ?string $message = null,
    ) {}

    /**
     * Get the commit hash this tag ultimately points to.
     * For annotated tags this is the dereferenced target; for lightweight it's the hash itself.
     */
    public function commitHash(): string
    {
        return $this->targetHash ?? $this->hash;
    }
}
