<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Repositories\PhoneScheduleRepository;
use Illuminate\Http\Request;
use App\Rules\PhoneNumberRule;
use App\Services\PhoneNumberNormalizer;
use Illuminate\Validation\Rule;
use App\Models\UserSchedule;
use App\Rules\ValidScheduleSlotRule;

class PhoneScheduleController extends Controller
{
    public function __construct(
        protected PhoneScheduleRepository $phoneScheduleRepository,
        protected PhoneNumberNormalizer $phoneNumberNormalizer,
    ) {
    }

    /**
     * Get assigned schedule for a phone number.
     */
    public function show(string $phoneNumber)
    {
        $this->validatePhoneNumber($phoneNumber);

        $schedule = $this->phoneScheduleRepository
            ->getScheduleForPhone($phoneNumber);

        return response()->json([
            'success' => true,
            'data' => $schedule,
        ]);
    }

    /**
     * Assign an existing reusable schedule.
     */
    public function assignExisting(Request $request)
    {
        $validated = $request->validate([
            'phone_number' => [
                'required', 
                new PhoneNumberRule($this->phoneNumberNormalizer),
            ],
            'schedule_id' => [
                'required',
                'integer',
                Rule::exists('schedules', 'id')
                    ->where('user_id', getLogInUserId()),
            ],
        ]);

        $schedule = $this->phoneScheduleRepository
            ->assignExistingSchedule(
                $validated['phone_number'],
                $validated['schedule_id']
            );

        return response()->json([
            'success' => true,
            'message' => 'Existing schedule assigned successfully.',
            'data' => $schedule,
        ]);
    }

    /**
     * Assign a custom schedule.
     */
    public function assignCustom(Request $request)
    {
        $validated = $request->validate([
            'phone_number' => [
                'required', 
                new PhoneNumberRule($this->phoneNumberNormalizer),
            ],

            'slots' => 'required|array|min:1',

            'slots.*' => [
                new ValidScheduleSlotRule(),
            ],

            'slots.*.day_of_week' => [
                'required',
                'integer',
                Rule::in(array_keys(UserSchedule::WEEKDAY)),
            ],
            'slots.*.from_time' => 'required|string',
            'slots.*.to_time' => 'required|string',
        ]);

        $schedule = $this->phoneScheduleRepository
            ->assignCustomSchedule(
                $validated['phone_number'],
                $validated['slots']
            );

        return response()->json([
            'success' => true,
            'message' => 'Custom schedule assigned successfully.',
            'data' => $schedule,
        ]);
    }

    /**
     * Update an assignment.
     *
     * Existing Schedule:
     *      phone_number + schedule_id
     *
     * Custom Schedule:
     *      phone_number + slots[]
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'phone_number' => [
                'required', 
                new PhoneNumberRule($this->phoneNumberNormalizer),
            ],

            'schedule_id' => [
                'nullable',
                'required_without:slots',
                'prohibits:slots',
                'integer',
                Rule::exists('schedules', 'id')
                    ->where('user_id', getLogInUserId()),
            ],

            'slots' => [
                'nullable',
                'required_without:schedule_id',
                'prohibits:schedule_id',
                'array',
            ],

            'slots.*' => [
                new ValidScheduleSlotRule(),
            ],

            'slots.*.day_of_week' => [
                'required',
                'integer',
                Rule::in(array_keys(UserSchedule::WEEKDAY)),
            ],
            'slots.*.from_time' => 'required_with:slots|string',
            'slots.*.to_time' => 'required_with:slots|string',
        ]);

        if (!empty($validated['schedule_id'])) {

            $schedule = $this->phoneScheduleRepository
                ->assignExistingSchedule(
                    $validated['phone_number'],
                    $validated['schedule_id']
                );

        } else {

            $schedule = $this->phoneScheduleRepository
                ->assignCustomSchedule(
                    $validated['phone_number'],
                    $validated['slots']
                );
        }

        return response()->json([
            'success' => true,
            'message' => 'Phone schedule updated successfully.',
            'data' => $schedule,
        ]);
    }

    /**
     * Remove phone schedule assignment.
     */
    public function destroy(string $phone)
    {
        $this->validatePhoneNumber($phone);

        $deleted = $this->phoneScheduleRepository
            ->removeSchedule($phone);

        return response()->json([
            'success' => $deleted,
            'message' => $deleted
                ? 'Phone schedule removed successfully.'
                : 'No phone schedule found.',
        ]);
    }

    /**
     * Helper Function
     */
    private function validatePhoneNumber(string $phoneNumber): void
    {
        validator(
            ['phone_number' => $phoneNumber],
            [
                'phone_number' => [
                    'required',
                    new PhoneNumberRule($this->phoneNumberNormalizer),
                ],
            ]
        )->validate();
    }
}