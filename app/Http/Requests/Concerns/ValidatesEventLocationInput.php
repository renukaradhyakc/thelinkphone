<?php

namespace App\Http\Requests\Concerns;

use App\Models\Event;
use App\Models\Location;
use Illuminate\Validation\Rule;

trait ValidatesEventLocationInput
{
    protected function eventLocationRules(): array
    {
        return [
            'event_location' => ['required', Rule::in(array_keys(Event::LOCATION_ARRAY))],
            'location_meta' => ['nullable', 'string', 'max:5000'],
            'new_location_type' => ['nullable', Rule::in([Location::FIXED, Location::LIVE])],
            'new_location_latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:new_location_longitude'],
            'new_location_longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:new_location_latitude'],
            'new_location_accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'new_location_address' => ['nullable', 'string', 'max:1000'],
            'new_location_is_live_sharing_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $requestedType = (int) ($this->input('new_location_type') ?? \App\Models\Location::FIXED);
            $requestedSharingActive = $requestedType === \App\Models\Location::LIVE
                && $this->boolean('new_location_is_live_sharing_active');

            if (! $requestedSharingActive) {
                return;
            }

            $event = $this->route('event'); // null on create, real Event on update
            $wasAlreadyActiveOnThisEvent = $event
                ? (bool) optional($event->location)->is_live_sharing_active
                : false;

            \Log::info('[CallaLink] withValidator conflict check', [ // ADD — temporary
                'event_id' => optional($event)->id,
                'requestedType' => $requestedType,
                'requestedSharingActive' => $requestedSharingActive,
                'wasAlreadyActiveOnThisEvent' => $wasAlreadyActiveOnThisEvent,
            ]);

            if (! $requestedSharingActive) {
                \Log::info('[CallaLink] withValidator — not requesting sharing, skipping conflict check'); // ADD
                return;
            }

            if ($wasAlreadyActiveOnThisEvent) {
                \Log::info('[CallaLink] withValidator — already active on this event, skipping'); // ADD
                return; // re-saving an already-active session is fine
            }

            $conflict = \App\Models\Event::where('user_id', getLogInUserId())
                ->when($event, fn ($q) => $q->where('id', '!=', $event->id))
                ->whereHas('location', fn ($q) => $q->where('location_type', \App\Models\Location::LIVE)->where('is_live_sharing_active', true))
                ->first();

            \Log::info('[CallaLink] withValidator — conflict query result', ['conflict_event_id' => optional($conflict)->id]); // ADD

            if ($conflict) {
                $validator->errors()->add(
                    'new_location_is_live_sharing_active',
                    "You're already sharing live location for \"{$conflict->name}\". Stop that one first."
                );
            }
        });
    }
}
