<?php

namespace App\DTOs;

/**
 * Immutable result of a git command execution.
 */
final readonly class GitResult
{
    public function __construct(
        public bool $success,
        public string $output,
        public string $error,
        public int $exitCode,
        public string $command,
    ) {}

    /**
     * Get stdout split into lines (empty lines removed from the end).
     *
     * @return list<string>
     */
    public function lines(): array
    {
        if ($this->output === '') {
            return [];
        }

        return explode("\n", rtrim($this->output, "\n"));
    }

    /**
     * Throw a RuntimeException if the command failed.
     * Returns $this for chaining on success.
     */
    public function throw(?string $context = null): self
    {
        if (! $this->success) {
            $message = $context
                ? "{$context}: {$this->error}"
                : "Git command failed: {$this->error}";

            throw new \RuntimeException($message, $this->exitCode);
        }

        return $this;
    }
}
