<?php

namespace App\Services\Git;

use App\DTOs\Branch;
use App\DTOs\Commit;
use App\DTOs\DiffFile;
use App\DTOs\GitResult;
use App\DTOs\RepoState;
use App\DTOs\StashEntry;
use App\DTOs\Tag;
use App\Services\Git\Parsers\BranchParser;
use App\Services\Git\Parsers\DiffParser;
use App\Services\Git\Parsers\LogParser;
use App\Services\Git\Parsers\StashParser;
use App\Services\Git\Parsers\StatusParser;
use App\Services\Git\Parsers\TagParser;

/**
 * High-level Git API for the UI layer.
 *
 * Wraps GitCommandRunner + parsers into a clean interface that returns DTOs.
 * All methods throw RuntimeException on git failures (via GitResult::throw())
 * unless documented otherwise.
 *
 * Usage:
 *   $git = app(GitService::class);
 *   $git->open('/path/to/repo');
 *   $state = $git->getStatus();
 */
class GitService
{
    private ?string $repoPath = null;

    public function __construct(
        private readonly GitCommandRunner $commandRunner,
        private readonly StatusParser $statusParser,
        private readonly LogParser $logParser,
        private readonly BranchParser $branchParser,
        private readonly DiffParser $diffParser,
        private readonly TagParser $tagParser,
        private readonly StashParser $stashParser,
    ) {}

    /**
     * Set the repository to operate on.
     *
     * @throws \RuntimeException if the path is not a valid git repository
     */
    public function open(string $path): self
    {
        $this->repoPath = $path;
        $this->commandRunner->setRepoPath($path);

        if (! $this->commandRunner->isValidRepo()) {
            $this->repoPath = null;
            throw new \RuntimeException("Not a git repository: {$path}");
        }

        return $this;
    }

    /**
     * Get the current repo path.
     */
    public function getRepoPath(): ?string
    {
        return $this->repoPath;
    }

    /**
     * Check if a repo is currently open.
     */
    public function isOpen(): bool
    {
        return $this->repoPath !== null;
    }

    // ─── READ OPERATIONS ────────────────────────────────────────────────

    /**
     * Get the full repository status (branch info + file statuses).
     */
    public function getStatus(): RepoState
    {
        $this->ensureOpen();

        $result = $this->commandRunner->status();
        $result->throw('Failed to get repository status');

        return $this->statusParser->parse($result->output);
    }

    /**
     * Get the commit log.
     *
     * @param int $limit Maximum number of commits to return
     * @param bool $all Include all branches (--all flag)
     * @return list<Commit>
     */
    public function getLog(int $limit = 200, bool $all = true): array
    {
        $this->ensureOpen();

        $extraArgs = $all ? ['--all'] : [];
        $result = $this->commandRunner->log($limit, $extraArgs);
        $result->throw('Failed to get commit log');

        return $this->logParser->parse($result->output);
    }

    /**
     * Get all branches (local and remote).
     *
     * @return list<Branch>
     */
    public function getBranches(): array
    {
        $this->ensureOpen();

        $result = $this->commandRunner->branches();
        $result->throw('Failed to list branches');

        return $this->branchParser->parse($result->output);
    }

    /**
     * Get all tags.
     *
     * @return list<Tag>
     */
    public function getTags(): array
    {
        $this->ensureOpen();

        $result = $this->commandRunner->tags();
        $result->throw('Failed to list tags');

        return $this->tagParser->parse($result->output);
    }

    /**
     * Get all stash entries.
     *
     * @return list<StashEntry>
     */
    public function getStashes(): array
    {
        $this->ensureOpen();

        $result = $this->commandRunner->stashes();
        $result->throw('Failed to list stashes');

        return $this->stashParser->parse($result->output);
    }

    /**
     * Get the diff for staged changes.
     *
     * @param list<string> $paths Limit to specific file paths
     * @return list<DiffFile>
     */
    public function getStagedDiff(array $paths = []): array
    {
        $this->ensureOpen();

        $result = $this->commandRunner->diff(staged: true, paths: $paths);
        $result->throw('Failed to get staged diff');

        return $this->diffParser->parse($result->output);
    }

    /**
     * Get the diff for unstaged (working tree) changes.
     *
     * @param list<string> $paths Limit to specific file paths
     * @return list<DiffFile>
     */
    public function getUnstagedDiff(array $paths = []): array
    {
        $this->ensureOpen();

        $result = $this->commandRunner->diff(staged: false, paths: $paths);
        $result->throw('Failed to get unstaged diff');

        return $this->diffParser->parse($result->output);
    }

    /**
     * Get the diff for a specific commit.
     *
     * @return list<DiffFile>
     */
    public function getCommitDiff(string $commitHash): array
    {
        $this->ensureOpen();

        $result = $this->commandRunner->run(['diff', $commitHash . '^!'], timeout: 60);
        $result->throw("Failed to get diff for commit {$commitHash}");

        return $this->diffParser->parse($result->output);
    }

    /**
     * Show the diff for a single file between working tree and HEAD.
     * For untracked files, returns the file content as an "added" diff.
     *
     * @return list<DiffFile>
     */
    public function getFileDiff(string $path, bool $staged = false): array
    {
        $this->ensureOpen();

        if ($staged) {
            return $this->getStagedDiff([$path]);
        }

        return $this->getUnstagedDiff([$path]);
    }

    // ─── STAGING OPERATIONS ─────────────────────────────────────────────

    /**
     * Stage one or more files.
     *
     * @param list<string> $paths File paths relative to repo root
     */
    public function stage(array $paths): GitResult
    {
        $this->ensureOpen();

        $result = $this->commandRunner->runWithTranslation(
            array_merge(['add', '--'], $paths)
        );
        $result->throw('Failed to stage files');

        return $result;
    }

    /**
     * Stage all changes (tracked and untracked).
     */
    public function stageAll(): GitResult
    {
        $this->ensureOpen();

        $result = $this->commandRunner->runWithTranslation(['add', '-A']);
        $result->throw('Failed to stage all files');

        return $result;
    }

    /**
     * Unstage one or more files (reset from index).
     *
     * @param list<string> $paths File paths relative to repo root
     */
    public function unstage(array $paths): GitResult
    {
        $this->ensureOpen();

        $result = $this->commandRunner->runWithTranslation(
            array_merge(['reset', 'HEAD', '--'], $paths)
        );
        $result->throw('Failed to unstage files');

        return $result;
    }

    /**
     * Unstage all files.
     */
    public function unstageAll(): GitResult
    {
        $this->ensureOpen();

        $result = $this->commandRunner->runWithTranslation(['reset', 'HEAD']);
        $result->throw('Failed to unstage all files');

        return $result;
    }

    /**
     * Discard working tree changes for specific files.
     *
     * @param list<string> $paths File paths relative to repo root
     */
    public function discardChanges(array $paths): GitResult
    {
        $this->ensureOpen();

        $result = $this->commandRunner->runWithTranslation(
            array_merge(['checkout', '--'], $paths)
        );
        $result->throw('Failed to discard changes');

        return $result;
    }

    // ─── COMMIT OPERATIONS ──────────────────────────────────────────────

    /**
     * Create a commit with the staged changes.
     */
    public function commit(string $message): GitResult
    {
        $this->ensureOpen();

        if (trim($message) === '') {
            throw new \InvalidArgumentException('Commit message cannot be empty.');
        }

        $result = $this->commandRunner->runWithTranslation(
            ['commit', '-m', $message]
        );
        $result->throw('Failed to create commit');

        return $result;
    }

    /**
     * Amend the last commit with currently staged changes.
     */
    public function amendCommit(?string $message = null): GitResult
    {
        $this->ensureOpen();

        $args = ['commit', '--amend'];
        if ($message !== null) {
            $args[] = '-m';
            $args[] = $message;
        } else {
            $args[] = '--no-edit';
        }

        $result = $this->commandRunner->runWithTranslation($args);
        $result->throw('Failed to amend commit');

        return $result;
    }

    // ─── BRANCH OPERATIONS ──────────────────────────────────────────────

    /**
     * Create a new branch at the current HEAD (does not switch to it).
     */
    public function createBranch(string $name, ?string $startPoint = null): GitResult
    {
        $this->ensureOpen();

        $args = ['branch', $name];
        if ($startPoint !== null) {
            $args[] = $startPoint;
        }

        $result = $this->commandRunner->runWithTranslation($args);
        $result->throw("Failed to create branch '{$name}'");

        return $result;
    }

    /**
     * Switch to an existing branch or commit.
     */
    public function checkout(string $ref): GitResult
    {
        $this->ensureOpen();

        $result = $this->commandRunner->runWithTranslation(['checkout', $ref]);
        $result->throw("Failed to checkout '{$ref}'");

        return $result;
    }

    /**
     * Switch to a remote branch by checking out or creating its local tracking branch.
     */
    public function checkoutRemoteBranch(string $remoteRef): GitResult
    {
        $this->ensureOpen();

        $localBranch = $this->localBranchNameFromRemoteRef($remoteRef);
        $localExists = collect($this->getBranches())
            ->contains(fn (Branch $branch) => ! $branch->isRemote && $branch->name === $localBranch);

        if ($localExists) {
            return $this->checkout($localBranch);
        }

        $result = $this->commandRunner->runWithTranslation(['checkout', '--track', $remoteRef]);
        $result->throw("Failed to checkout remote branch '{$remoteRef}'");

        return $result;
    }

    /**
     * Create and switch to a new branch.
     */
    public function checkoutNewBranch(string $name, ?string $startPoint = null): GitResult
    {
        $this->ensureOpen();

        $args = ['checkout', '-b', $name];
        if ($startPoint !== null) {
            $args[] = $startPoint;
        }

        $result = $this->commandRunner->runWithTranslation($args);
        $result->throw("Failed to create and checkout branch '{$name}'");

        return $result;
    }

    private function localBranchNameFromRemoteRef(string $remoteRef): string
    {
        if (! str_contains($remoteRef, '/')) {
            return $remoteRef;
        }

        [, $localBranch] = explode('/', $remoteRef, 2);

        return $localBranch !== '' ? $localBranch : $remoteRef;
    }

    /**
     * Delete a local branch.
     *
     * @param bool $force Force-delete even if not fully merged (uses -D)
     */
    public function deleteBranch(string $name, bool $force = false): GitResult
    {
        $this->ensureOpen();

        $flag = $force ? '-D' : '-d';
        $result = $this->commandRunner->runWithTranslation(['branch', $flag, $name]);
        $result->throw("Failed to delete branch '{$name}'");

        return $result;
    }

    /**
     * Merge a branch into the current branch.
     */
    public function merge(string $branch, bool $noFf = false): GitResult
    {
        $this->ensureOpen();

        $args = ['merge', $branch];
        if ($noFf) {
            $args[] = '--no-ff';
        }

        $result = $this->commandRunner->runWithTranslation($args);
        // Don't throw on merge conflicts — the caller should check the result
        return $result;
    }

    /**
     * Abort an in-progress merge.
     */
    public function mergeAbort(): GitResult
    {
        $this->ensureOpen();

        $result = $this->commandRunner->runWithTranslation(['merge', '--abort']);
        $result->throw('Failed to abort merge');

        return $result;
    }

    // ─── TAG OPERATIONS ─────────────────────────────────────────────────

    /**
     * Create a lightweight tag.
     */
    public function createTag(string $name, ?string $ref = null): GitResult
    {
        $this->ensureOpen();

        $args = ['tag', $name];
        if ($ref !== null) {
            $args[] = $ref;
        }

        $result = $this->commandRunner->runWithTranslation($args);
        $result->throw("Failed to create tag '{$name}'");

        return $result;
    }

    /**
     * Create an annotated tag.
     */
    public function createAnnotatedTag(string $name, string $message, ?string $ref = null): GitResult
    {
        $this->ensureOpen();

        $args = ['tag', '-a', $name, '-m', $message];
        if ($ref !== null) {
            $args[] = $ref;
        }

        $result = $this->commandRunner->runWithTranslation($args);
        $result->throw("Failed to create annotated tag '{$name}'");

        return $result;
    }

    /**
     * Delete a local tag.
     */
    public function deleteTag(string $name): GitResult
    {
        $this->ensureOpen();

        $result = $this->commandRunner->runWithTranslation(['tag', '-d', $name]);
        $result->throw("Failed to delete tag '{$name}'");

        return $result;
    }

    /**
     * Push a specific tag to a remote.
     */
    public function pushTag(string $name, string $remote = 'origin'): GitResult
    {
        $this->ensureOpen();

        $result = $this->commandRunner->runWithTranslation(
            ['push', $remote, "refs/tags/{$name}"],
            timeout: 60
        );
        $result->throw("Failed to push tag '{$name}'");

        return $result;
    }

    /**
     * Push all tags to a remote.
     */
    public function pushAllTags(string $remote = 'origin'): GitResult
    {
        $this->ensureOpen();

        $result = $this->commandRunner->runWithTranslation(
            ['push', $remote, '--tags'],
            timeout: 60
        );
        $result->throw('Failed to push tags');

        return $result;
    }

    // ─── STASH OPERATIONS ───────────────────────────────────────────────

    /**
     * Stash current changes.
     */
    public function stash(?string $message = null): GitResult
    {
        $this->ensureOpen();

        $args = ['stash', 'push'];
        if ($message !== null) {
            $args[] = '-m';
            $args[] = $message;
        }

        $result = $this->commandRunner->runWithTranslation($args);
        $result->throw('Failed to stash changes');

        return $result;
    }

    /**
     * Apply a stash entry (without removing it from the stash list).
     */
    public function stashApply(string $ref = 'stash@{0}'): GitResult
    {
        $this->ensureOpen();

        $result = $this->commandRunner->runWithTranslation(['stash', 'apply', $ref]);
        // Don't throw — may result in conflicts that the caller should handle
        return $result;
    }

    /**
     * Pop a stash entry (apply and remove from stash list).
     */
    public function stashPop(string $ref = 'stash@{0}'): GitResult
    {
        $this->ensureOpen();

        $result = $this->commandRunner->runWithTranslation(['stash', 'pop', $ref]);
        // Don't throw — may result in conflicts
        return $result;
    }

    /**
     * Drop a stash entry.
     */
    public function stashDrop(string $ref = 'stash@{0}'): GitResult
    {
        $this->ensureOpen();

        $result = $this->commandRunner->runWithTranslation(['stash', 'drop', $ref]);
        $result->throw('Failed to drop stash');

        return $result;
    }

    // ─── REMOTE SYNC (SYNC VARIANTS) ───────────────────────────────────
    // Note: For real-time progress in the UI, use NativePHP ChildProcess
    // directly. These sync methods are useful for quick operations or tests.

    /**
     * Fetch from a remote (sync — no progress streaming).
     */
    public function fetch(string $remote = 'origin', bool $prune = true): GitResult
    {
        $this->ensureOpen();

        $args = ['fetch', $remote];
        if ($prune) {
            $args[] = '--prune';
        }

        $result = $this->commandRunner->runWithTranslation($args, timeout: 120);
        $result->throw("Failed to fetch from '{$remote}'");

        return $result;
    }

    /**
     * Pull from a remote (sync — no progress streaming).
     */
    public function pull(string $remote = 'origin', ?string $branch = null): GitResult
    {
        $this->ensureOpen();

        $args = ['pull', $remote];
        if ($branch !== null) {
            $args[] = $branch;
        }

        $result = $this->commandRunner->runWithTranslation($args, timeout: 120);
        // Don't throw — may result in merge conflicts
        return $result;
    }

    /**
     * Push to a remote (sync — no progress streaming).
     */
    public function push(string $remote = 'origin', ?string $branch = null, bool $setUpstream = false): GitResult
    {
        $this->ensureOpen();

        $args = ['push'];
        if ($setUpstream) {
            $args[] = '-u';
        }
        $args[] = $remote;
        if ($branch !== null) {
            $args[] = $branch;
        }

        $result = $this->commandRunner->runWithTranslation($args, timeout: 120);
        $result->throw("Failed to push to '{$remote}'");

        return $result;
    }

    // ─── UTILITY ────────────────────────────────────────────────────────

    /**
     * Get the current branch name (or null if detached).
     */
    public function getCurrentBranch(): ?string
    {
        $this->ensureOpen();

        $result = $this->commandRunner->run(['rev-parse', '--abbrev-ref', 'HEAD']);
        if (! $result->success) {
            return null;
        }

        $branch = trim($result->output);

        return $branch === 'HEAD' ? null : $branch;
    }

    /**
     * Get list of remote names.
     *
     * @return list<string>
     */
    public function getRemotes(): array
    {
        $this->ensureOpen();

        $result = $this->commandRunner->run(['remote']);
        if (! $result->success) {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode("\n", trim($result->output))),
            fn (string $line) => $line !== ''
        ));
    }

    /**
     * Get the URL for a remote.
     */
    public function getRemoteUrl(string $remote = 'origin'): ?string
    {
        $this->ensureOpen();

        $result = $this->commandRunner->run(['remote', 'get-url', $remote]);
        if (! $result->success) {
            return null;
        }

        return trim($result->output);
    }

    /**
     * Get the underlying command runner (for advanced/custom operations).
     */
    public function getCommandRunner(): GitCommandRunner
    {
        return $this->commandRunner;
    }

    /**
     * Ensure a repo is open before running commands.
     *
     * @throws \RuntimeException
     */
    private function ensureOpen(): void
    {
        if ($this->repoPath === null) {
            throw new \RuntimeException('No repository is open. Call open() first.');
        }
    }
}
