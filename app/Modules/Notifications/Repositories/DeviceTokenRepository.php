<?php

namespace App\Modules\Notifications\Repositories;

use App\Modules\Notifications\Models\DeviceToken;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

/**
 * Push destinations (FCM tokens and browser subscriptions).
 * One row per token. Registering a token that belongs to someone else moves it
 * to the current user, so a shared phone never keeps the old user's pushes.
 */
class DeviceTokenRepository
{
    /**
     * @param  array{device_id?: ?string, app_version?: ?string, web_keys?: ?array}  $extra
     */
    public function upsert(int $userId, string $platform, string $token, array $extra = []): DeviceToken
    {
        $hash = DeviceToken::hashToken($token);
        $retried = false;

        while (true) {
            try {
                $device = DeviceToken::where('token_hash', $hash)->first() ?? new DeviceToken();

                $device->fill([
                    'user_id' => $userId,
                    'platform' => $platform,
                    'token' => $token, // the model also sets token_hash
                    'device_id' => $extra['device_id'] ?? $device->device_id,
                    'app_version' => $extra['app_version'] ?? $device->app_version,
                    'web_keys' => $extra['web_keys'] ?? $device->web_keys,
                    'last_seen_at' => now(),
                    'revoked_at' => null,
                ])->save();

                return $device;
            } catch (QueryException $exception) {
                // two requests registered the same new token at once: try once more
                if (! $retried && $this->isDuplicateKey($exception)) {
                    $retried = true;

                    continue;
                }

                throw $exception;
            }
        }
    }

    public function findByToken(string $token): ?DeviceToken
    {
        return DeviceToken::where('token_hash', DeviceToken::hashToken($token))->first();
    }

    /**
     * Revoke one of the caller's own devices (logout). Returns false if not found.
     */
    public function revokeForUser(int $userId, string $token): bool
    {
        return DeviceToken::where('user_id', $userId)
            ->where('token_hash', DeviceToken::hashToken($token))
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]) > 0;
    }

    /**
     * Revoke after the provider said the destination is invalid or expired.
     */
    public function revoke(DeviceToken $device): void
    {
        $device->forceFill(['revoked_at' => now()])->save();
    }

    /**
     * @param  string[]|null  $platforms  null = all platforms
     */
    public function activeForUser(int $userId, ?array $platforms = null): Collection
    {
        $query = DeviceToken::active()->where('user_id', $userId);

        if ($platforms !== null) {
            $query->whereIn('platform', $platforms);
        }

        return $query->get();
    }

    public function deleteRevokedBefore(CarbonInterface $before): int
    {
        return DeviceToken::whereNotNull('revoked_at')->where('revoked_at', '<', $before)->delete();
    }

    private function isDuplicateKey(QueryException $exception): bool
    {
        return (int) ($exception->errorInfo[1] ?? 0) === 1062;
    }
}