<?php

namespace App\Services;

use App\Models\EventSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class EventCallScheduleService
{
    /**
     * Returns the active appointment (if any)
     * for the incoming caller.
     */
    public function __construct(protected TimeParser $timeParser) {}

    public function getActiveAppointment(string $normalizedNumber): ?EventSchedule
    {
        // Use IST timezone (Asia/Kolkata)
        $now = Carbon::now('Asia/Kolkata');
        Log::info('Current time', ['now' => $now->toDateTimeString()]);

        // Find events for today and matching phone number
        // $events = EventSchedule::where('phone_call', 'LIKE', "%{$callerNumber}%")
        //     ->where('schedule_date', $now->toDateString())
        //     ->get();
        $events = EventSchedule::where('phone_call', $normalizedNumber)
            ->where('schedule_date', $now->toDateString())
            ->get();

        Log::info('Events query result', [
            'count' => $events->count(),
            'date_checked' => $now->toDateString(),
            'phone_checked' => $normalizedNumber,
            // Raw DB check without date filter to isolate the issue:
            // 'phone_only_count' => EventSchedule::where('phone_call','LIKE',"%{$normalizedNumber}%")->count(),
            'phone_only_count' => EventSchedule::where('phone_call',$normalizedNumber)->count(),
        ]);

        $event = $events->filter(function ($item) use ($now) {

            Log::info('Checking event slot', ['slot_time' => $item->slot_time]);

            $slot = preg_replace('/[–—]/u', '-', $item->slot_time);
            $parts = array_map('trim', explode('-', $slot));

            if (count($parts) !== 2) {
                Log::warning('Invalid slot_time format', ['slot_time' => $item->slot_time]);
                return false;
            }

            [$startRaw, $endRaw] = $parts;

            $startTime = $this->timeParser->parseFlexibleTime($startRaw, $now);
            $endTime   = $this->timeParser->parseFlexibleTime($endRaw, $now);

            if (!$startTime || !$endTime) {

                Log::error('EVENT_SLOT_TIME_PARSE_FAILED', [
                    'event_id' => $item->id,
                    'start'    => $startRaw,
                    'end'      => $endRaw,
                    'slot'     => $item->slot_time,
                ]);
                
                return false;
            }

            Log::info('Parsed slot', [
                'start' => $startTime->toDateTimeString(),
                'end'   => $endTime->toDateTimeString(),
                'now'   => $now->toDateTimeString(),
            ]);

            return $now->between($startTime, $endTime, true);

        })->first();

        Log::info('Event found', ['event' => $event]);

        return $event;
    }
}