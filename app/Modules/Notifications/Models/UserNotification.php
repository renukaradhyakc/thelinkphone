<?php

namespace App\Modules\Notifications\Models;

use App\Models\User;
use Dyrynda\Database\Support\GeneratesUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One inbox row per recipient. The public id is `uuid`; `id` is only for paging.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $type
 * @property string $reason
 * @property string $category
 * @property string $title
 * @property string $body
 * @property array|null $data
 * @property string|null $deep_link
 * @property string $dedupe_key
 */
class UserNotification extends Model
{
    use GeneratesUuid;

    /**
     * @var string
     */
    protected $table = 'user_notifications';

    /**
     * @var string[]
     */
    protected $fillable = [
        'uuid',
        'user_id',
        'type',
        'reason',
        'category',
        'title',
        'body',
        'data',
        'deep_link',
        'dedupe_key',
        'read_at',
        'expires_at',
    ];

    /**
     * @var string[]
     */
    protected $casts = [
        'user_id' => 'integer',
        'data' => 'array',
        'read_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }
}