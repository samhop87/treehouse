<?php

namespace Tests\Unit\Services\Git;

use App\DTOs\Branch;
use App\DTOs\Commit;
use App\DTOs\DiffFile;
use App\DTOs\GitResult;
use App\DTOs\RepoState;
use App\DTOs\StashEntry;
use App\DTOs\Tag;
use App\Services\Git\GitCommandRunner;
use App\Services\Git\GitErrorTranslator;
use App\Services\Git\GitService;
use App\Services\Git\Parsers\BranchParser;
use App\Services\Git\Parsers\DiffParser;
use App\Services\Git\Parsers\LogParser;
use App\Services\Git\Parsers\StashParser;
use App\Services\Git\Parsers\StatusParser;
use App\Services\Git\Parsers\TagParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GitServiceTest extends TestCase
{
    private GitService $git;

    protected function setUp(): void
    {
        parent::setUp();
        $this->git = app(GitService::class);
    }

    // ─── OPEN / LIFECYCLE ───────────────────────────────────────────────

    #[Test]
    public function it_opens_a_valid_repo(): void
    {
        $repo = $this->findTestRepo();
        if ($repo === null) {
            $this->markTestSkipped('No test git repository available.');
        }

        $result = $this->git->open($repo);

        $this->assertSame($this->git, $result); // returns self for chaining
        $this->assertTrue($this->git->isOpen());
        $this->assertSame($repo, $this->git->getRepoPath());
    }

    #[Test]
    public function it_throws_when_opening_non_repo(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not a git repository');

        $this->git->open('/tmp');
    }

    #[Test]
    public function it_throws_when_opening_nonexistent_path(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->git->open('/tmp/definitely-not-real-' . uniqid());
    }

    #[Test]
    public function it_resets_repo_path_on_failed_open(): void
    {
        try {
            $this->git->open('/tmp');
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertFalse($this->git->isOpen());
        $this->assertNull($this->git->getRepoPath());
    }

    #[Test]
    public function is_open_returns_false_by_default(): void
    {
        $this->assertFalse($this->git->isOpen());
    }

    // ─── ENSURE OPEN GUARD ──────────────────────────────────────────────

    #[Test]
    public function get_status_throws_when_no_repo_open(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No repository is open');

        $this->git->getStatus();
    }

    #[Test]
    public function get_log_throws_when_no_repo_open(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No repository is open');

        $this->git->getLog();
    }

    #[Test]
    public function get_branches_throws_when_no_repo_open(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No repository is open');

        $this->git->getBranches();
    }

    #[Test]
    public function get_tags_throws_when_no_repo_open(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No repository is open');

        $this->git->getTags();
    }

    #[Test]
    public function get_stashes_throws_when_no_repo_open(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No repository is open');

        $this->git->getStashes();
    }

    #[Test]
    public function stage_throws_when_no_repo_open(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No repository is open');

        $this->git->stage(['file.txt']);
    }

    #[Test]
    public function commit_throws_when_no_repo_open(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No repository is open');

        $this->git->commit('test');
    }

    // ─── READ OPERATIONS (integration against real repos) ───────────────

    #[Test]
    public function get_status_returns_repo_state(): void
    {
        $repo = $this->findTestRepo();
        if ($repo === null) {
            $this->markTestSkipped('No test git repository available.');
        }

        $this->git->open($repo);
        $state = $this->git->getStatus();

        $this->assertInstanceOf(RepoState::class, $state);
        $this->assertNotEmpty($state->headHash);
        $this->assertNotEmpty($state->branch);
    }

    #[Test]
    public function get_log_returns_commits(): void
    {
        $repo = $this->findTestRepo();
        if ($repo === null) {
            $this->markTestSkipped('No test git repository available.');
        }

        $this->git->open($repo);
        $commits = $this->git->getLog(limit: 5);

        $this->assertNotEmpty($commits);
        $this->assertContainsOnlyInstancesOf(Commit::class, $commits);
        $this->assertLessThanOrEqual(5, count($commits));

        $first = $commits[0];
        $this->assertNotEmpty($first->hash);
        $this->assertNotEmpty($first->shortHash);
        $this->assertNotEmpty($first->author);
    }

    #[Test]
    public function get_log_respects_all_flag(): void
    {
        $repo = $this->findTestRepo();
        if ($repo === null) {
            $this->markTestSkipped('No test git repository available.');
        }

        $this->git->open($repo);

        // Both should return commits without error
        $allCommits = $this->git->getLog(limit: 5, all: true);
        $currentOnly = $this->git->getLog(limit: 5, all: false);

        $this->assertNotEmpty($allCommits);
        $this->assertNotEmpty($currentOnly);
    }

    #[Test]
    public function get_branches_returns_branches(): void
    {
        $repo = $this->findTestRepo();
        if ($repo === null) {
            $this->markTestSkipped('No test git repository available.');
        }

        $this->git->open($repo);
        $branches = $this->git->getBranches();

        $this->assertNotEmpty($branches);
        $this->assertContainsOnlyInstancesOf(Branch::class, $branches);

        // There should be at least one current branch
        $currentBranches = array_filter($branches, fn (Branch $b) => $b->isCurrent);
        $this->assertNotEmpty($currentBranches);
    }

    #[Test]
    public function get_tags_returns_tags_array(): void
    {
        $repo = $this->findTestRepo();
        if ($repo === null) {
            $this->markTestSkipped('No test git repository available.');
        }

        $this->git->open($repo);
        $tags = $this->git->getTags();

        // Tags array may be empty for repos with no tags — just assert it's an array
        $this->assertIsArray($tags);
        foreach ($tags as $tag) {
            $this->assertInstanceOf(Tag::class, $tag);
        }
    }

    #[Test]
    public function get_stashes_returns_stash_entries_array(): void
    {
        $repo = $this->findTestRepo();
        if ($repo === null) {
            $this->markTestSkipped('No test git repository available.');
        }

        $this->git->open($repo);
        $stashes = $this->git->getStashes();

        $this->assertIsArray($stashes);
        foreach ($stashes as $stash) {
            $this->assertInstanceOf(StashEntry::class, $stash);
        }
    }

    #[Test]
    public function get_staged_diff_returns_diff_files(): void
    {
        $repo = $this->findTestRepo();
        if ($repo === null) {
            $this->markTestSkipped('No test git repository available.');
        }

        $this->git->open($repo);
        $diffs = $this->git->getStagedDiff();

        // May be empty if nothing is staged
        $this->assertIsArray($diffs);
        foreach ($diffs as $diff) {
            $this->assertInstanceOf(DiffFile::class, $diff);
        }
    }

    #[Test]
    public function get_unstaged_diff_returns_diff_files(): void
    {
        $repo = $this->findTestRepo();
        if ($repo === null) {
            $this->markTestSkipped('No test git repository available.');
        }

        $this->git->open($repo);
        $diffs = $this->git->getUnstagedDiff();

        $this->assertIsArray($diffs);
        foreach ($diffs as $diff) {
            $this->assertInstanceOf(DiffFile::class, $diff);
        }
    }

    // ─── UTILITY OPERATIONS ─────────────────────────────────────────────

    #[Test]
    public function get_current_branch_returns_branch_name(): void
    {
        $repo = $this->findTestRepo();
        if ($repo === null) {
            $this->markTestSkipped('No test git repository available.');
        }

        $this->git->open($repo);
        $branch = $this->git->getCurrentBranch();

        // Could be null if detached, but for our test repos it should have a branch
        $this->assertNotNull($branch);
        $this->assertNotEmpty($branch);
    }

    #[Test]
    public function get_remotes_returns_list(): void
    {
        $repo = $this->findTestRepo();
        if ($repo === null) {
            $this->markTestSkipped('No test git repository available.');
        }

        $this->git->open($repo);
        $remotes = $this->git->getRemotes();

        $this->assertIsArray($remotes);
        // Most repos have at least 'origin'
        if (! empty($remotes)) {
            $this->assertContains('origin', $remotes);
        }
    }

    #[Test]
    public function get_remote_url_returns_url_for_origin(): void
    {
        $repo = $this->findTestRepo();
        if ($repo === null) {
            $this->markTestSkipped('No test git repository available.');
        }

        $this->git->open($repo);
        $remotes = $this->git->getRemotes();

        if (empty($remotes)) {
            $this->markTestSkipped('Test repo has no remotes.');
        }

        $url = $this->git->getRemoteUrl('origin');
        $this->assertNotNull($url);
        $this->assertNotEmpty($url);
    }

    #[Test]
    public function get_remote_url_returns_null_for_nonexistent_remote(): void
    {
        $repo = $this->findTestRepo();
        if ($repo === null) {
            $this->markTestSkipped('No test git repository available.');
        }

        $this->git->open($repo);
        $url = $this->git->getRemoteUrl('nonexistent-remote-' . uniqid());

        $this->assertNull($url);
    }

    #[Test]
    public function get_command_runner_returns_runner_instance(): void
    {
        $runner = $this->git->getCommandRunner();
        $this->assertInstanceOf(GitCommandRunner::class, $runner);
    }

    // ─── WRITE OPERATIONS (mocked to avoid modifying real repos) ────────

    #[Test]
    public function commit_rejects_empty_message(): void
    {
        $repo = $this->findTestRepo();
        if ($repo === null) {
            $this->markTestSkipped('No test git repository available.');
        }

        $this->git->open($repo);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Commit message cannot be empty');

        $this->git->commit('');
    }

    #[Test]
    public function commit_rejects_whitespace_only_message(): void
    {
        $repo = $this->findTestRepo();
        if ($repo === null) {
            $this->markTestSkipped('No test git repository available.');
        }

        $this->git->open($repo);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Commit message cannot be empty');

        $this->git->commit('   ');
    }

    #[Test]
    public function stage_calls_git_add_with_paths(): void
    {
        $mockRunner = $this->createMock(GitCommandRunner::class);
        $mockRunner->method('isValidRepo')->willReturn(true);
        $mockRunner->method('setRepoPath')->willReturnSelf();
        $mockRunner->method('runWithTranslation')
            ->with(['add', '--', 'file1.txt', 'file2.txt'])
            ->willReturn(new GitResult(
                success: true, output: '', error: '', exitCode: 0, command: 'git add -- file1.txt file2.txt'
            ));

        $git = $this->makeGitServiceWithRunner($mockRunner);
        $git->open('/fake/repo');
        $result = $git->stage(['file1.txt', 'file2.txt']);

        $this->assertTrue($result->success);
    }

    #[Test]
    public function stage_all_calls_git_add_a(): void
    {
        $mockRunner = $this->createMock(GitCommandRunner::class);
        $mockRunner->method('isValidRepo')->willReturn(true);
        $mockRunner->method('setRepoPath')->willReturnSelf();
        $mockRunner->method('runWithTranslation')
            ->with(['add', '-A'])
            ->willReturn(new GitResult(
                success: true, output: '', error: '', exitCode: 0, command: 'git add -A'
            ));

        $git = $this->makeGitServiceWithRunner($mockRunner);
        $git->open('/fake/repo');
        $result = $git->stageAll();

        $this->assertTrue($result->success);
    }

    #[Test]
    public function unstage_calls_git_reset_head(): void
    {
        $mockRunner = $this->createMock(GitCommandRunner::class);
        $mockRunner->method('isValidRepo')->willReturn(true);
        $mockRunner->method('setRepoPath')->willReturnSelf();
        $mockRunner->method('runWithTranslation')
            ->with(['reset', 'HEAD', '--', 'file.txt'])
            ->willReturn(new GitResult(
                success: true, output: '', error: '', exitCode: 0, command: 'git reset HEAD -- file.txt'
            ));

        $git = $this->makeGitServiceWithRunner($mockRunner);
        $git->open('/fake/repo');
        $result = $git->unstage(['file.txt']);

        $this->assertTrue($result->success);
    }

    #[Test]
    public function unstage_all_calls_git_reset_head(): void
    {
        $mockRunner = $this->createMock(GitCommandRunner::class);
        $mockRunner->method('isValidRepo')->willReturn(true);
        $mockRunner->method('setRepoPath')->willReturnSelf();
        $mockRunner->method('runWithTranslation')
            ->with(['reset', 'HEAD'])
            ->willReturn(new GitResult(
                success: true, output: '', error: '', exitCode: 0, command: 'git reset HEAD'
            ));

        $git = $this->makeGitServiceWithRunner($mockRunner);
        $git->open('/fake/repo');
        $result = $git->unstageAll();

        $this->assertTrue($result->success);
    }

    #[Test]
    public function discard_changes_calls_git_checkout(): void
    {
        $mockRunner = $this->createMock(GitCommandRunner::class);
        $mockRunner->method('isValidRepo')->willReturn(true);
        $mockRunner->method('setRepoPath')->willReturnSelf();
        $mockRunner->method('runWithTranslation')
            ->with(['checkout', '--', 'file.txt'])
            ->willReturn(new GitResult(
                success: true, output: '', error: '', exitCode: 0, command: 'git checkout -- file.txt'
            ));

        $git = $this->makeGitServiceWithRunner($mockRunner);
        $git->open('/fake/repo');
        $result = $git->discardChanges(['file.txt']);

        $this->assertTrue($result->success);
    }

    #[Test]
    public function commit_calls_git_commit_with_message(): void
    {
        $mockRunner = $this->createMock(GitCommandRunner::class);
        $mockRunner->method('isValidRepo')->willReturn(true);
        $mockRunner->method('setRepoPath')->willReturnSelf();
        $mockRunner->method('runWithTranslation')
            ->with(['commit', '-m', 'Initial commit'])
            ->willReturn(new GitResult(
                success: true, output: '[main abc1234] Initial commit', error: '', exitCode: 0, command: 'git commit -m "Initial commit"'
            ));

        $git = $this->makeGitServiceWithRunner($mockRunner);
        $git->open('/fake/repo');
        $result = $git->commit('Initial commit');

        $this->assertTrue($result->success);
    }

    #[Test]
    public function amend_commit_with_message(): void
    {
        $mockRunner = $this->createMock(GitCommandRunner::class);
        $mockRunner->method('isValidRepo')->willReturn(true);
        $mockRunner->method('setRepoPath')->willReturnSelf();
        $mockRunner->method('runWithTranslation')
            ->with(['commit', '--amend', '-m', 'Updated message'])
            ->willReturn(new GitResult(
                success: true, output: '', error: '', exitCode: 0, command: 'git commit --amend'
            ));

        $git = $this->makeGitServiceWithRunner($mockRunner);
        $git->open('/fake/repo');
        $result = $git->amendCommit('Updated message');

        $this->assertTrue($result->success);
    }

    #[Test]
    public function amend_commit_without_message_uses_no_edit(): void
    {
        $mockRunner = $this->createMock(GitCommandRunner::class);
        $mockRunner->method('isValidRepo')->willReturn(true);
        $mockRunner->method('setRepoPath')->willReturnSelf();
        $mockRunner->method('runWithTranslation')
            ->with(['commit', '--amend', '--no-edit'])
            ->willReturn(new GitResult(
                success: true, output: '', error: '', exitCode: 0, command: 'git commit --amend --no-edit'
            ));

        $git = $this->makeGitServiceWithRunner($mockRunner);
        $git->open('/fake/repo');
        $result = $git->amendCommit();

        $this->assertTrue($result->success);
    }

    #[Test]
    public function create_branch_calls_git_branch(): void
    {
        $mockRunner = $this->createMock(GitCommandRunner::class);
        $mockRunner->method('isValidRepo')->willReturn(true);
        $mockRunner->method('setRepoPath')->willReturnSelf();
        $mockRunner->method('runWithTranslation')
            ->with(['branch', 'feature-x'])
            ->willReturn(new GitResult(
                success: true, output: '', error: '', exitCode: 0, command: 'git branch feature-x'
            ));

        $git = $this->makeGitServiceWithRunner($mockRunner);
        $git->open('/fake/repo');
        $result = $git->createBranch('feature-x');

        $this->assertTrue($result->success);
    }

    #[Test]
    public function create_branch_with_start_point(): void
    {
        $mockRunner = $this->createMock(GitCommandRunner::class);
        $mockRunner->method('isValidRepo')->willReturn(true);
        $mockRunner->method('setRepoPath')->willReturnSelf();
        $mockRunner->method('runWithTranslation')
            ->with(['branch', 'feature-x', 'abc1234'])
            ->willReturn(new GitResult(
                success: true, output: '', error: '', exitCode: 0, command: 'git branch feature-x abc1234'
            ));

        $git = $this->makeGitServiceWithRunner($mockRunner);
        $git->open('/fake/repo');
        $result = $git->createBranch('feature-x', 'abc1234');

        $this->assertTrue($result->success);
    }

    #[Test]
    public function checkout_calls_git_checkout(): void
    {
        $mockRunner = $this->createMock(GitCommandRunner::class);
        $mockRunner->method('isValidRepo')->willReturn(true);
        $mockRunner->method('setRepoPath')->willReturnSelf();
        $mockRunner->method('runWithTranslation')
            ->with(['checkout', 'main'])
            ->willReturn(new GitResult(
                success: true, output: '', error: '', exitCode: 0, command: 'git checkout main'
            ));

        $git = $this->makeGitServiceWithRunner($mockRunner);
        $git->open('/fake/repo');
        $result = $git->checkout('main');

        $this->assertTrue($result->success);
    }

    #[Test]
    public function checkout_new_branch_uses_b_flag(): void
    {
        $mockRunner = $this->createMock(GitCommandRunner::class);
        $mockRunner->method('isValidRepo')->willReturn(true);
        $mockRunner->method('setRepoPath')->willReturnSelf();
        $mockRunner->method('runWithTranslation')
            ->with(['checkout', '-b', 'new-feature'])
            ->willReturn(new GitResult(
                success: true, output: '', error: '', exitCode: 0, command: 'git checkout -b new-feature'
            ));

        $git = $this->makeGitServiceWithRunner($mockRunner);
        $git->open('/fake/repo');
        $result = $git->checkoutNewBranch('new-feature');

        $this->assertTrue($result->success);
    }

    #[Test]
    public function delete_branch_uses_d_flag(): void
    {
        $mockRunner = $this->createMock(GitCommandRunner::class);
        $mockRunner->method('isValidRepo')->willReturn(true);
        $mockRunner->method('setRepoPath')->willReturnSelf();
        $mockRunner->method('runWithTranslation')
            ->with(['branch', '-d', 'old-branch'])
            ->willReturn(new GitResult(
                success: true, output: '', error: '', exitCode: 0, command: 'git branch -d old-branch'
            ));

        $git = $this->makeGitServiceWithRunner($mockRunner);
        $git->open('/fake/repo');
        $result = $git->deleteBranch('old-branch');

        $this->assertTrue($result->success);
    }

    #[Test]
    public function force_delete_branch_uses_capital_d_flag(): void
    {
        $mockRunner = $this->createMock(GitCommandRunner::class);
        $mockRunner->method('isValidRepo')->willReturn(true);
        $mockRunner->method('setRepoPath')->willReturnSelf();
        $mockRunner->method('runWithTranslation')
            ->with(['branch', '-D', 'old-branch'])
            ->willReturn(new GitResult(
                success: true, output: '', error: '', exitCode: 0, command: 'git branch -D old-branch'
            ));

        $git = $this->makeGitServiceWithRunner($mockRunner);
        $git->open('/fake/repo');
        $result = $git->deleteBranch('old-branch', force: true);

        $this->assertTrue($result->success);
    }

    #[Test]
    public function merge_returns_result_without_throwing(): void
    {
        $mockRunner = $this->createMock(GitCommandRunner::class);
        $mockRunner->method('isValidRepo')->willReturn(true);
        $mockRunner->method('setRepoPath')->willReturnSelf();
        $mockRunner->method('runWithTranslation')
            ->with(['merge', 'feature-branch'])
            ->willReturn(new GitResult(
                success: false, output: '', error: 'CONFLICT (content): Merge conflict', exitCode: 1, command: 'git merge feature-branch'
            ));

        $git = $this->makeGitServiceWithRunner($mockRunner);
        $git->open('/fake/repo');
        $result = $git->merge('feature-branch');

        // merge doesn't throw on conflict — caller checks result
        $this->assertFalse($result->success);
    }

    #[Test]
    public function merge_with_no_ff_flag(): void
    {
        $mockRunner = $this->createMock(GitCommandRunner::class);
        $mockRunner->method('isValidRepo')->willReturn(true);
        $mockRunner->method('setRepoPath')->willReturnSelf();
        $mockRunner->method('runWithTranslation')
            ->with(['merge', 'feature-branch', '--no-ff'])
            ->willReturn(new GitResult(
                success: true, output: '', error: '', exitCode: 0, command: 'git merge feature-branch --no-ff'
            ));

        $git = $this->makeGitServiceWithRunner($mockRunner);
        $git->open('/fake/repo');
        $result = $git->merge('feature-branch', noFf: true);

        $this->assertTrue($result->success);
    }

    #[Test]
    public function create_tag_calls_git_tag(): void
    {
        $mockRunner = $this->createMock(GitCommandRunner::class);
        $mockRunner->method('isValidRepo')->willReturn(true);
        $mockRunner->method('setRepoPath')->willReturnSelf();
        $mockRunner->method('runWithTranslation')
            ->with(['tag', 'v1.0'])
            ->willReturn(new GitResult(
                success: true, output: '', error: '', exitCode: 0, command: 'git tag v1.0'
            ));

        $git = $this->makeGitServiceWithRunner($mockRunner);
        $git->open('/fake/repo');
        $result = $git->createTag('v1.0');

        $this->assertTrue($result->success);
    }

    #[Test]
    public function create_annotated_tag_uses_a_and_m_flags(): void
    {
        $mockRunner = $this->createMock(GitCommandRunner::class);
        $mockRunner->method('isValidRepo')->willReturn(true);
        $mockRunner->method('setRepoPath')->willReturnSelf();
        $mockRunner->method('runWithTranslation')
            ->with(['tag', '-a', 'v1.0', '-m', 'Release 1.0'])
            ->willReturn(new GitResult(
                success: true, output: '', error: '', exitCode: 0, command: 'git tag -a v1.0 -m "Release 1.0"'
            ));

        $git = $this->makeGitServiceWithRunner($mockRunner);
        $git->open('/fake/repo');
        $result = $git->createAnnotatedTag('v1.0', 'Release 1.0');

        $this->assertTrue($result->success);
    }

    #[Test]
    public function delete_tag_calls_git_tag_d(): void
    {
        $mockRunner = $this->createMock(GitCommandRunner::class);
        $mockRunner->method('isValidRepo')->willReturn(true);
        $mockRunner->method('setRepoPath')->willReturnSelf();
        $mockRunner->method('runWithTranslation')
            ->with(['tag', '-d', 'v1.0'])
            ->willReturn(new GitResult(
                success: true, output: '', error: '', exitCode: 0, command: 'git tag -d v1.0'
            ));

        $git = $this->makeGitServiceWithRunner($mockRunner);
        $git->open('/fake/repo');
        $result = $git->deleteTag('v1.0');

        $this->assertTrue($result->success);
    }

    #[Test]
    public function push_tag_includes_refs_tags_prefix(): void
    {
        $mockRunner = $this->createMock(GitCommandRunner::class);
        $mockRunner->method('isValidRepo')->willReturn(true);
        $mockRunner->method('setRepoPath')->willReturnSelf();
        $mockRunner->method('runWithTranslation')
            ->with(['push', 'origin', 'refs/tags/v1.0'], 60)
            ->willReturn(new GitResult(
                success: true, output: '', error: '', exitCode: 0, command: 'git push origin refs/tags/v1.0'
            ));

        $git = $this->makeGitServiceWithRunner($mockRunner);
        $git->open('/fake/repo');
        $result = $git->pushTag('v1.0');

        $this->assertTrue($result->success);
    }

    #[Test]
    public function push_all_tags_uses_tags_flag(): void
    {
        $mockRunner = $this->createMock(GitCommandRunner::class);
        $mockRunner->method('isValidRepo')->willReturn(true);
        $mockRunner->method('setRepoPath')->willReturnSelf();
        $mockRunner->method('runWithTranslation')
            ->with(['push', 'origin', '--tags'], 60)
            ->willReturn(new GitResult(
                success: true, output: '', error: '', exitCode: 0, command: 'git push origin --tags'
            ));

        $git = $this->makeGitServiceWithRunner($mockRunner);
        $git->open('/fake/repo');
        $result = $git->pushAllTags();

        $this->assertTrue($result->success);
    }

    #[Test]
    public function stash_calls_git_stash_push(): void
    {
        $mockRunner = $this->createMock(GitCommandRunner::class);
        $mockRunner->method('isValidRepo')->willReturn(true);
        $mockRunner->method('setRepoPath')->willReturnSelf();
        $mockRunner->method('runWithTranslation')
            ->with(['stash', 'push'])
            ->willReturn(new GitResult(
                success: true, output: 'Saved working directory', error: '', exitCode: 0, command: 'git stash push'
            ));

        $git = $this->makeGitServiceWithRunner($mockRunner);
        $git->open('/fake/repo');
        $result = $git->stash();

        $this->assertTrue($result->success);
    }

    #[Test]
    public function stash_with_message(): void
    {
        $mockRunner = $this->createMock(GitCommandRunner::class);
        $mockRunner->method('isValidRepo')->willReturn(true);
        $mockRunner->method('setRepoPath')->willReturnSelf();
        $mockRunner->method('runWithTranslation')
            ->with(['stash', 'push', '-m', 'WIP: feature'])
            ->willReturn(new GitResult(
                success: true, output: '', error: '', exitCode: 0, command: 'git stash push -m "WIP: feature"'
            ));

        $git = $this->makeGitServiceWithRunner($mockRunner);
        $git->open('/fake/repo');
        $result = $git->stash('WIP: feature');

        $this->assertTrue($result->success);
    }

    #[Test]
    public function stash_pop_does_not_throw_on_conflict(): void
    {
        $mockRunner = $this->createMock(GitCommandRunner::class);
        $mockRunner->method('isValidRepo')->willReturn(true);
        $mockRunner->method('setRepoPath')->willReturnSelf();
        $mockRunner->method('runWithTranslation')
            ->with(['stash', 'pop', 'stash@{0}'])
            ->willReturn(new GitResult(
                success: false, output: '', error: 'CONFLICT', exitCode: 1, command: 'git stash pop'
            ));

        $git = $this->makeGitServiceWithRunner($mockRunner);
        $git->open('/fake/repo');
        $result = $git->stashPop();

        // Should not throw — conflicts are expected
        $this->assertFalse($result->success);
    }

    #[Test]
    public function fetch_uses_prune_by_default(): void
    {
        $mockRunner = $this->createMock(GitCommandRunner::class);
        $mockRunner->method('isValidRepo')->willReturn(true);
        $mockRunner->method('setRepoPath')->willReturnSelf();
        $mockRunner->method('runWithTranslation')
            ->with(['fetch', 'origin', '--prune'], 120)
            ->willReturn(new GitResult(
                success: true, output: '', error: '', exitCode: 0, command: 'git fetch origin --prune'
            ));

        $git = $this->makeGitServiceWithRunner($mockRunner);
        $git->open('/fake/repo');
        $result = $git->fetch();

        $this->assertTrue($result->success);
    }

    #[Test]
    public function fetch_without_prune(): void
    {
        $mockRunner = $this->createMock(GitCommandRunner::class);
        $mockRunner->method('isValidRepo')->willReturn(true);
        $mockRunner->method('setRepoPath')->willReturnSelf();
        $mockRunner->method('runWithTranslation')
            ->with(['fetch', 'origin'], 120)
            ->willReturn(new GitResult(
                success: true, output: '', error: '', exitCode: 0, command: 'git fetch origin'
            ));

        $git = $this->makeGitServiceWithRunner($mockRunner);
        $git->open('/fake/repo');
        $result = $git->fetch(prune: false);

        $this->assertTrue($result->success);
    }

    #[Test]
    public function pull_does_not_throw_on_conflict(): void
    {
        $mockRunner = $this->createMock(GitCommandRunner::class);
        $mockRunner->method('isValidRepo')->willReturn(true);
        $mockRunner->method('setRepoPath')->willReturnSelf();
        $mockRunner->method('runWithTranslation')
            ->with(['pull', 'origin'], 120)
            ->willReturn(new GitResult(
                success: false, output: '', error: 'CONFLICT', exitCode: 1, command: 'git pull origin'
            ));

        $git = $this->makeGitServiceWithRunner($mockRunner);
        $git->open('/fake/repo');
        $result = $git->pull();

        $this->assertFalse($result->success);
    }

    #[Test]
    public function push_with_set_upstream(): void
    {
        $mockRunner = $this->createMock(GitCommandRunner::class);
        $mockRunner->method('isValidRepo')->willReturn(true);
        $mockRunner->method('setRepoPath')->willReturnSelf();
        $mockRunner->method('runWithTranslation')
            ->with(['push', '-u', 'origin', 'feature-x'], 120)
            ->willReturn(new GitResult(
                success: true, output: '', error: '', exitCode: 0, command: 'git push -u origin feature-x'
            ));

        $git = $this->makeGitServiceWithRunner($mockRunner);
        $git->open('/fake/repo');
        $result = $git->push('origin', 'feature-x', setUpstream: true);

        $this->assertTrue($result->success);
    }

    #[Test]
    public function merge_abort_calls_git_merge_abort(): void
    {
        $mockRunner = $this->createMock(GitCommandRunner::class);
        $mockRunner->method('isValidRepo')->willReturn(true);
        $mockRunner->method('setRepoPath')->willReturnSelf();
        $mockRunner->method('runWithTranslation')
            ->with(['merge', '--abort'])
            ->willReturn(new GitResult(
                success: true, output: '', error: '', exitCode: 0, command: 'git merge --abort'
            ));

        $git = $this->makeGitServiceWithRunner($mockRunner);
        $git->open('/fake/repo');
        $result = $git->mergeAbort();

        $this->assertTrue($result->success);
    }

    #[Test]
    public function stash_apply_does_not_throw_on_conflict(): void
    {
        $mockRunner = $this->createMock(GitCommandRunner::class);
        $mockRunner->method('isValidRepo')->willReturn(true);
        $mockRunner->method('setRepoPath')->willReturnSelf();
        $mockRunner->method('runWithTranslation')
            ->with(['stash', 'apply', 'stash@{0}'])
            ->willReturn(new GitResult(
                success: false, output: '', error: 'CONFLICT', exitCode: 1, command: 'git stash apply'
            ));

        $git = $this->makeGitServiceWithRunner($mockRunner);
        $git->open('/fake/repo');
        $result = $git->stashApply();

        $this->assertFalse($result->success);
    }

    #[Test]
    public function stash_drop_calls_git_stash_drop(): void
    {
        $mockRunner = $this->createMock(GitCommandRunner::class);
        $mockRunner->method('isValidRepo')->willReturn(true);
        $mockRunner->method('setRepoPath')->willReturnSelf();
        $mockRunner->method('runWithTranslation')
            ->with(['stash', 'drop', 'stash@{1}'])
            ->willReturn(new GitResult(
                success: true, output: '', error: '', exitCode: 0, command: 'git stash drop stash@{1}'
            ));

        $git = $this->makeGitServiceWithRunner($mockRunner);
        $git->open('/fake/repo');
        $result = $git->stashDrop('stash@{1}');

        $this->assertTrue($result->success);
    }

    // ─── HELPERS ────────────────────────────────────────────────────────

    /**
     * Find an available test repo on this machine.
     */
    private function findTestRepo(): ?string
    {
        $candidates = [
            '/Users/samhopkinson/webroot/ptp',
            '/Users/samhopkinson/webroot/counting_cards',
            '/Users/samhopkinson/webroot/winecx',
        ];

        foreach ($candidates as $repo) {
            if (is_dir($repo . '/.git')) {
                return $repo;
            }
        }

        return null;
    }

    /**
     * Create a GitService with a custom (mock) command runner.
     */
    private function makeGitServiceWithRunner(GitCommandRunner $runner): GitService
    {
        return new GitService(
            commandRunner: $runner,
            statusParser: new StatusParser,
            logParser: new LogParser,
            branchParser: new BranchParser,
            diffParser: new DiffParser,
            tagParser: new TagParser,
            stashParser: new StashParser,
        );
    }
}
