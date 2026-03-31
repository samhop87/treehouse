<?php

namespace App\Livewire;

use App\Services\GitHub\GitHubAuthService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Native\Desktop\Facades\Shell;

#[Layout('components.layouts.app')]
#[Title('GitHub Login — Treehouse')]
class GitHubLogin extends Component
{
    // State machine: idle → requesting → awaiting → success | error | expired | denied
    public string $state = 'idle';

    public string $userCode = '';
    public string $verificationUri = '';
    public string $deviceCode = '';
    public int $pollInterval = 8;
    public string $errorMessage = '';
    public bool $codeCopied = false;

    private ?GitHubAuthService $authService = null;

    public function boot(GitHubAuthService $authService): void
    {
        $this->authService = $authService;
    }

    public function mount(GitHubAuthService $authService): void
    {
        // If already authenticated, redirect to landing
        if ($authService->hasToken()) {
            $this->redirect('/');
            return;
        }

        // Check if GitHub Client ID is configured
        if (! $authService->isConfigured()) {
            $this->state = 'error';
            $this->errorMessage = 'GitHub Client ID is not configured. Add GITHUB_CLIENT_ID to your .env file.';
        }
    }

    /**
     * Start the device flow — request a code from GitHub.
     */
    public function startAuth(): void
    {
        $this->state = 'requesting';
        $this->errorMessage = '';

        try {
            $response = $this->authService->requestDeviceCode();

            $this->userCode = $response->userCode;
            $this->verificationUri = $response->verificationUri;
            $this->deviceCode = $response->deviceCode;
            // Use at least 8s to avoid borderline slow_down from GitHub
            $this->pollInterval = max($response->interval, 8);
            $this->state = 'awaiting';
        } catch (\RuntimeException $e) {
            $this->state = 'error';
            $this->errorMessage = $e->getMessage();
        }
    }

    /**
     * Open the GitHub verification URL in the system browser.
     */
    public function openGitHub(): void
    {
        Shell::openExternal($this->verificationUri);
    }

    /**
     * Poll GitHub for the token. Called by Alpine.js setTimeout loop.
     */
    public function pollToken(): void
    {
        if ($this->state !== 'awaiting' || empty($this->deviceCode)) {
            return;
        }

        try {
            $result = $this->authService->pollForToken($this->deviceCode);
        } catch (\Exception $e) {
            $this->state = 'error';
            $this->errorMessage = $e->getMessage();
            return;
        }

        match ($result['status']) {
            'success' => $this->handleSuccess(),
            'pending' => null, // Keep polling
            'slow_down' => $this->pollInterval = max($result['interval'] + 5, $this->pollInterval + 5),
            'expired' => $this->handleExpired(),
            'denied' => $this->handleDenied(),
            'error' => $this->handleError($result['message'] ?? 'Unknown error'),
        };
    }

    private function handleSuccess(): void
    {
        $this->state = 'success';
        // Brief delay then redirect so user sees the success state
    }

    private function handleExpired(): void
    {
        $this->state = 'expired';
        $this->errorMessage = 'The device code expired. Please try again.';
    }

    private function handleDenied(): void
    {
        $this->state = 'denied';
        $this->errorMessage = 'Access was denied. Please try again if this was a mistake.';
    }

    private function handleError(string $message): void
    {
        $this->state = 'error';
        $this->errorMessage = $message;
    }

    /**
     * Reset to try again.
     */
    public function retry(): void
    {
        $this->state = 'idle';
        $this->userCode = '';
        $this->verificationUri = '';
        $this->deviceCode = '';
        $this->errorMessage = '';
        $this->codeCopied = false;
    }

    /**
     * Navigate to landing after successful auth.
     */
    public function goToLanding(): void
    {
        $this->redirect('/');
    }

    public function render()
    {
        return view('livewire.github-login');
    }
}
