<?php

namespace App\DTOs;

/**
 * The independent Git reads needed to paint a repository tab.
 *
 * GitService creates this from one concurrent command pool so callers receive
 * a coherent UI payload without coordinating individual Git processes.
 */
final readonly class RepoSnapshot
{
    /**
     * @param  list<Commit>  $commits
     * @param  list<Branch>  $branches
     * @param  list<Tag>  $tags
     * @param  list<StashEntry>  $stashes
     */
    public function __construct(
        public RepoState $state,
        public array $commits,
        public array $branches,
        public array $tags,
        public array $stashes,
    ) {}
}
