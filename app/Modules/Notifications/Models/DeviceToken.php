<?php

namespace App\Modules\Notifications\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per push destination (FCM token or browser subscription).
 * Setting `token` also sets `token_hash`, which is the unique key.
 *
 * @property int $id
 * @property int $user_id
 * @property string $platform
 * @property string $token
 * @property string $token_hash
 */
class DeviceToken extends Model
{
    public const ANDROID = 'android';

    public const IOS = 'ios';

    public const WEB = 'web';

    public const PLATFORMS = [self::ANDROID, self::IOS, self::WEB];

    /**
     * @var string
     */
    protected $table = 'device_tokens';

    /**
     * @var string[]
     */
    protected $fillable = [
        'user_id',
        'platform',
        'token',
        'token_hash',
        'device_id',
        'app_version',
        'web_keys',
        'last_seen_at',
        'revoked_at',
    ];

    /**
     * @var string[]
     */
    protected $casts = [
        'user_id' => 'integer',
        'web_keys' => 'array',
        'last_seen_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function setTokenAttribute(string $value): void
    {
        $this->attributes['token'] = $value;
        $this->attributes['token_hash'] = self::hashToken($value);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }
}