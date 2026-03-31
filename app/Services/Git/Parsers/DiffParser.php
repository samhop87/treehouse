<?php

namespace App\Services\Git\Parsers;

use App\DTOs\DiffFile;
use App\DTOs\DiffHunk;
use App\DTOs\DiffLine;

/**
 * Parses unified diff output from `git diff`.
 *
 * Handles:
 * - Standard modified files
 * - New files (--- /dev/null)
 * - Deleted files (+++ /dev/null)
 * - Renamed files (diff --git a/old b/new with rename from/to)
 * - Binary files
 * - Multiple hunks per file
 * - Line number tracking
 */
class DiffParser
{
    /**
     * Parse unified diff output into DiffFile DTOs.
     *
     * @return list<DiffFile>
     */
    public function parse(string $output): array
    {
        if (trim($output) === '') {
            return [];
        }

        // Split into per-file chunks by "diff --git" headers
        $chunks = preg_split('/^(?=diff --git )/m', $output);
        $files = [];

        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '' || ! str_starts_with($chunk, 'diff --git ')) {
                continue;
            }

            $file = $this->parseFileChunk($chunk);
            if ($file !== null) {
                $files[] = $file;
            }
        }

        return $files;
    }

    private function parseFileChunk(string $chunk): ?DiffFile
    {
        $lines = explode("\n", $chunk);

        // Parse the "diff --git a/path b/path" header
        $header = $lines[0];
        if (! preg_match('#^diff --git a/(.*?) b/(.*)$#', $header, $m)) {
            return null;
        }

        $oldPath = $m[1];
        $newPath = $m[2];
        $status = 'modified';
        $isBinary = false;
        $renamedFrom = null;
        $hunks = [];

        $i = 1;
        $lineCount = count($lines);

        // Parse metadata lines between the diff header and the first hunk
        while ($i < $lineCount) {
            $line = $lines[$i];

            if (str_starts_with($line, '@@')) {
                // Start of first hunk
                break;
            }

            if (str_starts_with($line, 'new file mode')) {
                $status = 'added';
            } elseif (str_starts_with($line, 'deleted file mode')) {
                $status = 'deleted';
            } elseif (str_starts_with($line, 'rename from ')) {
                $renamedFrom = substr($line, strlen('rename from '));
                $status = 'renamed';
            } elseif (str_starts_with($line, 'similarity index') || str_starts_with($line, 'rename to ')) {
                // Part of rename metadata, skip
            } elseif (str_starts_with($line, 'copy from ')) {
                $renamedFrom = substr($line, strlen('copy from '));
                $status = 'copied';
            } elseif (str_starts_with($line, 'Binary files')) {
                $isBinary = true;
            } elseif ($line === '--- /dev/null') {
                if ($status === 'modified') {
                    $status = 'added';
                }
            } elseif ($line === '+++ /dev/null') {
                if ($status === 'modified') {
                    $status = 'deleted';
                }
            }

            $i++;
        }

        // Parse hunks
        while ($i < $lineCount) {
            if (str_starts_with($lines[$i], '@@')) {
                $hunk = $this->parseHunk($lines, $i);
                if ($hunk !== null) {
                    $hunks[] = $hunk;
                }
            } else {
                $i++;
            }
        }

        return new DiffFile(
            path: $newPath,
            status: $status,
            oldPath: $renamedFrom,
            isBinary: $isBinary,
            hunks: $hunks,
        );
    }

    /**
     * Parse a single hunk starting at the @@ line.
     * Advances $index past the end of the hunk.
     */
    private function parseHunk(array $lines, int &$index): ?DiffHunk
    {
        $headerLine = $lines[$index];

        // Parse @@ -oldStart,oldCount +newStart,newCount @@ optional context
        if (! preg_match('/^@@ -(\d+)(?:,(\d+))? \+(\d+)(?:,(\d+))? @@/', $headerLine, $m)) {
            $index++;

            return null;
        }

        $oldStart = (int) $m[1];
        $oldCount = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 1;
        $newStart = (int) $m[3];
        $newCount = isset($m[4]) && $m[4] !== '' ? (int) $m[4] : 1;

        $index++;
        $diffLines = [];
        $oldLine = $oldStart;
        $newLine = $newStart;

        while ($index < count($lines)) {
            $line = $lines[$index];

            // Next hunk or end of file
            if (str_starts_with($line, '@@') || str_starts_with($line, 'diff --git ')) {
                break;
            }

            if (str_starts_with($line, '+')) {
                $diffLines[] = new DiffLine(
                    type: 'add',
                    content: substr($line, 1),
                    oldLine: null,
                    newLine: $newLine,
                );
                $newLine++;
            } elseif (str_starts_with($line, '-')) {
                $diffLines[] = new DiffLine(
                    type: 'remove',
                    content: substr($line, 1),
                    oldLine: $oldLine,
                    newLine: null,
                );
                $oldLine++;
            } elseif (str_starts_with($line, ' ') || $line === '') {
                // Context line (or empty context line)
                $content = $line !== '' ? substr($line, 1) : '';
                $diffLines[] = new DiffLine(
                    type: 'context',
                    content: $content,
                    oldLine: $oldLine,
                    newLine: $newLine,
                );
                $oldLine++;
                $newLine++;
            } elseif (str_starts_with($line, '\\')) {
                // "\ No newline at end of file" — skip, not a real diff line
                $index++;
                continue;
            }

            $index++;
        }

        return new DiffHunk(
            oldStart: $oldStart,
            oldCount: $oldCount,
            newStart: $newStart,
            newCount: $newCount,
            header: $headerLine,
            lines: $diffLines,
        );
    }
}
