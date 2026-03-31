<?php

namespace App\DTOs;

/**
 * Represents a single line within a diff hunk.
 */
final readonly class DiffLine
{
    /**
     * @param string   $type    'add', 'remove', or 'context'
     * @param string   $content The line content (without the leading +/-/space)
     * @param int|null $oldLine Line number in the old file (null for added lines)
     * @param int|null $newLine Line number in the new file (null for removed lines)
     */
    public function __construct(
        public string $type,
        public string $content,
        public ?int $oldLine = null,
        public ?int $newLine = null,
    ) {}
}
