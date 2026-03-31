<?php

namespace Tests\Unit\Parsers;

use App\Services\Git\Parsers\StatusParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class StatusParserTest extends TestCase
{
    private StatusParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new StatusParser;
    }

    #[Test]
    public function it_parses_clean_repo_status(): void
    {
        $output = <<<'GIT'
# branch.oid 288ae4816945a4a12f7349a6498e830b18fcd836
# branch.head master
# branch.upstream origin/master
# branch.ab +0 -0
GIT;

        $state = $this->parser->parse($output);

        $this->assertSame('288ae4816945a4a12f7349a6498e830b18fcd836', $state->headHash);
        $this->assertSame('master', $state->branch);
        $this->assertSame('origin/master', $state->upstream);
        $this->assertSame(0, $state->ahead);
        $this->assertSame(0, $state->behind);
        $this->assertFalse($state->isDetached);
        $this->assertTrue($state->isClean());
        $this->assertCount(0, $state->files);
    }

    #[Test]
    public function it_parses_ahead_behind_counts(): void
    {
        $output = <<<'GIT'
# branch.oid abc123
# branch.head feature
# branch.upstream origin/feature
# branch.ab +3 -1
GIT;

        $state = $this->parser->parse($output);

        $this->assertSame(3, $state->ahead);
        $this->assertSame(1, $state->behind);
    }

    #[Test]
    public function it_parses_detached_head(): void
    {
        $output = <<<'GIT'
# branch.oid abc123
# branch.head (detached)
GIT;

        $state = $this->parser->parse($output);

        $this->assertTrue($state->isDetached);
        $this->assertSame('(detached)', $state->branch);
        $this->assertNull($state->upstream);
    }

    #[Test]
    public function it_parses_no_upstream(): void
    {
        $output = <<<'GIT'
# branch.oid abc123
# branch.head new-branch
GIT;

        $state = $this->parser->parse($output);

        $this->assertNull($state->upstream);
        $this->assertSame(0, $state->ahead);
        $this->assertSame(0, $state->behind);
    }

    #[Test]
    public function it_parses_ordinary_modified_file(): void
    {
        $output = <<<'GIT'
# branch.oid abc123
# branch.head main
1 .M N... 100644 100644 100644 abc123 def456 src/app.php
GIT;

        $state = $this->parser->parse($output);

        $this->assertCount(1, $state->files);
        $file = $state->files[0];
        $this->assertSame('src/app.php', $file->path);
        $this->assertSame(' ', $file->indexStatus);
        $this->assertSame('M', $file->workStatus);
        $this->assertFalse($file->isStaged());
        $this->assertTrue($file->isModified());
        $this->assertNull($file->origPath);
    }

    #[Test]
    public function it_parses_staged_file(): void
    {
        $output = <<<'GIT'
# branch.oid abc123
# branch.head main
1 M. N... 100644 100644 100644 abc123 def456 src/app.php
GIT;

        $state = $this->parser->parse($output);

        $file = $state->files[0];
        $this->assertSame('M', $file->indexStatus);
        $this->assertSame(' ', $file->workStatus);
        $this->assertTrue($file->isStaged());
        $this->assertFalse($file->isModified());
    }

    #[Test]
    public function it_parses_added_file(): void
    {
        $output = <<<'GIT'
# branch.oid abc123
# branch.head main
1 A. N... 000000 100644 100644 0000000 abc1234 newfile.txt
GIT;

        $state = $this->parser->parse($output);

        $file = $state->files[0];
        $this->assertSame('A', $file->indexStatus);
        $this->assertTrue($file->isStaged());
        $this->assertSame('Added', $file->label());
    }

    #[Test]
    public function it_parses_deleted_file(): void
    {
        $output = <<<'GIT'
# branch.oid abc123
# branch.head main
1 D. N... 100644 000000 000000 abc1234 0000000 oldfile.txt
GIT;

        $state = $this->parser->parse($output);

        $file = $state->files[0];
        $this->assertSame('D', $file->indexStatus);
        $this->assertSame('Deleted', $file->label());
    }

    #[Test]
    public function it_parses_renamed_file(): void
    {
        $output = <<<'GIT'
# branch.oid abc123
# branch.head main
2 R. N... 100644 100644 100644 abc123 def456 R100 new/path.php	old/path.php
GIT;

        $state = $this->parser->parse($output);

        $file = $state->files[0];
        $this->assertSame('new/path.php', $file->path);
        $this->assertSame('old/path.php', $file->origPath);
        $this->assertSame('R', $file->indexStatus);
        $this->assertTrue($file->isRenamed());
        $this->assertSame('Renamed', $file->label());
    }

    #[Test]
    public function it_parses_untracked_file(): void
    {
        $output = <<<'GIT'
# branch.oid abc123
# branch.head main
? newfile.txt
GIT;

        $state = $this->parser->parse($output);

        $file = $state->files[0];
        $this->assertSame('newfile.txt', $file->path);
        $this->assertTrue($file->isUntracked());
        $this->assertSame('Untracked', $file->label());
    }

    #[Test]
    public function it_parses_unmerged_file(): void
    {
        $output = <<<'GIT'
# branch.oid abc123
# branch.head main
u UU N... 100644 100644 100644 100644 abc123 def456 ghi789 conflict.php
GIT;

        $state = $this->parser->parse($output);

        $file = $state->files[0];
        $this->assertSame('conflict.php', $file->path);
        $this->assertTrue($file->isConflicted());
        $this->assertSame('Conflicted', $file->label());
    }

    #[Test]
    public function it_parses_mixed_file_statuses(): void
    {
        $output = <<<'GIT'
# branch.oid abc123
# branch.head main
# branch.upstream origin/main
# branch.ab +2 -0
1 M. N... 100644 100644 100644 abc123 def456 staged.php
1 .M N... 100644 100644 100644 abc123 def456 modified.php
? untracked.txt
GIT;

        $state = $this->parser->parse($output);

        $this->assertCount(3, $state->files);
        $this->assertCount(1, $state->stagedFiles());
        $this->assertCount(1, $state->unstagedFiles());
        $this->assertCount(1, $state->untrackedFiles());
        $this->assertFalse($state->isClean());
        $this->assertFalse($state->hasConflicts());
    }

    #[Test]
    public function it_categorizes_files_correctly_in_repo_state(): void
    {
        $output = <<<'GIT'
# branch.oid abc123
# branch.head main
1 M. N... 100644 100644 100644 abc123 def456 staged.php
1 .M N... 100644 100644 100644 abc123 def456 worktree.php
? new.txt
u UU N... 100644 100644 100644 100644 abc123 def456 ghi789 conflict.php
GIT;

        $state = $this->parser->parse($output);

        $this->assertCount(4, $state->files);
        $this->assertSame('staged.php', $state->stagedFiles()[0]->path);
        $this->assertSame('worktree.php', $state->unstagedFiles()[0]->path);
        $this->assertSame('new.txt', $state->untrackedFiles()[0]->path);
        $this->assertSame('conflict.php', $state->conflictedFiles()[0]->path);
        $this->assertTrue($state->hasConflicts());
    }

    #[Test]
    public function it_handles_empty_output(): void
    {
        $state = $this->parser->parse('');

        $this->assertSame('', $state->headHash);
        $this->assertSame('', $state->branch);
        $this->assertTrue($state->isClean());
    }
}
