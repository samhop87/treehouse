<?php

namespace App\Livewire;

use App\Events\OpenRepoRequested;
use App\Services\RepoManager;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Native\Desktop\Dialog;

#[Layout('components.layouts.app')]
#[Title('Treehouse')]
class Workspace extends Component
{
    #[Url]
    public string $path = '';

    /** @var array<int, array{id: string, path: string, repoName: string, currentBranch: ?string, isDetached: bool}> */
    public array $tabs = [];

    public ?string $activeTabId = null;

    public string $errorMessage = '';

    private bool $isSyncingPath = false;

    public function mount(RepoManager $repoManager): void
    {
        if (trim($this->path) === '') {
            $this->redirect('/');
            return;
        }

        if (! $this->openOrActivateTab($this->path, $repoManager) && count($this->tabs) === 0) {
            $this->redirect('/');
        }
    }

    public function updatedPath(string $path): void
    {
        if ($this->isSyncingPath || trim($path) === '') {
            return;
        }

        $this->openOrActivateTab($path);
    }

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
            return;
        }

        $this->openOrActivateTab($path, $repoManager);
    }

    public function activateTab(string $tabId): void
    {
        if ($this->findTabIndexById($tabId) === null) {
            return;
        }

        $this->activeTabId = $tabId;
        $this->syncPathToActiveTab();
    }

    public function openRepoByPath(string $path): void
    {
        $this->openOrActivateTab($path);
    }

    public function closeTab(string $tabId): void
    {
        $index = $this->findTabIndexById($tabId);
        if ($index === null) {
            return;
        }

        $wasActive = $this->activeTabId === $tabId;

        array_splice($this->tabs, $index, 1);

        if (count($this->tabs) === 0) {
            $this->activeTabId = null;
            $this->redirect('/');
            return;
        }

        if ($wasActive) {
            $nextIndex = min($index, count($this->tabs) - 1);
            $this->activeTabId = $this->tabs[$nextIndex]['id'];
        }

        $this->syncPathToActiveTab();
    }

    #[On('workspace-tab-context-updated')]
    public function updateTabContext(
        string $tabId,
        string $repoName,
        ?string $currentBranch = null,
        ?string $path = null,
        bool $isDetached = false,
    ): void {
        $index = $this->findTabIndexById($tabId);
        if ($index === null) {
            return;
        }

        $this->tabs[$index]['repoName'] = $repoName;
        $this->tabs[$index]['currentBranch'] = $currentBranch;
        $this->tabs[$index]['isDetached'] = $isDetached;

        if ($path !== null && $path !== '') {
            $this->tabs[$index]['path'] = $path;
        }

        if ($this->activeTabId === $tabId) {
            $this->syncPathToActiveTab();
        }
    }

    #[On('native:' . OpenRepoRequested::class)]
    public function onMenuOpenRepo(): void
    {
        $this->openRepo(app(RepoManager::class));
    }

    private function openOrActivateTab(string $path, ?RepoManager $repoManager = null): bool
    {
        $repoManager ??= app(RepoManager::class);
        $this->errorMessage = '';

        try {
            $normalizedPath = $this->normalizePath($path);
            $recentRepo = $repoManager->open($normalizedPath);
            $existingIndex = $this->findTabIndexByPath($normalizedPath);

            if ($existingIndex !== null) {
                $this->tabs[$existingIndex]['repoName'] = $recentRepo->name;
                $this->tabs[$existingIndex]['currentBranch'] = $recentRepo->branch;
                $this->tabs[$existingIndex]['isDetached'] = $recentRepo->branch === null;
                $this->activeTabId = $this->tabs[$existingIndex]['id'];
            } else {
                $tabId = $this->makeTabId($normalizedPath);

                $this->tabs[] = [
                    'id' => $tabId,
                    'path' => $normalizedPath,
                    'repoName' => $recentRepo->name,
                    'currentBranch' => $recentRepo->branch,
                    'isDetached' => $recentRepo->branch === null,
                ];

                $this->activeTabId = $tabId;
            }

            $this->syncPathToActiveTab();

            return true;
        } catch (\RuntimeException $e) {
            $this->errorMessage = $e->getMessage();

            return false;
        }
    }

    private function syncPathToActiveTab(): void
    {
        $activeTab = $this->getActiveTab();
        if ($activeTab === null) {
            return;
        }

        $this->isSyncingPath = true;
        $this->path = $activeTab['path'];
        $this->isSyncingPath = false;
    }

    private function normalizePath(string $path): string
    {
        $trimmed = rtrim($path, '/');

        return realpath($trimmed) ?: $trimmed;
    }

    private function findTabIndexByPath(string $path): ?int
    {
        foreach ($this->tabs as $index => $tab) {
            if ($tab['path'] === $path) {
                return $index;
            }
        }

        return null;
    }

    private function findTabIndexById(string $tabId): ?int
    {
        foreach ($this->tabs as $index => $tab) {
            if ($tab['id'] === $tabId) {
                return $index;
            }
        }

        return null;
    }

    private function makeTabId(string $path): string
    {
        return 'tab-' . substr(md5($path), 0, 12);
    }

    private function getActiveTab(): ?array
    {
        if ($this->activeTabId === null) {
            return null;
        }

        $index = $this->findTabIndexById($this->activeTabId);

        return $index === null ? null : $this->tabs[$index];
    }

    private function isNativeContext(): bool
    {
        return (bool) config('nativephp-internal.running', false);
    }

    public function render()
    {
        return view('livewire.workspace', [
            'activeTab' => $this->getActiveTab(),
        ]);
    }
}
