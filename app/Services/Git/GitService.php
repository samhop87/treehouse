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

    private ?string $gitDir = null;

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
        $this->gitDir = null;
        $this->commandRunner->setRepoPath($path);

        // This validates the repository and gives operation-state checks a path
        // they can inspect without starting more Git processes.
        $this->gitDir = $this->commandRunner->absoluteGitDir();

        // Keep compatibility with older Git versions that do not support
        // `--absolute-git-dir`.
        if ($this->gitDir === null && ! $this->commandRunner->isValidRepo()) {
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
     * @param  int  $limit  Maximum number of commits to return
     * @param  bool  $all  Include all branches (--all flag)
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
     * Get the history reachable from one branch or remote-tracking ref.
     *
     * @return list<Commit>
     */
    public function getLogForRef(string $ref, int $limit = 200): array
    {
        $this->ensureOpen();

        $result = $this->commandRunner->log($limit, [$ref]);
        $result->throw("Failed to get commit log for '{$ref}'");

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
     * @param  list<string>  $paths  Limit to specific file paths
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
     * @param  list<string>  $paths  Limit to specific file paths
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

        $result = $this->commandRunner->run(['diff', $commitHash.'^!'], timeout: 60);
        $result->throw("Failed to get diff for commit {$commitHash}");

        return $this->diffParser->parse($result->output);
    }

    /**
     * Get the diff between the current HEAD and another ref.
     *
     * Uses triple-dot syntax so branch selection shows changes introduced on
     * the selected branch since it diverged from the current HEAD.
     *
     * @return list<DiffFile>
     */
    public function getRefComparisonDiff(string $ref, string $baseRef = 'HEAD'): array
    {
        $this->ensureOpen();

        $result = $this->commandRunner->run(['diff', $baseRef.'...'.$ref], timeout: 60);
        $result->throw("Failed to get diff for ref {$ref}");

        return $this->diffParser->parse($result->output);
    }

    /**
     * Get changed-file metadata for a ref without loading its patch contents.
     *
     * Branch inspection uses this bounded form first. The full patch is loaded
     * only after the user chooses a specific file.
     *
     * @return list<DiffFile>
     */
    public function getRefComparisonFiles(string $ref, string $baseRef = 'HEAD'): array
    {
        $this->ensureOpen();

        $result = $this->commandRunner->run([
            'diff', '--name-status', '-z', $baseRef.'...'.$ref,
        ], timeout: 60);
        $result->throw("Failed to list changed files for ref {$ref}");

        $fields = explode("\0", rtrim($result->output, "\0"));
        $diffs = [];

        for ($index = 0, $count = count($fields); $index < $count;) {
            $status = $fields[$index++] ?? '';
            if ($status === '') {
                continue;
            }

            $statusCode = $status[0];
            $oldPath = null;
            $path = $fields[$index++] ?? '';

            if ($statusCode === 'R' || $statusCode === 'C') {
                $oldPath = $path;
                $path = $fields[$index++] ?? '';
            }

            if ($path === '') {
                continue;
            }

            $diffs[] = new DiffFile(
                path: $path,
                status: match ($statusCode) {
                    'A' => 'added',
                    'D' => 'deleted',
                    'R' => 'renamed',
                    'C' => 'copied',
                    default => 'modified',
                },
                oldPath: $oldPath,
            );
        }

        return $diffs;
    }

    /**
     * Get the full diff for one file in a ref comparison.
     *
     * @return list<DiffFile>
     */
    public function getRefComparisonFileDiff(string $ref, string $path, string $baseRef = 'HEAD'): array
    {
        $this->ensureOpen();

        $result = $this->commandRunner->run([
            'diff', $baseRef.'...'.$ref, '--', $path,
        ], timeout: 60);
        $result->throw("Failed to get diff for {$path} in ref {$ref}");

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

        $untracked = $this->commandRunner->run([
            'ls-files', '--others', '--exclude-standard', '-z', '--', $path,
        ]);

        if ($untracked->success && in_array($path, explode("\0", rtrim($untracked->output, "\0")), true)) {
            // `git diff --no-index` uses exit code 1 when a difference exists.
            $result = $this->commandRunner->run([
                '-c', 'core.quotePath=false',
                'diff', '--no-index', '--src-prefix=a/', '--dst-prefix=b/',
                '--', '/dev/null', $path,
            ], timeout: 60);

            if (! in_array($result->exitCode, [0, 1], true)) {
                $result->throw("Failed to preview untracked file {$path}");
            }

            return $this->diffParser->parse($result->output);
        }

        return $this->getUnstagedDiff([$path]);
    }

    // ─── STAGING OPERATIONS ─────────────────────────────────────────────

    /**
     * Stage one or more files.
     *
     * @param  list<string>  $paths  File paths relative to repo root
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
     * @param  list<string>  $paths  File paths relative to repo root
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
     * @param  list<string>  $paths  File paths relative to repo root
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

    /**
     * Revert a commit by creating a new inverse commit.
     */
    public function revertCommit(string $commitHash): GitResult
    {
        $this->ensureOpen();

        $result = $this->commandRunner->runWithTranslation(
            ['revert', '--no-edit', $commitHash],
            timeout: 60
        );
        $result->throw("Failed to revert commit {$commitHash}");

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
     * Determine whether a local branch can advance to a remote-tracking ref
     * without discarding local commits.
     */
    public function canFastForward(string $localBranch, string $remoteRef): bool
    {
        $this->ensureOpen();

        return $this->commandRunner
            ->run(['merge-base', '--is-ancestor', $localBranch, $remoteRef])
            ->success;
    }

    /**
     * Switch to a local branch and advance it to the remote-tracking ref
     * without creating a merge commit.
     */
    public function checkoutAndFastForward(string $localBranch, string $remoteRef): GitResult
    {
        $this->ensureOpen();

        if (! $this->canFastForward($localBranch, $remoteRef)) {
            throw new \RuntimeException("'{$localBranch}' cannot be fast-forwarded to '{$remoteRef}'.");
        }

        $this->checkout($localBranch);

        $result = $this->commandRunner->runWithTranslation(['merge', '--ff-only', $remoteRef], timeout: 120);
        $result->throw("Failed to fast-forward '{$localBranch}' to '{$remoteRef}'");

        return $result;
    }

    /**
     * Switch to a local branch and replace its tip and working tree with the
     * remote-tracking ref. Callers must obtain explicit user confirmation.
     */
    public function checkoutAndResetToRemote(string $localBranch, string $remoteRef): GitResult
    {
        $this->ensureOpen();

        $this->checkout($localBranch);

        $result = $this->commandRunner->runWithTranslation(['reset', '--hard', $remoteRef], timeout: 120);
        $result->throw("Failed to reset '{$localBranch}' to '{$remoteRef}'");

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
     * @param  bool  $force  Force-delete even if not fully merged (uses -D)
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
     * Delete a remote branch.
     */
    public function deleteRemoteBranch(string $remoteRef): GitResult
    {
        $this->ensureOpen();

        [$remote, $branch] = $this->splitRemoteRef($remoteRef);

        $result = $this->commandRunner->runWithTranslation(
            ['push', $remote, '--delete', $branch],
            timeout: 60
        );
        $result->throw("Failed to delete remote branch '{$remoteRef}'");

        return $result;
    }

    /**
     * Delete the local branch and its paired remote branch when present.
     */
    public function deleteBranchAndRemote(
        string $name,
        bool $forceLocal = false,
        ?string $confirmedRemoteRef = null,
    ): GitResult {
        $this->ensureOpen();

        $branches = $this->getBranches();
        $matchingRemote = collect($branches)
            ->first(fn (Branch $branch) => $branch->isRemote && $branch->name === $name);
        $localName = $matchingRemote !== null ? $this->localBranchNameFromRemoteRef($matchingRemote->name) : $name;
        $remoteRef = $confirmedRemoteRef ?? $this->resolveRemoteBranchRef($name, $branches);

        $localExists = collect($branches)
            ->contains(fn (Branch $branch) => ! $branch->isRemote && $branch->name === $localName);

        $lastResult = null;

        if ($localExists) {
            $lastResult = $this->deleteBranch($localName, $forceLocal);
        }

        if ($remoteRef !== null) {
            $lastResult = $this->deleteRemoteBranch($remoteRef);
        }

        if ($lastResult === null) {
            throw new \RuntimeException("Failed to find branch '{$name}'");
        }

        return $lastResult;
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

    /**
     * Rebase the current branch onto another ref.
     *
     * Conflicts are returned to the caller so the UI can expose recovery.
     */
    public function rebase(string $target): GitResult
    {
        $this->ensureOpen();

        return $this->commandRunner->runWithTranslation(['rebase', $target], timeout: 120);
    }

    /**
     * Detect the Git operation whose recovery controls should be shown.
     */
    public function getOperationState(): ?string
    {
        $this->ensureOpen();

        if ($this->gitDir !== null) {
            if (file_exists($this->gitDir.DIRECTORY_SEPARATOR.'rebase-merge')
                || file_exists($this->gitDir.DIRECTORY_SEPARATOR.'rebase-apply')) {
                return 'rebase';
            }

            foreach (['MERGE_HEAD' => 'merge', 'CHERRY_PICK_HEAD' => 'cherry-pick', 'REVERT_HEAD' => 'revert'] as $path => $operation) {
                if (file_exists($this->gitDir.DIRECTORY_SEPARATOR.$path)) {
                    return $operation;
                }
            }

            return null;
        }

        // Fallback for Git versions without `--absolute-git-dir` support.
        if ($this->gitPathExists('rebase-merge') || $this->gitPathExists('rebase-apply')) {
            return 'rebase';
        }

        foreach (['MERGE_HEAD' => 'merge', 'CHERRY_PICK_HEAD' => 'cherry-pick', 'REVERT_HEAD' => 'revert'] as $path => $operation) {
            if ($this->gitPathExists($path)) {
                return $operation;
            }
        }

        return null;
    }

    public function continueOperation(string $operation): GitResult
    {
        $this->ensureOpen();
        $args = match ($operation) {
            'rebase' => ['-c', 'core.editor=true', 'rebase', '--continue'],
            'merge' => ['-c', 'core.editor=true', 'merge', '--continue'],
            'cherry-pick' => ['-c', 'core.editor=true', 'cherry-pick', '--continue'],
            'revert' => ['-c', 'core.editor=true', 'revert', '--continue'],
            default => throw new \InvalidArgumentException("Unsupported Git operation: {$operation}"),
        };

        return $this->commandRunner->runWithTranslation($args, timeout: 120);
    }

    public function abortOperation(string $operation): GitResult
    {
        $this->ensureOpen();
        $args = match ($operation) {
            'rebase' => ['rebase', '--abort'],
            'merge' => ['merge', '--abort'],
            'cherry-pick' => ['cherry-pick', '--abort'],
            'revert' => ['revert', '--abort'],
            default => throw new \InvalidArgumentException("Unsupported Git operation: {$operation}"),
        };

        $result = $this->commandRunner->runWithTranslation($args, timeout: 120);
        $result->throw("Failed to abort {$operation}");

        return $result;
    }

    public function skipRebaseCommit(): GitResult
    {
        $this->ensureOpen();

        return $this->commandRunner->runWithTranslation(['rebase', '--skip'], timeout: 120);
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

        // Avoid inheriting a machine-specific pull.rebase setting. The toolbar's
        // Pull action always performs a merge-style pull; rebase is explicit.
        $args = ['pull', '--no-rebase', $remote];
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
     * @return array{0: string, 1: string}
     */
    private function splitRemoteRef(string $remoteRef): array
    {
        if (! str_contains($remoteRef, '/')) {
            return ['origin', $remoteRef];
        }

        [$remote, $branch] = explode('/', $remoteRef, 2);

        return [$remote, $branch !== '' ? $branch : $remoteRef];
    }

    /**
     * @param  list<Branch>  $branches
     */
    private function resolveRemoteBranchRef(string $name, array $branches): ?string
    {
        $matchingRemote = collect($branches)
            ->first(fn (Branch $branch) => $branch->isRemote && $branch->name === $name);

        if ($matchingRemote !== null) {
            return $matchingRemote->name;
        }

        $matchingLocal = collect($branches)
            ->first(fn (Branch $branch) => ! $branch->isRemote && $branch->name === $name);

        if ($matchingLocal?->upstream) {
            return $matchingLocal->upstream;
        }

        $localName = str_contains($name, '/') ? $this->localBranchNameFromRemoteRef($name) : $name;

        $pairedRemote = collect($branches)
            ->first(fn (Branch $branch) => $branch->isRemote && $this->localBranchNameFromRemoteRef($branch->name) === $localName);

        return $pairedRemote?->name;
    }

    private function gitPathExists(string $name): bool
    {
        $result = $this->commandRunner->run(['rev-parse', '--git-path', $name]);
        if (! $result->success || trim($result->output) === '') {
            return false;
        }

        $path = trim($result->output);
        if (! str_starts_with($path, DIRECTORY_SEPARATOR)) {
            $path = $this->repoPath.DIRECTORY_SEPARATOR.$path;
        }

        return file_exists($path);
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
