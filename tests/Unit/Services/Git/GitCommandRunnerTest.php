<?php

namespace Tests\Unit\Services\Git;

use App\DTOs\GitResult;
use App\Services\Git\GitCommandRunner;
use App\Services\Git\GitErrorTranslator;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GitCommandRunnerTest extends TestCase
{
    private GitCommandRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runner = new GitCommandRunner(new GitErrorTranslator);
    }

    #[Test]
    public function it_runs_git_version_against_real_git(): void
    {
        $version = $this->runner->version();

        $this->assertNotNull($version);
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $version);
    }

    #[Test]
    public function it_returns_successful_result_for_valid_command(): void
    {
        $result = $this->runner->run(['--version']);

        $this->assertTrue($result->success);
        $this->assertSame(0, $result->exitCode);
        $this->assertStringContains('git version', $result->output);
        $this->assertSame('git --version', $result->command);
    }

    #[Test]
    public function it_returns_failure_for_non_git_directory(): void
    {
        $this->runner->setRepoPath('/tmp');

        $result = $this->runner->run(['status']);

        $this->assertFalse($result->success);
        $this->assertNotSame(0, $result->exitCode);
    }

    #[Test]
    public function it_returns_failure_for_nonexistent_path(): void
    {
        $this->runner->setRepoPath('/tmp/definitely-not-a-real-path-'.uniqid());

        $result = $this->runner->run(['status']);

        $this->assertFalse($result->success);
        $this->assertSame(128, $result->exitCode);
    }

    #[Test]
    public function it_returns_a_failure_when_git_cannot_be_started(): void
    {
        Process::fake(fn () => throw new \RuntimeException('git executable not found'));

        $result = $this->runner->run(['--version']);

        $this->assertFalse($result->success);
        $this->assertSame(127, $result->exitCode);
        $this->assertSame('git executable not found', $result->error);
        $this->assertNull($this->runner->version());
    }

    #[Test]
    public function it_detects_valid_git_repo(): void
    {
        $repo = $this->findTestRepo();
        if ($repo === null) {
            $this->markTestSkipped('No test git repository available.');
        }

        $this->runner->setRepoPath($repo);
        $this->assertTrue($this->runner->isValidRepo());
    }

    #[Test]
    public function it_resolves_the_absolute_git_directory(): void
    {
        $repo = $this->findTestRepo();
        if ($repo === null) {
            $this->markTestSkipped('No test git repository available.');
        }

        $this->runner->setRepoPath($repo);
        $gitDir = $this->runner->absoluteGitDir();

        $this->assertNotNull($gitDir);
        $this->assertDirectoryExists($gitDir);
    }

    #[Test]
    public function it_detects_invalid_repo_path(): void
    {
        $this->runner->setRepoPath('/tmp');
        $this->assertFalse($this->runner->isValidRepo());
    }

    #[Test]
    public function it_returns_false_for_null_repo_path(): void
    {
        $this->assertFalse($this->runner->isValidRepo());
    }

    #[Test]
    public function it_translates_errors_with_run_with_translation(): void
    {
        $this->runner->setRepoPath('/tmp');

        $result = $this->runner->runWithTranslation(['status']);

        $this->assertFalse($result->success);
        $this->assertSame('This directory is not a Git repository.', $result->error);
    }

    #[Test]
    public function it_translates_errors_for_nonexistent_path(): void
    {
        $this->runner->setRepoPath('/tmp/definitely-not-a-real-path-'.uniqid());

        $result = $this->runner->runWithTranslation(['status']);

        $this->assertFalse($result->success);
        $this->assertSame('This directory is not a Git repository.', $result->error);
    }

    #[Test]
    public function it_preserves_output_on_successful_translated_run(): void
    {
        $result = $this->runner->runWithTranslation(['--version']);

        $this->assertTrue($result->success);
        $this->assertStringContains('git version', $result->output);
    }

    #[Test]
    public function set_repo_path_returns_self_for_chaining(): void
    {
        $returned = $this->runner->setRepoPath('/tmp');
        $this->assertSame($this->runner, $returned);
    }

    #[Test]
    public function get_repo_path_returns_set_path(): void
    {
        $this->assertNull($this->runner->getRepoPath());

        $this->runner->setRepoPath('/some/path');
        $this->assertSame('/some/path', $this->runner->getRepoPath());
    }

    #[Test]
    public function it_can_run_status_on_real_repo(): void
    {
        $repo = $this->findTestRepo();
        if ($repo === null) {
            $this->markTestSkipped('No test git repository available.');
        }

        $this->runner->setRepoPath($repo);
        $result = $this->runner->status();

        $this->assertTrue($result->success);
        // porcelain v2 branch output starts with "# branch."
        $this->assertStringContains('# branch.', $result->output);
    }

    #[Test]
    public function it_can_run_log_on_real_repo(): void
    {
        $repo = $this->findTestRepo();
        if ($repo === null) {
            $this->markTestSkipped('No test git repository available.');
        }

        $this->runner->setRepoPath($repo);
        $result = $this->runner->log(5);

        $this->assertTrue($result->success);
        $this->assertGreaterThanOrEqual(9, substr_count($result->output, "\0"));
    }

    #[Test]
    public function it_can_run_branches_on_real_repo(): void
    {
        $repo = $this->findTestRepo();
        if ($repo === null) {
            $this->markTestSkipped('No test git repository available.');
        }

        $this->runner->setRepoPath($repo);
        $result = $this->runner->branches();

        $this->assertTrue($result->success);
        $this->assertNotEmpty($result->lines());
    }

    #[Test]
    public function git_result_lines_splits_output_correctly(): void
    {
        $result = new GitResult(
            success: true,
            output: "line1\nline2\nline3\n",
            error: '',
            exitCode: 0,
            command: 'test',
        );

        $this->assertSame(['line1', 'line2', 'line3'], $result->lines());
    }

    #[Test]
    public function git_result_lines_returns_empty_for_no_output(): void
    {
        $result = new GitResult(
            success: true,
            output: '',
            error: '',
            exitCode: 0,
            command: 'test',
        );

        $this->assertSame([], $result->lines());
    }

    #[Test]
    public function git_result_throw_throws_on_failure(): void
    {
        $result = new GitResult(
            success: false,
            output: '',
            error: 'something went wrong',
            exitCode: 128,
            command: 'git status',
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Git command failed: something went wrong');
        $this->expectExceptionCode(128);

        $result->throw();
    }

    #[Test]
    public function git_result_throw_with_context(): void
    {
        $result = new GitResult(
            success: false,
            output: '',
            error: 'bad ref',
            exitCode: 1,
            command: 'git log',
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Loading commit history: bad ref');

        $result->throw('Loading commit history');
    }

    #[Test]
    public function git_result_throw_returns_self_on_success(): void
    {
        $result = new GitResult(
            success: true,
            output: 'ok',
            error: '',
            exitCode: 0,
            command: 'git status',
        );

        $this->assertSame($result, $result->throw());
    }

    /**
     * Find an available test repo on this machine.
     */
    private function findTestRepo(): ?string
    {
        return file_exists(base_path('.git')) ? base_path() : null;
    }

    /**
     * Helper to assert a string contains a substring.
     */
    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertStringContainsString($needle, $haystack);
    }
}
