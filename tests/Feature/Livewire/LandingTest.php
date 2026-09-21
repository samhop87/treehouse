<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Landing;
use Livewire\Livewire;
use Tests\TestCase;

class LandingTest extends TestCase
{
    public function test_landing_shell_defers_startup_checks_until_after_its_first_render(): void
    {
        Livewire::test(Landing::class)
            ->assertSet('startupStateLoaded', false)
            ->assertSeeHtml('wire:init="loadStartupState"');
    }

    public function test_recent_repository_renders_a_complete_wire_click_action(): void
    {
        $html = view('livewire.landing', [
            'gitVersion' => '2.50.1',
            'errorMessage' => '',
            'recentRepos' => [[
                'name' => 'Quoted repo',
                'path' => "/tmp/O'Brien repo",
                'branch' => 'main',
                'last_opened_at' => 'just now',
            ]],
            'isGitHubConnected' => false,
            'gitHubUser' => null,
            'startupStateLoaded' => true,
        ])->render();

        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $actions = [];
        foreach ($document->getElementsByTagName('*') as $element) {
            if ($element->hasAttribute('wire:click')) {
                $actions[] = $element->getAttribute('wire:click');
            }
        }

        $this->assertContains(
            "openRepoByPath('\\/tmp\\/O\\u0027Brien repo')",
            $actions,
        );
    }
}
