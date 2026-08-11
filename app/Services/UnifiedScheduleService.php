<?php

namespace App\Services;

use App\Models\EventSchedule;
use App\Models\PhoneSchedule;
use App\Models\UserSchedule;
use App\Models\User;
use App\Repositories\UnifiedScheduleRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Collection;

class UnifiedScheduleService
{
    public function __construct(protected UnifiedScheduleRepository $repo)
    {
    }

    /**
     * Single call, both directions bundled — toggle is client-side only,
     * no extra network round-trip when the user switches tabs.
     */
    public function getUnifiedSchedules(): array
    {
        $me = Auth::user();

        $phonesByMe = $this->repo->phoneSchedulesGivenByMe($me->id);
        $eventsByMe = $this->repo->eventSchedulesGivenByMe($me->id);

        $phoneNumbersToResolve = $phonesByMe->pluck('phone_number_normalized')
            ->concat($eventsByMe->pluck('phone_call'))
            ->filter()
            ->all();
        $usersByPhone = $this->repo->usersByPhoneNumbers($phoneNumbersToResolve);

        $givenByMe = $this->mapPhoneSchedules($phonesByMe, 'given_by_me', $usersByPhone)
            ->concat($this->mapEventSchedules($eventsByMe, 'given_by_me', $usersByPhone))
            ->sortBy('sort_key')->values();

        $givenToMe = $this->mapPhoneSchedules($this->repo->phoneSchedulesGivenToMe($me->phone_number), 'given_to_me')
            ->concat($this->mapEventSchedules($this->repo->eventSchedulesGivenToMe($me->phone_number), 'given_to_me'))
            ->sortBy('sort_key')->values();

        return [
            'given_by_me' => $givenByMe,
            'given_to_me' => $givenToMe,
        ];
    }

    protected function mapPhoneSchedules($phoneSchedules, string $direction, ?Collection $usersByPhone = null)
    {

        $customSlotIds = $phoneSchedules->whereNull('schedule_id')->pluck('id')->all();
        $customSlotsByPhoneScheduleId = $this->repo->customSlotsForPhoneSchedules($customSlotIds);

        return $phoneSchedules->map(function (PhoneSchedule $ps) use ($direction, $usersByPhone, $customSlotsByPhoneScheduleId) {
            $times = $this->resolvePhoneScheduleTimes($ps, $customSlotsByPhoneScheduleId);

            $otherParty = $direction === 'given_by_me'
                ? $this->partyPayload($usersByPhone?->get($ps->phone_number_normalized), null, $ps->phone_number_normalized)
                : $this->partyPayload($ps->user, null, null);

            return [
                'type' => 'phone',
                'id' => $ps->id,
                'direction' => $direction,
                'other_party' => $otherParty,
                'uses_pre-existing_schedule' => ! is_null($ps->schedule_id),
                'schedule_name' => optional($ps->schedule)->schedule_name,
                'times' => $times,
                'sort_key' => $ps->created_at,
            ];
        });
    }

    protected function resolvePhoneScheduleTimes(PhoneSchedule $ps, Collection $customSlotsByPhoneScheduleId): array
    {
        if ($ps->schedule_id) {
            // pre-existing weekly template
            return optional($ps->schedule)->userSchedules
                ? $ps->schedule->userSchedules->map(fn (UserSchedule $us) => [
                    'day_of_week' => $us->day_of_week,
                    'from_time' => $us->from_time,
                    'to_time' => $us->to_time,
                ])->toArray()
                : [];
        }

        $slots = $customSlotsByPhoneScheduleId->get($ps->id, collect());

        // custom slots directly on this phone schedule
        return $slots->map(fn (UserSchedule $us) => [
            'day_of_week' => $us->day_of_week,
            'from_time' => $us->from_time,
            'to_time' => $us->to_time,
        ])->toArray();
    }

    protected function mapEventSchedules($eventSchedules, string $direction, ?Collection $usersByPhone = null)
    {
        return $eventSchedules->map(function ($es) use ($direction, $usersByPhone) {
            $otherParty = $direction === 'given_by_me'
                ? $this->partyPayload($usersByPhone?->get($es->phone_call), $es->name, $es->phone_call)
                : $this->partyPayload($es->user, null, null);

            if ($direction === 'given_by_me') {
                $otherParty['email'] = $es->email;
            }

            return [
                'type' => 'event',
                'id' => $es->id,
                'direction' => $direction,
                'other_party' => $otherParty,
                'event_id' => $es->event_id,
                'event_name' => optional($es->event)->name,
                'date' => $es->schedule_date,
                'slot_time' => $es->slot_time,
                'status' => EventSchedule::STATUS[$es->status] ?? null,
                'description' => ($es->description !== null && $es->description !== '') ? $es->description : null,
                'cancel_reason' => $es->cancel_reason,
                'sort_key' => $es->schedule_date,
            ];
        });
    }

    protected function partyPayload(?User $user, ?string $fallbackName, ?string $fallbackPhone): array
    {
        if ($user) {
            return [
                'name' => $user->full_name,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'phone_number' => $user->phone_number,
                'email' => $user->email,
                'domain_url' => $user->domain_url,
                'is_callalink_user' => true,
            ];
        }

        return [
            'name' => $fallbackName,
            'first_name' => null,
            'last_name' => null,
            'phone_number' => $fallbackPhone,
            'email' => null,
            'domain_url' => null,
            'is_callalink_user' => false,
        ];
    }
}