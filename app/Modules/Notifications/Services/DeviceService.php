<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Models\DeviceToken;
use App\Modules\Notifications\Repositories\DeviceTokenRepository;

class DeviceService
{
    public function __construct(private DeviceTokenRepository $devices)
    {
    }

    public function register(int $userId, string $platform, string $token, array $extra = []): DeviceToken
    {
        return $this->devices->upsert($userId, $platform, $token, $extra);
    }

    public function unregister(int $userId, string $token): bool
    {
        return $this->devices->revokeForUser($userId, $token);
    }

    /** Called by the FCM channel when the provider rejects a token. */
    public function revokeInvalid(DeviceToken $device): void
    {
        $this->devices->revoke($device);
    }
}