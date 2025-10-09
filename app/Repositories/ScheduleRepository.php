<?php

namespace App\Repositories;

use App\Models\Schedule;
use App\Models\UserSchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ScheduleRepository
{
    /**
     * Get all schedule names for the logged-in user.
     *
     * @return array
     */
    public function getScheduleNames(): array
    {
        return Schedule::where('user_id', getLogInUserId())
            ->pluck('schedule_name', 'id')
            ->toArray();
    }

    /**
     * Store a schedule without time slots.
     *
     * @param array $input
     * @return Schedule
     */
    public function store(array $input): Schedule
    {
        $input['user_id'] = getLogInUserId();
        $input['status'] = $input['status'] ?? 0;
        $input['is_default'] = $input['is_default'] ?? 0;
        $input['is_custom'] = $input['is_custom'] ?? 1;

        return Schedule::create($input);
    }

    /**
     * Store schedule with its time slots.
     *
     * @param array $scheduleInput
     * @param array $scheduleSlotTime
     * @return Schedule
     */
    public function storeScheduleWithSlot(array $scheduleInput, array $scheduleSlotTime): Schedule
    {
        DB::beginTransaction();
        try {
            $schedule = $this->store($scheduleInput);

            // Store associated time slots in UserSchedule
            foreach ($scheduleSlotTime['slots'] ?? [] as $slot) {
                UserSchedule::create([
                    'schedule_id' => $schedule->id,
                    'user_id' => getLogInUserId(),
                    'from_time' => $slot['from_time'],
                    'to_time' => $slot['to_time'],
                    'day_of_week' => $slot['day_of_week'],
                    'check_tab' => 0,
                    'check_default' => 0,
                    'event_id' => $scheduleSlotTime['event_id'] ?? null,
                ]);
            }

            DB::commit();
            return $schedule;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Store a schedule time slot via AJAX.
     *
     * @param array $input
     * @return array
     */
    public function storeScheduleTimeSlot(array $input): array
    {
        DB::beginTransaction();
        try {
            foreach ($input['slots'] ?? [] as $slot) {
                UserSchedule::create([
                    'schedule_id' => $input['schedule_id'],
                    'user_id' => getLogInUserId(),
                    'from_time' => $slot['from_time'],
                    'to_time' => $slot['to_time'],
                    'day_of_week' => $slot['day_of_week'],
                    'check_tab' => 0,
                    'check_default' => 0,
                    'event_id' => $input['event_id'] ?? null,
                ]);
            }

            DB::commit();
            return ['success' => true];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update schedule.
     *
     * @param array $input
     * @param int $id
     * @return Schedule
     */
    public function update(array $input, int $id): Schedule
    {
        $schedule = Schedule::findOrFail($id);
        $schedule->update($input);
        return $schedule;
    }
}
