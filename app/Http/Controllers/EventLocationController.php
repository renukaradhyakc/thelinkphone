<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\UserSchedule;
use App\Services\TimeParser;

class EventLocationController extends AppBaseController
{

    public function startSharing(Request $request, Event $event): JsonResponse
    {
        $this->authorizeEvent($event);

        $location = $event->location;
        // \Log::info('[CallaLink] startSharing called', ['event_id' => $event->id, 'location_type' => optional($location)->location_type, 'event_status' => $event->status]);

        if (! $location || (int) $location->location_type !== Location::LIVE) {
            // \Log::warning('[CallaLink] startSharing rejected — not set to Live', ['event_id' => $event->id]);
            return $this->sendError('Set this event\'s location to Live and save before starting sharing.', 422);
        }

        if (! (bool) $event->status) {
            // \Log::warning('[CallaLink] startSharing rejected — event not active', ['event_id' => $event->id]);
            return $this->sendError('This event is not active.', 422);
        }

        $conflict = Event::where('user_id', getLogInUserId())
            ->where('id', '!=', $event->id)
            ->whereHas('location', fn ($q) => $q->where('location_type', Location::LIVE)->where('is_live_sharing_active', true))
            ->first();

        if ($conflict) {
            // \Log::warning('[CallaLink] startSharing rejected — another event already live', [
            //     'event_id' => $event->id, 'conflicting_event_id' => $conflict->id,
            // ]);
            return $this->sendError("You're already sharing live location for \"{$conflict->name}\". Stop that one first.", 409);
        }

        $location->update([
            'is_live_sharing_active' => true,
            'live_started_at' => now(),
        ]);
        // \Log::info('[CallaLink] startSharing succeeded', ['event_id' => $event->id]);
        return $this->sendResponse(['event_id' => $event->id], 'Live sharing started.');
    }

    public function stopSharing(Request $request, Event $event): JsonResponse
    {
        $this->authorizeEvent($event);

        $location = $event->location;

        if ($location) {
            $location->update([
                'is_live_sharing_active' => false,
                'live_started_at' => null,
            ]);
        }

        return $this->sendSuccess('Live sharing stopped.');
    }

    public function updateLive(Request $request, Event $event): JsonResponse
    {
        $this->authorizeEvent($event);

        $location = $event->location;

        if (! $this->isSessionValid($event, $location)) {
            // \Log::warning('[CallaLink] updateLive: session invalid', ['event_id' => $event->id]);
            return $this->sendError('Live sharing is not active for this event.', 409);
        }

        if ($this->isLiveExpired($event, $location)) {
            // \Log::warning('[CallaLink] updateLive: expired', ['event_id' => $event->id]);
            $this->stopSharingInternal($location);
            return $this->sendError('Live sharing window has expired.', 409);
        }

        $data = $this->validatedCoords($request);

        $movedMeters = $this->distanceInMeters(
            (float) $location->latitude, (float) $location->longitude,
            (float) $data['latitude'], (float) $data['longitude']
        );

        $hasMovedMeaningfully = is_null($location->latitude) || $movedMeters >= 150;

        // \Log::info('[CallaLink] updateLive writing', [
        //     'event_id' => $event->id,
        //     'data' => $data,
        //     'movedMeters' => round($movedMeters, 1),
        //     'hasMovedMeaningfully' => $hasMovedMeaningfully,
        //     'was_dirty' => $location->isDirty()
        // ]);

        if ($hasMovedMeaningfully) {
            $data['address'] = null;
            $location->update($data); 
        } else {
            $location->touch(); 
        }

        return $this->sendResponse(['updated_at' => $location->fresh()->updated_at], 'Location updated.');
    }

    public function activeSession(): JsonResponse
    {
        $userId = getLogInUserId();
        // \Log::info('[CallaLink] activeSession check', ['user_id' => $userId]);

        $event = Event::where('user_id', $userId)
            ->where('status', Event::ACTIVE)
            ->whereHas('location', function ($q) {
                $q->where('location_type', Location::LIVE)
                  ->where('is_live_sharing_active', true);
            })
            ->with('location')
            ->first();

        if (! $event) {
            // \Log::info('[CallaLink] activeSession: no matching event found');
            return $this->sendResponse(['active' => false], 'No active live session.');
        }

        if ($this->isLiveExpired($event, $event->location)) {
            // \Log::info('[CallaLink] activeSession: expired, stopping', ['event_id' => $event->id]);
            $this->stopSharingInternal($event->location);
            return $this->sendResponse(['active' => false], 'Live session expired.');
        }

        // \Log::info('[CallaLink] activeSession: active', ['event_id' => $event->id]);
        return $this->sendResponse([
            'active' => true,
            'event_id' => $event->id,
            'updated_at' => optional($event->location)->updated_at,
        ], 'Active live session found.');
    }

    
    private function isSessionValid(Event $event, ?Location $location): bool
    {
        return $location
            && (int) $location->location_type === Location::LIVE
            && (bool) $location->is_live_sharing_active
            && (bool) $event->status;
    }
    
    // private function liveExpiresAt(Event $event, Location $location): ?Carbon
    // {
    //     if (! $location->live_started_at) {
    //         return null;
    //     }

    //     $cap = $location->live_started_at->copy()->addHours(24);

    //     if ((int) $event->date_range === 0 && ! empty($event->schedule_to)) {
    //         $scheduleEndDate = Carbon::parse(trim($event->schedule_to), 'Asia/Kolkata');
    //         $dayCode = $scheduleEndDate->dayOfWeekIso;

    //          $latestToTime = UserSchedule::where('event_id', $event->id)
    //             ->where('day_of_week', $dayCode)
    //             ->pluck('to_time')
    //             ->map(function ($toTime) use ($scheduleEndDate) {
    //                 $parsed = app(TimeParser::class)->parseFlexibleTime($toTime, $scheduleEndDate);
    //                 return $parsed;
    //             })
    //             ->filter()
    //             ->max();

    //         $scheduleEnd = $latestToTime ? $scheduleEndDate->copy()->setTime($latestToTime->hour, $latestToTime->minute, 0) : $scheduleEndDate->endOfDay();

    //         $scheduleEnd->setTimezone('UTC'); 

    //         \Log::info('[CallaLink] liveExpiresAt (date_range=0)', [
    //             'event_id' => $event->id,
    //             'dayCode' => $dayCode,
    //             'cap' => $cap->toDateTimeString(),
    //             'scheduleEnd' => $scheduleEnd->toDateTimeString(),
    //         ]);

    //         return $cap->lessThan($scheduleEnd) ? $cap : $scheduleEnd;
    //     }

    //     \Log::info('[CallaLink] liveExpiresAt (24h cap)', ['event_id' => $event->id, 'cap' => $cap->toDateTimeString()]);
    //     return $cap;
    // }

    private function isLiveExpired(Event $event, Location $location): bool
    {
        $expiresAt = $location->liveExpiresAt($event);
        $expired = $expiresAt !== null && now()->greaterThanOrEqualTo($expiresAt);
        // \Log::info('[CallaLink] isLiveExpired check', ['event_id' => $event->id, 'now' => now()->toDateTimeString(), 'expiresAt' => optional($expiresAt)->toDateTimeString(), 'expired' => $expired]);

        return $expired;
    }

    private function stopSharingInternal(Location $location): void
    {
        $location->update([
            'is_live_sharing_active' => false,
            'live_started_at' => null,
        ]);
    }

    private function authorizeEvent(Event $event): void
    {
        if ((int) $event->user_id !== (int) getLogInUserId()) {
            abort(403);
        }
    }

    private function validatedCoords(Request $request): array
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy' => 'nullable|numeric|min:0',
            'address' => 'nullable|string|max:1000',
        ]);

        return [
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'accuracy' => $validated['accuracy'] ?? null,
            'address' => $validated['address'] ?? null,
        ];
    }

    public function resolveAddress(Event $event): JsonResponse
    {
        $this->authorizeEventViewer($event);

        $location = $event->location;

        if (! $location) {
            return $this->sendResponse(['address' => null, 'status' => 'no_location'], 'No location set.');
        }

        if ((int) $location->location_type === Location::FIXED) {
            return $this->sendResponse(['address' => $location->address, 'status' => 'fixed'], 'Resolved.');
        }

        if ($location->latitude === null || $location->longitude === null) {
            return $this->sendResponse(['address' => null, 'status' => 'not_started'], 'Not started yet.');
        }

        if (! $location->is_live_sharing_active) {
            return $this->sendResponse(['address' => $location->address, 'status' => 'stopped'], 'Sharing stopped.');
        }

        if ($location->address) {
            return $this->sendResponse(['address' => $location->address, 'status' => 'active'], 'Resolved.');
        }

        $lat = $location->latitude;
        $lon = $location->longitude;

        $cacheKey = "location_address_{$location->id}_{$lat}_{$lon}";

        $address = \Cache::remember($cacheKey, now()->addMinutes(15), function () use ($lat, $lon) {
            $response = \Http::withHeaders(['User-Agent' => 'CallaLink/1.0 (support@callalink.com)'])
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'format' => 'json',
                    'lat' => $lat,
                    'lon' => $lon,
                ]);

            return $response->successful() ? $response->json('display_name') : null;
        });

        if ($address && $location->address !== $address) {
            Location::where('id', $location->id)
                ->where('latitude', $lat)
                ->where('longitude', $lon)
                ->update(['address' => $address]);
        }

        return $this->sendResponse(['address' => $address, 'status' => 'active'], 'Resolved.');
    }

    private function distanceInMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // meters
        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);
        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lonDelta / 2) ** 2;
        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function authorizeEventViewer(Event $event): void
    {
        $userId = getLogInUserId();

        if ((int) $event->user_id === (int) $userId) {
            return; 
        }

        $myPhone = getLoginUser()->phone_number ?? null;

        $hasBooking = \App\Models\EventSchedule::where('event_id', $event->id)
            ->where('phone_call', $myPhone)
            ->where('status', '!=', \App\Models\EventSchedule::CANCELLED)
            ->exists();

        if (! $hasBooking) {
            abort(403);
        }
    }

}