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
        $output = $this->record(
            hash: '288ae4816945a4a12f7349a6498e830b18fcd836',
            shortHash: '288ae48',
            parents: '5438a960b7c958ab2cd331a6aac2298be3b454fe',
            author: 'Sam Hopkinson',
            email: 's.hopkinson87@gmail.com',
            date: '2026-01-18T19:48:49Z',
            refs: 'HEAD -> master, origin/master, origin/HEAD',
            message: 'Adds peer dependencies',
        );

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
        $this->assertSame('', $commit->description);
        $this->assertSame(['HEAD -> master', 'origin/master', 'origin/HEAD'], $commit->refs);
        $this->assertFalse($commit->isMerge());
        $this->assertFalse($commit->isRoot());
    }

    #[Test]
    public function it_parses_multiple_commits(): void
    {
        $output = $this->record(
            hash: '288ae4816945a4a12f7349a6498e830b18fcd836',
            shortHash: '288ae48',
            message: 'First commit',
        ).$this->record(
            hash: '5438a960b7c958ab2cd331a6aac2298be3b454fe',
            shortHash: '5438a96',
            message: 'Second commit',
        );

        $commits = $this->parser->parse($output);

        $this->assertCount(2, $commits);
        $this->assertSame('288ae48', $commits[0]->shortHash);
        $this->assertSame('5438a96', $commits[1]->shortHash);
    }

    #[Test]
    public function it_parses_merge_commit_with_two_parents(): void
    {
        $output = $this->record(
            hash: 'efe8dcd82065d86a2868e9af4ee232a51ec92e0f',
            shortHash: 'efe8dcd',
            parents: '775653a6584b3b94688dea27e29d9a0876bdafc6 392fda1524587db2881f59ea18399161ea7a0c2e',
            message: 'Merge branch \'production\'',
        );

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
        $output = $this->record(hash: 'aaa111', shortHash: 'aaa', parents: '', message: 'Initial commit');

        $commits = $this->parser->parse($output);
        $commit = $commits[0];

        $this->assertTrue($commit->isRoot());
        $this->assertSame([], $commit->parents);
    }

    #[Test]
    public function it_parses_commit_with_no_refs(): void
    {
        $output = $this->record(refs: '', message: 'Updates Inertia');

        $commits = $this->parser->parse($output);

        $this->assertSame([], $commits[0]->refs);
    }

    #[Test]
    public function it_handles_subject_containing_pipes(): void
    {
        $output = $this->record(message: 'Fix: handle x | y | z edge case');

        $commits = $this->parser->parse($output);

        $this->assertSame('Fix: handle x | y | z edge case', $commits[0]->message);
    }

    #[Test]
    public function it_preserves_a_multiline_commit_description(): void
    {
        $output = $this->record(
            message: 'Explain the change',
            description: "First paragraph with a | pipe.\n\nSecond paragraph.\n",
        );

        $commits = $this->parser->parse($output);

        $this->assertSame("First paragraph with a | pipe.\n\nSecond paragraph.", $commits[0]->description);
    }

    #[Test]
    public function it_handles_empty_output(): void
    {
        $this->assertSame([], $this->parser->parse(''));
    }

    #[Test]
    public function it_ignores_an_incomplete_trailing_record(): void
    {
        $output = $this->record(message: 'Good commit').'incomplete'."\0".'record';

        $commits = $this->parser->parse($output);

        $this->assertCount(1, $commits);
        $this->assertSame('Good commit', $commits[0]->message);
    }

    #[Test]
    public function it_parses_ref_with_tag(): void
    {
        $output = $this->record(refs: 'HEAD -> main, tag: v1.0.0, origin/main', message: 'Release 1.0');

        $commits = $this->parser->parse($output);

        $this->assertSame(['HEAD -> main', 'tag: v1.0.0', 'origin/main'], $commits[0]->refs);
    }

    private function record(
        string $hash = 'abc123',
        string $shortHash = 'abc',
        string $parents = 'def456',
        string $author = 'Author',
        string $email = 'a@b.com',
        string $date = '2024-01-01T00:00:00Z',
        string $refs = '',
        string $message = 'Commit',
        string $description = '',
    ): string {
        return implode("\0", [
            $hash,
            $shortHash,
            $parents,
            $author,
            $email,
            $date,
            $refs,
            $message,
            $description,
        ])."\0";
    }
}
