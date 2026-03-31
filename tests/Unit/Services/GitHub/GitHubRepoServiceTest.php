<?php

namespace Tests\Unit\Services\GitHub;

use App\Services\GitHub\GitHubAuthService;
use App\Services\GitHub\GitHubRepoService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Native\Desktop\Facades\Settings;
use Tests\TestCase;

class GitHubRepoServiceTest extends TestCase
{
    private GitHubRepoService $service;
    private GitHubAuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.github.client_id' => 'test-client-id',
            'services.github.device_code_url' => 'https://github.com/login/device/code',
            'services.github.access_token_url' => 'https://github.com/login/oauth/access_token',
            'services.github.api_base_url' => 'https://api.github.com',
            'services.github.scopes' => 'repo',
            'nativephp-internal.running' => true,
        ]);

        $this->authService = new GitHubAuthService();
        $this->service = new GitHubRepoService($this->authService);
    }

    private function mockAuthenticatedToken(): void
    {
        $encrypted = Crypt::encryptString('gho_test_token');
        Settings::shouldReceive('get')
            ->with('github_token')
            ->andReturn($encrypted);
    }

    private function sampleRepo(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'full_name' => 'testuser/my-repo',
            'name' => 'my-repo',
            'owner' => ['login' => 'testuser'],
            'description' => 'A test repository',
            'private' => false,
            'clone_url' => 'https://github.com/testuser/my-repo.git',
            'ssh_url' => 'git@github.com:testuser/my-repo.git',
            'html_url' => 'https://github.com/testuser/my-repo',
            'default_branch' => 'main',
            'language' => 'PHP',
            'stargazers_count' => 42,
            'updated_at' => '2025-01-15T10:00:00Z',
        ], $overrides);
    }

    public function test_list_user_repos_returns_mapped_repos(): void
    {
        $this->mockAuthenticatedToken();

        Http::fake([
            'api.github.com/user/repos*' => Http::response([
                $this->sampleRepo(),
                $this->sampleRepo(['id' => 2, 'full_name' => 'testuser/other-repo', 'name' => 'other-repo']),
            ], 200),
        ]);

        $result = $this->service->listUserRepos();

        $this->assertCount(2, $result['repos']);
        $this->assertFalse($result['hasMore']);

        $repo = $result['repos'][0];
        $this->assertEquals(1, $repo['id']);
        $this->assertEquals('testuser/my-repo', $repo['full_name']);
        $this->assertEquals('my-repo', $repo['name']);
        $this->assertEquals('testuser', $repo['owner']);
        $this->assertEquals('A test repository', $repo['description']);
        $this->assertFalse($repo['private']);
        $this->assertEquals('https://github.com/testuser/my-repo.git', $repo['clone_url']);
        $this->assertEquals('main', $repo['default_branch']);
        $this->assertEquals('PHP', $repo['language']);
    }

    public function test_list_user_repos_detects_pagination(): void
    {
        $this->mockAuthenticatedToken();

        Http::fake([
            'api.github.com/user/repos*' => Http::response(
                [$this->sampleRepo()],
                200,
                ['Link' => '<https://api.github.com/user/repos?page=2>; rel="next"']
            ),
        ]);

        $result = $this->service->listUserRepos();

        $this->assertTrue($result['hasMore']);
    }

    public function test_list_user_repos_returns_empty_on_failure(): void
    {
        $this->mockAuthenticatedToken();

        Http::fake([
            'api.github.com/user/repos*' => Http::response('error', 500),
        ]);

        $result = $this->service->listUserRepos();

        $this->assertEmpty($result['repos']);
        $this->assertFalse($result['hasMore']);
    }

    public function test_list_user_repos_throws_when_not_authenticated(): void
    {
        Settings::shouldReceive('get')
            ->with('github_token')
            ->andReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not authenticated with GitHub');

        $this->service->listUserRepos();
    }

    public function test_search_repos_returns_results(): void
    {
        $this->mockAuthenticatedToken();

        Http::fake([
            'api.github.com/search/repositories*' => Http::response([
                'total_count' => 1,
                'items' => [$this->sampleRepo()],
            ], 200),
        ]);

        $result = $this->service->searchRepos('my-repo');

        $this->assertCount(1, $result);
        $this->assertEquals('testuser/my-repo', $result[0]['full_name']);
    }

    public function test_search_repos_user_only_adds_user_filter(): void
    {
        $this->mockAuthenticatedToken();

        Settings::shouldReceive('get')
            ->with('github_user')
            ->andReturn(json_encode(['login' => 'testuser', 'name' => 'Test', 'avatar_url' => '']));

        Http::fake([
            'api.github.com/search/repositories*' => Http::response([
                'total_count' => 0,
                'items' => [],
            ], 200),
        ]);

        $this->service->searchRepos('query', userOnly: true);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'user%3Atestuser')
                || str_contains(urldecode($request->url()), 'user:testuser');
        });
    }

    public function test_search_repos_returns_empty_on_failure(): void
    {
        $this->mockAuthenticatedToken();

        Http::fake([
            'api.github.com/search/repositories*' => Http::response('error', 500),
        ]);

        $result = $this->service->searchRepos('test');

        $this->assertEmpty($result);
    }

    public function test_get_repo_returns_mapped_repo(): void
    {
        $this->mockAuthenticatedToken();

        Http::fake([
            'api.github.com/repos/testuser/my-repo' => Http::response($this->sampleRepo(), 200),
        ]);

        $result = $this->service->getRepo('testuser', 'my-repo');

        $this->assertNotNull($result);
        $this->assertEquals('testuser/my-repo', $result['full_name']);
    }

    public function test_get_repo_returns_null_for_not_found(): void
    {
        $this->mockAuthenticatedToken();

        Http::fake([
            'api.github.com/repos/testuser/missing' => Http::response(['message' => 'Not Found'], 404),
        ]);

        $result = $this->service->getRepo('testuser', 'missing');

        $this->assertNull($result);
    }

    public function test_401_response_clears_token_and_throws(): void
    {
        $this->mockAuthenticatedToken();

        Settings::shouldReceive('set')
            ->with('github_token', null)
            ->once();
        Settings::shouldReceive('set')
            ->with('github_user', null)
            ->once();

        Http::fake([
            'api.github.com/user/repos*' => Http::response(['message' => 'Bad credentials'], 401),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('GitHub authentication expired');

        $this->service->listUserRepos();
    }
}
