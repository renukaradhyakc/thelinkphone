<?php

namespace App\Modules\Notifications\Repositories;

use App\Modules\Notifications\Models\DeviceToken;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Push destinations (FCM tokens and browser subscriptions).
  *
  * Rules:
  *  - One row per token (token_hash is unique). Registering a token that belongs to someone
  *    else moves it to the current user, so a shared phone never keeps the old user's pushes.
  *  - One ACTIVE row per physical device. A device only ever has one valid FCM token, so when a
  *    new token arrives for (platform, device_id), every other active row for that device is
  *    retired, whichever user it belonged to. Web subscriptions are excluded (no device_id).
 */
class DeviceTokenRepository
{

    /** Android emulators / broken builds report this constant; it identifies nothing. */
    private const BAD_DEVICE_IDS = ['9774d56d682e549c', '0000000000000000'];

    /**
     * @param  array{device_id?: ?string, app_version?: ?string, web_keys?: ?array}  $extra
     */
    public function upsert(int $userId, string $platform, string $token, array $extra = []): DeviceToken
    {
        $hash = DeviceToken::hashToken($token);
        $deviceId = $this->cleanDeviceId($extra['device_id'] ?? null);
        $retried = false;

        while (true) {
            try {
                return $this->withDeviceLock($platform, $deviceId, fn () => 
                    DB::transaction(function () use ($userId, $platform, $token, $hash, $deviceId, $extra) {
                    $device = DeviceToken::where('token_hash', $hash)->lockForUpdate()->first()
                        ?? new DeviceToken();

                    $device->fill([
                        'user_id' => $userId,
                        'platform' => $platform,
                        'token' => $token, // the model also sets token_hash
                        'device_id' => $deviceId ?? $device->device_id,
                        'app_version' => $extra['app_version'] ?? $device->app_version,
                        'web_keys' => $extra['web_keys'] ?? $device->web_keys,
                        'last_seen_at' => now(),
                        'revoked_at' => null,
                    ])->save();

                    $this->retireSiblings($device);

                    return $device;
                }));
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
    public function revokeForUser(int $userId, string $token, ?string $deviceId = null): bool
    {
        $deviceId = $this->cleanDeviceId($deviceId);
        $hash = DeviceToken::hashToken($token);

        return DeviceToken::where('user_id', $userId)
            ->whereNull('revoked_at')
            ->where(function ($query) use ($hash, $deviceId) {
                $query->where('token_hash', $hash);

                if ($deviceId !== null) {
                    $query->orWhere('device_id', $deviceId);
                }
            })
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

        return $query->get()
            ->groupBy(fn (DeviceToken $d) => $d->device_id && $d->platform !== DeviceToken::WEB
                ? $d->platform.':'.$d->device_id
                : 'row:'.$d->id)
            ->map(fn (Collection $group) => $group
                ->sortByDesc(fn (DeviceToken $d) => [optional($d->last_seen_at)->timestamp ?? 0, $d->id])
                ->first())
            ->values();
    }

    /**
     * Mobile tokens that have not checked in for a long time are almost certainly uninstalled apps.
     * Clients must re-register periodically (see PushManager) so live devices keep last_seen_at fresh.
     */
    public function revokeStale(CarbonInterface $before): int
    {
        return DeviceToken::whereNull('revoked_at')
            ->whereIn('platform', [DeviceToken::ANDROID, DeviceToken::IOS])
            ->where('last_seen_at', '<', $before)
            ->update(['revoked_at' => now()]);
    }

    public function deleteRevokedBefore(CarbonInterface $before): int
    {
        return DeviceToken::whereNotNull('revoked_at')->where('revoked_at', '<', $before)->delete();
    }

    /**
     * A device has exactly one valid token at a time: retire every other active row for it.
     */
    private function retireSiblings(DeviceToken $device): void
    {
        if ($device->platform === DeviceToken::WEB || empty($device->device_id)) {
            return;
        }

        $retired = DeviceToken::where('platform', $device->platform)
            ->where('device_id', $device->device_id)
            ->where('id', '!=', $device->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        if ($retired > 0) {
            Log::info('device_token.siblings_retired', [
                'device_row' => $device->id,
                'platform' => $device->platform,
                'retired' => $retired,
            ]);
        }
    }

    private function withDeviceLock(string $platform, ?string $deviceId, callable $callback)
    {
        if ($deviceId === null || $platform === DeviceToken::WEB) {
            return $callback();
        }

        $name = 'device_token:'.substr(hash('sha256', $platform.':'.$deviceId), 0, 40);
        $acquired = (int) (DB::selectOne('SELECT GET_LOCK(?, 5) AS got', [$name])->got ?? 0);

        try {
            return $callback();
        } finally {
            if ($acquired === 1) {
                DB::select('SELECT RELEASE_LOCK(?)', [$name]);
            }
        }
    }

    private function cleanDeviceId(?string $deviceId): ?string
    {
        $deviceId = trim((string) $deviceId);

        if ($deviceId === '' || in_array(strtolower($deviceId), self::BAD_DEVICE_IDS, true)) {
            return null;
        }

        return $deviceId;
    }

    private function isDuplicateKey(QueryException $exception): bool
    {
        return (int) ($exception->errorInfo[1] ?? 0) === 1062;
    }
}