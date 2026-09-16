<?php

namespace Tests\Feature\Livewire;

use App\DTOs\Branch;
use App\DTOs\Commit;
use App\DTOs\DiffFile;
use App\DTOs\DiffHunk;
use App\DTOs\DiffLine;
use App\DTOs\GitResult;
use App\DTOs\RepoState;
use App\DTOs\StashEntry;
use App\DTOs\Tag;
use App\Livewire\RepoView;
use App\Services\Git\GitService;
use Carbon\CarbonImmutable;
use Livewire\Livewire;
use Mockery;
use Mockery\MockInterface;
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

    public function test_commit_row_renders_a_complete_wire_click_action(): void
    {
        $this->bindSelectableGitServiceMock();

        $html = Livewire::test(RepoView::class, [
            'path' => '/fake/repo',
            'tabId' => 'tab-1',
            'isActive' => true,
        ])->html();

        $this->assertContains(
            "selectCommit('abc123456789')",
            $this->wireActionsFrom($html, 'wire:click'),
        );
    }

    public function test_commit_graph_uses_one_component_scoped_redraw_path(): void
    {
        $this->bindSelectableGitServiceMock();

        $html = Livewire::test(RepoView::class, [
            'path' => '/fake/repo',
            'tabId' => 'tab-1',
            'isActive' => true,
        ])->html();
        $javascript = file_get_contents(resource_path('js/app.js'));

        $this->assertStringNotContainsString('x-effect="$wire.commits', $html);
        $this->assertStringContainsString('component.id !== this.$wire.id', $javascript);
        $this->assertStringContainsString('if (!graphChanged && !selectionChanged) return;', $javascript);
        $this->assertStringContainsString('if (this._removeCommitHook) this._removeCommitHook();', $javascript);
    }

    public function test_commit_description_renders_in_the_graph_and_selected_commit_details(): void
    {
        $description = "Explains the change.\n\nIncludes the full second paragraph.";
        $this->bindSelectableGitServiceMock();

        $component = Livewire::test(RepoView::class, [
            'path' => '/fake/repo',
            'tabId' => 'tab-1',
            'isActive' => true,
        ])
            ->assertSeeHtml('data-commit-description-preview')
            ->assertSee('Explains the change.')
            ->call('selectCommit', 'abc123456789')
            ->assertSet('selectedCommitData.description', $description)
            ->assertSeeHtml('data-commit-description')
            ->assertSee('Includes the full second paragraph.');

        $this->assertStringContainsString('whitespace-pre-wrap', $component->html());
    }

    public function test_targeting_the_current_checkout_reveals_and_dispatches_its_commit_row(): void
    {
        $this->bindSelectableGitServiceMock();

        Livewire::test(RepoView::class, [
            'path' => '/fake/repo',
            'tabId' => 'tab-1',
            'isActive' => true,
        ])
            ->set('selectedFile', 'README.md')
            ->call('handleShortcut', 'target')
            ->assertSet('selectedFile', null)
            ->assertDispatched('target-current-branch', hash: 'abc123456789');
    }

    public function test_create_commit_invokes_git_with_summary_and_description(): void
    {
        $git = $this->bindGitServiceMock(loadCount: 2, openCount: 2);
        $git->shouldReceive('commit')->once()->with("UI commit works\n\nThis explains the change.")->andReturn(new GitResult(
            success: true,
            output: '',
            error: '',
            exitCode: 0,
            command: "git commit -m 'UI commit works' -m 'This explains the change.'",
        ));

        Livewire::test(RepoView::class, [
            'path' => '/fake/repo',
            'tabId' => 'tab-1',
            'isActive' => true,
        ])
            ->set('commitSummary', 'UI commit works')
            ->set('commitDescription', 'This explains the change.')
            ->call('createCommit')
            ->assertSet('errorMessage', '')
            ->assertSet('commitSummary', '')
            ->assertSet('commitDescription', '')
            ->assertDispatched('toast', message: 'Committed: UI commit works', type: 'success');
    }

    public function test_stage_file_refreshes_working_tree_without_reloading_history_or_refs(): void
    {
        $git = $this->bindGitServiceMock(
            loadCount: 1,
            openCount: 2,
            workingTreeRefreshCount: 1,
        );
        $git->shouldReceive('stage')->once()->with(['README.md'])->andReturn(new GitResult(
            success: true,
            output: '',
            error: '',
            exitCode: 0,
            command: 'git add -- README.md',
        ));

        Livewire::test(RepoView::class, [
            'path' => '/fake/repo',
            'tabId' => 'tab-1',
            'isActive' => true,
        ])
            ->call('stageFile', 'README.md')
            ->assertSet('errorMessage', '');
    }

    public function test_commit_pull_push_and_checkout_render_central_operation_modals(): void
    {
        $this->bindGitServiceMock(loadCount: 1);

        $html = Livewire::test(RepoView::class, [
            'path' => '/fake/repo',
            'tabId' => 'tab-1',
            'isActive' => true,
        ])->html();

        $this->assertStringContainsString('data-operation-modal="commit"', $html);
        $this->assertStringContainsString('wire:target="createCommit"', $html);
        $this->assertStringContainsString('Committing changes…', $html);
        $this->assertStringContainsString('data-operation-modal="pull"', $html);
        $this->assertStringContainsString('wire:target="pullRemote"', $html);
        $this->assertStringContainsString('Pulling changes from the remote…', $html);
        $this->assertStringContainsString('data-operation-modal="push"', $html);
        $this->assertStringContainsString('wire:target="pushRemote"', $html);
        $this->assertStringContainsString('Pushing commits to the remote…', $html);
        $this->assertStringContainsString('data-operation-modal="checkout"', $html);
        $this->assertStringContainsString('wire:target="checkoutBranch, checkoutWorkspaceBranch, checkoutLocalBranch, checkoutRemoteBranch, requestRemoteCheckout, checkoutGraphRef, checkoutContextMenuBranchAction, checkoutExistingRemoteChoice, fastForwardRemoteChoice, resetRemoteCheckoutChoice"', $html);
        $this->assertStringContainsString('Checking out branch…', $html);
    }

    public function test_native_pull_and_push_keep_the_modal_open_until_the_process_exits(): void
    {
        $this->bindGitServiceMock(loadCount: 1);

        $component = Livewire::test(RepoView::class, [
            'path' => '/fake/repo',
            'tabId' => 'tab-1',
            'isActive' => true,
        ]);

        $component
            ->set('remoteOperation', 'pull')
            ->assertSeeHtml('data-operation-modal-persistent')
            ->assertSee('Pulling changes from the remote…', false)
            ->set('remoteOperation', 'push')
            ->assertSeeHtml('data-operation-modal-persistent')
            ->assertSee('Pushing commits to the remote…', false)
            ->set('remoteOperation', 'fetch')
            ->set('pendingRemoteCheckout', 'origin/feature/test')
            ->assertSeeHtml('data-operation-modal-persistent')
            ->assertSee('Preparing branch checkout…', false)
            ->set('remoteOperation', null)
            ->assertDontSeeHtml('data-operation-modal-persistent');
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

    public function test_selecting_a_branch_from_the_workspace_header_checks_it_out(): void
    {
        $git = $this->bindSelectableGitServiceMock();
        $git->shouldReceive('checkout')->once()->with('feature/test')->andReturn(new GitResult(
            success: true,
            output: '',
            error: '',
            exitCode: 0,
            command: 'git checkout feature/test',
        ));

        Livewire::test(RepoView::class, [
            'path' => '/fake/repo',
            'tabId' => 'tab-1',
            'isActive' => true,
        ])
            ->set('focusedHistoryRef', 'origin/feature/test')
            ->set('historyLimit', 400)
            ->call('checkoutWorkspaceBranch', 'feature/test')
            ->assertSet('errorMessage', '')
            ->assertSet('focusedHistoryRef', null)
            ->assertSet('historyLimit', 200)
            ->assertDispatched('toast', message: "Switched to 'feature/test'", type: 'success');
    }

    public function test_branch_context_menu_offers_checkout_and_switches_to_the_branch(): void
    {
        $git = $this->bindSelectableGitServiceMock();
        $git->shouldReceive('checkout')->once()->with('feature/test')->andReturn(new GitResult(
            success: true,
            output: '',
            error: '',
            exitCode: 0,
            command: 'git checkout feature/test',
        ));

        Livewire::test(RepoView::class, [
            'path' => '/fake/repo',
            'tabId' => 'tab-1',
            'isActive' => true,
        ])
            ->call('openBranchContextMenu', 'feature/test', 40, 60)
            ->assertSee('Check out feature/test')
            ->assertSeeHtml('wire:click="checkoutContextMenuBranchAction"')
            ->call('checkoutContextMenuBranchAction')
            ->assertSet('showContextMenu', false)
            ->assertSet('errorMessage', '')
            ->assertDispatched('toast', message: "Switched to 'feature/test'", type: 'success');
    }

    public function test_remote_checkout_fetches_then_offers_safe_update_choices_for_an_existing_local_branch(): void
    {
        $git = $this->bindSelectableGitServiceMock();
        $git->shouldReceive('fetch')->once()->andReturn(new GitResult(
            success: true,
            output: '',
            error: '',
            exitCode: 0,
            command: 'git fetch origin --prune',
        ));
        $git->shouldReceive('canFastForward')
            ->once()
            ->with('feature/test', 'origin/feature/test')
            ->andReturn(true);

        Livewire::test(RepoView::class, [
            'path' => '/fake/repo',
            'tabId' => 'tab-1',
            'isActive' => true,
        ])
            ->call('requestRemoteCheckout', 'origin/feature/test')
            ->assertSet('showRemoteCheckoutOptions', true)
            ->assertSet('remoteCheckoutLocal', 'feature/test')
            ->assertSet('remoteCheckoutRemote', 'origin/feature/test')
            ->assertSet('remoteCheckoutCanFastForward', true)
            ->assertSee('Check out local branch')
            ->assertSee('Fast-forward local branch, then check out')
            ->assertSee('Reset local branch to remote, then check out');
    }

    public function test_focus_graph_loads_history_reachable_from_the_selected_branch(): void
    {
        $git = $this->bindSelectableGitServiceMock();
        $focusedCommit = new Commit(
            hash: 'def567812345',
            shortHash: 'def5678',
            parents: [],
            author: 'Test User',
            email: 'test@example.com',
            date: CarbonImmutable::parse('2026-04-01T12:00:00Z'),
            message: 'Old feature commit',
            refs: ['origin/feature/test'],
        );
        $git->shouldReceive('getLogForRef')
            ->once()
            ->with('origin/feature/test', 200)
            ->andReturn([$focusedCommit]);

        Livewire::test(RepoView::class, [
            'path' => '/fake/repo',
            'tabId' => 'tab-1',
            'isActive' => true,
        ])
            ->call('focusGraphOnBranch', 'origin/feature/test')
            ->assertSet('focusedHistoryRef', 'origin/feature/test')
            ->assertSet('historyLimit', 200)
            ->assertSet('commits.0.hash', 'def567812345')
            ->assertDispatched('target-current-branch', hash: 'def567812345');
    }

    public function test_reveal_branch_in_all_history_expands_in_batches_until_its_tip_is_visible(): void
    {
        $git = $this->bindSelectableGitServiceMock();
        $oldCommit = new Commit(
            hash: 'def567812345',
            shortHash: 'def5678',
            parents: [],
            author: 'Test User',
            email: 'test@example.com',
            date: CarbonImmutable::parse('2026-03-01T12:00:00Z'),
            message: 'Old feature commit',
            refs: ['origin/feature/test'],
        );
        $git->shouldReceive('getLog')
            ->with(400, true)
            ->once()
            ->andReturn([
                new Commit(
                    hash: 'abc123456789',
                    shortHash: 'abc1234',
                    parents: [],
                    author: 'Test User',
                    email: 'test@example.com',
                    date: CarbonImmutable::parse('2026-04-01T12:00:00Z'),
                    message: 'Initial commit',
                    refs: ['HEAD -> main'],
                ),
                $oldCommit,
            ]);

        Livewire::test(RepoView::class, [
            'path' => '/fake/repo',
            'tabId' => 'tab-1',
            'isActive' => true,
        ])
            ->call('revealBranchInAllHistory', 'origin/feature/test')
            ->assertSet('focusedHistoryRef', null)
            ->assertSet('historyLimit', 400)
            ->assertSet('commits.1.hash', 'def567812345')
            ->assertDispatched('target-current-branch', hash: 'def567812345')
            ->assertDispatched('toast', message: "Revealed 'origin/feature/test' in 400 commits", type: 'success');
    }

    public function test_target_current_checkout_expands_history_when_the_checked_out_commit_is_old(): void
    {
        $git = $this->bindSelectableGitServiceMock();
        $oldCommit = new Commit(
            hash: 'def567812345',
            shortHash: 'def5678',
            parents: [],
            author: 'Test User',
            email: 'test@example.com',
            date: CarbonImmutable::parse('2026-03-01T12:00:00Z'),
            message: 'Old feature commit',
            refs: ['origin/feature/test'],
        );
        $git->shouldReceive('getLog')
            ->with(400, true)
            ->once()
            ->andReturn([
                new Commit(
                    hash: 'abc123456789',
                    shortHash: 'abc1234',
                    parents: [],
                    author: 'Test User',
                    email: 'test@example.com',
                    date: CarbonImmutable::parse('2026-04-01T12:00:00Z'),
                    message: 'Initial commit',
                    refs: ['HEAD -> main'],
                ),
                $oldCommit,
            ]);

        Livewire::test(RepoView::class, [
            'path' => '/fake/repo',
            'tabId' => 'tab-1',
            'isActive' => true,
        ])
            ->set('status.headHash', 'def5678')
            ->call('targetCurrentCheckout')
            ->assertSet('historyLimit', 400)
            ->assertSet('commits.1.hash', 'def567812345')
            ->assertDispatched('target-current-branch', hash: 'def567812345');
    }

    public function test_graph_branch_badge_defers_selection_and_handles_double_click_client_side(): void
    {
        $this->bindSelectableGitServiceMock();

        $html = Livewire::test(RepoView::class, [
            'path' => '/fake/repo',
            'tabId' => 'tab-1',
            'isActive' => true,
        ])->html();

        $this->assertStringContainsString('x-on:click.stop="handleGraphRefClick(', $html);
        $this->assertStringContainsString('x-on:dblclick.stop.prevent="handleGraphRefDoubleClick(', $html);
        $this->assertStringNotContainsString('wire:dblclick.stop="checkoutGraphRef(', $html);
    }

    public function test_repository_layout_renders_a_resizer_for_every_column_boundary(): void
    {
        $this->bindSelectableGitServiceMock();

        $html = Livewire::test(RepoView::class, [
            'path' => '/fake/repo',
            'tabId' => 'tab-1',
            'isActive' => true,
        ])->html();

        $this->assertStringContainsString('data-resize-handle="filter-panel"', $html);
        $this->assertStringContainsString('data-resize-handle="branch-column"', $html);
        $this->assertStringContainsString('data-resize-handle="graph-column"', $html);
        $this->assertStringContainsString('data-resize-handle="detail-panel"', $html);
        $this->assertStringContainsString("'width:' + filterPanelWidth + 'px'", $html);
        $this->assertStringContainsString("'width:' + detailPanelWidth + 'px'", $html);
    }

    public function test_reference_filter_has_a_progress_indicator_and_zero_counts_by_default(): void
    {
        $this->bindSelectableGitServiceMock();

        $html = Livewire::test(RepoView::class, [
            'path' => '/fake/repo',
            'tabId' => 'tab-1',
            'isActive' => true,
        ])->html();

        $this->assertStringContainsString('wire:model.live.debounce.300ms="referenceFilter"', $html);
        $this->assertStringContainsString('data-reference-filter-progress', $html);
        $this->assertStringContainsString('wire:target="referenceFilter"', $html);

        foreach (['local', 'remote', 'stashes', 'tags'] as $type) {
            $this->assertMatchesRegularExpression(
                '/data-reference-count="'.preg_quote($type, '/').'"[^>]*>\s*0\s*<\/span>/',
                $html,
            );
        }
    }

    public function test_reference_filter_finds_prefix_matches_outside_the_bounded_sidebar_snapshot(): void
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

        $branches = [new Branch('main', 'abc1234', true, false, 'origin/main')];
        foreach (range(1, 500) as $index) {
            $branches[] = new Branch("feature/{$index}", 'def5678');
        }
        $branches[] = new Branch('DEV-90-feature', 'def5678', false, false, 'origin/DEV-90-feature');

        foreach (range(1, 500) as $index) {
            $branches[] = new Branch("origin/feature/{$index}", 'def5678', false, true);
        }
        $branches[] = new Branch('origin/DEV-90-feature', 'def5678', false, true);

        $tags = [
            new Tag('DEV-90-release', 'def5678'),
            new Tag('release-DEV-90', 'def5678'),
        ];
        $stashes = [
            new StashEntry('stash@{0}', 'def5678', 'DEV-90 work'),
            new StashEntry('stash@{1}', 'def5678', 'other work'),
        ];

        $git = Mockery::mock(GitService::class);
        $git->shouldReceive('open')->times(3)->with('/fake/repo')->andReturnSelf();
        $git->shouldReceive('getStatus')->once()->andReturn($state);
        $git->shouldReceive('getOperationState')->once()->andReturnNull();
        $git->shouldReceive('getLog')->once()->with(200, true)->andReturn([$commit]);
        $git->shouldReceive('getBranches')->twice()->andReturn($branches);
        $git->shouldReceive('getTags')->twice()->andReturn($tags);
        $git->shouldReceive('getStashes')->twice()->andReturn($stashes);
        $git->shouldReceive('getRefComparisonFiles')->once()->with('DEV-90-feature')->andReturn([]);
        $this->app->instance(GitService::class, $git);

        $component = Livewire::test(RepoView::class, [
            'path' => '/fake/repo',
            'tabId' => 'tab-1',
            'isActive' => true,
        ]);

        $this->assertNotContains('DEV-90-feature', array_column($component->get('localBranches'), 'name'));
        $this->assertNotContains('origin/DEV-90-feature', array_column($component->get('remoteBranches'), 'name'));

        $component->set('referenceFilter', 'dev-90');

        $this->assertSame(['DEV-90-feature'], array_column($component->get('filteredLocalBranches'), 'name'));
        $this->assertSame(['origin/DEV-90-feature'], array_column($component->get('filteredRemoteBranches'), 'name'));
        $this->assertSame(['DEV-90-release'], array_column($component->get('filteredTags'), 'name'));
        $this->assertSame(['stash@{0}'], array_column($component->get('filteredStashes'), 'ref'));

        $component
            ->call('selectBranch', 'DEV-90-feature')
            ->assertSet('selectedBranch', 'DEV-90-feature');

        $html = $component->html();
        foreach (['local', 'remote', 'stashes', 'tags'] as $type) {
            $this->assertMatchesRegularExpression(
                '/data-reference-count="'.preg_quote($type, '/').'"[^>]*>\s*1\s*<\/span>/',
                $html,
            );
        }
    }

    public function test_sidebar_branch_rows_defer_single_click_before_checkout(): void
    {
        $this->bindSelectableGitServiceMock();

        $html = Livewire::test(RepoView::class, [
            'path' => '/fake/repo',
            'tabId' => 'tab-1',
            'isActive' => true,
        ])->html();

        $this->assertStringContainsString('handleBranchClick(', $html);
        $this->assertStringContainsString('handleBranchDoubleClick(', $html);
        $this->assertStringNotContainsString('wire:dblclick="checkoutLocalBranch(', $html);
        $this->assertStringNotContainsString('wire:dblclick="checkoutRemoteBranch(', $html);
    }

    public function test_context_menu_checks_out_a_branch_outside_the_bounded_sidebar_snapshot(): void
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
        $branches = [new Branch('main', 'abc1234', true, false, 'origin/main')];
        foreach (range(1, 500) as $index) {
            $branches[] = new Branch("feature/{$index}", 'def5678');
        }
        $branches[] = new Branch('feature/target', 'def5678');

        $git = Mockery::mock(GitService::class);
        $git->shouldReceive('open')->with('/fake/repo')->andReturnSelf();
        $git->shouldReceive('getStatus')->andReturn($state);
        $git->shouldReceive('getOperationState')->andReturnNull();
        $git->shouldReceive('getLog')->with(200, true)->andReturn([$commit]);
        $git->shouldReceive('getBranches')->andReturn($branches);
        $git->shouldReceive('getTags')->andReturn([]);
        $git->shouldReceive('getStashes')->andReturn([]);
        $git->shouldReceive('checkout')->once()->with('feature/target')->andReturn(new GitResult(
            success: true,
            output: '',
            error: '',
            exitCode: 0,
            command: 'git checkout feature/target',
        ));
        $this->app->instance(GitService::class, $git);

        Livewire::test(RepoView::class, [
            'path' => '/fake/repo',
            'tabId' => 'tab-1',
            'isActive' => true,
        ])
            ->call('openBranchContextMenu', 'feature/target', 40, 60)
            ->call('checkoutContextMenuBranchAction')
            ->assertSet('errorMessage', '')
            ->assertDispatched('toast', message: "Switched to 'feature/target'", type: 'success');
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
        $this->assertArrayNotHasKey('hunks', $component->get('selectedHistoryDiffs')[0]);
        $this->assertSame('README.md', $component->get('selectedFile'));
        $this->assertCount(1, $component->get('diffFiles'));
        $this->assertArrayHasKey('hunks', $component->get('diffFiles')[0]);
        $this->assertSame('README.md', $component->get('diffFiles')[0]['path']);
    }

    public function test_large_history_diff_is_bounded_before_it_enters_the_livewire_snapshot(): void
    {
        $largeDiff = new DiffFile(
            path: 'large.txt',
            status: 'modified',
            oldPath: null,
            isBinary: false,
            hunks: [
                new DiffHunk(
                    oldStart: 1,
                    oldCount: 2101,
                    newStart: 1,
                    newCount: 2101,
                    header: '@@ -1,2101 +1,2101 @@',
                    lines: array_map(
                        fn (int $line) => new DiffLine('context', "line {$line}", $line, $line),
                        range(1, 2101),
                    ),
                ),
            ],
        );

        $this->bindSelectableGitServiceMock(commitDiffs: [$largeDiff]);

        $component = Livewire::test(RepoView::class, [
            'path' => '/fake/repo',
            'tabId' => 'tab-1',
            'isActive' => true,
        ]);

        $component
            ->call('selectCommit', 'abc123456789')
            ->call('selectHistoryFile', 'large.txt');

        $lines = $component->get('diffFiles')[0]['hunks'][0]['lines'];

        $this->assertCount(500, $lines);
        $this->assertTrue($component->get('diffFiles')[0]['isTruncated']);
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

    public function test_failed_native_remote_operation_keeps_the_git_error(): void
    {
        $this->bindGitServiceMock(loadCount: 2);

        Livewire::test(RepoView::class, [
            'path' => '/fake/repo',
            'tabId' => 'tab-1',
            'isActive' => true,
        ])
            ->set('remoteOperation', 'push')
            ->set('remoteErrorOutput', 'remote: permission denied')
            ->call('onRemoteProcessExited', 'git-remote-op-tab-1', 1)
            ->assertSet('remoteOperation', null)
            ->assertSet('errorMessage', 'Push failed: remote: permission denied');
    }

    private function bindGitServiceMock(
        int $loadCount,
        ?int $openCount = null,
        int $workingTreeRefreshCount = 0,
    ): MockInterface {
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
        $git->shouldReceive('open')->times($openCount ?? $loadCount)->with('/fake/repo')->andReturnSelf();
        $git->shouldReceive('getStatus')->times($loadCount + $workingTreeRefreshCount)->andReturn($state);
        $git->shouldReceive('getOperationState')->times($loadCount + $workingTreeRefreshCount)->andReturnNull();
        $git->shouldReceive('getLog')->times($loadCount)->with(200, true)->andReturn([$commit]);
        $git->shouldReceive('getBranches')->times($loadCount)->andReturn([$branch]);
        $git->shouldReceive('getTags')->times($loadCount)->andReturn([]);
        $git->shouldReceive('getStashes')->times($loadCount)->andReturn([]);

        $this->app->instance(GitService::class, $git);

        return $git;
    }

    /**
     * @param  list<DiffFile>  $commitDiffs
     * @param  list<DiffFile>  $refDiffs
     * @param  list<DiffFile>  $fileDiffs
     */
    private function bindSelectableGitServiceMock(
        array $commitDiffs = [],
        array $refDiffs = [],
        array $fileDiffs = [],
    ): MockInterface {
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
            description: "Explains the change.\n\nIncludes the full second paragraph.",
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
                name: 'feature/test',
                hash: 'def5678',
                isCurrent: false,
                isRemote: false,
                upstream: 'origin/feature/test',
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
        $git->shouldReceive('getOperationState')->andReturnNull();
        $git->shouldReceive('getLog')->with(200, true)->andReturn([$commit]);
        $git->shouldReceive('getBranches')->andReturn($branches);
        $git->shouldReceive('getTags')->andReturn([]);
        $git->shouldReceive('getStashes')->andReturn([]);
        $git->shouldReceive('getCommitDiff')->with('abc123456789')->andReturn($commitDiffs);
        $git->shouldReceive('getRefComparisonDiff')->with('origin/feature/test')->andReturn($refDiffs);
        $git->shouldReceive('getRefComparisonFiles')->with('origin/feature/test')->andReturn($refDiffs);
        $git->shouldReceive('getRefComparisonFileDiff')->with('origin/feature/test', Mockery::type('string'))->andReturn($refDiffs);
        $git->shouldReceive('getFileDiff')->with('resources/js/app.js', false)->andReturn($fileDiffs);

        $this->app->instance(GitService::class, $git);

        return $git;
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

    /** @return list<string> */
    private function wireActionsFrom(string $html, string $attribute): array
    {
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $actions = [];
        foreach ($document->getElementsByTagName('*') as $element) {
            if ($element->hasAttribute($attribute)) {
                $actions[] = $element->getAttribute($attribute);
            }
        }

        return $actions;
    }
}
