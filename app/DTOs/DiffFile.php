<?php

namespace App\DTOs;

/**
 * Represents a single file in a diff output.
 */
final readonly class DiffFile
{
    /**
     * @param string $path      File path (new path if renamed)
     * @param string $status    'added', 'deleted', 'modified', 'renamed', 'copied'
     * @param string|null $oldPath  Previous path if renamed/copied
     * @param bool   $isBinary  Whether the file is binary
     * @param list<DiffHunk> $hunks  The diff hunks for this file
     */
    public function __construct(
        public string $path,
        public string $status,
        public ?string $oldPath = null,
        public bool $isBinary = false,
        public array $hunks = [],
    ) {}

    /**
     * Total number of added lines across all hunks.
     */
    public function additions(): int
    {
        $count = 0;
        foreach ($this->hunks as $hunk) {
            foreach ($hunk->lines as $line) {
                if ($line->type === 'add') {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Total number of removed lines across all hunks.
     */
    public function deletions(): int
    {
        $count = 0;
        foreach ($this->hunks as $hunk) {
            foreach ($hunk->lines as $line) {
                if ($line->type === 'remove') {
                    $count++;
                }
            }
        }

        return $count;
    }
}
