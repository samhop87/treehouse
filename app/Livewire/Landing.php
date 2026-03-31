<?php

namespace App\Livewire;

use App\Events\OpenRepoRequested;
use App\Services\GitHub\GitHubAuthService;
use App\Services\RepoManager;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Native\Desktop\Dialog;

#[Layout('components.layouts.app')]
#[Title('Treehouse')]
class Landing extends Component
{
    public bool $isGitHubConnected = false;
    public ?array $gitHubUser = null;
    public array $recentRepos = [];
    public string $errorMessage = '';

    public function mount(GitHubAuthService $authService, RepoManager $repoManager): void
    {
        $this->isGitHubConnected = $authService->hasToken();
        if ($this->isGitHubConnected) {
            $this->gitHubUser = $authService->getUser();
        }

        $this->loadRecentRepos($repoManager);
    }

    /**
     * Handle the "Open Repository" action — opens a native folder picker.
     */
    public function openRepo(RepoManager $repoManager): void
    {
        $this->errorMessage = '';

        if (! $this->isNativeContext()) {
            $this->errorMessage = 'Folder picker is only available in the desktop app.';
            return;
        }

        $path = Dialog::new()
            ->title('Open Git Repository')
            ->folders()
            ->button('Open')
            ->open();

        if (empty($path)) {
            return; // User cancelled
        }

        $this->openRepoByPath($path, $repoManager);
    }

    /**
     * Open a repo by its path (used by both folder picker and recent list).
     */
    public function openRepoByPath(string $path, ?RepoManager $repoManager = null): void
    {
        $repoManager ??= app(RepoManager::class);
        $this->errorMessage = '';

        try {
            $recentRepo = $repoManager->open($path);
            // Navigate to the repo view (placeholder for now — will be implemented in Phase 3)
            $this->redirect("/repo?path=" . urlencode($path));
        } catch (\RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    /**
     * Remove a repo from the recent list.
     */
    public function removeRecent(string $path, RepoManager $repoManager): void
    {
        $repoManager->removeFromRecent($path);
        $this->loadRecentRepos($repoManager);
    }

    /**
     * Handle menu event: Open Repository (Cmd+O).
     */
    #[On('native:' . OpenRepoRequested::class)]
    public function onMenuOpenRepo(): void
    {
        $this->openRepo(app(RepoManager::class));
    }

    public function disconnectGitHub(GitHubAuthService $authService): void
    {
        $authService->clearToken();
        $this->isGitHubConnected = false;
        $this->gitHubUser = null;
    }

    private function loadRecentRepos(?RepoManager $repoManager = null): void
    {
        $repoManager ??= app(RepoManager::class);

        try {
            $this->recentRepos = $repoManager->getRecentRepos()
                ->map(fn ($repo) => [
                    'name' => $repo->name,
                    'path' => $repo->path,
                    'branch' => $repo->branch,
                    'last_opened_at' => $repo->last_opened_at->diffForHumans(),
                ])
                ->all();
        } catch (\Exception $e) {
            $this->recentRepos = [];
        }
    }

    private function isNativeContext(): bool
    {
        return (bool) config('nativephp-internal.running', false);
    }

    public function render()
    {
        return view('livewire.landing');
    }
}
