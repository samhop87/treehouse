<?php

namespace Tests\Feature\Livewire;

use Tests\TestCase;

class LandingTest extends TestCase
{
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
