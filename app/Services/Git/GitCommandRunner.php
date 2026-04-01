<?php

namespace App\Services\Git;

use App\DTOs\GitResult;
use Illuminate\Support\Facades\Process;

/**
 * Executes git commands against a repository directory using the system git binary.
 *
 * All synchronous git operations go through this service.
 * Long-running async operations (clone, fetch, push) should use
 * NativePHP's ChildProcess instead for real-time progress.
 */
class GitCommandRunner
{
    private ?string $repoPath = null;

    public function __construct(
        private readonly GitErrorTranslator $errorTranslator,
    ) {}

    /**
     * Set the working directory for subsequent git commands.
     */
    public function setRepoPath(string $path): self
    {
        $this->repoPath = $path;

        return $this;
    }

    /**
     * Get the current repo path.
     */
    public function getRepoPath(): ?string
    {
        return $this->repoPath;
    }

    /**
     * Run a git command and return a GitResult.
     *
     * @param list<string> $args  Arguments to pass after "git"
     * @param int          $timeout  Timeout in seconds (default 30)
     */
    public function run(array $args, int $timeout = 30): GitResult
    {
        $command = array_merge(['git'], $args);
        $commandString = implode(' ', $command);

        // Validate the working directory exists before running
        if ($this->repoPath !== null && ! is_dir($this->repoPath)) {
            return new GitResult(
                success: false,
                output: '',
                error: "fatal: not a git repository (or any of the parent directories): .git",
                exitCode: 128,
                command: $commandString,
            );
        }

        $process = Process::timeout($timeout);

        if ($this->repoPath !== null) {
            $process = $process->path($this->repoPath);
        }

        $result = $process->run($command);

        return new GitResult(
            success: $result->successful(),
            output: $result->output(),
            error: $result->errorOutput(),
            exitCode: $result->exitCode(),
            command: $commandString,
        );
    }

    /**
     * Run a git command, translating errors to human-readable messages.
     * On failure, the error field contains the translated message.
     *
     * @param list<string> $args
     */
    public function runWithTranslation(array $args, int $timeout = 30): GitResult
    {
        $result = $this->run($args, $timeout);

        if (! $result->success) {
            return new GitResult(
                success: false,
                output: $result->output,
                error: $this->errorTranslator->translate($result->error),
                exitCode: $result->exitCode,
                command: $result->command,
            );
        }

        return $result;
    }

    /**
     * Check if the current repo path is a valid git repository.
     */
    public function isValidRepo(): bool
    {
        if ($this->repoPath === null) {
            return false;
        }

        $result = $this->run(['rev-parse', '--git-dir']);

        return $result->success;
    }

    /**
     * Get the git version string.
     */
    public function version(): ?string
    {
        $result = $this->run(['--version']);

        if (! $result->success) {
            return null;
        }

        // "git version 2.50.1" -> "2.50.1"
        if (preg_match('/(\d+\.\d+\.\d+)/', $result->output, $matches)) {
            return $matches[1];
        }

        return trim($result->output);
    }

    /**
     * Convenience: run status --porcelain=v2 --branch.
     */
    public function status(): GitResult
    {
        return $this->run(['status', '--porcelain=v2', '--branch']);
    }

    /**
     * Convenience: run log with our standard format.
     *
     * @param int $limit  Maximum number of commits
     * @param list<string> $extraArgs  Additional args (e.g., ['--all'])
     */
    public function log(int $limit = 200, array $extraArgs = []): GitResult
    {
        $args = [
            'log',
            '--format=%H|%h|%P|%an|%ae|%aI|%D|%s',
            "-n{$limit}",
            ...$extraArgs,
        ];

        return $this->run($args);
    }

    /**
     * Convenience: list branches.
     */
    public function branches(): GitResult
    {
        return $this->run([
            'branch', '-a',
            '--format=%(refname)|%(refname:short)|%(objectname:short)|%(HEAD)|%(upstream:short)|%(upstream:track)',
        ]);
    }

    /**
     * Convenience: list tags.
     */
    public function tags(): GitResult
    {
        return $this->run([
            'tag', '-l',
            '--format=%(refname:short)|%(objectname:short)|%(*objectname:short)|%(objecttype)|%(creatordate:iso-strict)|%(subject)',
        ]);
    }

    /**
     * Convenience: list stashes.
     */
    public function stashes(): GitResult
    {
        return $this->run(['stash', 'list', '--format=%gd|%H|%gs']);
    }

    /**
     * Convenience: get diff (staged or unstaged).
     *
     * @param bool $staged  If true, show staged changes (--cached)
     * @param list<string> $paths  Limit to specific paths
     */
    public function diff(bool $staged = false, array $paths = []): GitResult
    {
        $args = ['diff'];

        if ($staged) {
            $args[] = '--cached';
        }

        if (! empty($paths)) {
            $args[] = '--';
            $args = array_merge($args, $paths);
        }

        return $this->run($args, timeout: 60);
    }
}
