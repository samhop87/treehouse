<?php

namespace App\Livewire;

use App\DTOs\Branch;
use App\DTOs\Commit;
use App\DTOs\DiffFile;
use App\DTOs\RepoState;
use App\DTOs\StashEntry;
use App\DTOs\Tag;
use App\Services\Git\GitService;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Livewire\Component;
use Native\Desktop\Events\ChildProcess\ErrorReceived;
use Native\Desktop\Events\ChildProcess\ProcessExited;
use Native\Desktop\Facades\ChildProcess;

class RepoView extends Component
{
    // ─── STATE ───────────────────────────────────────────────────────────

    public string $path = '';

    public string $tabId = '';

    #[Reactive]
    public bool $isActive = false;

    /** Repo status (branch, ahead/behind, files) */
    public ?array $status = null;

    /** Commit log for the graph */
    public array $commits = [];

    /** Local and remote branches */
    public array $localBranches = [];
    public array $remoteBranches = [];
    public ?string $currentBranch = null;

    /** Tags (first-class!) */
    public array $tags = [];

    /** Stash entries */
    public array $stashes = [];

    /** Staged/unstaged/untracked file lists */
    public array $stagedFiles = [];
    public array $unstagedFiles = [];
    public array $untrackedFiles = [];
    public array $conflictedFiles = [];

    /** Currently selected file for diff view */
    public ?string $selectedFile = null;
    public bool $selectedFileStaged = false;

    /** Diff data for the selected file */
    public array $diffFiles = [];

    /** Which history entity is selected: null, 'commit', or 'branch' */
    public ?string $selectedHistoryType = null;

    /** Diff payload for the selected commit/branch, used to populate the right panel */
    public array $selectedHistoryDiffs = [];

    /** Currently selected commit hash for detail view */
    public ?string $selectedCommit = null;

    /** Metadata for the selected commit (for detail panel) */
    public ?array $selectedCommitData = null;

    /** Currently selected branch for history inspection */
    public ?string $selectedBranch = null;
    public ?array $selectedBranchData = null;

    /** Repo metadata */
    public string $repoName = '';
    public ?string $upstream = null;
    public int $ahead = 0;
    public int $behind = 0;
    public bool $isDetached = false;

    /** Error state */
    public string $errorMessage = '';

    /** Loading states */
    public bool $isLoading = true;

    // ─── LIFECYCLE ───────────────────────────────────────────────────────

    public function mount(string $path, string $tabId, bool $isActive = false): void
    {
        $this->path = $path;
        $this->tabId = $tabId;
        $this->isActive = $isActive;

        $this->repoName = basename($this->path);
        $this->loadRepoData();
    }

    // ─── DATA LOADING ────────────────────────────────────────────────────

    /**
     * Refresh all repo data from git.
     */
    public function refresh(): void
    {
        $this->loadRepoData();
    }

    private function loadRepoData(): void
    {
        $this->isLoading = true;
        $this->errorMessage = '';

        try {
            $git = app(GitService::class);
            $git->open($this->path);

            // Load status
            $state = $git->getStatus();
            $this->populateStatusFromState($state);

            // Load commits
            $commits = $git->getLog(limit: 200, all: true);
            $this->commits = $this->serializeCommits($commits);

            // Load branches
            $branches = $git->getBranches();
            $this->hydrateBranches($branches);

            // Load tags
            $tags = $git->getTags();
            $this->tags = $this->serializeTags($tags);

            // Load stashes
            $stashes = $git->getStashes();
            $this->stashes = $this->serializeStashes($stashes);

            // Keep selection state in sync after refreshes.
            if ($this->selectedHistoryType === 'commit' && $this->selectedCommit !== null) {
                $this->selectedCommitData = collect($this->commits)
                    ->firstWhere('hash', $this->selectedCommit);

                if ($this->selectedCommitData !== null) {
                    $this->loadSelectedCommitHistory($this->selectedCommit);
                    $this->restoreSelectedHistoryFile();
                } else {
                    $this->clearSelection();
                }
            } elseif ($this->selectedHistoryType === 'branch' && $this->selectedBranch !== null) {
                $this->selectedBranchData = $this->findBranchByName($this->selectedBranch);

                if ($this->selectedBranchData !== null) {
                    $this->loadSelectedBranchHistory($this->selectedBranch);
                    $this->restoreSelectedHistoryFile();
                } else {
                    $this->clearSelection();
                }
            } elseif ($this->selectedFile !== null) {
                $this->loadWorkingTreeFileDiff();
            }

            $this->syncWorkspaceTabContext();

        } catch (\RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        } finally {
            $this->isLoading = false;
        }
    }

    private function populateStatusFromState(RepoState $state): void
    {
        $this->currentBranch = $state->isDetached ? null : $state->branch;
        $this->upstream = $state->upstream;
        $this->ahead = $state->ahead;
        $this->behind = $state->behind;
        $this->isDetached = $state->isDetached;

        $this->status = [
            'headHash' => $state->headHash,
            'branch' => $state->branch,
            'isClean' => $state->isClean(),
            'hasConflicts' => $state->hasConflicts(),
        ];

        $this->stagedFiles = $this->serializeFiles($state->stagedFiles());
        $this->unstagedFiles = $this->serializeFiles($state->unstagedFiles());
        $this->untrackedFiles = $this->serializeFiles($state->untrackedFiles());
        $this->conflictedFiles = $this->serializeFiles($state->conflictedFiles());
    }

    private function hydrateBranches(array $branches): void
    {
        $this->localBranches = [];
        $this->remoteBranches = [];

        foreach ($branches as $branch) {
            $data = [
                'name' => $branch->name,
                'hash' => $branch->hash,
                'isCurrent' => $branch->isCurrent,
                'isRemote' => $branch->isRemote,
                'upstream' => $branch->upstream,
                'ahead' => $branch->ahead,
                'behind' => $branch->behind,
            ];

            if ($branch->isRemote) {
                $this->remoteBranches[] = $data;
            } else {
                $this->localBranches[] = $data;
            }
        }
    }

    // ─── FILE SELECTION & DIFF ───────────────────────────────────────────

    /**
     * Select a file to view its diff.
     */
    public function selectFile(string $path, bool $staged = false): void
    {
        $this->selectedHistoryType = null;
        $this->selectedHistoryDiffs = [];
        $this->selectedFile = $path;
        $this->selectedFileStaged = $staged;
        $this->selectedCommit = null;
        $this->selectedCommitData = null;
        $this->selectedBranch = null;
        $this->selectedBranchData = null;
        $this->loadWorkingTreeFileDiff();
    }

    /**
     * Clear file selection.
     */
    public function clearFileSelection(): void
    {
        $this->selectedFile = null;
        $this->selectedFileStaged = false;
        $this->diffFiles = [];
    }

    /**
     * Clear the current file or commit selection.
     */
    public function clearSelection(): void
    {
        $this->clearFileSelection();
        $this->selectedHistoryType = null;
        $this->selectedHistoryDiffs = [];
        $this->selectedCommit = null;
        $this->selectedCommitData = null;
        $this->selectedBranch = null;
        $this->selectedBranchData = null;
    }

    /**
     * Select a commit to view its changed files in the right panel.
     */
    public function selectCommit(string $hash): void
    {
        $this->selectedHistoryType = 'commit';
        $this->selectedCommit = $hash;
        $this->selectedBranch = null;
        $this->selectedBranchData = null;
        $this->clearFileSelection();

        // Find commit data from the loaded commits array
        $this->selectedCommitData = null;
        foreach ($this->commits as $commit) {
            if ($commit['hash'] === $hash) {
                $this->selectedCommitData = $commit;
                break;
            }
        }

        $this->loadSelectedCommitHistory($hash);
    }

    /**
     * Select a branch to view changed files in the right panel.
     */
    public function selectBranch(string $name): void
    {
        $branch = $this->findBranchByName($name);
        if ($branch === null) {
            return;
        }

        $this->selectedHistoryType = 'branch';
        $this->selectedBranch = $name;
        $this->selectedBranchData = $branch;
        $this->selectedCommit = null;
        $this->selectedCommitData = null;
        $this->clearFileSelection();
        $this->loadSelectedBranchHistory($name);
    }

    /**
     * Select a file from the currently selected commit or branch.
     */
    public function selectHistoryFile(string $path): void
    {
        if ($this->selectedHistoryType === null) {
            return;
        }

        $this->selectedFile = $path;
        $this->selectedFileStaged = false;
        $this->diffFiles = array_values(array_filter(
            $this->selectedHistoryDiffs,
            fn (array $diff) => $diff['path'] === $path
        ));
    }

    /**
     * Checkout a commit in detached HEAD state.
     */
    public function checkoutCommit(string $hash): void
    {
        $shortHash = substr($hash, 0, 7);

        $this->runGitAction(
            fn (GitService $git) => $git->checkout($hash),
            "Checked out commit {$shortHash}"
        );
    }

    /**
     * Select a branch ref from the center graph.
     */
    public function selectGraphRef(string $ref): void
    {
        $branchName = $this->normalizeGraphBranchRef($ref);
        if ($branchName === null) {
            return;
        }

        $this->selectBranch($branchName);
    }

    /**
     * Checkout a branch ref from the center graph.
     */
    public function checkoutGraphRef(string $ref): void
    {
        $branchName = $this->normalizeGraphBranchRef($ref);
        if ($branchName === null) {
            return;
        }

        $branch = $this->findBranchByName($branchName);
        if ($branch === null) {
            return;
        }

        if ($branch['isRemote']) {
            $this->checkoutRemoteBranch($branchName);
            return;
        }

        $this->checkoutLocalBranch($branchName);
    }

    private function loadWorkingTreeFileDiff(): void
    {
        try {
            $git = app(GitService::class);
            $git->open($this->path);

            $diffs = $git->getFileDiff($this->selectedFile, $this->selectedFileStaged);
            $this->diffFiles = $this->serializeDiffFiles($diffs);
        } catch (\RuntimeException $e) {
            $this->diffFiles = [];
        }
    }

    private function loadSelectedCommitHistory(string $hash): void
    {
        try {
            $git = app(GitService::class);
            $git->open($this->path);

            $diffs = $git->getCommitDiff($hash);
            $this->selectedHistoryDiffs = $this->serializeDiffFiles($diffs);
        } catch (\RuntimeException $e) {
            $this->selectedHistoryDiffs = [];
        }
    }

    private function loadSelectedBranchHistory(string $name): void
    {
        try {
            $git = app(GitService::class);
            $git->open($this->path);

            $diffs = $git->getRefComparisonDiff($name);
            $this->selectedHistoryDiffs = $this->serializeDiffFiles($diffs);
        } catch (\RuntimeException $e) {
            $this->selectedHistoryDiffs = [];
        }
    }

    // ─── STAGING ACTIONS ─────────────────────────────────────────────────

    /**
     * Stage a single file.
     */
    public function stageFile(string $path): void
    {
        $this->runGitAction(fn (GitService $git) => $git->stage([$path]));
    }

    /**
     * Stage all files.
     */
    public function stageAll(): void
    {
        $this->runGitAction(fn (GitService $git) => $git->stageAll());
    }

    /**
     * Unstage a single file.
     */
    public function unstageFile(string $path): void
    {
        $this->runGitAction(fn (GitService $git) => $git->unstage([$path]));
    }

    /**
     * Unstage all files.
     */
    public function unstageAll(): void
    {
        $this->runGitAction(fn (GitService $git) => $git->unstageAll());
    }

    /**
     * Discard changes to a file.
     */
    public function discardFile(string $path): void
    {
        $this->runGitAction(fn (GitService $git) => $git->discardChanges([$path]));
    }

    // ─── COMMIT ──────────────────────────────────────────────────────────

    public string $commitMessage = '';

    /**
     * Create a commit with the current message.
     */
    public function commit(): void
    {
        if (trim($this->commitMessage) === '') {
            $this->errorMessage = 'Commit message cannot be empty.';
            return;
        }

        $msg = $this->commitMessage;
        $this->runGitAction(function (GitService $git) {
            $git->commit($this->commitMessage);
            $this->commitMessage = '';
        }, 'Committed: ' . \Illuminate\Support\Str::limit(trim($msg), 50));
    }

    // ─── BRANCH ACTIONS ──────────────────────────────────────────────────

    /** State for branch creation UI */
    public bool $showCreateBranch = false;
    public string $newBranchName = '';
    public string $newBranchStartPoint = '';

    /** State for merge UI */
    public bool $showMergeConfirm = false;
    public string $mergeBranchName = '';

    /**
     * Checkout an existing branch.
     */
    public function checkoutBranch(string $name): void
    {
        $this->checkoutLocalBranch($name);
    }

    /**
     * Checkout an existing local branch.
     */
    public function checkoutLocalBranch(string $name): void
    {
        $this->runGitAction(fn (GitService $git) => $git->checkout($name), "Switched to '{$name}'");
    }

    /**
     * Checkout an existing remote branch via a local tracking branch.
     */
    public function checkoutRemoteBranch(string $name): void
    {
        $localBranch = $this->localBranchNameFromRemote($name);

        $this->runGitAction(
            fn (GitService $git) => $git->checkoutRemoteBranch($name),
            "Switched to '{$localBranch}'"
        );
    }

    /**
     * Open the create branch form.
     */
    public function openCreateBranch(): void
    {
        $this->showCreateBranch = true;
        $this->newBranchName = '';
        $this->newBranchStartPoint = '';
    }

    /**
     * Close the create branch form.
     */
    public function closeCreateBranch(): void
    {
        $this->showCreateBranch = false;
        $this->newBranchName = '';
        $this->newBranchStartPoint = '';
    }

    /**
     * Create a new branch and switch to it.
     */
    public function createBranch(): void
    {
        $name = trim($this->newBranchName);
        if ($name === '') {
            $this->errorMessage = 'Branch name cannot be empty.';
            return;
        }

        $startPoint = trim($this->newBranchStartPoint) ?: null;

        $this->runGitAction(function (GitService $git) use ($name, $startPoint) {
            $git->checkoutNewBranch($name, $startPoint);
        }, "Created and switched to '{$name}'");

        $this->showCreateBranch = false;
        $this->newBranchName = '';
        $this->newBranchStartPoint = '';
    }

    /**
     * Delete a local branch.
     */
    public function deleteBranch(string $name, bool $force = false): void
    {
        $this->runGitAction(fn (GitService $git) => $git->deleteBranch($name, $force), "Deleted branch '{$name}'");
    }

    /**
     * Open merge confirmation for a branch.
     */
    public function openMerge(string $name): void
    {
        $this->showMergeConfirm = true;
        $this->mergeBranchName = $name;
    }

    /**
     * Close merge confirmation.
     */
    public function closeMerge(): void
    {
        $this->showMergeConfirm = false;
        $this->mergeBranchName = '';
    }

    /**
     * Merge a branch into the current branch.
     */
    public function mergeBranch(): void
    {
        if (empty($this->mergeBranchName)) {
            return;
        }

        $branch = $this->mergeBranchName;
        $this->showMergeConfirm = false;
        $this->mergeBranchName = '';

        $this->errorMessage = '';

        try {
            $git = app(GitService::class);
            $git->open($this->path);

            $result = $git->merge($branch);

            if (! $result->success) {
                // Surface merge conflicts or errors without throwing
                $this->errorMessage = $result->error ?: 'Merge failed.';
            }

            $this->loadRepoData();

            if ($result->success) {
                $this->dispatch('toast', message: "Merged '{$branch}'", type: 'success');
            }
        } catch (\RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    /**
     * Abort an in-progress merge.
     */
    public function mergeAbort(): void
    {
        $this->runGitAction(fn (GitService $git) => $git->mergeAbort(), 'Merge aborted');
    }

    // ─── TAG ACTIONS ─────────────────────────────────────────────────────

    /** State for tag creation UI */
    public bool $showCreateTag = false;
    public string $newTagName = '';
    public string $newTagRef = '';
    public bool $newTagAnnotated = false;
    public string $newTagMessage = '';

    /**
     * Open the create tag form.
     */
    public function openCreateTag(): void
    {
        $this->showCreateTag = true;
        $this->newTagName = '';
        $this->newTagRef = '';
        $this->newTagAnnotated = false;
        $this->newTagMessage = '';
    }

    /**
     * Close the create tag form.
     */
    public function closeCreateTag(): void
    {
        $this->showCreateTag = false;
        $this->newTagName = '';
        $this->newTagRef = '';
        $this->newTagAnnotated = false;
        $this->newTagMessage = '';
    }

    /**
     * Create a new tag (lightweight or annotated).
     */
    public function createTag(): void
    {
        $name = trim($this->newTagName);
        if ($name === '') {
            $this->errorMessage = 'Tag name cannot be empty.';
            return;
        }

        if ($this->newTagAnnotated && trim($this->newTagMessage) === '') {
            $this->errorMessage = 'Annotated tags require a message.';
            return;
        }

        $ref = trim($this->newTagRef) ?: null;

        if ($this->newTagAnnotated) {
            $this->runGitAction(fn (GitService $git) => $git->createAnnotatedTag($name, trim($this->newTagMessage), $ref), "Created tag '{$name}'");
        } else {
            $this->runGitAction(fn (GitService $git) => $git->createTag($name, $ref), "Created tag '{$name}'");
        }

        $this->showCreateTag = false;
        $this->newTagName = '';
        $this->newTagRef = '';
        $this->newTagAnnotated = false;
        $this->newTagMessage = '';
    }

    /**
     * Delete a local tag.
     */
    public function deleteTag(string $name): void
    {
        $this->runGitAction(fn (GitService $git) => $git->deleteTag($name), "Deleted tag '{$name}'");
    }

    /**
     * Push a single tag to origin.
     */
    public function pushTag(string $name): void
    {
        $this->runGitAction(fn (GitService $git) => $git->pushTag($name), "Pushed tag '{$name}'");
    }

    /**
     * Push all tags to origin.
     */
    public function pushAllTags(): void
    {
        $this->runGitAction(fn (GitService $git) => $git->pushAllTags(), 'Pushed all tags');
    }

    // ─── STASH ACTIONS ──────────────────────────────────────────────────

    /** State for stash creation UI */
    public bool $showCreateStash = false;
    public string $newStashMessage = '';

    /**
     * Open the create stash form.
     */
    public function openCreateStash(): void
    {
        $this->showCreateStash = true;
        $this->newStashMessage = '';
    }

    /**
     * Close the create stash form.
     */
    public function closeCreateStash(): void
    {
        $this->showCreateStash = false;
        $this->newStashMessage = '';
    }

    /**
     * Stash current working tree changes.
     */
    public function createStash(): void
    {
        $message = trim($this->newStashMessage) ?: null;

        $this->runGitAction(fn (GitService $git) => $git->stash($message), 'Changes stashed');

        $this->showCreateStash = false;
        $this->newStashMessage = '';
    }

    /**
     * Apply a stash entry (keeps it in the stash list).
     */
    public function stashApply(string $ref): void
    {
        $this->errorMessage = '';

        try {
            $git = app(GitService::class);
            $git->open($this->path);
            $result = $git->stashApply($ref);

            if (! $result->success) {
                $this->errorMessage = $result->error ?: 'Failed to apply stash.';
            }

            $this->loadRepoData();

            if ($result->success) {
                $this->dispatch('toast', message: "Applied {$ref}", type: 'success');
            }
        } catch (\RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    /**
     * Pop a stash entry (apply and remove from list).
     */
    public function stashPop(string $ref): void
    {
        $this->errorMessage = '';

        try {
            $git = app(GitService::class);
            $git->open($this->path);
            $result = $git->stashPop($ref);

            if (! $result->success) {
                $this->errorMessage = $result->error ?: 'Failed to pop stash.';
            }

            $this->loadRepoData();

            if ($result->success) {
                $this->dispatch('toast', message: "Popped {$ref}", type: 'success');
            }
        } catch (\RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    /**
     * Drop a stash entry (delete without applying).
     */
    public function stashDrop(string $ref): void
    {
        $this->runGitAction(fn (GitService $git) => $git->stashDrop($ref), "Dropped {$ref}");
    }

    // ─── REMOTE SYNC (FETCH / PULL / PUSH) ──────────────────────────────

    /** Which remote operation is running: null, 'fetch', 'pull', 'push' */
    public ?string $remoteOperation = null;

    /** Progress message from async ChildProcess */
    public string $remoteProgress = '';

    /**
     * Fetch from remote.
     */
    public function fetchRemote(): void
    {
        $this->errorMessage = '';

        if ($this->remoteOperation !== null) {
            return; // Already running an operation
        }

        if ($this->isNativeContext()) {
            $this->remoteOperation = 'fetch';
            $this->remoteProgress = 'Fetching...';
            ChildProcess::start(
                cmd: ['git', 'fetch', '--prune'],
                alias: $this->remoteOperationAlias(),
                cwd: $this->path,
            );
        } else {
            // Sync fallback for browser dev
            try {
                $git = app(GitService::class);
                $git->open($this->path);
                $git->fetch();
                $this->loadRepoData();
                $this->dispatch('toast', message: 'Fetched from remote', type: 'success');
            } catch (\RuntimeException $e) {
                $this->errorMessage = $e->getMessage();
            }
        }
    }

    /**
     * Pull from remote.
     */
    public function pullRemote(): void
    {
        $this->errorMessage = '';

        if ($this->remoteOperation !== null) {
            return;
        }

        if ($this->isNativeContext()) {
            $this->remoteOperation = 'pull';
            $this->remoteProgress = 'Pulling...';
            ChildProcess::start(
                cmd: ['git', 'pull'],
                alias: $this->remoteOperationAlias(),
                cwd: $this->path,
            );
        } else {
            try {
                $git = app(GitService::class);
                $git->open($this->path);
                $result = $git->pull();

                if (! $result->success) {
                    $this->errorMessage = $result->error ?: 'Pull failed.';
                }

                $this->loadRepoData();

                if ($result->success) {
                    $this->dispatch('toast', message: 'Pulled from remote', type: 'success');
                }
            } catch (\RuntimeException $e) {
                $this->errorMessage = $e->getMessage();
            }
        }
    }

    /**
     * Push to remote. If no upstream is set, push with -u.
     */
    public function pushRemote(): void
    {
        $this->errorMessage = '';

        if ($this->remoteOperation !== null) {
            return;
        }

        $setUpstream = empty($this->upstream);

        if ($this->isNativeContext()) {
            $this->remoteOperation = 'push';
            $this->remoteProgress = 'Pushing...';

            $cmd = ['git', 'push'];
            if ($setUpstream && $this->currentBranch) {
                $cmd = ['git', 'push', '-u', 'origin', $this->currentBranch];
            }

            ChildProcess::start(
                cmd: $cmd,
                alias: $this->remoteOperationAlias(),
                cwd: $this->path,
            );
        } else {
            try {
                $git = app(GitService::class);
                $git->open($this->path);
                $git->push(
                    branch: $setUpstream && $this->currentBranch ? $this->currentBranch : null,
                    setUpstream: $setUpstream,
                );
                $this->loadRepoData();
                $this->dispatch('toast', message: 'Pushed to remote', type: 'success');
            } catch (\RuntimeException $e) {
                $this->errorMessage = $e->getMessage();
            }
        }
    }

    /**
     * Handle ChildProcess exit for remote operations.
     */
    #[On('native:' . ProcessExited::class)]
    public function onRemoteProcessExited(string $alias, int $code): void
    {
        if ($alias !== $this->remoteOperationAlias() || $this->remoteOperation === null) {
            return;
        }

        $op = $this->remoteOperation;
        $this->remoteOperation = null;
        $this->remoteProgress = '';

        // If the last progress line looked like an error, surface it
        // Otherwise just refresh
        $this->loadRepoData();

        // For pull, check if we now have conflicts
        if ($op === 'pull' && $this->status && $this->status['hasConflicts']) {
            $this->errorMessage = 'Pull resulted in merge conflicts. Resolve them and commit, or abort the merge.';
        } else {
            $label = match ($op) {
                'fetch' => 'Fetched from remote',
                'pull' => 'Pulled from remote',
                'push' => 'Pushed to remote',
                default => ucfirst($op) . ' complete',
            };
            $this->dispatch('toast', message: $label, type: 'success');
        }
    }

    /**
     * Handle stderr progress from ChildProcess (git sends progress to stderr).
     */
    #[On('native:' . ErrorReceived::class)]
    public function onRemoteProgress(string $alias, mixed $data = null): void
    {
        if ($alias !== $this->remoteOperationAlias() || $this->remoteOperation === null) {
            return;
        }

        if (is_string($data)) {
            $this->remoteProgress = $data;
        } elseif (is_array($data) && isset($data['data'])) {
            $this->remoteProgress = $data['data'];
        }
    }

    // ─── WINDOW EVENTS ───────────────────────────────────────────────────

    public function handleWindowFocus(): void
    {
        if (! $this->isActive) {
            return;
        }

        $this->refresh();
    }

    public function handleShortcut(string $action): void
    {
        if (! $this->isActive) {
            return;
        }

        match ($action) {
            'refresh' => $this->refresh(),
            'push' => $this->pushRemote(),
            'fetch' => $this->fetchRemote(),
            'pull' => $this->pullRemote(),
            'commit' => $this->commit(),
            'escape' => $this->closeTransientUi(),
            default => null,
        };
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────

    /**
     * Run a git action, refresh data on success, capture errors.
     * Optionally dispatches a success toast notification.
     */
    private function runGitAction(callable $action, ?string $successMessage = null): void
    {
        $this->errorMessage = '';

        try {
            $git = app(GitService::class);
            $git->open($this->path);
            $action($git);
            $this->loadRepoData();

            if ($successMessage) {
                $this->dispatch('toast', message: $successMessage, type: 'success');
            }
        } catch (\RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    private function closeTransientUi(): void
    {
        $this->clearSelection();
        $this->closeCreateBranch();
        $this->closeCreateTag();
        $this->closeCreateStash();
        $this->closeMerge();
    }

    private function remoteOperationAlias(): string
    {
        return 'git-remote-op-' . $this->tabId;
    }

    private function localBranchNameFromRemote(string $name): string
    {
        if (! str_contains($name, '/')) {
            return $name;
        }

        [, $localBranch] = explode('/', $name, 2);

        return $localBranch !== '' ? $localBranch : $name;
    }

    private function findBranchByName(string $name): ?array
    {
        foreach (array_merge($this->localBranches, $this->remoteBranches) as $branch) {
            if ($branch['name'] === $name) {
                return $branch;
            }
        }

        return null;
    }

    private function normalizeGraphBranchRef(string $ref): ?string
    {
        if (str_starts_with($ref, 'tag:')) {
            return null;
        }

        if (str_contains($ref, 'HEAD -> ')) {
            $ref = trim(substr($ref, strpos($ref, '->') + 2));
        }

        if ($ref === 'HEAD' || str_ends_with($ref, '/HEAD')) {
            return null;
        }

        return $this->findBranchByName($ref) !== null ? $ref : null;
    }

    private function restoreSelectedHistoryFile(): void
    {
        if ($this->selectedFile === null) {
            $this->diffFiles = [];
            return;
        }

        $this->diffFiles = array_values(array_filter(
            $this->selectedHistoryDiffs,
            fn (array $diff) => $diff['path'] === $this->selectedFile
        ));
    }

    private function syncWorkspaceTabContext(): void
    {
        $this->dispatch(
            'workspace-tab-context-updated',
            tabId: $this->tabId,
            repoName: $this->repoName,
            currentBranch: $this->currentBranch,
            path: $this->path,
            isDetached: $this->isDetached,
        );
    }

    private function isNativeContext(): bool
    {
        return (bool) config('nativephp-internal.running', false);
    }

    // ─── SERIALIZATION (DTOs → arrays for Livewire) ──────────────────────

    private function serializeFiles(array $files): array
    {
        return array_map(fn ($f) => [
            'path' => $f->path,
            'indexStatus' => $f->indexStatus,
            'workStatus' => $f->workStatus,
            'origPath' => $f->origPath,
            'label' => $f->label(),
            'isRenamed' => $f->isRenamed(),
        ], $files);
    }

    /**
     * @param list<Commit> $commits
     */
    private function serializeCommits(array $commits): array
    {
        return array_map(fn (Commit $c) => [
            'hash' => $c->hash,
            'shortHash' => $c->shortHash,
            'parents' => $c->parents,
            'author' => $c->author,
            'email' => $c->email,
            'date' => $c->date->toIso8601String(),
            'dateHuman' => $c->date->diffForHumans(),
            'message' => $c->message,
            'refs' => $c->refs,
            'isMerge' => $c->isMerge(),
        ], $commits);
    }

    /**
     * @param list<Tag> $tags
     */
    private function serializeTags(array $tags): array
    {
        return array_map(fn (Tag $t) => [
            'name' => $t->name,
            'hash' => $t->hash,
            'commitHash' => $t->commitHash(),
            'isAnnotated' => $t->isAnnotated,
            'date' => $t->date?->toIso8601String(),
            'message' => $t->message,
        ], $tags);
    }

    /**
     * @param list<StashEntry> $stashes
     */
    private function serializeStashes(array $stashes): array
    {
        return array_map(fn (StashEntry $s) => [
            'ref' => $s->ref,
            'hash' => $s->hash,
            'message' => $s->message,
            'index' => $s->index(),
        ], $stashes);
    }

    /**
     * @param list<DiffFile> $diffs
     */
    private function serializeDiffFiles(array $diffs): array
    {
        return array_map(fn (DiffFile $d) => [
            'path' => $d->path,
            'status' => $d->status,
            'oldPath' => $d->oldPath,
            'isBinary' => $d->isBinary,
            'additions' => $d->additions(),
            'deletions' => $d->deletions(),
            'hunks' => array_map(fn ($h) => [
                'header' => $h->header,
                'oldStart' => $h->oldStart,
                'oldCount' => $h->oldCount,
                'newStart' => $h->newStart,
                'newCount' => $h->newCount,
                'lines' => array_map(fn ($l) => [
                    'type' => $l->type,
                    'content' => $l->content,
                    'oldLine' => $l->oldLine,
                    'newLine' => $l->newLine,
                ], $h->lines),
            ], $d->hunks),
        ], $diffs);
    }

    // ─── RENDER ──────────────────────────────────────────────────────────

    public function render()
    {
        return view('livewire.repo-view');
    }
}
