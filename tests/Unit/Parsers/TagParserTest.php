<?php

namespace Tests\Unit\Parsers;

use App\Services\Git\Parsers\TagParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TagParserTest extends TestCase
{
    private TagParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new TagParser;
    }

    #[Test]
    public function it_parses_annotated_tag(): void
    {
        $output = 'v1.0.0|abc1234|def5678|tag|2024-06-15T12:00:00Z|Release version 1.0.0';

        $tags = $this->parser->parse($output);

        $this->assertCount(1, $tags);
        $tag = $tags[0];
        $this->assertSame('v1.0.0', $tag->name);
        $this->assertSame('abc1234', $tag->hash);
        $this->assertSame('def5678', $tag->targetHash);
        $this->assertTrue($tag->isAnnotated);
        $this->assertNotNull($tag->date);
        $this->assertSame('Release version 1.0.0', $tag->message);
        $this->assertSame('def5678', $tag->commitHash());
    }

    #[Test]
    public function it_parses_lightweight_tag(): void
    {
        $output = 'v0.1.0|abc1234||commit|2024-01-01T00:00:00Z|Some commit message';

        $tags = $this->parser->parse($output);

        $tag = $tags[0];
        $this->assertSame('v0.1.0', $tag->name);
        $this->assertSame('abc1234', $tag->hash);
        $this->assertNull($tag->targetHash);
        $this->assertFalse($tag->isAnnotated);
        $this->assertSame('abc1234', $tag->commitHash());
    }

    #[Test]
    public function it_parses_multiple_tags(): void
    {
        $output = <<<'GIT'
v2.0.0|aaa1111|bbb2222|tag|2024-12-01T10:00:00Z|Major release
v1.1.0|ccc3333|ddd4444|tag|2024-06-01T10:00:00Z|Minor release
v1.0.0|eee5555||commit|2024-01-01T10:00:00Z|Initial release
GIT;

        $tags = $this->parser->parse($output);

        $this->assertCount(3, $tags);
        $this->assertSame('v2.0.0', $tags[0]->name);
        $this->assertSame('v1.1.0', $tags[1]->name);
        $this->assertSame('v1.0.0', $tags[2]->name);
        $this->assertTrue($tags[0]->isAnnotated);
        $this->assertTrue($tags[1]->isAnnotated);
        $this->assertFalse($tags[2]->isAnnotated);
    }

    #[Test]
    public function it_handles_tag_message_with_pipes(): void
    {
        $output = 'v1.0.0|abc1234|def5678|tag|2024-06-15T12:00:00Z|Fix: handle x | y case';

        $tags = $this->parser->parse($output);

        $this->assertSame('Fix: handle x | y case', $tags[0]->message);
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
v1.0.0|abc1234|def5678|tag|2024-06-15T12:00:00Z|Good tag
bad line
v0.1.0|ghi9012||commit|2024-01-01T00:00:00Z|Another tag
GIT;

        $tags = $this->parser->parse($output);

        $this->assertCount(2, $tags);
    }

    #[Test]
    public function it_handles_tag_without_date(): void
    {
        $output = 'v1.0.0|abc1234||commit||';

        $tags = $this->parser->parse($output);

        $tag = $tags[0];
        $this->assertNull($tag->date);
        $this->assertNull($tag->message);
    }

    #[Test]
    public function stash_entry_index_returns_correct_number(): void
    {
        // Quick test of StashEntry DTO
        $entry = new \App\DTOs\StashEntry(
            ref: 'stash@{3}',
            hash: 'abc123',
            message: 'test',
        );

        $this->assertSame(3, $entry->index());
    }
}
