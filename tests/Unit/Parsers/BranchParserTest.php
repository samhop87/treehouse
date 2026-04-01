<?php

namespace Tests\Unit\Parsers;

use App\Services\Git\Parsers\BranchParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BranchParserTest extends TestCase
{
    private BranchParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new BranchParser;
    }

    #[Test]
    public function it_parses_current_local_branch(): void
    {
        $output = 'refs/heads/master|master|288ae48|*|origin/master|';

        $branches = $this->parser->parse($output);

        $this->assertCount(1, $branches);
        $branch = $branches[0];
        $this->assertSame('master', $branch->name);
        $this->assertSame('288ae48', $branch->hash);
        $this->assertTrue($branch->isCurrent);
        $this->assertFalse($branch->isRemote);
        $this->assertSame('origin/master', $branch->upstream);
        $this->assertNull($branch->ahead);
        $this->assertNull($branch->behind);
    }

    #[Test]
    public function it_parses_remote_branch(): void
    {
        $output = 'refs/remotes/origin/master|origin/master|288ae48| ||';

        $branches = $this->parser->parse($output);

        $branch = $branches[0];
        $this->assertSame('origin/master', $branch->name);
        $this->assertFalse($branch->isCurrent);
        $this->assertTrue($branch->isRemote);
        $this->assertNull($branch->upstream);
    }

    #[Test]
    public function it_parses_ahead_behind_tracking(): void
    {
        $output = 'refs/heads/feature|feature|abc1234|*|origin/feature|[ahead 3, behind 1]';

        $branches = $this->parser->parse($output);

        $branch = $branches[0];
        $this->assertSame(3, $branch->ahead);
        $this->assertSame(1, $branch->behind);
    }

    #[Test]
    public function it_parses_ahead_only(): void
    {
        $output = 'refs/heads/feature|feature|abc1234| |origin/feature|[ahead 5]';

        $branches = $this->parser->parse($output);

        $branch = $branches[0];
        $this->assertSame(5, $branch->ahead);
        $this->assertNull($branch->behind);
    }

    #[Test]
    public function it_parses_behind_only(): void
    {
        $output = 'refs/heads/feature|feature|abc1234| |origin/feature|[behind 2]';

        $branches = $this->parser->parse($output);

        $branch = $branches[0];
        $this->assertNull($branch->ahead);
        $this->assertSame(2, $branch->behind);
    }

    #[Test]
    public function it_parses_multiple_branches(): void
    {
        $output = <<<'GIT'
refs/heads/master|master|288ae48|*|origin/master|
refs/remotes/origin/HEAD|origin/HEAD|288ae48| ||
refs/remotes/origin/master|origin/master|288ae48| ||
refs/remotes/origin/production|origin/production|9a31d05| ||
GIT;

        $branches = $this->parser->parse($output);

        $this->assertCount(3, $branches);

        // First is local current branch
        $this->assertSame('master', $branches[0]->name);
        $this->assertTrue($branches[0]->isCurrent);
        $this->assertFalse($branches[0]->isRemote);

        // Remote branches
        $this->assertTrue($branches[1]->isRemote);
        $this->assertSame('origin/master', $branches[1]->name);
        $this->assertTrue($branches[2]->isRemote);
        $this->assertSame('origin/production', $branches[2]->name);
    }

    #[Test]
    public function it_parses_branch_without_upstream(): void
    {
        $output = 'refs/heads/new-feature|new-feature|def4567| ||';

        $branches = $this->parser->parse($output);

        $branch = $branches[0];
        $this->assertSame('new-feature', $branch->name);
        $this->assertNull($branch->upstream);
        $this->assertNull($branch->ahead);
        $this->assertNull($branch->behind);
    }

    #[Test]
    public function it_handles_empty_output(): void
    {
        $this->assertSame([], $this->parser->parse(''));
    }

    #[Test]
    public function it_skips_malformed_lines(): void
    {
        $output = <<<'GIT'
refs/heads/master|master|288ae48|*|origin/master|
broken-line
refs/heads/feature|feature|def456| |origin/feature|[ahead 1]
GIT;

        $branches = $this->parser->parse($output);

        $this->assertCount(2, $branches);
        $this->assertSame('master', $branches[0]->name);
        $this->assertSame('feature', $branches[1]->name);
    }
}
