<?php

namespace App\Services\Git\Parsers;

use App\DTOs\StashEntry;

/**
 * Parses output of `git stash list --format='%gd|%H|%gs'`.
 *
 * Fields (pipe-delimited):
 *   %gd - stash ref (e.g., "stash@{0}")
 *   %H  - full commit hash
 *   %gs - stash message
 */
class StashParser
{
    /**
     * Parse stash list output into StashEntry DTOs.
     *
     * @return list<StashEntry>
     */
    public function parse(string $output): array
    {
        $output = trim($output);

        if ($output === '') {
            return [];
        }

        $lines = explode("\n", $output);
        $entries = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $entry = $this->parseLine($line);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    private function parseLine(string $line): ?StashEntry
    {
        // Split on pipe, limit to 3 (message may contain pipes)
        $parts = explode('|', $line, 3);

        if (count($parts) < 3) {
            return null;
        }

        [$ref, $hash, $message] = $parts;

        return new StashEntry(
            ref: $ref,
            hash: $hash,
            message: $message,
        );
    }
}
