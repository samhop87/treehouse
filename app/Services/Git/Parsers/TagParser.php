<?php

namespace App\Services\Git\Parsers;

use App\DTOs\Tag;
use Carbon\CarbonImmutable;

/**
 * Parses output of `git tag -l --format='%(refname:short)|%(objectname:short)|%(*objectname:short)|%(objecttype)|%(creatordate:iso-strict)|%(subject)'`.
 *
 * Fields (pipe-delimited):
 *   %(refname:short)          - tag name
 *   %(objectname:short)       - object hash (tag object for annotated, commit for lightweight)
 *   %(*objectname:short)      - dereferenced commit hash (only for annotated tags, empty for lightweight)
 *   %(objecttype)             - "commit" for lightweight, "tag" for annotated
 *   %(creatordate:iso-strict) - creation date
 *   %(subject)                - tag message (annotated) or commit subject (lightweight)
 */
class TagParser
{
    /**
     * Parse tag listing output into Tag DTOs.
     *
     * @return list<Tag>
     */
    public function parse(string $output): array
    {
        $output = trim($output);

        if ($output === '') {
            return [];
        }

        $lines = explode("\n", $output);
        $tags = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $tag = $this->parseLine($line);
            if ($tag !== null) {
                $tags[] = $tag;
            }
        }

        return $tags;
    }

    private function parseLine(string $line): ?Tag
    {
        // Split on pipe, limit to 6 (subject may contain pipes)
        $parts = explode('|', $line, 6);

        if (count($parts) < 6) {
            return null;
        }

        [$name, $hash, $targetHash, $objectType, $dateStr, $message] = $parts;

        $isAnnotated = $objectType === 'tag';

        return new Tag(
            name: $name,
            hash: $hash,
            targetHash: $targetHash !== '' ? $targetHash : null,
            isAnnotated: $isAnnotated,
            date: $dateStr !== '' ? CarbonImmutable::parse($dateStr) : null,
            message: $message !== '' ? $message : null,
        );
    }
}
