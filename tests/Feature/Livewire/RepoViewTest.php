<?php

namespace Tests\Feature\Livewire;

use App\DTOs\Branch;
use App\DTOs\Commit;
use App\DTOs\RepoState;
use App\Livewire\RepoView;
use App\Services\Git\GitService;
use Carbon\CarbonImmutable;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class RepoViewTest extends TestCase
{
    public function test_inactive_tabs_ignore_window_focus_refreshes(): void
    {
        $this->bindGitServiceMock(loadCount: 1);

        Livewire::test(RepoView::class, [
            'path' => '/fake/repo',
            'tabId' => 'tab-1',
            'isActive' => false,
        ])->call('handleWindowFocus');
    }

    public function test_inactive_tabs_ignore_keyboard_shortcuts(): void
    {
        $this->bindGitServiceMock(loadCount: 1);

        Livewire::test(RepoView::class, [
            'path' => '/fake/repo',
            'tabId' => 'tab-1',
            'isActive' => false,
        ])->call('handleShortcut', 'refresh');
    }

    public function test_active_tabs_handle_refresh_shortcuts(): void
    {
        $this->bindGitServiceMock(loadCount: 2);

        Livewire::test(RepoView::class, [
            'path' => '/fake/repo',
            'tabId' => 'tab-1',
            'isActive' => true,
        ])->call('handleShortcut', 'refresh');
    }

    private function bindGitServiceMock(int $loadCount): void
    {
        $state = new RepoState(
            headHash: 'abc1234',
            branch: 'main',
            upstream: 'origin/main',
            ahead: 0,
            behind: 0,
            isDetached: false,
            files: [],
        );

        $commit = new Commit(
            hash: 'abc123456789',
            shortHash: 'abc1234',
            parents: [],
            author: 'Test User',
            email: 'test@example.com',
            date: CarbonImmutable::parse('2026-04-01T12:00:00Z'),
            message: 'Initial commit',
            refs: ['HEAD -> main'],
        );

        $branch = new Branch(
            name: 'main',
            hash: 'abc1234',
            isCurrent: true,
            isRemote: false,
            upstream: 'origin/main',
            ahead: 0,
            behind: 0,
        );

        $git = Mockery::mock(GitService::class);
        $git->shouldReceive('open')->times($loadCount)->with('/fake/repo')->andReturnSelf();
        $git->shouldReceive('getStatus')->times($loadCount)->andReturn($state);
        $git->shouldReceive('getLog')->times($loadCount)->with(200, true)->andReturn([$commit]);
        $git->shouldReceive('getBranches')->times($loadCount)->andReturn([$branch]);
        $git->shouldReceive('getTags')->times($loadCount)->andReturn([]);
        $git->shouldReceive('getStashes')->times($loadCount)->andReturn([]);

        $this->app->instance(GitService::class, $git);
    }
}
