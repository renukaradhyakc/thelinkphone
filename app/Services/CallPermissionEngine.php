<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class CallPermissionEngine
{
    public function __construct(
        protected EventCallScheduleService $eventCallScheduleService,
        protected PhoneCallScheduleService $phoneCallScheduleService,
        protected PhoneNumberNormalizer $phoneNormalizer
    ) {
    }

    /**
     * Determine whether an incoming caller
     * is currently allowed.
     */
    public function evaluate(string $callerNumber): array
    {
        /*
         * Normalize once.
         */
        $normalizedNumber = $this->phoneNormalizer->normalize($callerNumber);
        Log::info('Caller number received', ['caller_number' => $normalizedNumber]);

         Log::info('CP_ENGINE_START', [
            'input_number' => $callerNumber,
            'normalized' => $normalizedNumber,
        ]);

        /*
         * Lookup caller.
         */
        // $caller = User::where('phone_number','LIKE',"%{$callerNumber}%")->first();
        $caller = User::where('phone_number',$normalizedNumber)->first();

        Log::info('CP_ENGINE_USER_LOOKUP', [
            'found' => $caller !== null,
            'user_id' => $caller?->id,
            'first_name' => $caller?->first_name,
            'last_name' => $caller?->last_name,
            'domain_url' => $caller?->domain_url,
        ]);

        $isCallalinkUser = $caller !== null;
        $callerId = $caller?->id;
        $callerFirstName = $caller?->first_name;
        $callerLastName = $caller?->last_name;
        $callerDomainUrl = $caller?->domain_url;

        /*
         * Highest priority:
         * Active Appointment.
         * EVENT CHECK
        */
        Log::info('CP_ENGINE_EVENT_CHECK_START');

        $event = $this->eventCallScheduleService
            ->getActiveAppointment($normalizedNumber);

        Log::info('CP_ENGINE_EVENT_CHECK_END', [
            'event_found' => $event !== null,
            'event_id' => $event?->id,
        ]);

        if ($event) {

            Log::info('CP_ENGINE_DECISION_EVENT', [
                'allowed' => true,
                'source' => 'event',
                'event_id' => $event->id,
            ]);

            return [
                'allowed' => true,
                'eventname' => $event->name,
                'slot_time' => $event->slot_time,
                'is_callalink_user' => $isCallalinkUser,
                'id' => $callerId,
                'username' => $callerFirstName,
                'last_name' => $callerLastName,
                'domain_url' => $callerDomainUrl,
            ];
        }

        /*
         * Second priority:
         * Phone specific schedule.
         * PHONE SCHEDULE CHECK
        */
        Log::info('CP_ENGINE_PHONE_SLOT_CHECK_START');

        $phoneSlot = $this->phoneCallScheduleService
            ->getActivePhoneSchedule($normalizedNumber);

        Log::info('CP_ENGINE_PHONE_SLOT_CHECK_END', [
            'slot_found' => $phoneSlot !== null,
            'slot_id' => $phoneSlot?->id,
        ]);

        if ($phoneSlot) {

            Log::info('CP_ENGINE_DECISION_PHONE_SLOT', [
                'allowed' => true,
                'source' => 'phone_schedule',
                'slot' => "{$phoneSlot->from_time}-{$phoneSlot->to_time}",
            ]);

            return [
                'allowed' => true,
                'eventname' => null,
                'slot_time' => "{$phoneSlot->from_time} - {$phoneSlot->to_time}",
                'is_callalink_user' => $isCallalinkUser,
                'id' => $callerId,
                'username' => $callerFirstName,
                'last_name' => $callerLastName,
                'domain_url' => $callerDomainUrl,
            ];
        }

        /*
         * Third priority:
         * Default schedule.
         * DEFAULT CHECK
        */
        Log::info('CP_ENGINE_DEFAULT_SLOT_CHECK_START');

        $defaultSlot = $this->phoneCallScheduleService
            ->getActiveDefaultSchedule();

        Log::info('CP_ENGINE_DEFAULT_SLOT_CHECK_END', [
            'slot_found' => $defaultSlot !== null,
            'slot_id' => $defaultSlot?->id,
        ]);

        if ($defaultSlot) {

            return [
                'allowed' => true,
                'eventname' => null,
                'slot_time' => "{$defaultSlot->from_time} - {$defaultSlot->to_time}",
                'is_callalink_user' => $isCallalinkUser,
                'id' => $callerId,
                'username' => $callerFirstName,
                'last_name' => $callerLastName,
                'domain_url' => $callerDomainUrl,
            ];
        }

        /*
         * Block.
         */

        Log::critical('Invariant violated: no default schedule found.', ['owner_id' => getLogInUserId(),]);

        Log::critical('CP_ENGINE_BLOCKED_NO_SCHEDULE', [
            'normalized' => $normalizedNumber,
            'user_id' => $caller?->id,
        ]);

        return [
            'allowed' => false,
            'eventname' => null,
            'slot_time' => null,
            'is_callalink_user' => $isCallalinkUser,
            'id' => $callerId,
            'username' => $callerFirstName,
            'last_name' => $callerLastName,
            'domain_url' => $callerDomainUrl,
        ];
    }
}