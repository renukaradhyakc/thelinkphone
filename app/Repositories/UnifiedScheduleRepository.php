<?php

namespace App\Repositories;

use App\Models\EventSchedule;
use App\Models\PhoneSchedule;
use App\Models\UserSchedule;
use App\Models\User;
use App\Services\TimeParser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class UnifiedScheduleRepository
{
    public function __construct(protected TimeParser $timeParser)
    {
    }

    /* Drops today's events that have fully ENDED. A slot that has already
     * started but not finished (currently ongoing) still counts as active
     * and must stay visible — only checking start time was wrong, since it
     * dropped a slot the moment it began rather than when it actually ended.
     * Future dates pass through untouched.
     */
    protected function excludePassedToday(Collection $events): Collection
    {
        $now = Carbon::now('Asia/Kolkata');
        $today = $now->toDateString();

        return $events->filter(function (EventSchedule $es) use ($now, $today) {
            if ($es->schedule_date !== $today) {
                return true; // not today — date filter already handled this
            }

            $parts = explode('-', $es->slot_time ?? '');
            $endRaw = trim($parts[1] ?? '');
            $end = $endRaw ? $this->timeParser->parseFlexibleTime($endRaw, $now) : null;

            // if we can't parse it, keep it rather than silently hiding data
            return $end === null || $end->greaterThanOrEqualTo($now);
        })->values();
    }
    /**
     * Phone schedules I have assigned to others.
     */
    public function phoneSchedulesGivenByMe(int $userId): Collection
    {
        return PhoneSchedule::with(['schedule.userSchedules' => function ($q) {
                // template weekly pattern rows for a pre-existing schedule
                $q->whereNull('phone_schedule_id')->whereNull('event_id');
            }])
            ->where('user_id', $userId)
            ->get();
    }

    /**
     * Phone schedules others have assigned to me (reverse lookup — new).
     */
    public function phoneSchedulesGivenToMe(string $myPhoneNumber): Collection
    {
        return PhoneSchedule::with([
                'user', // assigner
                'schedule.userSchedules' => function ($q) {
                    $q->whereNull('phone_schedule_id')->whereNull('event_id');
                },
            ])
            ->where('phone_number_normalized', $myPhoneNumber)
            ->get();
    }

    /**
     * Custom (non-recurring-template) time slots for a given phone schedule.
     * Only relevant when phone_schedules.schedule_id is NULL.
     */
    public function customSlotsForPhoneSchedule(int $phoneScheduleId): Collection
    {
        return UserSchedule::where('phone_schedule_id', $phoneScheduleId)->get();
    }

    public function customSlotsForPhoneSchedules(array $phoneScheduleIds): Collection
    {
        if (empty($phoneScheduleIds)) {
           return new Collection();
        }

        return UserSchedule::whereIn('phone_schedule_id', $phoneScheduleIds)
            ->get()
            ->groupBy('phone_schedule_id');
    }

    /**
     * Event bookings on MY events (people who booked time with me).
     * schedule_date + slot_time already live directly on event_schedules.
     */
    public function eventSchedulesGivenByMe(int $userId): Collection
    {
        $events = EventSchedule::with(['user', 'event'])
            ->where('user_id', $userId)
            ->whereIn('status', [EventSchedule::BOOKED, EventSchedule::HOLD, EventSchedule::CANCELLED])
            ->whereDate('schedule_date', '>=', now()->toDateString())
            ->orderBy('schedule_date')
            ->get();

        return $this->excludePassedToday($events);
    }

    /**
     * Events I have booked on someone else's calendar (reverse lookup — new).
     * Matched via my own phone_number, since booker must be a logged-in
     * CallALink user (email is free-text on the booking form, not reliable).
     */
    public function eventSchedulesGivenToMe(string $myPhoneNumber): Collection
    {
        $events = EventSchedule::with(['user', 'event'])
            ->where('phone_call', $myPhoneNumber)
            ->whereIn('status', [EventSchedule::BOOKED, EventSchedule::HOLD, EventSchedule::CANCELLED])
            ->whereDate('schedule_date', '>=', now()->toDateString())
            ->orderBy('schedule_date')
            ->get();

        return $this->excludePassedToday($events);
    }

    public function usersByPhoneNumbers(array $phoneNumbers): Collection
    {
        $numbers = array_values(array_unique(array_filter($phoneNumbers)));
        if (empty($numbers)) {
            return collect();
        }
        return User::whereIn('phone_number', $numbers)->get()->keyBy('phone_number');
    }
}