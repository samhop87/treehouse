<?php

namespace Tests\Unit\Parsers;

use App\Services\Git\Parsers\DiffParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DiffParserTest extends TestCase
{
    private DiffParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new DiffParser;
    }

    #[Test]
    public function it_parses_simple_modification(): void
    {
        $output = <<<'DIFF'
diff --git a/src/app.php b/src/app.php
index abc1234..def5678 100644
--- a/src/app.php
+++ b/src/app.php
@@ -1,5 +1,5 @@
 <?php
 
-function hello() {
+function hello(): void {
     echo "hello";
 }
DIFF;

        $files = $this->parser->parse($output);

        $this->assertCount(1, $files);
        $file = $files[0];
        $this->assertSame('src/app.php', $file->path);
        $this->assertSame('modified', $file->status);
        $this->assertNull($file->oldPath);
        $this->assertFalse($file->isBinary);

        $this->assertCount(1, $file->hunks);
        $hunk = $file->hunks[0];
        $this->assertSame(1, $hunk->oldStart);
        $this->assertSame(5, $hunk->oldCount);
        $this->assertSame(1, $hunk->newStart);
        $this->assertSame(5, $hunk->newCount);

        // 6 lines: <?php, blank context, -function, +function, echo, }
        $this->assertCount(6, $hunk->lines);
        $this->assertSame('context', $hunk->lines[0]->type);
        $this->assertSame('<?php', $hunk->lines[0]->content);
        $this->assertSame(1, $hunk->lines[0]->oldLine);
        $this->assertSame(1, $hunk->lines[0]->newLine);

        // Blank context line
        $this->assertSame('context', $hunk->lines[1]->type);
        $this->assertSame(2, $hunk->lines[1]->oldLine);

        $this->assertSame('remove', $hunk->lines[2]->type);
        $this->assertSame('function hello() {', $hunk->lines[2]->content);
        $this->assertSame(3, $hunk->lines[2]->oldLine);
        $this->assertNull($hunk->lines[2]->newLine);

        $this->assertSame('add', $hunk->lines[3]->type);
        $this->assertSame('function hello(): void {', $hunk->lines[3]->content);
        $this->assertNull($hunk->lines[3]->oldLine);
        $this->assertSame(3, $hunk->lines[3]->newLine);
    }

    #[Test]
    public function it_tracks_line_numbers_correctly(): void
    {
        $output = <<<'DIFF'
diff --git a/file.txt b/file.txt
index abc..def 100644
--- a/file.txt
+++ b/file.txt
@@ -10,4 +10,5 @@
 line10
 line11
+new line
 line12
 line13
DIFF;

        $files = $this->parser->parse($output);
        $lines = $files[0]->hunks[0]->lines;

        // Context: line10 -> old:10, new:10
        $this->assertSame(10, $lines[0]->oldLine);
        $this->assertSame(10, $lines[0]->newLine);

        // Context: line11 -> old:11, new:11
        $this->assertSame(11, $lines[1]->oldLine);
        $this->assertSame(11, $lines[1]->newLine);

        // Add: new line -> old:null, new:12
        $this->assertNull($lines[2]->oldLine);
        $this->assertSame(12, $lines[2]->newLine);

        // Context: line12 -> old:12, new:13
        $this->assertSame(12, $lines[3]->oldLine);
        $this->assertSame(13, $lines[3]->newLine);
    }

    #[Test]
    public function it_parses_new_file(): void
    {
        $output = <<<'DIFF'
diff --git a/newfile.txt b/newfile.txt
new file mode 100644
index 0000000..abc1234
--- /dev/null
+++ b/newfile.txt
@@ -0,0 +1,3 @@
+line 1
+line 2
+line 3
DIFF;

        $files = $this->parser->parse($output);

        $this->assertCount(1, $files);
        $file = $files[0];
        $this->assertSame('newfile.txt', $file->path);
        $this->assertSame('added', $file->status);
        $this->assertSame(3, $file->additions());
        $this->assertSame(0, $file->deletions());
    }

    #[Test]
    public function it_parses_deleted_file(): void
    {
        $output = <<<'DIFF'
diff --git a/oldfile.txt b/oldfile.txt
deleted file mode 100644
index abc1234..0000000
--- a/oldfile.txt
+++ /dev/null
@@ -1,2 +0,0 @@
-line 1
-line 2
DIFF;

        $files = $this->parser->parse($output);

        $file = $files[0];
        $this->assertSame('oldfile.txt', $file->path);
        $this->assertSame('deleted', $file->status);
        $this->assertSame(0, $file->additions());
        $this->assertSame(2, $file->deletions());
    }

    #[Test]
    public function it_parses_renamed_file(): void
    {
        $output = <<<'DIFF'
diff --git a/old/name.php b/new/name.php
similarity index 95%
rename from old/name.php
rename to new/name.php
index abc1234..def5678 100644
--- a/old/name.php
+++ b/new/name.php
@@ -1,3 +1,3 @@
 <?php
-// old
+// new
 echo "hi";
DIFF;

        $files = $this->parser->parse($output);

        $file = $files[0];
        $this->assertSame('new/name.php', $file->path);
        $this->assertSame('old/name.php', $file->oldPath);
        $this->assertSame('renamed', $file->status);
    }

    #[Test]
    public function it_parses_binary_file(): void
    {
        $output = <<<'DIFF'
diff --git a/image.png b/image.png
index abc1234..def5678 100644
Binary files a/image.png and b/image.png differ
DIFF;

        $files = $this->parser->parse($output);

        $file = $files[0];
        $this->assertSame('image.png', $file->path);
        $this->assertTrue($file->isBinary);
        $this->assertEmpty($file->hunks);
    }

    #[Test]
    public function it_parses_multiple_files(): void
    {
        $output = <<<'DIFF'
diff --git a/file1.txt b/file1.txt
index abc..def 100644
--- a/file1.txt
+++ b/file1.txt
@@ -1,3 +1,3 @@
 line1
-old
+new
 line3
diff --git a/file2.txt b/file2.txt
new file mode 100644
index 0000000..abc1234
--- /dev/null
+++ b/file2.txt
@@ -0,0 +1,2 @@
+hello
+world
DIFF;

        $files = $this->parser->parse($output);

        $this->assertCount(2, $files);
        $this->assertSame('file1.txt', $files[0]->path);
        $this->assertSame('modified', $files[0]->status);
        $this->assertSame('file2.txt', $files[1]->path);
        $this->assertSame('added', $files[1]->status);
    }

    #[Test]
    public function it_parses_multiple_hunks(): void
    {
        $output = <<<'DIFF'
diff --git a/big.txt b/big.txt
index abc..def 100644
--- a/big.txt
+++ b/big.txt
@@ -1,3 +1,4 @@
 first
+inserted
 second
 third
@@ -20,3 +21,3 @@
 twenty
-old line
+new line
 twentytwo
DIFF;

        $files = $this->parser->parse($output);
        $file = $files[0];

        $this->assertCount(2, $file->hunks);
        $this->assertSame(1, $file->hunks[0]->oldStart);
        $this->assertSame(20, $file->hunks[1]->oldStart);
    }

    #[Test]
    public function it_handles_no_newline_at_end_of_file(): void
    {
        $output = <<<'DIFF'
diff --git a/file.txt b/file.txt
index abc..def 100644
--- a/file.txt
+++ b/file.txt
@@ -1,2 +1,2 @@
 same
-old
\ No newline at end of file
+new
\ No newline at end of file
DIFF;

        $files = $this->parser->parse($output);
        $lines = $files[0]->hunks[0]->lines;

        // Should have 3 actual diff lines (context, remove, add)
        // The "\ No newline" markers should be skipped
        $this->assertCount(3, $lines);
        $this->assertSame('context', $lines[0]->type);
        $this->assertSame('remove', $lines[1]->type);
        $this->assertSame('add', $lines[2]->type);
    }

    #[Test]
    public function it_counts_additions_and_deletions(): void
    {
        $output = <<<'DIFF'
diff --git a/file.txt b/file.txt
index abc..def 100644
--- a/file.txt
+++ b/file.txt
@@ -1,4 +1,5 @@
 same
-removed1
-removed2
+added1
+added2
+added3
 same
DIFF;

        $files = $this->parser->parse($output);
        $file = $files[0];

        $this->assertSame(3, $file->additions());
        $this->assertSame(2, $file->deletions());
    }

    #[Test]
    public function it_handles_empty_output(): void
    {
        $this->assertSame([], $this->parser->parse(''));
    }

    #[Test]
    public function it_handles_hunk_with_single_line_counts(): void
    {
        // When count is 1, git may omit it: @@ -5 +5 @@ instead of @@ -5,1 +5,1 @@
        $output = <<<'DIFF'
diff --git a/file.txt b/file.txt
index abc..def 100644
--- a/file.txt
+++ b/file.txt
@@ -5 +5 @@
-old
+new
DIFF;

        $files = $this->parser->parse($output);

        $hunk = $files[0]->hunks[0];
        $this->assertSame(5, $hunk->oldStart);
        $this->assertSame(1, $hunk->oldCount);
        $this->assertSame(5, $hunk->newStart);
        $this->assertSame(1, $hunk->newCount);
    }
}
