<?php

namespace App\Services\GitHub;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Interacts with the GitHub API for repository operations.
 * Requires an authenticated token from GitHubAuthService.
 */
class GitHubRepoService
{
    private string $apiBaseUrl;

    public function __construct(
        private readonly GitHubAuthService $authService,
    ) {
        $this->apiBaseUrl = config('services.github.api_base_url');
    }

    /**
     * List the authenticated user's repositories.
     *
     * @param  string  $sort  'updated', 'created', 'pushed', 'full_name'
     * @param  int  $perPage  Results per page (max 100)
     * @param  int  $page  Page number
     * @return array{repos: array, hasMore: bool}
     *
     * @throws \RuntimeException if not authenticated
     */
    public function listUserRepos(string $sort = 'updated', int $perPage = 30, int $page = 1): array
    {
        $response = $this->authenticatedRequest()
            ->get("{$this->apiBaseUrl}/user/repos", [
                'sort' => $sort,
                'per_page' => min($perPage, 100),
                'page' => $page,
                'affiliation' => 'owner',
            ]);

        if ($response->failed()) {
            $this->handleFailure($response, 'list user repos');
            return ['repos' => [], 'hasMore' => false];
        }

        $repos = collect($response->json())
            ->map(fn (array $repo) => $this->mapRepo($repo))
            ->all();

        // Check if there are more pages via Link header
        $hasMore = str_contains($response->header('Link', ''), 'rel="next"');

        return ['repos' => $repos, 'hasMore' => $hasMore];
    }

    /**
     * Search GitHub repositories (user's repos or all of GitHub).
     *
     * @param  string  $query  Search query
     * @param  bool  $userOnly  If true, restrict to the authenticated user's repos
     * @param  int  $perPage  Results per page (max 100)
     * @return array List of mapped repository data
     *
     * @throws \RuntimeException if not authenticated
     */
    public function searchRepos(string $query, bool $userOnly = false, int $perPage = 20): array
    {
        $searchQuery = $query;

        if ($userOnly) {
            $user = $this->authService->getUser();
            if ($user && ! empty($user['login'])) {
                $searchQuery = "{$query} user:{$user['login']}";
            }
        }

        $response = $this->authenticatedRequest()
            ->get("{$this->apiBaseUrl}/search/repositories", [
                'q' => $searchQuery,
                'sort' => 'updated',
                'per_page' => min($perPage, 100),
            ]);

        if ($response->failed()) {
            $this->handleFailure($response, 'search repos');
            return [];
        }

        $data = $response->json();

        return collect($data['items'] ?? [])
            ->map(fn (array $repo) => $this->mapRepo($repo))
            ->all();
    }

    /**
     * Get a single repository by owner/name.
     *
     * @return array|null Repository data or null if not found
     *
     * @throws \RuntimeException if not authenticated
     */
    public function getRepo(string $owner, string $name): ?array
    {
        $response = $this->authenticatedRequest()
            ->get("{$this->apiBaseUrl}/repos/{$owner}/{$name}");

        if ($response->failed()) {
            if ($response->status() === 404) {
                return null;
            }
            $this->handleFailure($response, "get repo {$owner}/{$name}");
            return null;
        }

        return $this->mapRepo($response->json());
    }

    /**
     * Map a GitHub API repo response to a simplified array.
     */
    private function mapRepo(array $repo): array
    {
        return [
            'id' => $repo['id'],
            'full_name' => $repo['full_name'],
            'name' => $repo['name'],
            'owner' => $repo['owner']['login'] ?? '',
            'description' => $repo['description'] ?? '',
            'private' => $repo['private'] ?? false,
            'clone_url' => $repo['clone_url'] ?? '',
            'ssh_url' => $repo['ssh_url'] ?? '',
            'html_url' => $repo['html_url'] ?? '',
            'default_branch' => $repo['default_branch'] ?? 'main',
            'language' => $repo['language'] ?? '',
            'stargazers_count' => $repo['stargazers_count'] ?? 0,
            'updated_at' => $repo['updated_at'] ?? '',
        ];
    }

    /**
     * Create an authenticated HTTP client.
     *
     * @throws \RuntimeException if no token is available
     */
    private function authenticatedRequest(): \Illuminate\Http\Client\PendingRequest
    {
        $token = $this->authService->getToken();

        if ($token === null) {
            throw new \RuntimeException('Not authenticated with GitHub. Please log in first.');
        }

        return Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/vnd.github.v3+json',
        ])->timeout(15);
    }

    /**
     * Handle a failed API response.
     */
    private function handleFailure(\Illuminate\Http\Client\Response $response, string $operation): void
    {
        $status = $response->status();
        $body = $response->body();

        Log::error("GitHub API failure: {$operation}", [
            'status' => $status,
            'body' => $body,
        ]);

        // If 401, the token may be invalid — clear it
        if ($status === 401) {
            Log::warning('GitHub token appears invalid, clearing stored token.');
            $this->authService->clearToken();
            throw new \RuntimeException('GitHub authentication expired. Please log in again.');
        }
    }
}
