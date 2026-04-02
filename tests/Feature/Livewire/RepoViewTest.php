<?php

namespace Tests\Feature\Livewire;

use App\DTOs\Branch;
use App\DTOs\Commit;
use App\DTOs\DiffFile;
use App\DTOs\DiffHunk;
use App\DTOs\DiffLine;
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

    public function test_selecting_a_commit_populates_history_diffs_without_opening_the_centre_diff(): void
    {
        $historyDiff = $this->makeDiffFile('README.md');

        $this->bindSelectableGitServiceMock(
            commitDiffs: [$historyDiff],
        );

        $component = Livewire::test(RepoView::class, [
            'path' => '/fake/repo',
            'tabId' => 'tab-1',
            'isActive' => true,
        ]);

        $component->call('selectCommit', 'abc123456789');

        $this->assertSame('commit', $component->get('selectedHistoryType'));
        $this->assertSame('abc123456789', $component->get('selectedCommit'));
        $this->assertNull($component->get('selectedFile'));
        $this->assertCount(1, $component->get('selectedHistoryDiffs'));
        $this->assertSame([], $component->get('diffFiles'));
    }

    public function test_selecting_a_branch_populates_history_diffs_without_opening_the_centre_diff(): void
    {
        $historyDiff = $this->makeDiffFile('routes/web.php');

        $this->bindSelectableGitServiceMock(
            refDiffs: [$historyDiff],
        );

        $component = Livewire::test(RepoView::class, [
            'path' => '/fake/repo',
            'tabId' => 'tab-1',
            'isActive' => true,
        ]);

        $component->call('selectBranch', 'origin/feature/test');

        $this->assertSame('branch', $component->get('selectedHistoryType'));
        $this->assertSame('origin/feature/test', $component->get('selectedBranch'));
        $this->assertNull($component->get('selectedFile'));
        $this->assertCount(1, $component->get('selectedHistoryDiffs'));
        $this->assertSame([], $component->get('diffFiles'));
    }

    public function test_selecting_a_history_file_populates_the_centre_diff(): void
    {
        $historyDiff = $this->makeDiffFile('README.md');

        $this->bindSelectableGitServiceMock(
            commitDiffs: [$historyDiff],
        );

        $component = Livewire::test(RepoView::class, [
            'path' => '/fake/repo',
            'tabId' => 'tab-1',
            'isActive' => true,
        ]);

        $component
            ->call('selectCommit', 'abc123456789')
            ->call('selectHistoryFile', 'README.md');

        $this->assertSame('commit', $component->get('selectedHistoryType'));
        $this->assertSame('README.md', $component->get('selectedFile'));
        $this->assertCount(1, $component->get('diffFiles'));
        $this->assertSame('README.md', $component->get('diffFiles')[0]['path']);
    }

    public function test_selecting_a_working_tree_file_clears_history_selection(): void
    {
        $historyDiff = $this->makeDiffFile('README.md');
        $workingTreeDiff = $this->makeDiffFile('resources/js/app.js');

        $this->bindSelectableGitServiceMock(
            commitDiffs: [$historyDiff],
            fileDiffs: [$workingTreeDiff],
        );

        $component = Livewire::test(RepoView::class, [
            'path' => '/fake/repo',
            'tabId' => 'tab-1',
            'isActive' => true,
        ]);

        $component
            ->call('selectCommit', 'abc123456789')
            ->call('selectFile', 'resources/js/app.js');

        $this->assertNull($component->get('selectedHistoryType'));
        $this->assertNull($component->get('selectedCommit'));
        $this->assertNull($component->get('selectedBranch'));
        $this->assertSame('resources/js/app.js', $component->get('selectedFile'));
        $this->assertCount(1, $component->get('diffFiles'));
    }

    public function test_open_create_branch_from_ref_prefills_the_start_point(): void
    {
        $this->bindGitServiceMock(loadCount: 1);

        Livewire::test(RepoView::class, [
            'path' => '/fake/repo',
            'tabId' => 'tab-1',
            'isActive' => true,
        ])
            ->call('openCreateBranchFromRef', 'abc123456789')
            ->assertSet('showCreateBranch', true)
            ->assertSet('newBranchName', '')
            ->assertSet('newBranchStartPoint', 'abc123456789');
    }

    public function test_open_create_annotated_tag_from_ref_prefills_the_ref(): void
    {
        $this->bindGitServiceMock(loadCount: 1);

        Livewire::test(RepoView::class, [
            'path' => '/fake/repo',
            'tabId' => 'tab-1',
            'isActive' => true,
        ])
            ->call('openCreateTagFromRef', 'origin/main', true)
            ->assertSet('showCreateTag', true)
            ->assertSet('newTagName', '')
            ->assertSet('newTagRef', 'origin/main')
            ->assertSet('newTagAnnotated', true)
            ->assertSet('newTagMessage', '');
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

    /**
     * @param list<DiffFile> $commitDiffs
     * @param list<DiffFile> $refDiffs
     * @param list<DiffFile> $fileDiffs
     */
    private function bindSelectableGitServiceMock(
        array $commitDiffs = [],
        array $refDiffs = [],
        array $fileDiffs = [],
    ): void {
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

        $branches = [
            new Branch(
                name: 'main',
                hash: 'abc1234',
                isCurrent: true,
                isRemote: false,
                upstream: 'origin/main',
                ahead: 0,
                behind: 0,
            ),
            new Branch(
                name: 'origin/feature/test',
                hash: 'def5678',
                isCurrent: false,
                isRemote: true,
                upstream: null,
                ahead: 0,
                behind: 0,
            ),
        ];

        $git = Mockery::mock(GitService::class);
        $git->shouldReceive('open')->with('/fake/repo')->andReturnSelf();
        $git->shouldReceive('getStatus')->andReturn($state);
        $git->shouldReceive('getLog')->with(200, true)->andReturn([$commit]);
        $git->shouldReceive('getBranches')->andReturn($branches);
        $git->shouldReceive('getTags')->andReturn([]);
        $git->shouldReceive('getStashes')->andReturn([]);
        $git->shouldReceive('getCommitDiff')->with('abc123456789')->andReturn($commitDiffs);
        $git->shouldReceive('getRefComparisonDiff')->with('origin/feature/test')->andReturn($refDiffs);
        $git->shouldReceive('getFileDiff')->with('resources/js/app.js', false)->andReturn($fileDiffs);

        $this->app->instance(GitService::class, $git);
    }

    private function makeDiffFile(string $path): DiffFile
    {
        return new DiffFile(
            path: $path,
            status: 'modified',
            oldPath: null,
            isBinary: false,
            hunks: [
                new DiffHunk(
                    oldStart: 1,
                    oldCount: 1,
                    newStart: 1,
                    newCount: 2,
                    header: '@@ -1,1 +1,2 @@',
                    lines: [
                        new DiffLine('context', 'before line', 1, 1),
                        new DiffLine('add', 'after line', null, 2),
                    ],
                ),
            ],
        );
    }
}
