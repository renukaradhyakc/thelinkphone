<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Models\Event;
use App\Models\UserSchedule;
use Carbon\Carbon;
use App\Services\TimeParser;
use App\Models\EventSchedule;


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

    public function liveExpiresAt($locationable): ?Carbon
    {
        if (! $this->live_started_at) {
            return null;
        }

        $cap = $this->live_started_at->copy()->addHours(24);

        if ($locationable instanceof Event) {
            return $this->eventExpiryCap($locationable, $cap);
        }

        if ($locationable instanceof EventSchedule) {
            return $this->eventScheduleExpiryCap($locationable, $cap);
        }

        return $cap;
    }

    private function eventExpiryCap(Event $event, Carbon $cap): Carbon
    {
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

    private function eventScheduleExpiryCap(EventSchedule $eventSchedule, Carbon $cap): Carbon
    {
        $times = explode(' - ', $eventSchedule->slot_time);
        $endTimeRaw = trim($times[1] ?? $times[0]);

        $scheduleEnd = Carbon::parse($eventSchedule->schedule_date, 'Asia/Kolkata');
        $parsed = app(TimeParser::class)->parseFlexibleTime($endTimeRaw, $scheduleEnd);

        if ($parsed) {
            $scheduleEnd->setTime($parsed->hour, $parsed->minute, 0);
        } else {
            $scheduleEnd->endOfDay();
        }

        return $cap->lessThan($scheduleEnd) ? $cap : $scheduleEnd;
    }

    public function resolveAddress(): array
    {
        if ((int) $this->location_type === self::FIXED) {
            return ['address' => $this->address, 'status' => 'fixed'];
        }

        if ($this->latitude === null || $this->longitude === null) {
            return ['address' => null, 'status' => 'not_started'];
        }

        if (! $this->is_live_sharing_active) {
            return ['address' => $this->address, 'status' => 'stopped'];
        }

        if ($this->address) {
            return ['address' => $this->address, 'status' => 'active'];
        }

        $lat = $this->latitude;
        $lon = $this->longitude;
        $cacheKey = "location_address_{$this->id}_{$lat}_{$lon}";

        $address = \Cache::remember($cacheKey, now()->addMinutes(15), function () use ($lat, $lon) {
            $response = \Http::withHeaders(['User-Agent' => 'CallaLink/1.0 (support@callalink.com)'])
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'format' => 'json',
                    'lat' => $lat,
                    'lon' => $lon,
                ]);

            return $response->successful() ? $response->json('display_name') : null;
        });

        if ($address && $this->address !== $address) {
            static::where('id', $this->id)
                ->where('latitude', $lat)
                ->where('longitude', $lon)
                ->update(['address' => $address]);
        }

        return ['address' => $address, 'status' => 'active'];
    }
}