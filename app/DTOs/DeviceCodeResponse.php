<?php

namespace App\DTOs;

/**
 * Represents the response from GitHub's device code request.
 * Contains the user code to display and polling parameters.
 */
final readonly class DeviceCodeResponse
{
    public function __construct(
        public string $deviceCode,
        public string $userCode,
        public string $verificationUri,
        public int $expiresIn,
        public int $interval,
    ) {}

    /**
     * Create from GitHub API response array.
     */
    public static function fromResponse(array $data): self
    {
        return new self(
            deviceCode: $data['device_code'],
            userCode: $data['user_code'],
            verificationUri: $data['verification_uri'],
            expiresIn: $data['expires_in'],
            interval: $data['interval'],
        );
    }

    /**
     * Check if the device code has expired based on a start timestamp.
     */
    public function isExpired(int $startedAt): bool
    {
        return time() - $startedAt >= $this->expiresIn;
    }
}
