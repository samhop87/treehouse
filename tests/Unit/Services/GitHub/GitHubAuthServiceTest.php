<?php

namespace Tests\Unit\Services\GitHub;

use App\DTOs\DeviceCodeResponse;
use App\Services\GitHub\GitHubAuthService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Native\Desktop\Facades\Settings;
use Tests\TestCase;

class GitHubAuthServiceTest extends TestCase
{
    private GitHubAuthService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.github.client_id' => 'test-client-id',
            'services.github.device_code_url' => 'https://github.com/login/device/code',
            'services.github.access_token_url' => 'https://github.com/login/oauth/access_token',
            'services.github.api_base_url' => 'https://api.github.com',
            'services.github.scopes' => 'repo',
            'nativephp-internal.running' => true,
        ]);

        $this->service = new GitHubAuthService();
    }

    public function test_is_configured_returns_true_when_client_id_set(): void
    {
        $this->assertTrue($this->service->isConfigured());
    }

    public function test_is_configured_returns_false_when_client_id_empty(): void
    {
        config(['services.github.client_id' => '']);
        $service = new GitHubAuthService();

        $this->assertFalse($service->isConfigured());
    }

    public function test_request_device_code_returns_dto(): void
    {
        Http::fake([
            'github.com/login/device/code' => Http::response([
                'device_code' => 'dc-123',
                'user_code' => 'ABCD-1234',
                'verification_uri' => 'https://github.com/login/device',
                'expires_in' => 900,
                'interval' => 5,
            ], 200),
        ]);

        $result = $this->service->requestDeviceCode();

        $this->assertInstanceOf(DeviceCodeResponse::class, $result);
        $this->assertEquals('dc-123', $result->deviceCode);
        $this->assertEquals('ABCD-1234', $result->userCode);
        $this->assertEquals('https://github.com/login/device', $result->verificationUri);
        $this->assertEquals(900, $result->expiresIn);
        $this->assertEquals(5, $result->interval);
    }

    public function test_request_device_code_throws_when_not_configured(): void
    {
        config(['services.github.client_id' => '']);
        $service = new GitHubAuthService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('GitHub Client ID is not configured');

        $service->requestDeviceCode();
    }

    public function test_request_device_code_throws_on_http_failure(): void
    {
        Http::fake([
            'github.com/login/device/code' => Http::response('error', 500),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to request device code from GitHub');

        $this->service->requestDeviceCode();
    }

    public function test_request_device_code_throws_on_missing_fields(): void
    {
        Http::fake([
            'github.com/login/device/code' => Http::response([
                'device_code' => 'dc-123',
                // Missing user_code and verification_uri
            ], 200),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid response from GitHub');

        $this->service->requestDeviceCode();
    }

    public function test_poll_for_token_returns_success_and_stores_token(): void
    {
        Http::fake([
            'github.com/login/oauth/access_token' => Http::response([
                'access_token' => 'gho_test_token_123',
                'token_type' => 'bearer',
                'scope' => 'repo',
            ], 200),
            'api.github.com/user' => Http::response([
                'login' => 'testuser',
                'name' => 'Test User',
                'avatar_url' => 'https://avatars.githubusercontent.com/u/1',
            ], 200),
        ]);

        Settings::shouldReceive('set')
            ->with('github_token', \Mockery::type('string'))
            ->once();
        Settings::shouldReceive('set')
            ->with('github_user', \Mockery::type('string'))
            ->once();

        $result = $this->service->pollForToken('dc-123');

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('gho_test_token_123', $result['token']);
    }

    public function test_poll_for_token_returns_pending(): void
    {
        Http::fake([
            'github.com/login/oauth/access_token' => Http::response([
                'error' => 'authorization_pending',
                'error_description' => 'The authorization request is still pending.',
            ], 200),
        ]);

        $result = $this->service->pollForToken('dc-123');

        $this->assertEquals('pending', $result['status']);
    }

    public function test_poll_for_token_returns_slow_down(): void
    {
        Http::fake([
            'github.com/login/oauth/access_token' => Http::response([
                'error' => 'slow_down',
                'interval' => 10,
            ], 200),
        ]);

        $result = $this->service->pollForToken('dc-123');

        $this->assertEquals('slow_down', $result['status']);
        $this->assertEquals(10, $result['interval']);
    }

    public function test_poll_for_token_returns_expired(): void
    {
        Http::fake([
            'github.com/login/oauth/access_token' => Http::response([
                'error' => 'expired_token',
            ], 200),
        ]);

        $result = $this->service->pollForToken('dc-123');

        $this->assertEquals('expired', $result['status']);
    }

    public function test_poll_for_token_returns_denied(): void
    {
        Http::fake([
            'github.com/login/oauth/access_token' => Http::response([
                'error' => 'access_denied',
            ], 200),
        ]);

        $result = $this->service->pollForToken('dc-123');

        $this->assertEquals('denied', $result['status']);
    }

    public function test_poll_for_token_returns_error_on_http_failure(): void
    {
        Http::fake([
            'github.com/login/oauth/access_token' => Http::response('error', 500),
        ]);

        $result = $this->service->pollForToken('dc-123');

        $this->assertEquals('error', $result['status']);
    }

    public function test_get_token_returns_null_when_no_token_stored(): void
    {
        Settings::shouldReceive('get')
            ->with('github_token')
            ->andReturn(null);

        $this->assertNull($this->service->getToken());
    }

    public function test_get_token_decrypts_stored_token(): void
    {
        $encrypted = Crypt::encryptString('gho_test_token');

        Settings::shouldReceive('get')
            ->with('github_token')
            ->andReturn($encrypted);

        $this->assertEquals('gho_test_token', $this->service->getToken());
    }

    public function test_get_token_clears_on_decryption_failure(): void
    {
        Settings::shouldReceive('get')
            ->with('github_token')
            ->andReturn('invalid-encrypted-data');

        Settings::shouldReceive('set')
            ->with('github_token', null)
            ->once();
        Settings::shouldReceive('set')
            ->with('github_user', null)
            ->once();

        $this->assertNull($this->service->getToken());
    }

    public function test_has_token_returns_true_when_token_exists(): void
    {
        $encrypted = Crypt::encryptString('gho_test_token');

        Settings::shouldReceive('get')
            ->with('github_token')
            ->andReturn($encrypted);

        $this->assertTrue($this->service->hasToken());
    }

    public function test_has_token_returns_false_when_no_token(): void
    {
        Settings::shouldReceive('get')
            ->with('github_token')
            ->andReturn(null);

        $this->assertFalse($this->service->hasToken());
    }

    public function test_clear_token_removes_token_and_user(): void
    {
        Settings::shouldReceive('set')
            ->with('github_token', null)
            ->once();
        Settings::shouldReceive('set')
            ->with('github_user', null)
            ->once();

        $this->service->clearToken();
    }

    public function test_get_user_returns_null_when_no_user_stored(): void
    {
        Settings::shouldReceive('get')
            ->with('github_user')
            ->andReturn(null);

        $this->assertNull($this->service->getUser());
    }

    public function test_get_user_returns_decoded_user_data(): void
    {
        $userData = json_encode([
            'login' => 'testuser',
            'name' => 'Test User',
            'avatar_url' => 'https://example.com/avatar.png',
        ]);

        Settings::shouldReceive('get')
            ->with('github_user')
            ->andReturn($userData);

        $user = $this->service->getUser();

        $this->assertEquals('testuser', $user['login']);
        $this->assertEquals('Test User', $user['name']);
    }

    public function test_verify_token_returns_true_for_valid_token(): void
    {
        $encrypted = Crypt::encryptString('gho_valid_token');

        Settings::shouldReceive('get')
            ->with('github_token')
            ->andReturn($encrypted);
        Settings::shouldReceive('set')
            ->with('github_user', \Mockery::type('string'))
            ->once();

        Http::fake([
            'api.github.com/user' => Http::response([
                'login' => 'testuser',
                'name' => 'Test User',
                'avatar_url' => 'https://example.com/avatar.png',
            ], 200),
        ]);

        $this->assertTrue($this->service->verifyToken());
    }

    public function test_verify_token_returns_false_for_invalid_token(): void
    {
        $encrypted = Crypt::encryptString('gho_invalid_token');

        Settings::shouldReceive('get')
            ->with('github_token')
            ->andReturn($encrypted);

        Http::fake([
            'api.github.com/user' => Http::response(['message' => 'Bad credentials'], 401),
        ]);

        $this->assertFalse($this->service->verifyToken());
    }

    public function test_verify_token_returns_false_when_no_token(): void
    {
        Settings::shouldReceive('get')
            ->with('github_token')
            ->andReturn(null);

        $this->assertFalse($this->service->verifyToken());
    }

    public function test_device_code_response_is_expired(): void
    {
        $dto = new DeviceCodeResponse(
            deviceCode: 'dc-123',
            userCode: 'ABCD-1234',
            verificationUri: 'https://github.com/login/device',
            expiresIn: 900,
            interval: 5,
        );

        // Started 1000 seconds ago — should be expired
        $this->assertTrue($dto->isExpired(time() - 1000));

        // Started just now — should not be expired
        $this->assertFalse($dto->isExpired(time()));
    }
}
