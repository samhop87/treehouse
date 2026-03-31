<?php

namespace Tests\Unit\Parsers;

use App\Services\Git\Parsers\StashParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class StashParserTest extends TestCase
{
    private StashParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new StashParser;
    }

    #[Test]
    public function it_parses_single_stash_entry(): void
    {
        $output = 'stash@{0}|abc123def456789012345678901234567890abcd|WIP on main: abc1234 Some commit message';

        $entries = $this->parser->parse($output);

        $this->assertCount(1, $entries);
        $entry = $entries[0];
        $this->assertSame('stash@{0}', $entry->ref);
        $this->assertSame('abc123def456789012345678901234567890abcd', $entry->hash);
        $this->assertSame('WIP on main: abc1234 Some commit message', $entry->message);
        $this->assertSame(0, $entry->index());
    }

    #[Test]
    public function it_parses_multiple_stash_entries(): void
    {
        $output = <<<'GIT'
stash@{0}|aaa111aaa111aaa111aaa111aaa111aaa111aaa1|WIP on main: Latest stash
stash@{1}|bbb222bbb222bbb222bbb222bbb222bbb222bbb2|On feature: saving work
stash@{2}|ccc333ccc333ccc333ccc333ccc333ccc333ccc3|WIP on main: old stash
GIT;

        $entries = $this->parser->parse($output);

        $this->assertCount(3, $entries);
        $this->assertSame(0, $entries[0]->index());
        $this->assertSame(1, $entries[1]->index());
        $this->assertSame(2, $entries[2]->index());
        $this->assertSame('On feature: saving work', $entries[1]->message);
    }

    #[Test]
    public function it_handles_stash_message_with_pipes(): void
    {
        $output = 'stash@{0}|abc123def456789012345678901234567890abcd|On main: fix x | y | z';

        $entries = $this->parser->parse($output);

        $this->assertSame('On main: fix x | y | z', $entries[0]->message);
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
stash@{0}|abc123def456789012345678901234567890abcd|Good entry
broken
stash@{1}|def456ghi789012345678901234567890abcdef01|Another good one
GIT;

        $entries = $this->parser->parse($output);

        $this->assertCount(2, $entries);
    }

    #[Test]
    public function it_handles_custom_stash_message(): void
    {
        $output = 'stash@{0}|abc123def456789012345678901234567890abcd|On main: my custom stash name';

        $entries = $this->parser->parse($output);

        $this->assertSame('On main: my custom stash name', $entries[0]->message);
    }
}
