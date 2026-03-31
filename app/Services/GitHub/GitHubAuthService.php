<?php

namespace App\Services\GitHub;

use App\DTOs\DeviceCodeResponse;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Native\Desktop\Facades\Settings;

/**
 * Handles GitHub Device Flow OAuth authentication.
 *
 * Flow:
 * 1. requestDeviceCode() — POST to GitHub, get user_code + device_code
 * 2. User visits github.com/login/device and enters the user_code
 * 3. pollForToken() — poll GitHub until user approves, denied, or expired
 * 4. Token stored encrypted via NativePHP Settings
 */
class GitHubAuthService
{
    private const SETTINGS_TOKEN_KEY = 'github_token';
    private const SETTINGS_USER_KEY = 'github_user';

    private string $clientId;
    private string $deviceCodeUrl;
    private string $accessTokenUrl;
    private string $apiBaseUrl;
    private string $scopes;

    public function __construct()
    {
        $this->clientId = config('services.github.client_id', '');
        $this->deviceCodeUrl = config('services.github.device_code_url');
        $this->accessTokenUrl = config('services.github.access_token_url');
        $this->apiBaseUrl = config('services.github.api_base_url');
        $this->scopes = config('services.github.scopes');
    }

    /**
     * Check if a GitHub Client ID is configured.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->clientId);
    }

    /**
     * Step 1: Request a device code from GitHub.
     *
     * @throws \RuntimeException if client ID is not configured or request fails
     */
    public function requestDeviceCode(): DeviceCodeResponse
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException(
                'GitHub Client ID is not configured. Add GITHUB_CLIENT_ID to your .env file.'
            );
        }

        $response = $this->githubRequest()
            ->post($this->deviceCodeUrl, [
                'client_id' => $this->clientId,
                'scope' => $this->scopes,
            ]);

        if ($response->failed()) {
            Log::error('GitHub device code request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Failed to request device code from GitHub.');
        }

        $data = $response->json();

        if (! isset($data['device_code'], $data['user_code'], $data['verification_uri'])) {
            Log::error('GitHub device code response missing fields', ['data' => $data]);
            throw new \RuntimeException('Invalid response from GitHub device code endpoint.');
        }

        return DeviceCodeResponse::fromResponse($data);
    }

    /**
     * Step 3: Poll GitHub for an access token.
     *
     * Returns one of:
     * - ['status' => 'success', 'token' => '...'] — user authorized
     * - ['status' => 'pending'] — still waiting for user
     * - ['status' => 'slow_down', 'interval' => N] — increase poll interval
     * - ['status' => 'expired'] — device code expired
     * - ['status' => 'denied'] — user denied access
     * - ['status' => 'error', 'message' => '...'] — unexpected error
     */
    public function pollForToken(string $deviceCode): array
    {
        $response = $this->githubRequest()
            ->post($this->accessTokenUrl, [
                'client_id' => $this->clientId,
                'device_code' => $deviceCode,
                'grant_type' => 'urn:ietf:params:oauth:grant-type:device_code',
            ]);

        if ($response->failed()) {
            Log::warning('[GitHubAuth] Token poll HTTP request failed', ['status' => $response->status()]);
            return ['status' => 'error', 'message' => 'HTTP request to GitHub failed.'];
        }

        $data = $response->json();

        // Success — we got a token
        if (isset($data['access_token'])) {
            Log::info('[GitHubAuth] Access token received, storing');
            $this->storeToken($data['access_token']);
            $this->fetchAndStoreUser($data['access_token']);

            return ['status' => 'success', 'token' => $data['access_token']];
        }

        // Handle error states from GitHub
        $error = $data['error'] ?? 'unknown';

        if ($error === 'slow_down') {
            Log::warning('[GitHubAuth] GitHub requested slow_down', ['interval' => $data['interval'] ?? 10]);
        }

        return match ($error) {
            'authorization_pending' => ['status' => 'pending'],
            'slow_down' => ['status' => 'slow_down', 'interval' => ($data['interval'] ?? 10)],
            'expired_token' => ['status' => 'expired'],
            'access_denied' => ['status' => 'denied'],
            default => ['status' => 'error', 'message' => $data['error_description'] ?? 'Unknown error from GitHub.'],
        };
    }

    /**
     * Store the access token encrypted in NativePHP Settings.
     */
    public function storeToken(string $token): void
    {
        $this->settingsSet(self::SETTINGS_TOKEN_KEY, Crypt::encryptString($token));
    }

    /**
     * Retrieve the stored access token (decrypted).
     * Returns null if no token is stored.
     */
    public function getToken(): ?string
    {
        $encrypted = $this->settingsGet(self::SETTINGS_TOKEN_KEY);

        if (empty($encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            Log::warning('Failed to decrypt GitHub token, clearing stored value.');
            $this->clearToken();
            return null;
        }
    }

    /**
     * Check if we have a stored GitHub token.
     */
    public function hasToken(): bool
    {
        return $this->getToken() !== null;
    }

    /**
     * Clear the stored token and user info (logout).
     */
    public function clearToken(): void
    {
        $this->settingsSet(self::SETTINGS_TOKEN_KEY, null);
        $this->settingsSet(self::SETTINGS_USER_KEY, null);
    }

    /**
     * Get stored GitHub user info (login, name, avatar_url).
     */
    public function getUser(): ?array
    {
        $data = $this->settingsGet(self::SETTINGS_USER_KEY);

        if (empty($data)) {
            return null;
        }

        return is_array($data) ? $data : json_decode($data, true);
    }

    /**
     * Verify the stored token is still valid by calling GitHub API.
     * Returns true if valid, false if invalid/expired.
     */
    public function verifyToken(): bool
    {
        $token = $this->getToken();

        if ($token === null) {
            return false;
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ])->get("{$this->apiBaseUrl}/user");

        if ($response->failed()) {
            return false;
        }

        // Refresh stored user info
        $user = $response->json();
        $this->storeUser($user);

        return true;
    }

    /**
     * Fetch the authenticated user's info and store it.
     */
    private function fetchAndStoreUser(string $token): void
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Accept' => 'application/json',
            ])->get("{$this->apiBaseUrl}/user");

            if ($response->successful()) {
                $this->storeUser($response->json());
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch GitHub user info', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Store user info (just the fields we need).
     */
    private function storeUser(array $user): void
    {
        $this->settingsSet(self::SETTINGS_USER_KEY, json_encode([
            'login' => $user['login'] ?? '',
            'name' => $user['name'] ?? '',
            'avatar_url' => $user['avatar_url'] ?? '',
        ]));
    }

    /**
     * Create an HTTP client configured for GitHub API JSON requests.
     */
    private function githubRequest(): PendingRequest
    {
        return Http::acceptJson()
            ->asForm()
            ->timeout(15);
    }

    /**
     * Check if we're running inside a NativePHP context (Settings available).
     */
    private function isNativeContext(): bool
    {
        return (bool) config('nativephp-internal.running', false);
    }

    /**
     * Safe wrapper for Settings::get() that returns null when NativePHP isn't running.
     */
    private function settingsGet(string $key): mixed
    {
        if (! $this->isNativeContext()) {
            return null;
        }

        try {
            return Settings::get($key);
        } catch (\Exception $e) {
            Log::debug("Settings unavailable: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Safe wrapper for Settings::set() that no-ops when NativePHP isn't running.
     */
    private function settingsSet(string $key, mixed $value): void
    {
        if (! $this->isNativeContext()) {
            return;
        }

        try {
            Settings::set($key, $value);
        } catch (\Exception $e) {
            Log::debug("Settings unavailable: {$e->getMessage()}");
        }
    }
}
