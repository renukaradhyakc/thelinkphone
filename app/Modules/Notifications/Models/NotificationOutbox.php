<?php

namespace App\Modules\Notifications\Models;

use App\Models\EventSchedule;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scheduled sends. `notify_at` is always written and compared in UTC.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $event_schedule_id
 * @property string $type
 * @property string $reason
 * @property string $dedupe_key
 * @property string $status
 * @property int $attempts
 */
class NotificationOutbox extends Model
{
    public const PENDING = 'pending';

    public const CLAIMED = 'claimed';

    public const SENT = 'sent';

    public const CANCELLED = 'cancelled';

    public const FAILED = 'failed';

    /**
     * @var string
     */
    protected $table = 'notification_outbox';

    /**
     * @var string[]
     */
    protected $fillable = [
        'user_id',
        'event_schedule_id',
        'type',
        'reason',
        'notify_at',
        'dedupe_key',
        'status',
        'attempts',
        'claimed_at',
        'context',
    ];

    /**
     * @var string[]
     */
    protected $casts = [
        'user_id' => 'integer',
        'event_schedule_id' => 'integer',
        'claimed_at' => 'datetime',
        'attempts' => 'integer',
        'context' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function eventSchedule(): BelongsTo
    {
        return $this->belongsTo(EventSchedule::class, 'event_schedule_id');
    }

    /**
     * Stored as UTC text. Read back as a Carbon in UTC.
     */
    protected function notifyAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value, 'UTC') : null,
            set: fn ($value) => $value ? Carbon::parse($value)->utc()->format('Y-m-d H:i:s') : null,
        );
    }

    /**
     * Pending rows whose time has come. Pass the current time (any timezone).
     */
    public function scopeDue(Builder $query, CarbonInterface $now): Builder
    {
        return $query->where('status', self::PENDING)
            ->where('notify_at', '<=', $now->copy()->utc()->format('Y-m-d H:i:s'));
    }
}