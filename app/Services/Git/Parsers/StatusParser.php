<?php

namespace App\Services\Git\Parsers;

use App\DTOs\FileStatus;
use App\DTOs\RepoState;

/**
 * Parses output of `git status --porcelain=v2 --branch`, including the
 * NUL-delimited form used by GitCommandRunner.
 *
 * Format reference:
 *   Header lines:  # branch.oid <hash>
 *                  # branch.head <name>
 *                  # branch.upstream <name>
 *                  # branch.ab +<ahead> -<behind>
 *
 *   Tracked entries (ordinary): 1 <XY> <sub> <mH> <mI> <mW> <hH> <hI> <path>
 *   Tracked entries (renamed):  2 <XY> <sub> <mH> <mI> <mW> <hH> <hI> <X><score> <path><tab><origPath>
 *   Unmerged entries:           u <XY> <sub> <m1> <m2> <m3> <mW> <h1> <h2> <h3> <path>
 *   Untracked entries:          ? <path>
 */
class StatusParser
{
    /**
     * Parse the full porcelain v2 output into a RepoState.
     */
    public function parse(string $output): RepoState
    {
        $nulDelimited = str_contains($output, "\0");
        $lines = $nulDelimited
            ? explode("\0", rtrim($output, "\0"))
            : explode("\n", rtrim($output, "\n"));

        $headHash = '';
        $branch = '';
        $upstream = null;
        $ahead = 0;
        $behind = 0;
        $isDetached = false;
        $files = [];

        for ($index = 0; $index < count($lines); $index++) {
            $line = $lines[$index];
            if ($line === '') {
                continue;
            }

            if (str_starts_with($line, '# ')) {
                $this->parseBranchHeader($line, $headHash, $branch, $upstream, $ahead, $behind, $isDetached);
            } elseif (str_starts_with($line, '1 ')) {
                $files[] = $this->parseOrdinaryEntry($line);
            } elseif (str_starts_with($line, '2 ')) {
                $origPath = $nulDelimited ? ($lines[++$index] ?? null) : null;
                $files[] = $this->parseRenamedEntry($line, $origPath);
            } elseif (str_starts_with($line, 'u ')) {
                $files[] = $this->parseUnmergedEntry($line);
            } elseif (str_starts_with($line, '? ')) {
                $files[] = $this->parseUntrackedEntry($line);
            }
        }

        return new RepoState(
            headHash: $headHash,
            branch: $branch,
            upstream: $upstream,
            ahead: $ahead,
            behind: $behind,
            isDetached: $isDetached,
            files: $files,
        );
    }

    private function parseBranchHeader(
        string $line,
        string &$headHash,
        string &$branch,
        ?string &$upstream,
        int &$ahead,
        int &$behind,
        bool &$isDetached,
    ): void {
        if (str_starts_with($line, '# branch.oid ')) {
            $headHash = substr($line, strlen('# branch.oid '));
        } elseif (str_starts_with($line, '# branch.head ')) {
            $branch = substr($line, strlen('# branch.head '));
            if ($branch === '(detached)') {
                $isDetached = true;
            }
        } elseif (str_starts_with($line, '# branch.upstream ')) {
            $upstream = substr($line, strlen('# branch.upstream '));
        } elseif (str_starts_with($line, '# branch.ab ')) {
            $ab = substr($line, strlen('# branch.ab '));
            if (preg_match('/\+(\d+) -(\d+)/', $ab, $matches)) {
                $ahead = (int) $matches[1];
                $behind = (int) $matches[2];
            }
        }
    }

    /**
     * Parse an ordinary changed entry: 1 <XY> <sub> <mH> <mI> <mW> <hH> <hI> <path>
     */
    private function parseOrdinaryEntry(string $line): FileStatus
    {
        // Format: 1 XY N... <mH> <mI> <mW> <hH> <hI> <path>
        // XY is at position 2-3 (0-indexed: chars 2 and 3)
        $parts = explode(' ', $line, 9);

        $xy = $parts[1]; // e.g., "M.", ".M", "AM"
        $path = $parts[8];

        return new FileStatus(
            path: $path,
            indexStatus: $xy[0] === '.' ? ' ' : $xy[0],
            workStatus: $xy[1] === '.' ? ' ' : $xy[1],
        );
    }

    /**
     * Parse a renamed/copied entry: 2 <XY> <sub> <mH> <mI> <mW> <hH> <hI> <X><score> <path>\t<origPath>
     */
    private function parseRenamedEntry(string $line, ?string $nulOrigPath = null): FileStatus
    {
        $parts = explode(' ', $line, 10);

        $xy = $parts[1];
        $pathPart = $parts[9]; // "newpath\toldpath"
        $paths = $nulOrigPath === null
            ? explode("\t", $pathPart, 2)
            : [$pathPart, $nulOrigPath];

        return new FileStatus(
            path: $paths[0],
            indexStatus: $xy[0] === '.' ? ' ' : $xy[0],
            workStatus: $xy[1] === '.' ? ' ' : $xy[1],
            origPath: $paths[1] ?? null,
        );
    }

    /**
     * Parse an unmerged entry: u <XY> <sub> <m1> <m2> <m3> <mW> <h1> <h2> <h3> <path>
     */
    private function parseUnmergedEntry(string $line): FileStatus
    {
        $parts = explode(' ', $line, 11);

        $path = $parts[10];

        return new FileStatus(
            path: $path,
            indexStatus: 'u',
            workStatus: 'u',
        );
    }

    /**
     * Parse an untracked entry: ? <path>
     */
    private function parseUntrackedEntry(string $line): FileStatus
    {
        $path = substr($line, 2);

        return new FileStatus(
            path: $path,
            indexStatus: '?',
            workStatus: '?',
        );
    }
}
