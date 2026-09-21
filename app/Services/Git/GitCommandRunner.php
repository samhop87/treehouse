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
     * @param  list<string>  $args  Arguments to pass after "git"
     * @param  int  $timeout  Timeout in seconds (default 30)
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
                error: 'fatal: not a git repository (or any of the parent directories): .git',
                exitCode: 128,
                command: $commandString,
            );
        }

        $process = Process::timeout($timeout);

        if ($this->repoPath !== null) {
            $process = $process->path($this->repoPath);
        }

        try {
            $result = $process->run($command);
        } catch (\Throwable $exception) {
            return new GitResult(
                success: false,
                output: '',
                error: $exception->getMessage(),
                exitCode: 127,
                command: $commandString,
            );
        }

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
     * @param  list<string>  $args
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
     * Resolve the repository's real Git directory in one command.
     *
     * This also validates the repository and works for linked worktrees, where
     * `.git` is a file that points somewhere outside the working tree.
     */
    public function absoluteGitDir(): ?string
    {
        if ($this->repoPath === null) {
            return null;
        }

        $result = $this->run(['rev-parse', '--absolute-git-dir']);

        if (! $result->success) {
            return null;
        }

        $path = rtrim($result->output, "\r\n");

        return $path !== '' ? $path : null;
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
        // NUL delimiters preserve spaces, quotes, Unicode and newlines in paths.
        return $this->run($this->statusArgs());
    }

    /**
     * Convenience: run log with our standard format.
     *
     * @param  int  $limit  Maximum number of commits
     * @param  list<string>  $extraArgs  Additional args (e.g., ['--all'])
     */
    public function log(int $limit = 200, array $extraArgs = []): GitResult
    {
        return $this->run($this->logArgs($limit, $extraArgs));
    }

    /**
     * Convenience: list branches.
     */
    public function branches(): GitResult
    {
        return $this->run($this->branchesArgs());
    }

    /**
     * Convenience: list tags.
     */
    public function tags(): GitResult
    {
        return $this->run($this->tagsArgs());
    }

    /**
     * Convenience: list stashes.
     */
    public function stashes(): GitResult
    {
        return $this->run($this->stashesArgs());
    }

    /**
     * Run the independent reads that make up a repository tab at once.
     *
     * @param  list<string>  $logExtraArgs
     * @return array{status: GitResult, log: GitResult, branches: GitResult, tags: GitResult, stashes: GitResult}
     */
    public function overview(int $limit = 200, array $logExtraArgs = []): array
    {
        $commands = [
            'status' => $this->statusArgs(),
            'log' => $this->logArgs($limit, $logExtraArgs),
            'branches' => $this->branchesArgs(),
            'tags' => $this->tagsArgs(),
            'stashes' => $this->stashesArgs(),
        ];

        if ($this->repoPath !== null && ! is_dir($this->repoPath)) {
            return array_map(fn (array $args) => $this->run($args), $commands);
        }

        try {
            $results = Process::concurrently(function ($pool) use ($commands): void {
                foreach ($commands as $name => $args) {
                    $process = $pool->as($name)->timeout(30);

                    if ($this->repoPath !== null) {
                        $process = $process->path($this->repoPath);
                    }

                    $process->command(array_merge(['git'], $args));
                }
            });
        } catch (\Throwable $exception) {
            return array_map(
                fn (array $args) => new GitResult(
                    success: false,
                    output: '',
                    error: $exception->getMessage(),
                    exitCode: 127,
                    command: implode(' ', array_merge(['git'], $args)),
                ),
                $commands,
            );
        }

        $overview = [];
        foreach ($commands as $name => $args) {
            $result = $results[$name];
            $overview[$name] = new GitResult(
                success: $result->successful(),
                output: $result->output(),
                error: $result->errorOutput(),
                exitCode: $result->exitCode(),
                command: implode(' ', array_merge(['git'], $args)),
            );
        }

        return $overview;
    }

    /**
     * Convenience: get diff (staged or unstaged).
     *
     * @param  bool  $staged  If true, show staged changes (--cached)
     * @param  list<string>  $paths  Limit to specific paths
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

    /** @return list<string> */
    private function statusArgs(): array
    {
        return ['status', '--porcelain=v2', '--branch', '-z'];
    }

    /**
     * @param  list<string>  $extraArgs
     * @return list<string>
     */
    private function logArgs(int $limit, array $extraArgs): array
    {
        return [
            'log',
            '-z',
            '--format=%H%x00%h%x00%P%x00%an%x00%ae%x00%aI%x00%D%x00%s%x00%b',
            "-n{$limit}",
            ...$extraArgs,
        ];
    }

    /** @return list<string> */
    private function branchesArgs(): array
    {
        return [
            'branch', '-a',
            '--format=%(refname)|%(refname:short)|%(objectname:short)|%(HEAD)|%(upstream:short)|%(upstream:track)',
        ];
    }

    /** @return list<string> */
    private function tagsArgs(): array
    {
        return [
            'tag', '-l',
            '--format=%(refname:short)|%(objectname:short)|%(*objectname:short)|%(objecttype)|%(creatordate:iso-strict)|%(subject)',
        ];
    }

    /** @return list<string> */
    private function stashesArgs(): array
    {
        return ['stash', 'list', '--format=%gd|%H|%gs'];
    }
}
