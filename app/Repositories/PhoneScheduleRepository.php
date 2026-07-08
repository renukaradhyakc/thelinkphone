<?php

namespace App\Repositories;

use App\Models\PhoneSchedule;
use App\Models\Schedule;
use App\Models\UserSchedule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use App\Services\PhoneNumberNormalizer;
use Exception;

/**
 * NOTE:
 * This repository is designed to match your current project conventions.
 * - Uses getLogInUserId()
 * - Uses userSchedules relationship
 * - Five public methods only
 * - Handles CRUD operations for phone schedule assignments.
 * - All phone numbers are normalized internally using
 *   PhoneNumberNormalizer before any database lookup or persistence.
 */
class PhoneScheduleRepository
{

    public function __construct(protected PhoneNumberNormalizer $phoneNormalizer) {}

    public function assignExistingSchedule(string $phoneNumber, int $scheduleId): PhoneSchedule
    {
        try {
            return DB::transaction(function () use ($phoneNumber, $scheduleId) {

                $phoneNumber = $this->phoneNormalizer->normalize($phoneNumber);

                $mapping = PhoneSchedule::updateOrCreate(
                    [
                        'user_id' => getLogInUserId(),
                        'phone_number_normalized' => $phoneNumber,
                    ],
                    [
                        'schedule_id' => $scheduleId,
                    ]
                );

                UserSchedule::where('phone_schedule_id', $mapping->id)->delete();

                return $mapping;
            });
        } catch (Exception $e) {
            throw new UnprocessableEntityHttpException($e->getMessage());
        }
    }

    public function assignCustomSchedule(string $phoneNumber, array $slots): PhoneSchedule
    {
        try {
            return DB::transaction(function () use ($phoneNumber, $slots) {

                $phoneNumber = $this->phoneNormalizer->normalize($phoneNumber);

                $mapping = PhoneSchedule::updateOrCreate(
                    [
                        'user_id' => getLogInUserId(),
                        'phone_number_normalized' => $phoneNumber,
                    ],
                    [
                        'schedule_id' => null,
                    ]
                );

                $this->replaceCustomSlots($mapping, $slots);

                return $mapping->load('userSchedules');
            });
        } catch (Exception $e) {
            throw new UnprocessableEntityHttpException($e->getMessage());
        }
    }

    public function getScheduleForPhone(string $phoneNumber): ?PhoneSchedule
    {

        $phoneNumber = $this->phoneNormalizer->normalize($phoneNumber);

        return PhoneSchedule::where('user_id', getLogInUserId())
            ->where('phone_number_normalized', $phoneNumber)
            ->with(['schedule.userSchedules','userSchedules'])
            ->first();
    }

    public function removeSchedule(string $phoneNumber): bool
    {
        try {
            return DB::transaction(function () use ($phoneNumber) {

                $phoneNumber = $this->phoneNormalizer->normalize($phoneNumber);

                $mapping = PhoneSchedule::where('user_id', getLogInUserId())
                    ->where('phone_number_normalized', $phoneNumber)
                    ->first();

                if (!$mapping) {
                    return false;
                }

                UserSchedule::where('phone_schedule_id', $mapping->id)->delete();

                return $mapping->delete();
            });
        } catch (Exception $e) {
            throw new UnprocessableEntityHttpException($e->getMessage());
        }
    }

    public function listSchedules(): Collection
    {
        return Schedule::where('user_id', getLogInUserId())
            ->orderByDesc('is_default')
            ->orderBy('schedule_name')
            ->get();
    }

    private function replaceCustomSlots(
        PhoneSchedule $mapping,
        array $slots
    ): void {

        UserSchedule::where('phone_schedule_id', $mapping->id)->delete();

        foreach ($slots as $slot) {
            UserSchedule::create([
                'user_id' => getLogInUserId(),
                'schedule_id' => null,
                'phone_schedule_id' => $mapping->id,
                'event_id' => null,
                'day_of_week' => $slot['day_of_week'],
                'from_time' => $slot['from_time'],
                'to_time' => $slot['to_time'],
                'check_tab' => UserSchedule::CUSTOM_SCHEDULE,
                'check_default' => false,
            ]);
        }
    }
}
