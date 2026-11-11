<?php

namespace App\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $store
 * @property string|null $period_type
 * @property string $status
 * @property bool $will_renew
 * @property int $last_event_ms
 */
class UserSubscription extends Model
{
    public const ACTIVE = 'active';

    public const BILLING_ISSUE = 'billing_issue';

    public const EXPIRED = 'expired';

    /**
     * @var string
     */
    protected $table = 'user_subscriptions';

    /**
     * @var string[]
     */
    protected $fillable = [
        'user_id',
        'store',
        'product_id',
        'period_type',
        'status',
        'will_renew',
        'expires_at',
        'last_event_ms',
    ];

    /**
     * @var string[]
     */
    protected $casts = [
        'user_id' => 'integer',
        'will_renew' => 'boolean',
        'last_event_ms' => 'integer',
    ];

    /**
     * Stored as UTC text. Read back as a Carbon in UTC.
     */
    protected function expiresAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value, 'UTC') : null,
            set: fn ($value) => $value ? Carbon::parse($value)->utc()->format('Y-m-d H:i:s') : null,
        );
    }

    public function isTrial(): bool
    {
        return in_array($this->period_type, ['TRIAL', 'INTRO'], true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}