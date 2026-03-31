<?php

namespace App\Services\Git\Parsers;

use App\DTOs\Branch;

/**
 * Parses output of `git branch -a --format='%(refname:short)|%(objectname:short)|%(HEAD)|%(upstream:short)|%(upstream:track)'`.
 *
 * Fields (pipe-delimited):
 *   %(refname:short)     - branch name (e.g., "main", "origin/main")
 *   %(objectname:short)  - abbreviated commit hash
 *   %(HEAD)              - "*" if current branch, " " otherwise
 *   %(upstream:short)    - upstream branch name (empty if none)
 *   %(upstream:track)    - tracking info like "[ahead 2, behind 1]" (empty if no upstream)
 */
class BranchParser
{
    /**
     * Parse branch listing output into Branch DTOs.
     *
     * @return list<Branch>
     */
    public function parse(string $output): array
    {
        $output = trim($output);

        if ($output === '') {
            return [];
        }

        $lines = explode("\n", $output);
        $branches = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $branch = $this->parseLine($line);
            if ($branch !== null) {
                $branches[] = $branch;
            }
        }

        return $branches;
    }

    private function parseLine(string $line): ?Branch
    {
        $parts = explode('|', $line, 5);

        if (count($parts) < 5) {
            return null;
        }

        [$name, $hash, $headMarker, $upstream, $trackInfo] = $parts;

        // Skip the bare remote HEAD pointer (e.g., "origin" pointing to "origin/main")
        // These show up as "origin" with the same hash as "origin/main"
        // We can identify them because they have no upstream and aren't marked as HEAD
        $isRemote = str_contains($name, '/');

        // Parse ahead/behind from tracking info like "[ahead 2, behind 1]" or "[ahead 3]"
        $ahead = null;
        $behind = null;
        if ($trackInfo !== '') {
            if (preg_match('/ahead (\d+)/', $trackInfo, $m)) {
                $ahead = (int) $m[1];
            }
            if (preg_match('/behind (\d+)/', $trackInfo, $m)) {
                $behind = (int) $m[1];
            }
        }

        return new Branch(
            name: $name,
            hash: $hash,
            isCurrent: $headMarker === '*',
            isRemote: $isRemote,
            upstream: $upstream !== '' ? $upstream : null,
            ahead: $ahead,
            behind: $behind,
        );
    }
}
