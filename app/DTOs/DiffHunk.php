<?php

namespace App\DTOs;

/**
 * Represents a single hunk within a diff file.
 */
final readonly class DiffHunk
{
    /**
     * @param int    $oldStart  Starting line in the old file
     * @param int    $oldCount  Number of lines from the old file
     * @param int    $newStart  Starting line in the new file
     * @param int    $newCount  Number of lines from the new file
     * @param string $header    The full @@ header line (e.g., "@@ -1,5 +1,7 @@ function foo()")
     * @param list<DiffLine> $lines  The lines in this hunk
     */
    public function __construct(
        public int $oldStart,
        public int $oldCount,
        public int $newStart,
        public int $newCount,
        public string $header,
        public array $lines = [],
    ) {}
}
