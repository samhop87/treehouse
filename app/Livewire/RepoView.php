<?php

namespace App\Livewire;

use App\DTOs\Branch;
use App\DTOs\Commit;
use App\DTOs\DiffFile;
use App\DTOs\RepoState;
use App\DTOs\StashEntry;
use App\DTOs\Tag;
use App\Services\Git\GitService;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Livewire\Component;
use Native\Desktop\Events\ChildProcess\ErrorReceived;
use Native\Desktop\Events\ChildProcess\ProcessExited;
use Native\Desktop\Facades\ChildProcess;

class RepoView extends Component
{
    private const HISTORY_LIMIT = 200;

    private const HISTORY_BATCH_SIZE = 200;

    private const MAX_HISTORY_LIMIT = 1600;

    private const MAX_BRANCHES_PER_KIND = 500;

    private const MAX_TAGS = 500;

    private const MAX_STASHES = 200;

    private const MAX_STATUS_FILES = 500;

    private const MAX_COMMIT_REFS = 20;

    private const MAX_COMMIT_MESSAGE_LENGTH = 4000;

    private const MAX_TAG_MESSAGE_LENGTH = 4000;

    private const MAX_STASH_MESSAGE_LENGTH = 4000;

    private const MAX_DIFF_FILES = 100;

    private const MAX_DIFF_HUNKS = 100;

    private const MAX_DIFF_LINES_TOTAL = 2000;

    private const MAX_DIFF_LINES_PER_HUNK = 500;

    private const MAX_DIFF_LINE_LENGTH = 1000;

    // ─── STATE ───────────────────────────────────────────────────────────

    public string $path = '';

    public string $tabId = '';

    #[Reactive]
    public bool $isActive = false;

    /** Repo status (branch, ahead/behind, files) */
    public ?array $status = null;

    /** Commit log for the graph */
    public array $commits = [];

    /** Number of newest commits loaded for the all-refs graph. */
    public int $historyLimit = self::HISTORY_LIMIT;

    /** A branch or remote ref whose history is shown instead of the all-refs graph. */
    public ?string $focusedHistoryRef = null;

    /** Local and remote branches */
    public array $localBranches = [];

    public array $remoteBranches = [];

    /** Server-filtered reference results. Empty until a filter is entered. */
    public string $referenceFilter = '';

    public array $filteredLocalBranches = [];

    public array $filteredRemoteBranches = [];

    public array $filteredTags = [];

    public array $filteredStashes = [];

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

    /** In-progress Git operation that owns any recovery controls. */
    public ?string $currentOperation = null;

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

    /** Whether the deferred initial repository snapshot has been requested. */
    public bool $hasLoadedInitialData = false;

    /** Context menu state */
    public bool $showContextMenu = false;

    public int $contextMenuX = 12;

    public int $contextMenuY = 12;

    public ?array $contextMenuTarget = null;

    /** Remote checkout choice shown after Treehouse has fetched remote state. */
    public bool $showRemoteCheckoutOptions = false;

    public ?string $remoteCheckoutRemote = null;

    public ?string $remoteCheckoutLocal = null;

    public bool $remoteCheckoutCanFastForward = false;

    /** Remote ref to continue checking out after an asynchronous native fetch. */
    public ?string $pendingRemoteCheckout = null;

    // ─── LIFECYCLE ───────────────────────────────────────────────────────

    public function mount(string $path, string $tabId, bool $isActive = false): void
    {
        $this->path = $path;
        $this->tabId = $tabId;
        $this->isActive = $isActive;

        $this->repoName = basename($this->path);
    }

    // ─── DATA LOADING ────────────────────────────────────────────────────

    /**
     * Refresh all repo data from git.
     */
    public function refresh(): void
    {
        $this->loadRepoData();
    }

    /** Load the initial snapshot after Livewire has rendered the tab shell. */
    public function loadInitialRepoData(): void
    {
        if ($this->hasLoadedInitialData) {
            return;
        }

        $this->hasLoadedInitialData = true;
        $this->loadRepoData();
    }

    private function loadRepoData(?GitService $git = null): void
    {
        $this->isLoading = true;
        $this->errorMessage = '';

        try {
            if ($git === null) {
                $git = app(GitService::class);
                $git->open($this->path);
            }

            $limit = min(self::MAX_HISTORY_LIMIT, max(self::HISTORY_LIMIT, $this->historyLimit));
            $this->historyLimit = $limit;
            $snapshot = $git->getInitialSnapshot($limit, $this->focusedHistoryRef);

            $state = $snapshot->state;
            $this->populateStatusFromState($state);
            $this->currentOperation = $git->getOperationState();
            if ($state->hasConflicts() && $this->currentOperation === null) {
                $this->currentOperation = 'conflict';
            }

            $this->commits = $this->serializeCommits($snapshot->commits, $limit);
            $branches = $snapshot->branches;
            $this->hydrateBranches($branches);
            $tags = $snapshot->tags;
            $this->tags = $this->serializeTags($tags);
            $stashes = $snapshot->stashes;
            $this->stashes = $this->serializeStashes($stashes);
            $this->hydrateFilteredReferences($branches, $tags, $stashes);

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
        [$this->localBranches, $this->remoteBranches] = $this->serializeBranchGroups($branches, true);
    }

    /**
     * @param  list<Branch>  $branches
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private function serializeBranchGroups(array $branches, bool $bounded): array
    {
        $localBranches = [];
        $remoteBranches = [];

        foreach ($branches as $branch) {
            $data = [
                'name' => $branch->name,
                'hash' => $branch->hash,
                'isCurrent' => $branch->isCurrent,
                'isRemote' => $branch->isRemote,
                'localName' => $branch->isRemote ? $this->localBranchNameFromRemote($branch->name) : $branch->name,
                'upstream' => $branch->upstream,
                'ahead' => $branch->ahead,
                'behind' => $branch->behind,
            ];

            if ($branch->isRemote) {
                $remoteBranches[] = $data;
            } else {
                $localBranches[] = $data;
            }
        }

        if ($bounded) {
            $localBranches = $this->limitBranches($localBranches);
            $remoteBranches = $this->limitBranches($remoteBranches);
        }

        $remoteByLocalName = collect($remoteBranches)
            ->mapWithKeys(fn (array $branch) => [$branch['localName'] => $branch['name']])
            ->all();
        $localNames = collect($localBranches)->pluck('name')->all();

        $localBranches = array_map(function (array $branch) use ($remoteByLocalName) {
            $pairedRemote = $branch['upstream'] ?: ($remoteByLocalName[$branch['localName']] ?? null);

            return array_merge($branch, [
                'pairedRemote' => $pairedRemote,
                'hasPairedRemote' => $pairedRemote !== null,
                'hasLocalPair' => true,
            ]);
        }, $localBranches);

        $remoteBranches = array_map(function (array $branch) use ($localNames) {
            return array_merge($branch, [
                'pairedRemote' => $branch['name'],
                'hasPairedRemote' => true,
                'hasLocalPair' => in_array($branch['localName'], $localNames, true),
            ]);
        }, $remoteBranches);

        return [$localBranches, $remoteBranches];
    }

    /**
     * Re-query refs after the debounced filter changes so matches are not
     * restricted to the bounded sidebar snapshot.
     */
    public function updatedReferenceFilter(): void
    {
        if (trim($this->referenceFilter) === '') {
            $this->clearFilteredReferences();

            return;
        }

        try {
            $git = app(GitService::class);
            $git->open($this->path);

            $this->hydrateFilteredReferences(
                $git->getBranches(),
                $git->getTags(),
                $git->getStashes(),
            );
        } catch (\RuntimeException $e) {
            $this->clearFilteredReferences();
            $this->errorMessage = $e->getMessage();
        }
    }

    /**
     * @param  list<Branch>  $branches
     * @param  list<Tag>  $tags
     * @param  list<StashEntry>  $stashes
     */
    private function hydrateFilteredReferences(array $branches, array $tags, array $stashes): void
    {
        $query = Str::lower(trim($this->referenceFilter));

        if ($query === '') {
            $this->clearFilteredReferences();

            return;
        }

        $matchingBranches = array_values(array_filter(
            $branches,
            function (Branch $branch) use ($query): bool {
                if ($this->startsWithReferenceFilter($branch->name, $query)) {
                    return true;
                }

                return $branch->isRemote
                    && $this->startsWithReferenceFilter($this->localBranchNameFromRemote($branch->name), $query);
            },
        ));

        [$this->filteredLocalBranches, $this->filteredRemoteBranches] = $this->serializeBranchGroups(
            $matchingBranches,
            false,
        );

        $matchingTags = array_values(array_filter(
            $tags,
            fn (Tag $tag): bool => $this->startsWithReferenceFilter($tag->name, $query),
        ));
        $this->filteredTags = $this->serializeTags($matchingTags, false);

        $matchingStashes = array_values(array_filter(
            $stashes,
            fn (StashEntry $stash): bool => $this->startsWithReferenceFilter($stash->ref, $query)
                || $this->startsWithReferenceFilter($stash->message, $query),
        ));
        $this->filteredStashes = $this->serializeStashes($matchingStashes, false);
    }

    private function clearFilteredReferences(): void
    {
        $this->filteredLocalBranches = [];
        $this->filteredRemoteBranches = [];
        $this->filteredTags = [];
        $this->filteredStashes = [];
    }

    private function startsWithReferenceFilter(string $value, string $query): bool
    {
        return str_starts_with(Str::lower($value), $query);
    }

    /**
     * Keep branch snapshots bounded while retaining the checked-out branch.
     * Large repositories can have thousands of remote-tracking refs.
     *
     * @param  list<array<string, mixed>>  $branches
     * @return list<array<string, mixed>>
     */
    private function limitBranches(array $branches): array
    {
        $current = array_values(array_filter(
            $branches,
            fn (array $branch): bool => $branch['isCurrent'] === true,
        ));
        $remaining = array_values(array_filter(
            $branches,
            fn (array $branch): bool => $branch['isCurrent'] !== true,
        ));

        return array_slice(
            array_merge($current, $remaining),
            0,
            self::MAX_BRANCHES_PER_KIND,
        );
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
     * Reveal and focus the row for the currently checked-out commit.
     */
    public function targetCurrentCheckout(): void
    {
        $headHash = (string) ($this->status['headHash'] ?? '');
        $commitHash = $this->loadedCommitHashFor($headHash);

        if ($commitHash === null) {
            try {
                $commitHash = $this->expandAllHistoryUntil($headHash);
            } catch (\RuntimeException $e) {
                $this->errorMessage = $e->getMessage();

                return;
            }
        }

        if ($commitHash === null) {
            $this->dispatch(
                'toast',
                message: 'The current checkout is older than the expanded graph history.',
                type: 'error',
            );

            return;
        }

        $this->clearFileSelection();
        $this->dispatch('target-current-branch', hash: $commitHash);
    }

    /** Show the history reachable from one local or remote branch. */
    public function focusGraphOnBranch(string $name): void
    {
        $branch = $this->findBranchByName($name) ?? $this->findBranchInRepository($name);
        if ($branch === null) {
            $this->errorMessage = "Branch '{$name}' was not found.";

            return;
        }

        $this->focusedHistoryRef = $branch['name'];
        $this->historyLimit = self::HISTORY_LIMIT;
        $this->loadRepoData();

        $commitHash = $this->loadedCommitHashFor((string) $branch['hash']);
        if ($commitHash !== null) {
            $this->dispatch('target-current-branch', hash: $commitHash);
        }
    }

    /**
     * Expand the all-refs graph in bounded batches until a branch tip appears.
     */
    public function revealBranchInAllHistory(string $name): void
    {
        $branch = $this->findBranchByName($name) ?? $this->findBranchInRepository($name);
        if ($branch === null) {
            $this->errorMessage = "Branch '{$name}' was not found.";

            return;
        }

        try {
            $commitHash = $this->expandAllHistoryUntil((string) $branch['hash']);
            if ($commitHash !== null) {
                $this->dispatch('target-current-branch', hash: $commitHash);
                $this->dispatch('toast', message: "Revealed '{$name}' in {$this->historyLimit} commits", type: 'success');

                return;
            }

            $this->errorMessage = "'{$name}' is older than the ".self::MAX_HISTORY_LIMIT.'-commit all-history limit. Use Focus graph to view it.';
        } catch (\RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    /** Restore the bounded, newest-first all-refs graph. */
    public function restoreDefaultHistory(): void
    {
        if ($this->focusedHistoryRef === null && $this->historyLimit === self::HISTORY_LIMIT) {
            return;
        }

        $this->resetGraphHistory();
        $this->loadRepoData();
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
        $this->loadSelectedHistoryFileDiff($path);
    }

    /**
     * Checkout a commit in detached HEAD state.
     */
    public function checkoutCommit(string $hash): void
    {
        $shortHash = substr($hash, 0, 7);

        $this->runGitAction(
            fn (GitService $git) => $git->checkout($hash),
            "Checked out commit {$shortHash}",
            resetGraphHistory: true,
        );
    }

    /**
     * Revert a commit by creating a new inverse commit.
     */
    public function revertCommit(string $hash): void
    {
        $shortHash = substr($hash, 0, 7);

        $this->runGitAction(
            fn (GitService $git) => $git->revertCommit($hash),
            "Reverted commit {$shortHash}"
        );
    }

    public function openCommitContextMenu(string $hash, int $x, int $y): void
    {
        $commit = collect($this->commits)->firstWhere('hash', $hash);
        if ($commit === null) {
            return;
        }

        $this->showContextMenu = true;
        $this->contextMenuX = max(12, $x);
        $this->contextMenuY = max(12, $y);
        $this->contextMenuTarget = [
            'type' => 'commit',
            'ref' => $commit['hash'],
            'shortHash' => $commit['shortHash'],
        ];
    }

    public function openBranchContextMenu(string $ref, int $x, int $y): void
    {
        $target = $this->contextMenuBranchTarget($ref);

        $this->showContextMenu = true;
        $this->contextMenuX = max(12, $x);
        $this->contextMenuY = max(12, $y);
        $this->contextMenuTarget = $target;
    }

    public function closeContextMenu(): void
    {
        $this->showContextMenu = false;
        $this->contextMenuTarget = null;
    }

    public function createBranchFromContextMenu(): void
    {
        $ref = $this->contextMenuTarget['ref'] ?? null;
        $this->closeContextMenu();

        if (is_string($ref) && $ref !== '') {
            $this->openCreateBranchFromRef($ref);
        }
    }

    public function createTagFromContextMenu(bool $annotated = false): void
    {
        $ref = $this->contextMenuTarget['ref'] ?? null;
        $this->closeContextMenu();

        if (is_string($ref) && $ref !== '') {
            $this->openCreateTagFromRef($ref, $annotated);
        }
    }

    public function revertContextMenuCommit(): void
    {
        if (($this->contextMenuTarget['type'] ?? null) !== 'commit') {
            return;
        }

        $hash = $this->contextMenuTarget['ref'] ?? null;
        $this->closeContextMenu();

        if (is_string($hash) && $hash !== '') {
            $this->revertCommit($hash);
        }
    }

    public function checkoutContextMenuBranchAction(): void
    {
        if (($this->contextMenuTarget['type'] ?? null) !== 'branch') {
            return;
        }

        $name = $this->contextMenuTarget['ref'] ?? null;
        $isCurrent = (bool) ($this->contextMenuTarget['isCurrent'] ?? false);
        $this->closeContextMenu();

        if (! $isCurrent && is_string($name) && $name !== '') {
            $this->checkoutGraphRef($name);
        }
    }

    public function deleteContextMenuBranchAction(): void
    {
        if (($this->contextMenuTarget['type'] ?? null) !== 'branch') {
            return;
        }

        $name = $this->contextMenuTarget['ref'] ?? null;
        $this->closeContextMenu();

        if (is_string($name) && $name !== '') {
            $this->deleteContextBranch($name);
        }
    }

    public function deleteContextMenuBranchAndRemoteAction(): void
    {
        if (($this->contextMenuTarget['type'] ?? null) !== 'branch') {
            return;
        }

        $name = $this->contextMenuTarget['ref'] ?? null;
        $this->closeContextMenu();

        if (is_string($name) && $name !== '') {
            $this->deleteBranchAndRemote($name);
        }
    }

    public function rebaseContextMenuBranchAction(): void
    {
        if (($this->contextMenuTarget['type'] ?? null) !== 'branch') {
            return;
        }

        $name = $this->contextMenuTarget['ref'] ?? null;
        $this->closeContextMenu();

        if (is_string($name) && $name !== '') {
            $this->openRebase($name);
        }
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

        // The graph can contain refs that are outside the bounded sidebar
        // snapshot. Resolve those against git before deciding how to check
        // them out instead of silently treating them as unavailable.
        $branch = $this->findBranchByName($branchName) ?? $this->findBranchInRepository($branchName);
        if ($branch === null) {
            $this->errorMessage = "Branch '{$branchName}' was not found.";

            return;
        }

        if ($branch['isRemote']) {
            $this->requestRemoteCheckout($branchName);

            return;
        }

        $this->checkoutLocalBranch($branchName);
    }

    private function loadWorkingTreeFileDiff(?GitService $git = null): void
    {
        try {
            if ($git === null) {
                $git = app(GitService::class);
                $git->open($this->path);
            }

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
            $this->selectedHistoryDiffs = $this->serializeDiffFiles($diffs, includeHunks: false);
        } catch (\RuntimeException $e) {
            $this->selectedHistoryDiffs = [];
        }
    }

    private function loadSelectedBranchHistory(string $name): void
    {
        try {
            $git = app(GitService::class);
            $git->open($this->path);

            $diffs = $git->getRefComparisonFiles($name);
            $this->selectedHistoryDiffs = $this->serializeDiffFiles($diffs, includeHunks: false);
        } catch (\RuntimeException $e) {
            $this->selectedHistoryDiffs = [];
        }
    }

    /**
     * Load only the selected history file's hunks. The file list above keeps
     * metadata only, so a large commit does not put its complete diff into the
     * Livewire snapshot.
     */
    private function loadSelectedHistoryFileDiff(string $path): void
    {
        if ($this->selectedHistoryType === null) {
            $this->diffFiles = [];

            return;
        }

        try {
            $git = app(GitService::class);
            $git->open($this->path);

            $diffs = $this->selectedHistoryType === 'commit'
                ? $git->getCommitDiff((string) $this->selectedCommit)
                : $git->getRefComparisonFileDiff((string) $this->selectedBranch, $path);

            if ($this->selectedHistoryType === 'commit') {
                $diffs = array_values(array_filter(
                    $diffs,
                    fn (DiffFile $diff) => $diff->path === $path,
                ));
            }

            $this->diffFiles = $this->serializeDiffFiles($diffs);
        } catch (\RuntimeException $e) {
            $this->diffFiles = [];
        }
    }

    // ─── STAGING ACTIONS ─────────────────────────────────────────────────

    /**
     * Stage a single file.
     */
    public function stageFile(string $path): void
    {
        $this->runGitAction(fn (GitService $git) => $git->stage([$path]), fullRefresh: false);
    }

    /**
     * Stage all files.
     */
    public function stageAll(): void
    {
        $this->runGitAction(fn (GitService $git) => $git->stageAll(), fullRefresh: false);
    }

    /**
     * Unstage a single file.
     */
    public function unstageFile(string $path): void
    {
        $file = collect($this->stagedFiles)->firstWhere('path', $path);
        $paths = array_values(array_unique(array_filter([
            $path,
            $file['origPath'] ?? null,
        ])));

        $this->runGitAction(fn (GitService $git) => $git->unstage($paths), fullRefresh: false);
    }

    /**
     * Unstage all files.
     */
    public function unstageAll(): void
    {
        $this->runGitAction(fn (GitService $git) => $git->unstageAll(), fullRefresh: false);
    }

    /**
     * Discard changes to a file.
     */
    public function discardFile(string $path): void
    {
        $this->runGitAction(fn (GitService $git) => $git->discardChanges([$path]), fullRefresh: false);
    }

    // ─── COMMIT ──────────────────────────────────────────────────────────

    public string $commitSummary = '';

    public string $commitDescription = '';

    /**
     * Create a commit from the current summary and description.
     */
    public function createCommit(): void
    {
        $summary = trim($this->commitSummary);
        $description = trim($this->commitDescription);

        if ($summary === '') {
            $this->errorMessage = 'Commit message cannot be empty.';

            return;
        }

        $msg = $summary.($description === '' ? '' : "\n\n{$description}");
        $this->runGitAction(function (GitService $git) use ($msg) {
            $git->commit($msg);
        }, 'Committed: '.Str::limit($summary, 50));

        if ($this->errorMessage === '') {
            $this->commitSummary = '';
            $this->commitDescription = '';
        }
    }

    // ─── BRANCH ACTIONS ──────────────────────────────────────────────────

    /** State for branch creation UI */
    public bool $showCreateBranch = false;

    public string $newBranchName = '';

    public string $newBranchStartPoint = '';

    /** State for merge UI */
    public bool $showMergeConfirm = false;

    public string $mergeBranchName = '';

    /** State for rebase UI */
    public bool $showRebaseConfirm = false;

    public string $rebaseTargetName = '';

    /**
     * Checkout an existing branch.
     */
    public function checkoutBranch(string $name): void
    {
        $this->checkoutLocalBranch($name);
    }

    /**
     * Checkout a local branch selected from the workspace header.
     */
    public function checkoutWorkspaceBranch(string $name): void
    {
        $branch = $this->findBranchByName($name);

        if (! $this->isActive || $branch === null || $branch['isRemote'] || $branch['isCurrent']) {
            return;
        }

        $this->checkoutLocalBranch($name);
    }

    /**
     * Checkout an existing local branch.
     */
    public function checkoutLocalBranch(string $name): void
    {
        $this->runGitAction(
            fn (GitService $git) => $git->checkout($name),
            "Switched to '{$name}'",
            resetGraphHistory: true,
        );
    }

    /**
     * Checkout an existing remote branch via a local tracking branch.
     */
    public function checkoutRemoteBranch(string $name): void
    {
        $this->requestRemoteCheckout($name);
    }

    /** Fetch a remote ref before choosing how its local tracking branch changes. */
    public function requestRemoteCheckout(string $name): void
    {
        $branch = $this->findBranchByName($name) ?? $this->findBranchInRepository($name);
        if ($branch === null || ! $branch['isRemote']) {
            $this->errorMessage = "Remote branch '{$name}' was not found.";

            return;
        }

        $this->pendingRemoteCheckout = $branch['name'];
        $this->startFetch();
    }

    public function closeRemoteCheckoutOptions(): void
    {
        $this->showRemoteCheckoutOptions = false;
        $this->remoteCheckoutRemote = null;
        $this->remoteCheckoutLocal = null;
        $this->remoteCheckoutCanFastForward = false;
    }

    /** Keep the existing local branch as-is and switch to it. */
    public function checkoutExistingRemoteChoice(): void
    {
        $localBranch = $this->remoteCheckoutLocal;
        $this->closeRemoteCheckoutOptions();

        if ($localBranch !== null) {
            $this->checkoutLocalBranch($localBranch);
        }
    }

    /** Fast-forward the existing local branch to its freshly fetched remote ref. */
    public function fastForwardRemoteChoice(): void
    {
        $localBranch = $this->remoteCheckoutLocal;
        $remoteRef = $this->remoteCheckoutRemote;
        $canFastForward = $this->remoteCheckoutCanFastForward;
        $this->closeRemoteCheckoutOptions();

        if ($localBranch === null || $remoteRef === null || ! $canFastForward) {
            return;
        }

        $this->runGitAction(
            fn (GitService $git) => $git->checkoutAndFastForward($localBranch, $remoteRef),
            "Fast-forwarded '{$localBranch}' to '{$remoteRef}'",
            resetGraphHistory: true,
        );
    }

    /** Replace the existing local branch with its freshly fetched remote ref. */
    public function resetRemoteCheckoutChoice(): void
    {
        $localBranch = $this->remoteCheckoutLocal;
        $remoteRef = $this->remoteCheckoutRemote;
        $this->closeRemoteCheckoutOptions();

        if ($localBranch === null || $remoteRef === null) {
            return;
        }

        $this->runGitAction(
            fn (GitService $git) => $git->checkoutAndResetToRemote($localBranch, $remoteRef),
            "Reset '{$localBranch}' to '{$remoteRef}'",
            resetGraphHistory: true,
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
     * Open the create branch form with a prefilled start point.
     */
    public function openCreateBranchFromRef(string $ref): void
    {
        $this->closeCreateTag();
        $this->closeCreateStash();
        $this->closeMerge();
        $this->errorMessage = '';
        $this->showCreateBranch = true;
        $this->newBranchName = '';
        $this->newBranchStartPoint = $ref;
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
        }, "Created and switched to '{$name}'", resetGraphHistory: true);

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
     * Delete the selected branch target, handling local and remote refs.
     */
    public function deleteContextBranch(string $name, bool $force = false): void
    {
        $branch = $this->findBranchByName($name);

        if ($branch !== null && $branch['isCurrent']) {
            $this->errorMessage = 'Cannot delete the currently checked out branch.';

            return;
        }

        if ($branch !== null && $branch['isRemote']) {
            $this->runGitAction(
                fn (GitService $git) => $git->deleteRemoteBranch($name),
                "Deleted branch '{$name}'"
            );

            return;
        }

        $this->deleteBranch($branch['name'] ?? $name, $force);
    }

    /**
     * Delete the selected branch and its paired remote branch.
     */
    public function deleteBranchAndRemote(string $name, bool $forceLocal = false): void
    {
        $branch = $this->findBranchByName($name);

        if ($branch !== null && $branch['isCurrent']) {
            $this->errorMessage = 'Cannot delete the currently checked out branch.';

            return;
        }

        $localName = $branch['localName'] ?? $this->localBranchNameFromRemote($name);
        $remoteName = $branch['pairedRemote'] ?? (str_contains($name, '/') ? $name : null);

        $message = $remoteName !== null
            ? "Deleted '{$localName}' and '{$remoteName}'"
            : "Deleted branch '{$localName}'";

        $this->runGitAction(
            fn (GitService $git) => $git->deleteBranchAndRemote($localName, $forceLocal, $remoteName),
            $message
        );
    }

    /**
     * Open merge confirmation for a branch.
     */
    public function openMerge(string $name): void
    {
        $this->closeRebase();
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
            $this->loadRepoData($git);

            if ($result->success) {
                $this->dispatch('toast', message: "Merged '{$branch}'", type: 'success');
            } else {
                $this->errorMessage = $result->error ?: 'Merge failed.';
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
        $this->abortCurrentOperation();
    }

    public function openRebase(string $target): void
    {
        $this->closeMerge();
        $this->showRebaseConfirm = true;
        $this->rebaseTargetName = $target;
    }

    public function closeRebase(): void
    {
        $this->showRebaseConfirm = false;
        $this->rebaseTargetName = '';
    }

    public function rebaseBranch(): void
    {
        $target = trim($this->rebaseTargetName);
        $this->closeRebase();

        if ($target === '' || $target === $this->currentBranch) {
            $this->errorMessage = 'Choose another branch to rebase onto.';

            return;
        }

        if (! ($this->status['isClean'] ?? false)) {
            $this->errorMessage = 'Commit or stash your changes before rebasing.';

            return;
        }

        $this->runRecoverableOperation(
            fn (GitService $git) => $git->rebase($target),
            "Rebased '{$this->currentBranch}' onto '{$target}'",
            'Rebase failed.'
        );
    }

    public function continueCurrentOperation(): void
    {
        if ($this->currentOperation === null || $this->currentOperation === 'conflict') {
            $this->errorMessage = 'Unable to determine which Git operation to continue.';

            return;
        }

        $operation = $this->currentOperation;
        $this->runRecoverableOperation(
            fn (GitService $git) => $git->continueOperation($operation),
            ucfirst($operation).' continued',
            'Unable to continue '.str_replace('-', ' ', $operation).'.'
        );
    }

    public function abortCurrentOperation(): void
    {
        if ($this->currentOperation === null || $this->currentOperation === 'conflict') {
            $this->errorMessage = 'Unable to determine which Git operation to abort.';

            return;
        }

        $operation = $this->currentOperation;
        $this->runGitAction(
            fn (GitService $git) => $git->abortOperation($operation),
            ucfirst($operation).' aborted'
        );
    }

    public function skipRebaseCommit(): void
    {
        if ($this->currentOperation !== 'rebase') {
            return;
        }

        $this->runRecoverableOperation(
            fn (GitService $git) => $git->skipRebaseCommit(),
            'Skipped commit and continued rebase',
            'Unable to skip this rebase commit.'
        );
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
     * Open the create tag form with a prefilled ref.
     */
    public function openCreateTagFromRef(string $ref, bool $annotated = false): void
    {
        $this->closeCreateBranch();
        $this->closeCreateStash();
        $this->closeMerge();
        $this->errorMessage = '';
        $this->showCreateTag = true;
        $this->newTagName = '';
        $this->newTagRef = $ref;
        $this->newTagAnnotated = $annotated;
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
            $this->loadRepoData($git);

            if ($result->success) {
                $this->dispatch('toast', message: "Applied {$ref}", type: 'success');
            } else {
                $this->errorMessage = $result->error ?: 'Failed to apply stash.';
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
            $this->loadRepoData($git);

            if ($result->success) {
                $this->dispatch('toast', message: "Popped {$ref}", type: 'success');
            } else {
                $this->errorMessage = $result->error ?: 'Failed to pop stash.';
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

    /** Recent stderr from the active remote operation. */
    public string $remoteErrorOutput = '';

    /**
     * Fetch from remote.
     */
    public function fetchRemote(): void
    {
        $this->pendingRemoteCheckout = null;
        $this->startFetch();
    }

    /** Start a fetch, preserving any remote checkout request until it finishes. */
    private function startFetch(): void
    {
        $this->errorMessage = '';

        if ($this->remoteOperation !== null) {
            return; // Already running an operation
        }

        if ($this->isNativeContext()) {
            $this->remoteOperation = 'fetch';
            $this->remoteProgress = 'Fetching...';
            $this->remoteErrorOutput = '';
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
                $this->loadRepoData($git);

                $pendingRemoteCheckout = $this->pendingRemoteCheckout;
                $this->pendingRemoteCheckout = null;
                if ($pendingRemoteCheckout !== null) {
                    $this->openRemoteCheckoutOptions($pendingRemoteCheckout);
                } else {
                    $this->dispatch('toast', message: 'Fetched from remote', type: 'success');
                }
            } catch (\RuntimeException $e) {
                $this->pendingRemoteCheckout = null;
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
            $this->remoteErrorOutput = '';
            ChildProcess::start(
                cmd: ['git', 'pull', '--no-rebase'],
                alias: $this->remoteOperationAlias(),
                cwd: $this->path,
            );
        } else {
            try {
                $git = app(GitService::class);
                $git->open($this->path);
                $result = $git->pull();
                $this->loadRepoData($git);

                if ($result->success) {
                    $this->dispatch('toast', message: 'Pulled from remote', type: 'success');
                } else {
                    $this->errorMessage = $result->error ?: 'Pull failed.';
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
            $this->remoteErrorOutput = '';

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
                $this->loadRepoData($git);
                $this->dispatch('toast', message: 'Pushed to remote', type: 'success');
            } catch (\RuntimeException $e) {
                $this->errorMessage = $e->getMessage();
            }
        }
    }

    /**
     * Handle ChildProcess exit for remote operations.
     */
    #[On('native:'.ProcessExited::class)]
    public function onRemoteProcessExited(string $alias, int $code): void
    {
        if ($alias !== $this->remoteOperationAlias() || $this->remoteOperation === null) {
            return;
        }

        $op = $this->remoteOperation;
        $errorOutput = trim($this->remoteErrorOutput);
        $pendingRemoteCheckout = $op === 'fetch' ? $this->pendingRemoteCheckout : null;
        $this->pendingRemoteCheckout = null;
        $this->remoteOperation = null;
        $this->remoteProgress = '';
        $this->remoteErrorOutput = '';
        $this->loadRepoData();

        if ($op === 'pull' && $this->status && $this->status['hasConflicts']) {
            $this->errorMessage = 'Pull resulted in merge conflicts. Resolve them and commit, or abort the merge.';
        } elseif ($code !== 0) {
            $label = ucfirst($op).' failed';
            $this->errorMessage = $errorOutput !== ''
                ? "{$label}: {$errorOutput}"
                : "{$label} with exit code {$code}.";
        } elseif ($pendingRemoteCheckout !== null) {
            $this->openRemoteCheckoutOptions($pendingRemoteCheckout);
        } else {
            $label = match ($op) {
                'fetch' => 'Fetched from remote',
                'pull' => 'Pulled from remote',
                'push' => 'Pushed to remote',
                default => ucfirst($op).' complete',
            };
            $this->dispatch('toast', message: $label, type: 'success');
        }
    }

    /** Present the post-fetch choices for an already-existing local branch. */
    private function openRemoteCheckoutOptions(string $remoteRef): void
    {
        $remoteBranch = $this->findBranchByName($remoteRef) ?? $this->findBranchInRepository($remoteRef);
        if ($remoteBranch === null || ! $remoteBranch['isRemote']) {
            $this->errorMessage = "Remote branch '{$remoteRef}' was not found after fetching.";

            return;
        }

        $localBranchName = $this->localBranchNameFromRemote($remoteBranch['name']);
        $localBranch = $this->findBranchByName($localBranchName) ?? $this->findBranchInRepository($localBranchName);

        if ($localBranch === null) {
            $this->runGitAction(
                fn (GitService $git) => $git->checkoutRemoteBranch($remoteBranch['name']),
                "Switched to '{$localBranchName}'",
                resetGraphHistory: true,
            );

            return;
        }

        try {
            $git = app(GitService::class);
            $git->open($this->path);

            $this->remoteCheckoutRemote = $remoteBranch['name'];
            $this->remoteCheckoutLocal = $localBranch['name'];
            $this->remoteCheckoutCanFastForward = $git->canFastForward(
                $localBranch['name'],
                $remoteBranch['name'],
            );
            $this->showRemoteCheckoutOptions = true;
        } catch (\RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    /**
     * Handle stderr progress from ChildProcess (git sends progress to stderr).
     */
    #[On('native:'.ErrorReceived::class)]
    public function onRemoteProgress(string $alias, mixed $data = null): void
    {
        if ($alias !== $this->remoteOperationAlias() || $this->remoteOperation === null) {
            return;
        }

        if (is_string($data)) {
            $this->remoteProgress = $data;
            $this->remoteErrorOutput = mb_substr($this->remoteErrorOutput.$data, -4000);
        } elseif (is_array($data) && isset($data['data'])) {
            $chunk = (string) $data['data'];
            $this->remoteProgress = $chunk;
            $this->remoteErrorOutput = mb_substr($this->remoteErrorOutput.$chunk, -4000);
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
            'target' => $this->targetCurrentCheckout(),
            'push' => $this->pushRemote(),
            'fetch' => $this->fetchRemote(),
            'pull' => $this->pullRemote(),
            'commit' => $this->createCommit(),
            'escape' => $this->closeTransientUi(),
            default => null,
        };
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────

    /**
     * Run a git action, refresh data on success, capture errors.
     * Optionally dispatches a success toast notification.
     */
    private function runGitAction(
        callable $action,
        ?string $successMessage = null,
        bool $resetGraphHistory = false,
        bool $fullRefresh = true,
    ): void {
        $this->errorMessage = '';

        try {
            $git = app(GitService::class);
            $git->open($this->path);
            $action($git);
            if ($resetGraphHistory) {
                $this->resetGraphHistory();
            }
            if ($fullRefresh) {
                $this->loadRepoData($git);
            } else {
                $this->refreshWorkingTreeData($git);
            }

            if ($successMessage) {
                $this->dispatch('toast', message: $successMessage, type: 'success');
            }
        } catch (\RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    private function runRecoverableOperation(
        callable $action,
        string $successMessage,
        string $fallbackError,
    ): void {
        $this->errorMessage = '';

        try {
            $git = app(GitService::class);
            $git->open($this->path);
            $result = $action($git);
            $this->loadRepoData($git);

            if ($result->success) {
                $this->dispatch('toast', message: $successMessage, type: 'success');
            } else {
                $this->errorMessage = $result->error ?: $fallbackError;
            }
        } catch (\RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    /**
     * Refresh only state affected by stage, unstage, and discard actions.
     * Commit history and refs cannot change during these operations, so avoid
     * re-querying them on every file click.
     */
    private function refreshWorkingTreeData(GitService $git): void
    {
        $state = $git->getStatus();
        $this->populateStatusFromState($state);
        $this->currentOperation = $git->getOperationState();

        if ($state->hasConflicts() && $this->currentOperation === null) {
            $this->currentOperation = 'conflict';
        }

        if ($this->selectedHistoryType === null && $this->selectedFile !== null) {
            $this->loadWorkingTreeFileDiff($git);
        }
    }

    private function closeTransientUi(): void
    {
        $this->closeContextMenu();
        $this->closeRemoteCheckoutOptions();
        $this->clearSelection();
        $this->closeCreateBranch();
        $this->closeCreateTag();
        $this->closeCreateStash();
        $this->closeMerge();
        $this->closeRebase();
    }

    private function remoteOperationAlias(): string
    {
        return 'git-remote-op-'.$this->tabId;
    }

    private function localBranchNameFromRemote(string $name): string
    {
        if (! str_contains($name, '/')) {
            return $name;
        }

        [, $localBranch] = explode('/', $name, 2);

        return $localBranch !== '' ? $localBranch : $name;
    }

    public function contextMenuBranchTarget(string $name): array
    {
        $branchName = $this->normalizeGraphBranchRef($name) ?? $name;
        $branch = $this->findBranchByName($branchName);

        $localName = $branch['localName'] ?? $this->localBranchNameFromRemote($branchName);
        $remoteName = $branch['pairedRemote'] ?? (str_contains($branchName, '/') ? $branchName : null);
        $isCurrent = (bool) ($branch['isCurrent'] ?? false);
        $isRemote = (bool) ($branch['isRemote'] ?? str_contains($branchName, '/'));

        return [
            'type' => 'branch',
            'ref' => $branchName,
            'displayName' => $branchName,
            'localName' => $localName,
            'remoteName' => $remoteName,
            'isCurrent' => $isCurrent,
            'isRemote' => $isRemote,
            'hasRemotePair' => $remoteName !== null,
        ];
    }

    private function findBranchByName(string $name): ?array
    {
        foreach (array_merge(
            $this->localBranches,
            $this->remoteBranches,
            $this->filteredLocalBranches,
            $this->filteredRemoteBranches,
        ) as $branch) {
            if ($branch['name'] === $name) {
                return $branch;
            }
        }

        return null;
    }

    private function resetGraphHistory(): void
    {
        $this->focusedHistoryRef = null;
        $this->historyLimit = self::HISTORY_LIMIT;
    }

    private function loadedCommitHashFor(string $hash): ?string
    {
        return $this->commitHashFrom($this->commits, $hash);
    }

    /** @param list<array<string, mixed>> $commits */
    private function commitHashFrom(array $commits, string $hash): ?string
    {
        foreach ($commits as $commit) {
            $candidate = (string) ($commit['hash'] ?? '');
            if ($candidate === $hash
                || ($hash !== '' && str_starts_with($candidate, $hash))
                || ($candidate !== '' && str_starts_with($hash, $candidate))) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Expand the all-refs graph in batches until a commit becomes visible.
     *
     * @throws \RuntimeException
     */
    private function expandAllHistoryUntil(string $hash): ?string
    {
        $this->focusedHistoryRef = null;
        $git = app(GitService::class);
        $git->open($this->path);
        $defaultCommits = [];

        for ($limit = self::HISTORY_LIMIT; $limit <= self::MAX_HISTORY_LIMIT; $limit += self::HISTORY_BATCH_SIZE) {
            $commits = $git->getLog(limit: $limit, all: true);
            $serializedCommits = $this->serializeCommits($commits, $limit);
            if ($limit === self::HISTORY_LIMIT) {
                $defaultCommits = $serializedCommits;
            }

            $commitHash = $this->commitHashFrom($serializedCommits, $hash);
            if ($commitHash === null) {
                continue;
            }

            $this->historyLimit = $limit;
            $this->commits = $serializedCommits;

            return $commitHash;
        }

        $this->historyLimit = self::HISTORY_LIMIT;
        $this->commits = $defaultCommits;

        return null;
    }

    /**
     * Resolve a branch directly from git when it is outside the bounded
     * Livewire sidebar snapshot.
     *
     * @return array<string, mixed>|null
     */
    private function findBranchInRepository(string $name): ?array
    {
        try {
            $git = app(GitService::class);
            $git->open($this->path);

            foreach ($git->getBranches() as $branch) {
                if ($branch->name !== $name) {
                    continue;
                }

                return [
                    'name' => $branch->name,
                    'hash' => $branch->hash,
                    'isCurrent' => $branch->isCurrent,
                    'isRemote' => $branch->isRemote,
                    'localName' => $branch->isRemote ? $this->localBranchNameFromRemote($branch->name) : $branch->name,
                    'upstream' => $branch->upstream,
                    'ahead' => $branch->ahead,
                    'behind' => $branch->behind,
                ];
            }
        } catch (\RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
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

        return $ref !== '' ? $ref : null;
    }

    private function restoreSelectedHistoryFile(): void
    {
        if ($this->selectedFile === null) {
            $this->diffFiles = [];

            return;
        }

        $this->loadSelectedHistoryFileDiff($this->selectedFile);
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
            localBranches: array_column($this->localBranches, 'name'),
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
        ], array_slice($files, 0, self::MAX_STATUS_FILES));
    }

    /**
     * @param  list<Commit>  $commits
     */
    private function serializeCommits(array $commits, int $limit = self::HISTORY_LIMIT): array
    {
        return array_map(function (Commit $c): array {
            return [
                'hash' => $c->hash,
                'shortHash' => $c->shortHash,
                'parents' => $c->parents,
                'author' => $c->author,
                'email' => $c->email,
                'date' => $c->date->toIso8601String(),
                'dateHuman' => $c->date->diffForHumans(),
                'message' => mb_substr($c->message, 0, self::MAX_COMMIT_MESSAGE_LENGTH),
                'description' => $c->description,
                'refs' => array_slice($c->refs, 0, self::MAX_COMMIT_REFS),
                'isMerge' => $c->isMerge(),
                'avatarUrl' => $this->avatarUrlForEmail($c->email),
                'avatarInitials' => $this->initialsForAuthor($c->author),
                'avatarHue' => $this->avatarHueForEmail($c->email),
            ];
        }, array_slice($commits, 0, $limit));
    }

    /**
     * @param  list<Tag>  $tags
     */
    private function serializeTags(array $tags, bool $bounded = true): array
    {
        if ($bounded) {
            $tags = array_slice($tags, 0, self::MAX_TAGS);
        }

        return array_map(fn (Tag $t) => [
            'name' => $t->name,
            'hash' => $t->hash,
            'commitHash' => $t->commitHash(),
            'isAnnotated' => $t->isAnnotated,
            'date' => $t->date?->toIso8601String(),
            'message' => $t->message !== null ? mb_substr($t->message, 0, self::MAX_TAG_MESSAGE_LENGTH) : null,
        ], $tags);
    }

    /**
     * @param  list<StashEntry>  $stashes
     */
    private function serializeStashes(array $stashes, bool $bounded = true): array
    {
        if ($bounded) {
            $stashes = array_slice($stashes, 0, self::MAX_STASHES);
        }

        return array_map(fn (StashEntry $s) => [
            'ref' => $s->ref,
            'hash' => $s->hash,
            'message' => mb_substr($s->message, 0, self::MAX_STASH_MESSAGE_LENGTH),
            'index' => $s->index(),
        ], $stashes);
    }

    /**
     * @param  list<DiffFile>  $diffs
     */
    private function serializeDiffFiles(array $diffs, bool $includeHunks = true): array
    {
        $serializedDiffs = [];
        $totalLines = 0;

        foreach (array_slice($diffs, 0, self::MAX_DIFF_FILES) as $d) {
            $serialized = [
                'path' => $d->path,
                'status' => $d->status,
                'oldPath' => $d->oldPath,
                'isBinary' => $d->isBinary,
                'additions' => $d->additions(),
                'deletions' => $d->deletions(),
            ];

            if (! $includeHunks) {
                $serializedDiffs[] = $serialized;

                continue;
            }

            $hunks = array_slice($d->hunks, 0, self::MAX_DIFF_HUNKS);
            $truncated = count($d->hunks) > count($hunks);
            $serialized['hunks'] = [];

            foreach ($hunks as $h) {
                $lines = [];

                foreach (array_slice($h->lines, 0, self::MAX_DIFF_LINES_PER_HUNK) as $l) {
                    if ($totalLines >= self::MAX_DIFF_LINES_TOTAL) {
                        $truncated = true;
                        break 2;
                    }

                    $lines[] = [
                        'type' => $l->type,
                        'content' => mb_substr($l->content, 0, self::MAX_DIFF_LINE_LENGTH),
                        'oldLine' => $l->oldLine,
                        'newLine' => $l->newLine,
                    ];
                    $totalLines++;
                }

                if (count($h->lines) > count($lines)) {
                    $truncated = true;
                }

                $serialized['hunks'][] = [
                    'header' => $h->header,
                    'oldStart' => $h->oldStart,
                    'oldCount' => $h->oldCount,
                    'newStart' => $h->newStart,
                    'newCount' => $h->newCount,
                    'lines' => $lines,
                ];
            }

            if ($truncated) {
                $serialized['isTruncated'] = true;
            }

            $serializedDiffs[] = $serialized;

            if ($totalLines >= self::MAX_DIFF_LINES_TOTAL) {
                break;
            }
        }

        return $serializedDiffs;
    }

    // ─── RENDER ──────────────────────────────────────────────────────────

    public function render()
    {
        return view('livewire.repo-view');
    }

    private function avatarUrlForEmail(string $email): string
    {
        return 'https://www.gravatar.com/avatar/'.md5(strtolower(trim($email))).'?s=64&d=mp';
    }

    private function initialsForAuthor(string $author): string
    {
        $parts = preg_split('/\s+/', trim($author)) ?: [];
        $initials = collect($parts)
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');

        return $initials !== '' ? $initials : '??';
    }

    private function avatarHueForEmail(string $email): int
    {
        return hexdec(substr(md5(strtolower(trim($email))), 0, 2)) % 360;
    }
}
