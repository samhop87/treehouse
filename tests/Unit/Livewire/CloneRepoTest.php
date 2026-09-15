<?php

namespace Tests\Unit\Livewire;

use App\Livewire\CloneRepo;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CloneRepoTest extends TestCase
{
    #[Test]
    public function failed_native_clone_reports_the_process_error(): void
    {
        $component = new CloneRepo;
        $component->state = 'cloning';
        $component->cloneProgress = 'fatal: repository not found';
        $component->destinationPath = '/tmp/treehouse-missing-clone';

        $component->onProcessExited('git-clone', 128);

        $this->assertSame('error', $component->state);
        $this->assertSame(
            'Clone failed: fatal: repository not found',
            $component->errorMessage,
        );
    }

    #[Test]
    public function unrelated_process_exit_is_ignored(): void
    {
        $component = new CloneRepo;
        $component->state = 'cloning';

        $component->onProcessExited('another-process', 1);

        $this->assertSame('cloning', $component->state);
        $this->assertSame('', $component->errorMessage);
    }
}
