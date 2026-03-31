<?php

namespace App\Services;

use App\Models\RecentRepo;
use App\Services\Git\GitCommandRunner;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Manages repository operations: open, validate, track recent, clone.
 * Acts as the bridge between the UI layer and the Git layer.
 */
class RepoManager
{
    private ?string $currentRepoPath = null;

    public function __construct(
        private readonly GitCommandRunner $git,
    ) {}

    /**
     * Open a repository by path. Validates it's a real git repo
     * and tracks it in recent repos.
     *
     * @throws \RuntimeException if path is not a valid git repo
     */
    public function open(string $path): RecentRepo
    {
        $path = rtrim($path, '/');

        if (! is_dir($path)) {
            throw new \RuntimeException("Directory does not exist: {$path}");
        }

        if (! $this->git->setRepoPath($path)->isValidRepo()) {
            throw new \RuntimeException("Not a Git repository: {$path}");
        }

        $this->currentRepoPath = $path;
        $name = basename($path);

        // Get current branch name
        $branch = $this->getCurrentBranch($path);

        // Upsert into recent repos
        $recentRepo = RecentRepo::updateOrCreate(
            ['path' => $path],
            [
                'name' => $name,
                'branch' => $branch,
                'last_opened_at' => now(),
            ]
        );

        return $recentRepo;
    }

    /**
     * Validate a path is a git repository without opening it.
     */
    public function isValidRepo(string $path): bool
    {
        $path = rtrim($path, '/');

        if (! is_dir($path)) {
            return false;
        }

        return $this->git->setRepoPath($path)->isValidRepo();
    }

    /**
     * Get the currently opened repo path.
     */
    public function getCurrentRepoPath(): ?string
    {
        return $this->currentRepoPath;
    }

    /**
     * Set the current repo path (used when restoring state).
     */
    public function setCurrentRepoPath(?string $path): void
    {
        $this->currentRepoPath = $path;
    }

    /**
     * Get recent repositories, filtering out those that no longer exist on disk.
     */
    public function getRecentRepos(int $limit = 10): Collection
    {
        $repos = RecentRepo::recent($limit)->get();

        // Filter out repos that no longer exist, and clean them from DB
        $valid = $repos->filter(function (RecentRepo $repo) {
            if ($repo->existsOnDisk()) {
                return true;
            }
            $repo->delete();
            return false;
        });

        return $valid->values();
    }

    /**
     * Remove a repo from the recent list.
     */
    public function removeFromRecent(string $path): void
    {
        RecentRepo::where('path', $path)->delete();
    }

    /**
     * Clear all recent repos.
     */
    public function clearRecentRepos(): void
    {
        RecentRepo::truncate();
    }

    /**
     * Build the git clone command for a given URL and destination.
     * Returns the command array for use with ChildProcess.
     */
    public function buildCloneCommand(string $url, string $destination, ?string $branch = null): array
    {
        $args = ['git', 'clone', '--progress'];

        if ($branch) {
            $args[] = '--branch';
            $args[] = $branch;
        }

        $args[] = $url;
        $args[] = $destination;

        return $args;
    }

    /**
     * Get the clone destination path for a repo name within a parent directory.
     */
    public function resolveCloneDestination(string $parentDir, string $repoName): string
    {
        return rtrim($parentDir, '/') . '/' . $repoName;
    }

    /**
     * Validate a clone destination doesn't already exist.
     */
    public function validateCloneDestination(string $destination): bool
    {
        return ! file_exists($destination);
    }

    /**
     * Get the current branch name for a repo path.
     */
    private function getCurrentBranch(string $path): ?string
    {
        $result = $this->git->setRepoPath($path)->run(['rev-parse', '--abbrev-ref', 'HEAD']);

        if ($result->success) {
            $branch = trim($result->output);
            return $branch === 'HEAD' ? null : $branch; // null if detached
        }

        return null;
    }
}
