<?php

namespace Tests\Feature;

use App\Services\Git\GitService;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class GitWorkflowIntegrationTest extends TestCase
{
    private string $repoPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoPath = sys_get_temp_dir().'/treehouse-git-'.bin2hex(random_bytes(6));
        mkdir($this->repoPath, 0700, true);
        $this->git('init', '-b', 'main');
        $this->git('config', 'user.name', 'Treehouse Tests');
        $this->git('config', 'user.email', 'treehouse@example.test');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->repoPath);

        parent::tearDown();
    }

    public function test_untracked_file_with_real_world_name_has_a_preview(): void
    {
        $path = "Bob's résumé.txt";
        file_put_contents($this->repoPath.'/'.$path, "hello\nworld\n");

        $git = app(GitService::class)->open($this->repoPath);
        $state = $git->getStatus();
        $diffs = $git->getFileDiff($path);

        $this->assertSame($path, $state->untrackedFiles()[0]->path);
        $this->assertCount(1, $diffs);
        $this->assertSame($path, $diffs[0]->path);
        $this->assertSame(2, $diffs[0]->additions());
    }

    public function test_a_staged_rename_can_be_fully_unstaged(): void
    {
        file_put_contents($this->repoPath.'/old name.txt', "content\n");
        $this->git('add', '--', 'old name.txt');
        $this->git('commit', '-m', 'base');
        $this->git('mv', 'old name.txt', 'new name.txt');

        $git = app(GitService::class)->open($this->repoPath);
        $rename = $git->getStatus()->stagedFiles()[0];
        $git->unstage([$rename->path, $rename->origPath]);

        $this->assertCount(0, $git->getStatus()->stagedFiles());
    }

    public function test_rebase_conflict_can_be_resolved_and_continued(): void
    {
        file_put_contents($this->repoPath.'/story.txt', "base\n");
        $this->git('add', 'story.txt');
        $this->git('commit', '-m', 'base');

        $this->git('checkout', '-b', 'feature');
        file_put_contents($this->repoPath.'/story.txt', "feature\n");
        $this->git('commit', '-am', 'feature change');

        $this->git('checkout', 'main');
        file_put_contents($this->repoPath.'/story.txt', "main\n");
        $this->git('commit', '-am', 'main change');
        $this->git('checkout', 'feature');

        $git = app(GitService::class)->open($this->repoPath);
        $result = $git->rebase('main');

        $this->assertFalse($result->success);
        $this->assertSame('rebase', $git->getOperationState());
        $this->assertTrue($git->getStatus()->hasConflicts());

        file_put_contents($this->repoPath.'/story.txt', "main\nfeature\n");
        $git->stage(['story.txt']);
        $continued = $git->continueOperation('rebase');

        $this->assertTrue($continued->success, $continued->error);
        $this->assertNull($git->getOperationState());
        $this->assertSame("main\nfeature\n", file_get_contents($this->repoPath.'/story.txt'));
    }

    private function git(string ...$arguments): void
    {
        $process = new Process(['git', ...$arguments], $this->repoPath);
        $process->mustRun();
    }
}
