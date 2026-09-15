<?php

namespace App\Services\Git\Parsers;

use App\DTOs\Commit;
use Carbon\CarbonImmutable;

/**
 * Parses NUL-delimited output from GitCommandRunner::log().
 *
 * Fields (NUL-delimited, nine per commit):
 *   %H  - full hash
 *   %h  - short hash
 *   %P  - parent hashes (space-separated, empty for root)
 *   %an - author name
 *   %ae - author email
 *   %aI - author date (ISO 8601 strict)
 *   %D  - ref decorations (comma-separated, empty if none)
 *   %s  - subject line
 *   %b  - body
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
        if ($output === '') {
            return [];
        }

        $fields = explode("\0", $output);

        if (end($fields) === '') {
            array_pop($fields);
        }

        $commits = [];

        foreach (array_chunk($fields, 9) as $record) {
            if (count($record) !== 9) {
                continue;
            }

            $commits[] = $this->parseRecord($record);
        }

        return $commits;
    }

    /**
     * @param  array{string, string, string, string, string, string, string, string, string}  $record
     */
    private function parseRecord(array $record): Commit
    {
        [$hash, $shortHash, $parentStr, $author, $email, $dateStr, $refsStr, $message, $description] = $record;

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
            description: trim($description),
            refs: $refs,
        );
    }
}
