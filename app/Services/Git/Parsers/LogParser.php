<?php

namespace App\Services\Git\Parsers;

use App\DTOs\Commit;
use Carbon\CarbonImmutable;

/**
 * Parses output of `git log --format='%H|%h|%P|%an|%ae|%aI|%D|%s'`.
 *
 * Fields (pipe-delimited):
 *   %H  - full hash
 *   %h  - short hash
 *   %P  - parent hashes (space-separated, empty for root)
 *   %an - author name
 *   %ae - author email
 *   %aI - author date (ISO 8601 strict)
 *   %D  - ref decorations (comma-separated, empty if none)
 *   %s  - subject line
 */
class LogParser
{
    /**
     * Parse git log output into an array of Commit DTOs.
     *
     * @return list<Commit>
     */
    public function parse(string $output): array
    {
        $output = trim($output);

        if ($output === '') {
            return [];
        }

        $lines = explode("\n", $output);
        $commits = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $commit = $this->parseLine($line);
            if ($commit !== null) {
                $commits[] = $commit;
            }
        }

        return $commits;
    }

    private function parseLine(string $line): ?Commit
    {
        // Split on pipe, limit to 8 parts (subject may contain pipes)
        $parts = explode('|', $line, 8);

        if (count($parts) < 8) {
            return null;
        }

        [$hash, $shortHash, $parentStr, $author, $email, $dateStr, $refsStr, $message] = $parts;

        // Parse parents: space-separated hashes, empty string for root commits
        $parents = $parentStr !== '' ? explode(' ', $parentStr) : [];

        // Parse refs: "HEAD -> main, origin/main, tag: v1.0" -> ["HEAD -> main", "origin/main", "tag: v1.0"]
        $refs = $refsStr !== '' ? array_map('trim', explode(',', $refsStr)) : [];

        return new Commit(
            hash: $hash,
            shortHash: $shortHash,
            parents: $parents,
            author: $author,
            email: $email,
            date: CarbonImmutable::parse($dateStr),
            message: $message,
            refs: $refs,
        );
    }
}
