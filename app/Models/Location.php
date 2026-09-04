<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Models\Event;
use App\Models\UserSchedule;
use Carbon\Carbon;
use App\Services\TimeParser;
class Location extends Model
{
    protected $table = 'locations';

    protected $fillable = [
        'locationable_type',
        'locationable_id',
        'location_type',
        'latitude',
        'longitude',
        'accuracy',
        'address',
        'live_started_at',
        'is_live_sharing_active',
    ];

    protected $casts = [
        'locationable_id' => 'integer',
        'location_type' => 'integer',
        'latitude' => 'double',
        'longitude' => 'double',
        'accuracy' => 'double',
        'live_started_at' => 'datetime',
        'is_live_sharing_active' => 'boolean',
    ];

    const FIXED = 1;
    const LIVE = 2;

    const LOCATION_TYPES = [
        self::FIXED => 'Fixed',
        self::LIVE => 'Live',
    ];

    public function locationable(): MorphTo
    {
        return $this->morphTo();
    }

    public function liveExpiresAt(Event $event): ? Carbon
    {
        if (! $this->live_started_at) {
            return null;
        }

        $cap = $this->live_started_at->copy()->addHours(24);

        if ((int) $event->date_range === 0 && ! empty($event->schedule_to)) {
            $scheduleEndDate = Carbon::parse(trim($event->schedule_to), 'Asia/Kolkata');
            $dayCode = $scheduleEndDate->dayOfWeekIso;

            $latestToTime = UserSchedule::where('event_id', $event->id)
                ->where('day_of_week', $dayCode)
                ->pluck('to_time')
                ->map(fn ($toTime) => app(TimeParser::class)->parseFlexibleTime($toTime, $scheduleEndDate))
                ->filter()
                ->max();

            $scheduleEnd = $latestToTime
                ? $scheduleEndDate->copy()->setTime($latestToTime->hour, $latestToTime->minute, 0)
                : $scheduleEndDate->endOfDay();

            return $cap->lessThan($scheduleEnd) ? $cap : $scheduleEnd;
        }

        return $cap;
    }
}