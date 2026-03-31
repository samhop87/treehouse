<?php

namespace App\Livewire;

use App\Services\GitHub\GitHubAuthService;
use App\Services\GitHub\GitHubRepoService;
use App\Services\RepoManager;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Native\Desktop\Dialog;
use Native\Desktop\Events\ChildProcess\ProcessExited;
use Native\Desktop\Events\ChildProcess\ErrorReceived;
use Native\Desktop\Facades\ChildProcess;

#[Layout('components.layouts.app')]
#[Title('Clone Repository — Treehouse')]
class CloneRepo extends Component
{
    // State: idle → cloning → success → error
    public string $state = 'idle';

    // Repo picker
    public string $filterQuery = '';
    public string $cloneUrl = '';
    public array $allRepos = [];
    public bool $isLoadingRepos = false;

    // Selected repo info
    public string $selectedRepoName = '';
    public string $selectedRepoDescription = '';
    public bool $selectedRepoPrivate = false;

    // Destination
    public string $destinationParent = '';
    public string $destinationPath = '';

    // Clone progress
    public string $cloneProgress = '';
    public string $errorMessage = '';

    // Auth state
    public bool $isGitHubConnected = false;

    public function mount(GitHubAuthService $authService): void
    {
        $this->isGitHubConnected = $authService->hasToken();

        // Default destination to home directory
        $this->destinationParent = $_SERVER['HOME'] ?? '/tmp';

        // Eagerly load all user-owned repos
        if ($this->isGitHubConnected) {
            $this->loadAllRepos();
        }
    }

    /**
     * Fetch all user-owned repos from GitHub (paginating if needed).
     */
    private function loadAllRepos(): void
    {
        $this->isLoadingRepos = true;

        try {
            $repoService = app(GitHubRepoService::class);
            $allRepos = [];
            $page = 1;

            do {
                $result = $repoService->listUserRepos(sort: 'updated', perPage: 100, page: $page);
                $allRepos = array_merge($allRepos, $result['repos']);
                $page++;
            } while ($result['hasMore'] && $page <= 10); // Safety cap at 1000 repos

            $this->allRepos = $allRepos;
        } catch (\Exception $e) {
            $this->errorMessage = $e->getMessage();
        }

        $this->isLoadingRepos = false;
    }

    /**
     * Get repos filtered by the current query (case-insensitive match on name/description).
     */
    public function filteredRepos(): array
    {
        if (trim($this->filterQuery) === '') {
            return $this->allRepos;
        }

        $query = mb_strtolower(trim($this->filterQuery));

        return array_values(array_filter($this->allRepos, function (array $repo) use ($query) {
            return str_contains(mb_strtolower($repo['full_name']), $query)
                || str_contains(mb_strtolower($repo['name']), $query)
                || str_contains(mb_strtolower($repo['description'] ?? ''), $query);
        }));
    }

    /**
     * Select a repo from the filtered list.
     */
    public function selectRepo(int $index): void
    {
        $filtered = $this->filteredRepos();

        if (! isset($filtered[$index])) {
            return;
        }

        $repo = $filtered[$index];
        $this->cloneUrl = $repo['clone_url'];
        $this->selectedRepoName = $repo['full_name'];
        $this->selectedRepoDescription = $repo['description'] ?? '';
        $this->selectedRepoPrivate = $repo['private'] ?? false;
        $this->filterQuery = '';

        $this->updateDestinationPath();
    }

    /**
     * Set the clone URL manually (when not using search).
     */
    public function setManualUrl(): void
    {
        $this->errorMessage = '';

        if (empty($this->cloneUrl)) {
            return;
        }

        // Extract repo name from URL
        $name = basename($this->cloneUrl, '.git');
        $this->selectedRepoName = $name;
        $this->selectedRepoDescription = '';
        $this->selectedRepoPrivate = false;

        $this->updateDestinationPath();
    }

    /**
     * Open folder picker for destination.
     */
    public function chooseDestination(): void
    {
        if (! $this->isNativeContext()) {
            return;
        }

        $path = Dialog::new()
            ->title('Choose Clone Destination')
            ->folders()
            ->button('Select')
            ->defaultPath($this->destinationParent)
            ->open();

        if (! empty($path)) {
            $this->destinationParent = $path;
            $this->updateDestinationPath();
        }
    }

    /**
     * Start the clone operation.
     */
    public function startClone(RepoManager $repoManager): void
    {
        $this->errorMessage = '';

        if (empty($this->cloneUrl)) {
            $this->errorMessage = 'Please enter a repository URL.';
            return;
        }

        if (empty($this->destinationPath)) {
            $this->errorMessage = 'Please choose a destination folder.';
            return;
        }

        if (! $repoManager->validateCloneDestination($this->destinationPath)) {
            $this->errorMessage = "Destination already exists: {$this->destinationPath}";
            return;
        }

        if (! is_dir($this->destinationParent)) {
            $this->errorMessage = "Parent directory does not exist: {$this->destinationParent}";
            return;
        }

        $this->state = 'cloning';
        $this->cloneProgress = 'Starting clone...';

        $cmd = $repoManager->buildCloneCommand($this->cloneUrl, $this->destinationPath);

        if ($this->isNativeContext()) {
            // Use ChildProcess for async clone with progress
            ChildProcess::start(
                cmd: $cmd,
                alias: 'git-clone',
                cwd: $this->destinationParent,
            );
        } else {
            // Fallback for browser dev: use sync Process
            $result = \Illuminate\Support\Facades\Process::run(implode(' ', array_map('escapeshellarg', $cmd)));

            if ($result->successful()) {
                $this->handleCloneSuccess();
            } else {
                $this->state = 'error';
                $this->errorMessage = 'Clone failed: ' . $result->errorOutput();
            }
        }
    }

    /**
     * Handle clone process exit (from ChildProcess event).
     */
    #[On('native:' . ProcessExited::class)]
    public function onProcessExited(): void
    {
        if ($this->state !== 'cloning') {
            return;
        }

        // Check if the clone destination was created successfully
        if (is_dir($this->destinationPath . '/.git')) {
            $this->handleCloneSuccess();
        } else {
            $this->state = 'error';
            $this->errorMessage = 'Clone process exited but repository was not created.';
        }
    }

    /**
     * Handle stderr output from clone (progress info).
     */
    #[On('native:' . ErrorReceived::class)]
    public function onCloneProgress($data = null): void
    {
        if ($this->state !== 'cloning') {
            return;
        }

        // Git clone sends progress to stderr
        if (is_string($data)) {
            $this->cloneProgress = $data;
        } elseif (is_array($data) && isset($data['data'])) {
            $this->cloneProgress = $data['data'];
        }
    }

    /**
     * Go to the cloned repo.
     */
    public function openClonedRepo(): void
    {
        $this->redirect("/repo?path=" . urlencode($this->destinationPath));
    }

    /**
     * Reset to try again.
     */
    public function reset_form(): void
    {
        $this->state = 'idle';
        $this->cloneUrl = '';
        $this->selectedRepoName = '';
        $this->selectedRepoDescription = '';
        $this->filterQuery = '';
        $this->cloneProgress = '';
        $this->errorMessage = '';
        $this->updateDestinationPath();
    }

    private function handleCloneSuccess(): void
    {
        $this->state = 'success';
        $this->cloneProgress = '';

        // Track in recent repos
        try {
            app(RepoManager::class)->open($this->destinationPath);
        } catch (\Exception $e) {
            // Non-critical, just tracking
        }
    }

    private function updateDestinationPath(): void
    {
        if (! empty($this->selectedRepoName) && ! empty($this->destinationParent)) {
            // selectedRepoName may be "owner/repo" — use just the repo part for the directory
            $dirName = str_contains($this->selectedRepoName, '/')
                ? substr($this->selectedRepoName, strpos($this->selectedRepoName, '/') + 1)
                : $this->selectedRepoName;

            $this->destinationPath = app(RepoManager::class)
                ->resolveCloneDestination($this->destinationParent, $dirName);
        }
    }

    private function isNativeContext(): bool
    {
        return (bool) config('nativephp-internal.running', false);
    }

    public function render()
    {
        return view('livewire.clone-repo');
    }
}
