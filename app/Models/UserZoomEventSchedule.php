<?php

namespace App\Models;

use Barryvdh\LaravelIdeHelper\Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\UserZoomEventSchedule
 *
 * @property int $id
 * @property int $user_id
 * @property int $event_schedule_id
 * @property string $zoom_meeting_id
 * @property string $zoom_join_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder|UserZoomEventSchedule newModelQuery()
 * @method static Builder|UserZoomEventSchedule newQuery()
 * @method static Builder|UserZoomEventSchedule query()
 * @method static Builder|UserZoomEventSchedule whereCreatedAt($value)
 * @method static Builder|UserZoomEventSchedule whereEventScheduleId($value)
 * @method static Builder|UserZoomEventSchedule whereZoomMeetingId($value)
 * @method static Builder|UserZoomEventSchedule whereZoomJoinUrl($value)
 * @method static Builder|UserZoomEventSchedule whereId($value)
 * @method static Builder|UserZoomEventSchedule whereUpdatedAt($value)
 * @method static Builder|UserZoomEventSchedule whereUserId($value)
 *
 * @mixin Eloquent
 */
class UserZoomEventSchedule extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'user_zoom_event_schedules';

    /**
     * @var string[]
     */
    protected $fillable = [
        'user_id',
        'event_schedule_id',
        'zoom_meeting_id',
        'zoom_join_url',
    ];

    /**
     * @var string[]
     */
    protected $casts = [
        'user_id' => 'integer',
        'event_schedule_id' => 'integer',
        'zoom_meeting_id' => 'string',
        'zoom_join_url' => 'string',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}