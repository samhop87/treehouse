<?php

namespace Tests\Unit\Parsers;

use App\Services\Git\Parsers\LogParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class LogParserTest extends TestCase
{
    private LogParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new LogParser;
    }

    #[Test]
    public function it_parses_a_single_commit(): void
    {
        $output = '288ae4816945a4a12f7349a6498e830b18fcd836|288ae48|5438a960b7c958ab2cd331a6aac2298be3b454fe|Sam Hopkinson|s.hopkinson87@gmail.com|2026-01-18T19:48:49Z|HEAD -> master, origin/master, origin/HEAD|Adds peer dependencies';

        $commits = $this->parser->parse($output);

        $this->assertCount(1, $commits);

        $commit = $commits[0];
        $this->assertSame('288ae4816945a4a12f7349a6498e830b18fcd836', $commit->hash);
        $this->assertSame('288ae48', $commit->shortHash);
        $this->assertSame(['5438a960b7c958ab2cd331a6aac2298be3b454fe'], $commit->parents);
        $this->assertSame('Sam Hopkinson', $commit->author);
        $this->assertSame('s.hopkinson87@gmail.com', $commit->email);
        $this->assertSame('2026-01-18T19:48:49+00:00', $commit->date->toIso8601String());
        $this->assertSame('Adds peer dependencies', $commit->message);
        $this->assertSame(['HEAD -> master', 'origin/master', 'origin/HEAD'], $commit->refs);
        $this->assertFalse($commit->isMerge());
        $this->assertFalse($commit->isRoot());
    }

    #[Test]
    public function it_parses_multiple_commits(): void
    {
        $output = <<<'GIT'
288ae4816945a4a12f7349a6498e830b18fcd836|288ae48|5438a960b7c958ab2cd331a6aac2298be3b454fe|Sam Hopkinson|sam@test.com|2026-01-18T19:48:49Z|HEAD -> master|First commit
5438a960b7c958ab2cd331a6aac2298be3b454fe|5438a96|d198fb097de1a66f7b018dfcec4e9d28de94f2d1|Sam Hopkinson|sam@test.com|2026-01-18T19:25:47Z||Second commit
GIT;

        $commits = $this->parser->parse($output);

        $this->assertCount(2, $commits);
        $this->assertSame('288ae48', $commits[0]->shortHash);
        $this->assertSame('5438a96', $commits[1]->shortHash);
    }

    #[Test]
    public function it_parses_merge_commit_with_two_parents(): void
    {
        $output = 'efe8dcd82065d86a2868e9af4ee232a51ec92e0f|efe8dcd|775653a6584b3b94688dea27e29d9a0876bdafc6 392fda1524587db2881f59ea18399161ea7a0c2e|Sam Hopkinson|sam@test.com|2022-02-15T14:24:27Z||Merge branch \'production\'';

        $commits = $this->parser->parse($output);
        $commit = $commits[0];

        $this->assertTrue($commit->isMerge());
        $this->assertCount(2, $commit->parents);
        $this->assertSame('775653a6584b3b94688dea27e29d9a0876bdafc6', $commit->parents[0]);
        $this->assertSame('392fda1524587db2881f59ea18399161ea7a0c2e', $commit->parents[1]);
    }

    #[Test]
    public function it_parses_root_commit_with_no_parents(): void
    {
        $output = 'aaa111|aaa||Initial Author|author@test.com|2020-01-01T00:00:00Z||Initial commit';

        $commits = $this->parser->parse($output);
        $commit = $commits[0];

        $this->assertTrue($commit->isRoot());
        $this->assertSame([], $commit->parents);
    }

    #[Test]
    public function it_parses_commit_with_no_refs(): void
    {
        $output = '5438a960b7c958ab2cd331a6aac2298be3b454fe|5438a96|d198fb097de1a66f7b018dfcec4e9d28de94f2d1|Sam Hopkinson|sam@test.com|2026-01-18T19:25:47Z||Updates Inertia';

        $commits = $this->parser->parse($output);

        $this->assertSame([], $commits[0]->refs);
    }

    #[Test]
    public function it_handles_subject_containing_pipes(): void
    {
        $output = 'abc123|abc|def456|Author|a@b.com|2024-01-01T00:00:00Z||Fix: handle x | y | z edge case';

        $commits = $this->parser->parse($output);

        $this->assertSame('Fix: handle x | y | z edge case', $commits[0]->message);
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
abc123|abc|def456|Author|a@b.com|2024-01-01T00:00:00Z||Good commit
this line is broken
def456|def|ghi789|Author|a@b.com|2024-01-02T00:00:00Z||Another good one
GIT;

        $commits = $this->parser->parse($output);

        $this->assertCount(2, $commits);
        $this->assertSame('Good commit', $commits[0]->message);
        $this->assertSame('Another good one', $commits[1]->message);
    }

    #[Test]
    public function it_parses_ref_with_tag(): void
    {
        $output = 'abc123|abc|def456|Author|a@b.com|2024-06-01T12:00:00Z|HEAD -> main, tag: v1.0.0, origin/main|Release 1.0';

        $commits = $this->parser->parse($output);

        $this->assertSame(['HEAD -> main', 'tag: v1.0.0', 'origin/main'], $commits[0]->refs);
    }
}
