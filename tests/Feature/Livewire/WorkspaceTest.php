<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Workspace;
use App\Models\RecentRepo;
use App\Services\RepoManager;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    public function test_it_seeds_a_tab_from_the_initial_path(): void
    {
        $this->bindRepoManagerMock([
            ['path' => '/fake/repo', 'repo' => $this->makeRecentRepo('repo', '/fake/repo', 'main')],
        ]);

        $component = Livewire::test(Workspace::class, ['path' => '/fake/repo']);

        $this->assertSame('tab-'.substr(md5('/fake/repo'), 0, 12), $component->instance()->activeTabId);
        $this->assertCount(1, $component->instance()->tabs);
        $this->assertSame('/fake/repo', $component->instance()->tabs[0]['path']);
        $this->assertSame('repo', $component->instance()->tabs[0]['repoName']);
        $this->assertSame('main', $component->instance()->tabs[0]['currentBranch']);
        $this->assertSame(['main'], $component->instance()->tabs[0]['localBranches']);
        $this->assertSame(['main'], $component->instance()->tabs[0]['recentBranches']);
    }

    public function test_it_deduplicates_existing_tabs_when_reopening_the_same_repo(): void
    {
        $this->bindRepoManagerMock([
            ['path' => '/fake/repo', 'repo' => $this->makeRecentRepo('repo', '/fake/repo', 'main')],
            ['path' => '/fake/repo', 'repo' => $this->makeRecentRepo('repo', '/fake/repo', 'main')],
        ]);

        $component = Livewire::test(Workspace::class, ['path' => '/fake/repo'])
            ->call('openRepoByPath', '/fake/repo');

        $this->assertCount(1, $component->instance()->tabs);
        $this->assertSame('tab-'.substr(md5('/fake/repo'), 0, 12), $component->instance()->activeTabId);
    }

    public function test_it_can_switch_between_open_tabs(): void
    {
        $this->bindRepoManagerMock([
            ['path' => '/fake/repo', 'repo' => $this->makeRecentRepo('repo', '/fake/repo', 'main')],
            ['path' => '/other/repo', 'repo' => $this->makeRecentRepo('other', '/other/repo', 'develop')],
        ]);

        $component = Livewire::test(Workspace::class, ['path' => '/fake/repo'])
            ->call('openRepoByPath', '/other/repo');

        $this->assertCount(2, $component->instance()->tabs);
        $this->assertSame('tab-'.substr(md5('/other/repo'), 0, 12), $component->instance()->activeTabId);

        $component->call('activateTab', 'tab-'.substr(md5('/fake/repo'), 0, 12));

        $this->assertSame('tab-'.substr(md5('/fake/repo'), 0, 12), $component->instance()->activeTabId);
        $this->assertSame('/fake/repo', $component->instance()->path);
    }

    public function test_it_redirects_home_when_the_last_tab_is_closed(): void
    {
        $this->bindRepoManagerMock([
            ['path' => '/fake/repo', 'repo' => $this->makeRecentRepo('repo', '/fake/repo', 'main')],
        ]);

        Livewire::test(Workspace::class, ['path' => '/fake/repo'])
            ->call('closeTab', 'tab-'.substr(md5('/fake/repo'), 0, 12))
            ->assertRedirect('/');
    }

    public function test_the_top_bar_includes_a_target_action_next_to_the_current_branch(): void
    {
        $this->bindRepoManagerMock([
            ['path' => '/fake/repo', 'repo' => $this->makeRecentRepo('repo', '/fake/repo', 'main')],
        ]);

        $html = Livewire::test(Workspace::class, ['path' => '/fake/repo'])->html();

        $this->assertStringContainsString('>Target</span>', $html);
        $this->assertStringContainsString("action: 'target'", $html);
    }

    public function test_it_populates_the_branch_menu_and_remembers_recently_selected_branches(): void
    {
        $this->bindRepoManagerMock([
            ['path' => '/fake/repo', 'repo' => $this->makeRecentRepo('repo', '/fake/repo', 'main')],
        ]);

        $component = Livewire::test(Workspace::class, ['path' => '/fake/repo'])
            ->call(
                'updateTabContext',
                'tab-'.substr(md5('/fake/repo'), 0, 12),
                'repo',
                'main',
                '/fake/repo',
                false,
                ['main', 'feature/dashboard'],
            )
            ->assertSee('feature/dashboard')
            ->call(
                'updateTabContext',
                'tab-'.substr(md5('/fake/repo'), 0, 12),
                'repo',
                'feature/dashboard',
                '/fake/repo',
                false,
                ['main', 'feature/dashboard'],
            );

        $this->assertSame(
            ['main', 'feature/dashboard'],
            $component->instance()->tabs[0]['localBranches'],
        );
        $this->assertSame(
            ['feature/dashboard', 'main'],
            $component->instance()->tabs[0]['recentBranches'],
        );
        $this->assertStringContainsString('checkout-branch', $component->html());
    }

    private function bindRepoManagerMock(array $responses): void
    {
        $repoManager = Mockery::mock(RepoManager::class);

        foreach ($responses as $response) {
            $repoManager->shouldReceive('open')
                ->once()
                ->with($response['path'])
                ->andReturn($response['repo']);
        }

        $this->app->instance(RepoManager::class, $repoManager);
    }

    private function makeRecentRepo(string $name, string $path, ?string $branch): RecentRepo
    {
        return new RecentRepo([
            'name' => $name,
            'path' => $path,
            'branch' => $branch,
            'last_opened_at' => now(),
        ]);
    }
}
