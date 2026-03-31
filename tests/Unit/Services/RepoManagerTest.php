<?php

namespace Tests\Unit\Services;

use App\Models\RecentRepo;
use App\Services\Git\GitCommandRunner;
use App\Services\Git\GitErrorTranslator;
use App\Services\RepoManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepoManagerTest extends TestCase
{
    use RefreshDatabase;

    private RepoManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = new RepoManager(new GitCommandRunner(new GitErrorTranslator()));
    }

    public function test_open_valid_repo(): void
    {
        $path = '/Users/samhopkinson/webroot/ptp';

        $recentRepo = $this->manager->open($path);

        $this->assertEquals('ptp', $recentRepo->name);
        $this->assertEquals($path, $recentRepo->path);
        $this->assertNotNull($recentRepo->last_opened_at);
        $this->assertDatabaseHas('recent_repos', ['path' => $path]);
    }

    public function test_open_sets_current_repo_path(): void
    {
        $path = '/Users/samhopkinson/webroot/ptp';

        $this->manager->open($path);

        $this->assertEquals($path, $this->manager->getCurrentRepoPath());
    }

    public function test_open_detects_branch(): void
    {
        $path = '/Users/samhopkinson/webroot/ptp';

        $recentRepo = $this->manager->open($path);

        // Should have a branch (likely 'main' or 'master')
        $this->assertNotNull($recentRepo->branch);
    }

    public function test_open_throws_for_nonexistent_directory(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Directory does not exist');

        $this->manager->open('/nonexistent/path');
    }

    public function test_open_throws_for_non_git_directory(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not a Git repository');

        $this->manager->open('/tmp');
    }

    public function test_open_updates_existing_recent_repo(): void
    {
        $path = '/Users/samhopkinson/webroot/ptp';

        // Open twice
        $this->manager->open($path);
        $this->manager->open($path);

        // Should only have one entry
        $this->assertEquals(1, RecentRepo::where('path', $path)->count());
    }

    public function test_is_valid_repo_for_real_repo(): void
    {
        $this->assertTrue($this->manager->isValidRepo('/Users/samhopkinson/webroot/ptp'));
    }

    public function test_is_valid_repo_for_non_repo(): void
    {
        $this->assertFalse($this->manager->isValidRepo('/tmp'));
    }

    public function test_is_valid_repo_for_nonexistent_path(): void
    {
        $this->assertFalse($this->manager->isValidRepo('/nonexistent/path'));
    }

    public function test_get_recent_repos(): void
    {
        // Open two repos with a small time gap
        $this->manager->open('/Users/samhopkinson/webroot/ptp');
        // Force a later timestamp for the second one
        sleep(1);
        $this->manager->open('/Users/samhopkinson/webroot/counting_cards');

        $recent = $this->manager->getRecentRepos();

        $this->assertCount(2, $recent);
        // Most recently opened should be first
        $this->assertEquals('counting_cards', $recent[0]->name);
        $this->assertEquals('ptp', $recent[1]->name);
    }

    public function test_get_recent_repos_filters_deleted(): void
    {
        // Manually insert a repo with a path that doesn't exist
        RecentRepo::create([
            'name' => 'deleted-repo',
            'path' => '/nonexistent/deleted-repo',
            'branch' => 'main',
            'last_opened_at' => now(),
        ]);

        $this->manager->open('/Users/samhopkinson/webroot/ptp');

        $recent = $this->manager->getRecentRepos();

        // Only the valid repo should remain
        $this->assertCount(1, $recent);
        $this->assertEquals('ptp', $recent[0]->name);

        // The deleted entry should be cleaned from DB
        $this->assertDatabaseMissing('recent_repos', ['path' => '/nonexistent/deleted-repo']);
    }

    public function test_remove_from_recent(): void
    {
        $path = '/Users/samhopkinson/webroot/ptp';
        $this->manager->open($path);

        $this->manager->removeFromRecent($path);

        $this->assertDatabaseMissing('recent_repos', ['path' => $path]);
    }

    public function test_clear_recent_repos(): void
    {
        $this->manager->open('/Users/samhopkinson/webroot/ptp');
        $this->manager->open('/Users/samhopkinson/webroot/counting_cards');

        $this->manager->clearRecentRepos();

        $this->assertEquals(0, RecentRepo::count());
    }

    public function test_build_clone_command(): void
    {
        $cmd = $this->manager->buildCloneCommand(
            'https://github.com/user/repo.git',
            '/Users/sam/code/repo'
        );

        $this->assertEquals([
            'git', 'clone', '--progress',
            'https://github.com/user/repo.git',
            '/Users/sam/code/repo',
        ], $cmd);
    }

    public function test_build_clone_command_with_branch(): void
    {
        $cmd = $this->manager->buildCloneCommand(
            'https://github.com/user/repo.git',
            '/Users/sam/code/repo',
            'develop'
        );

        $this->assertEquals([
            'git', 'clone', '--progress',
            '--branch', 'develop',
            'https://github.com/user/repo.git',
            '/Users/sam/code/repo',
        ], $cmd);
    }

    public function test_resolve_clone_destination(): void
    {
        $dest = $this->manager->resolveCloneDestination('/Users/sam/code', 'my-repo');

        $this->assertEquals('/Users/sam/code/my-repo', $dest);
    }

    public function test_resolve_clone_destination_trims_trailing_slash(): void
    {
        $dest = $this->manager->resolveCloneDestination('/Users/sam/code/', 'my-repo');

        $this->assertEquals('/Users/sam/code/my-repo', $dest);
    }

    public function test_validate_clone_destination_returns_true_for_new_path(): void
    {
        $this->assertTrue($this->manager->validateCloneDestination('/nonexistent/new-repo-12345'));
    }

    public function test_validate_clone_destination_returns_false_for_existing_path(): void
    {
        $this->assertFalse($this->manager->validateCloneDestination('/tmp'));
    }

    public function test_set_and_get_current_repo_path(): void
    {
        $this->assertNull($this->manager->getCurrentRepoPath());

        $this->manager->setCurrentRepoPath('/some/path');

        $this->assertEquals('/some/path', $this->manager->getCurrentRepoPath());
    }
}
