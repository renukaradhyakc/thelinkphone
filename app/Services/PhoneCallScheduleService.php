<?php

namespace App\Services;

use App\Models\PhoneSchedule;
use App\Models\Schedule;
use App\Models\UserSchedule;
use App\Repositories\PhoneScheduleRepository;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class PhoneCallScheduleService
{
    public function __construct(protected PhoneScheduleRepository $phoneScheduleRepository, protected TimeParser $timeParser) {
    }

    /**
     * Returns the active phone schedule slot for the
     * given caller number.
     *
     * Returns:
     *  - UserSchedule : active slot found
     *  - null         : no phone mapping or no active slot
     */
    public function getActivePhoneSchedule(string $normalizedNumber): ?UserSchedule
    {

        Log::info('PHONE_SLOT_START', [
            'number' => $normalizedNumber
        ]);

        $phoneSchedule = $this->phoneScheduleRepository
            ->getScheduleForPhone($normalizedNumber);

        Log::info('PHONE_SLOT_MAPPING_LOADED', [
            'exists' => $phoneSchedule !== null,
            'id' => $phoneSchedule?->id,
            'has_custom_slots' => $phoneSchedule?->userSchedules?->count(),
            'has_default_schedule' => $phoneSchedule?->schedule?->userSchedules?->count(),
        ]);

        if (!$phoneSchedule) {
            return null;
        }

        $slots = $this->loadScheduleSlots($phoneSchedule);

        Log::info('PHONE_SLOT_LOADED_SLOTS', [
            'count' => $slots->count(),
        ]);

        $result = $this->findActiveSlot($slots);

        Log::info('PHONE_SLOT_RESULT', [
            'active_slot' => $result?->id,
        ]);

        return $result;

    }

    /**
     * Determine which schedule should be evaluated.
     *
     * Existing Schedule
     *      PhoneSchedule
     *          ↓
     *      Schedule
     *          ↓
     *      UserSchedules
     *
     * Custom Schedule
     *      PhoneSchedule
     *          ↓
     *      UserSchedules
     */
    private function loadScheduleSlots(PhoneSchedule $phoneSchedule): Collection {

        /*
         * Custom Schedule
         */
        if ($phoneSchedule->userSchedules->isNotEmpty()) {
            return $phoneSchedule->userSchedules;
        }

        /*
         * Existing reusable Schedule
         */
        if ($phoneSchedule->schedule) {
            return $phoneSchedule->schedule->userSchedules;
        }

        return collect();
    }

    /**
     * Find the currently active schedule slot.
     */
    private function findActiveSlot(Collection $slots): ?UserSchedule {

        if ($slots->isEmpty()) {
            Log::info('PHONE_SLOT_EMPTY');
            return null;
        }

        $now = Carbon::now('Asia/Kolkata');

        Log::info('PHONE_SLOT_EVAL_START', [
            'now' => $now->toDateTimeString(),
            'total_slots' => $slots->count(),
        ]);

        foreach ($slots as $slot) {

            Log::info('PHONE_SLOT_CHECK', [
                'slot_id' => $slot->id,
                'day' => $slot->day_of_week,
                'from' => $slot->from_time,
                'to' => $slot->to_time,
            ]);

            if (!$this->isToday($slot, $now)) {
                Log::info('PHONE_SLOT_SKIP_DAY', ['slot_id' => $slot->id]);
                continue;
            }

            if ($this->isWithinTimeRange($slot, $now)) {
                Log::info('PHONE_SLOT_MATCHED', ['slot_id' => $slot->id]);
                return $slot;
            }

            Log::info('PHONE_SLOT_NOT_MATCHED_TIME', ['slot_id' => $slot->id]);
        }

        Log::info('PHONE_SLOT_NO_MATCH');
        return null;
    }

    /**
     * Check weekday.
     */
    private function isToday(UserSchedule $slot, Carbon $now): bool
    {
        return (int) $slot->day_of_week === (int) $now->dayOfWeekIso;
    }

    /**
     * Determine whether the current time falls
     * inside the schedule slot.
     */
    private function isWithinTimeRange(UserSchedule $slot,Carbon $now): bool {

        $startTime = $this->timeParser->parseFlexibleTime($slot->from_time, $now);
        $endTime   = $this->timeParser->parseFlexibleTime($slot->to_time, $now);

        Log::info('PHONE_SLOT_TIME_PARSE', [
            'slot_id' => $slot->id,
            'start_raw' => $slot->from_time,
            'end_raw' => $slot->to_time,
            'start_parsed' => $startTime?->toDateTimeString(),
            'end_parsed' => $endTime?->toDateTimeString(),
            'now' => $now->toDateTimeString(),
        ]);

        if (!$startTime || !$endTime) {

            Log::error('PHONE_SLOT_PARSE_FAIL', ['slot_id' => $slot->id]);
            return false;
        }

        Log::info('PHONE_SLOT_TIME_RESULT', [
            'slot_id' => $slot->id,
            'result' => $now->between($startTime, $endTime, true),
        ]);

        return $now->between($startTime, $endTime, true);
    }

    /**
     * TODO:
     * user_schedules currently contains:
     *   1. Default schedule rows
     *   2. Event custom schedule rows
     *   3. Phone custom schedule rows
     *
     * Event custom rows currently reuse the user's default schedule_id,
     * which pollutes the default schedule lookup.
     *
     * Temporary workaround:
     * Fetch the latest/default schedule rows for now.
     *
     * This must be redesigned later, most likely using the
     * check_default flag (or another discriminator) so only
     * the true default schedule rows are evaluated.
     */
    // FIXME:
    // Default schedule lookup is temporarily implemented.
    // user_schedules is currently polluted by event-specific custom rows
    // because they reuse the user's default schedule_id.
    //
    // Once the schedule model is refactored, this lookup must use
    // check_default (or another discriminator) instead of relying
    // solely on schedule_id.
    public function getActiveDefaultSchedule(): ?UserSchedule
    {
        $defaultSchedule = Schedule::where('user_id', getLogInUserId())
            ->where('is_default', true)
            ->with('userSchedules')
            ->first();

        if (!$defaultSchedule) {
            return null;
        }

        //Start:Extra Added for the purpose of API testing 
        $slots = $defaultSchedule->userSchedules()
                ->whereNull('event_id')
                ->whereNull('phone_schedule_id')
                ->get();

        return $this->findActiveSlot($slots);

        //End:Extra Added for the purpose of API testing 
        // return $this->findActiveSlot($defaultSchedule->userSchedules);
    }
}