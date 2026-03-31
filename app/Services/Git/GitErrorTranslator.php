<?php

namespace App\Services\Git;

/**
 * Translates raw git stderr output into human-readable error messages.
 *
 * Each pattern is a regex matched against the full stderr string.
 * Patterns are checked in order; the first match wins.
 */
class GitErrorTranslator
{
    /**
     * @var list<array{pattern: string, message: string}>
     */
    private const PATTERNS = [
        [
            'pattern' => '/fatal: not a git repository/i',
            'message' => 'This directory is not a Git repository.',
        ],
        [
            'pattern' => '/fatal: repository \'.*?\' not found/i',
            'message' => 'The remote repository was not found. Check the URL and your access permissions.',
        ],
        [
            'pattern' => '/fatal: Authentication failed/i',
            'message' => 'Authentication failed. Your credentials may be expired or invalid.',
        ],
        [
            'pattern' => '/CONFLICT \(content\)|Automatic merge failed/i',
            'message' => 'Merge conflicts were found. Resolve them before continuing.',
        ],
        [
            'pattern' => '/error: Your local changes to the following files would be overwritten/i',
            'message' => 'You have uncommitted changes that would be overwritten. Commit or stash them first.',
        ],
        [
            'pattern' => '/error: pathspec \'(.*?)\' did not match any file/i',
            'message' => 'The file or path "$1" was not found in the repository.',
        ],
        [
            'pattern' => '/fatal: bad revision \'(.*?)\'/i',
            'message' => 'The revision "$1" does not exist.',
        ],
        [
            'pattern' => '/error: failed to push some refs/i',
            'message' => 'Push was rejected. The remote has changes you don\'t have locally. Pull first.',
        ],
        [
            'pattern' => '/fatal: refusing to merge unrelated histories/i',
            'message' => 'These branches have no common history. Use --allow-unrelated-histories if intentional.',
        ],
        [
            'pattern' => '/You are in the middle of a (merge|rebase|cherry-pick)/i',
            'message' => 'There is an unfinished $1 in progress. Complete or abort it first.',
        ],
        [
            'pattern' => '/HEAD detached at (.*)/i',
            'message' => 'You are in detached HEAD state at $1. Create a branch to keep your changes.',
        ],
        [
            'pattern' => '/fatal: destination path \'(.*?)\' already exists/i',
            'message' => 'The directory "$1" already exists. Choose a different location.',
        ],
        [
            'pattern' => '/Could not resolve hostname/i',
            'message' => 'Cannot reach the remote server. Check your internet connection.',
        ],
    ];

    /**
     * Translate a git error string to a human-readable message.
     * Returns the original error if no pattern matches.
     */
    public function translate(string $stderr): string
    {
        $stderr = trim($stderr);

        if ($stderr === '') {
            return 'An unknown git error occurred.';
        }

        foreach (self::PATTERNS as $entry) {
            if (preg_match($entry['pattern'], $stderr, $matches)) {
                $message = $entry['message'];

                // Replace $1, $2, etc. with captured groups
                foreach ($matches as $index => $match) {
                    if ($index === 0) {
                        continue;
                    }
                    $message = str_replace('$'.$index, $match, $message);
                }

                return $message;
            }
        }

        // No pattern matched — return cleaned-up stderr
        // Strip "fatal: " and "error: " prefixes for readability
        $cleaned = preg_replace('/^(fatal|error):\s*/im', '', $stderr);

        return ucfirst(trim($cleaned));
    }
}
