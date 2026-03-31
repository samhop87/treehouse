<?php

namespace Tests\Unit\Services\Git;

use App\Services\Git\GitErrorTranslator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class GitErrorTranslatorTest extends TestCase
{
    private GitErrorTranslator $translator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->translator = new GitErrorTranslator;
    }

    #[Test]
    public function it_translates_not_a_git_repository(): void
    {
        $stderr = "fatal: not a git repository (or any of the parent directories): .git";
        $this->assertSame(
            'This directory is not a Git repository.',
            $this->translator->translate($stderr)
        );
    }

    #[Test]
    public function it_translates_repository_not_found(): void
    {
        $stderr = "fatal: repository 'https://github.com/user/nonexistent.git' not found";
        $this->assertSame(
            'The remote repository was not found. Check the URL and your access permissions.',
            $this->translator->translate($stderr)
        );
    }

    #[Test]
    public function it_translates_authentication_failed(): void
    {
        $stderr = "fatal: Authentication failed for 'https://github.com/user/repo.git'";
        $this->assertSame(
            'Authentication failed. Your credentials may be expired or invalid.',
            $this->translator->translate($stderr)
        );
    }

    #[Test]
    public function it_translates_merge_conflicts(): void
    {
        $stderr = "CONFLICT (content): Merge conflict in src/file.php\nAutomatic merge failed; fix conflicts and then commit the result.";
        $this->assertSame(
            'Merge conflicts were found. Resolve them before continuing.',
            $this->translator->translate($stderr)
        );
    }

    #[Test]
    public function it_translates_automatic_merge_failed(): void
    {
        $stderr = "Automatic merge failed; fix conflicts and then commit the result.";
        $this->assertSame(
            'Merge conflicts were found. Resolve them before continuing.',
            $this->translator->translate($stderr)
        );
    }

    #[Test]
    public function it_translates_local_changes_would_be_overwritten(): void
    {
        $stderr = "error: Your local changes to the following files would be overwritten by merge:\n\tsrc/file.php";
        $this->assertSame(
            'You have uncommitted changes that would be overwritten. Commit or stash them first.',
            $this->translator->translate($stderr)
        );
    }

    #[Test]
    public function it_translates_pathspec_not_found(): void
    {
        $stderr = "error: pathspec 'nonexistent.txt' did not match any file(s) known to git";
        $this->assertSame(
            'The file or path "nonexistent.txt" was not found in the repository.',
            $this->translator->translate($stderr)
        );
    }

    #[Test]
    public function it_translates_bad_revision(): void
    {
        $stderr = "fatal: bad revision 'nonexistent-branch'";
        $this->assertSame(
            'The revision "nonexistent-branch" does not exist.',
            $this->translator->translate($stderr)
        );
    }

    #[Test]
    public function it_translates_push_rejected(): void
    {
        $stderr = "error: failed to push some refs to 'origin'";
        $this->assertSame(
            "Push was rejected. The remote has changes you don't have locally. Pull first.",
            $this->translator->translate($stderr)
        );
    }

    #[Test]
    public function it_translates_unrelated_histories(): void
    {
        $stderr = "fatal: refusing to merge unrelated histories";
        $this->assertSame(
            'These branches have no common history. Use --allow-unrelated-histories if intentional.',
            $this->translator->translate($stderr)
        );
    }

    #[Test]
    #[DataProvider('inProgressOperationProvider')]
    public function it_translates_in_progress_operations(string $operation): void
    {
        $stderr = "You are in the middle of a {$operation}.";
        $this->assertSame(
            "There is an unfinished {$operation} in progress. Complete or abort it first.",
            $this->translator->translate($stderr)
        );
    }

    public static function inProgressOperationProvider(): array
    {
        return [
            'merge' => ['merge'],
            'rebase' => ['rebase'],
            'cherry-pick' => ['cherry-pick'],
        ];
    }

    #[Test]
    public function it_translates_detached_head(): void
    {
        $stderr = "HEAD detached at abc1234";
        $this->assertSame(
            'You are in detached HEAD state at abc1234. Create a branch to keep your changes.',
            $this->translator->translate($stderr)
        );
    }

    #[Test]
    public function it_translates_destination_already_exists(): void
    {
        $stderr = "fatal: destination path '/Users/sam/repos/project' already exists and is not an empty directory.";
        $this->assertSame(
            'The directory "/Users/sam/repos/project" already exists. Choose a different location.',
            $this->translator->translate($stderr)
        );
    }

    #[Test]
    public function it_translates_hostname_resolution_failure(): void
    {
        $stderr = "fatal: unable to access 'https://github.com/user/repo.git': Could not resolve hostname github.com";
        $this->assertSame(
            'Cannot reach the remote server. Check your internet connection.',
            $this->translator->translate($stderr)
        );
    }

    #[Test]
    public function it_returns_cleaned_stderr_for_unknown_errors(): void
    {
        $stderr = "fatal: some unusual error we haven't seen before";
        $result = $this->translator->translate($stderr);

        // Should strip "fatal: " prefix and capitalize
        $this->assertSame("Some unusual error we haven't seen before", $result);
    }

    #[Test]
    public function it_handles_empty_stderr(): void
    {
        $this->assertSame(
            'An unknown git error occurred.',
            $this->translator->translate('')
        );
    }

    #[Test]
    public function it_handles_whitespace_only_stderr(): void
    {
        $this->assertSame(
            'An unknown git error occurred.',
            $this->translator->translate("  \n  ")
        );
    }
}
