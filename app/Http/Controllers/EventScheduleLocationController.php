<?php

namespace App\Http\Controllers;

use App\Http\Requests\Concerns\HandlesLiveLocationSharing;
use App\Models\EventSchedule;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventScheduleLocationController extends AppBaseController
{
    use HandlesLiveLocationSharing;

    public function startBookerSharing(Request $request, EventSchedule $eventSchedule): JsonResponse
    {
        $this->authorizeBooker($eventSchedule);

        $location = $eventSchedule->location;

        if (! $location || (int) $location->location_type !== Location::LIVE) {
            return $this->sendError('Set this booking\'s location to Live and save before starting sharing.', 422);
        }

        if ($eventSchedule->status === EventSchedule::CANCELLED) {
            return $this->sendError('This booking is not active.', 422);
        }

        $myPhone = getLoginUser()->phone_number ?? null;

        $activeCount = EventSchedule::where('phone_call', $myPhone)
            ->where('id', '!=', $eventSchedule->id)
            ->where('status', '!=', EventSchedule::CANCELLED)
            ->whereHas('location', fn ($q) => $q->where('location_type', Location::LIVE)->where('is_live_sharing_active', true))
            ->count();

        if ($activeCount >= 3) {
            return $this->sendError('You can only share live location for up to 3 bookings at a time. Stop one first.', 409);
        }

        $location->update([
            'is_live_sharing_active' => true,
            'live_started_at' => now(),
        ]);

        return $this->sendResponse(['event_schedule_id' => $eventSchedule->id], 'Live sharing started.');
    }

    public function stopBookerSharing(Request $request, EventSchedule $eventSchedule): JsonResponse
    {
        $this->authorizeBooker($eventSchedule);

        if ($location = $eventSchedule->location) {
            $this->stopSharingInternal($location);
        }

        return $this->sendSuccess('Live sharing stopped.');
    }

    public function updateBookerLive(Request $request): JsonResponse
    {
        $myPhone = getLoginUser()->phone_number ?? null;

        if (! $myPhone) {
            return $this->sendError('No phone number on file for this account.', 422);
        }

        $data = $this->validatedCoords($request);

        $activeSchedules = EventSchedule::where('phone_call', $myPhone)
            ->where('status', '!=', EventSchedule::CANCELLED)
            ->whereHas('location', fn ($q) => $q->where('location_type', Location::LIVE)->where('is_live_sharing_active', true))
            ->with('location')
            ->get();

        if ($activeSchedules->isEmpty()) {
            return $this->sendError('Live sharing is not active for any of your bookings.', 409);
        }

        $updatedAny = false;

        foreach ($activeSchedules as $eventSchedule) {
            $location = $eventSchedule->location;

            if ($this->isLiveExpired($eventSchedule, $location)) {
                $this->stopSharingInternal($location);
                continue;
            }

            $movedMeters = $this->distanceInMeters(
                (float) $location->latitude, (float) $location->longitude,
                (float) $data['latitude'], (float) $data['longitude']
            );

            $hasMovedMeaningfully = is_null($location->latitude) || $movedMeters >= 150;

            if ($hasMovedMeaningfully) {
                $writeData = $data;
                $writeData['address'] = null;   
                $location->update($writeData);
            } else {
                $location->touch();
            }

            $updatedAny = true;
        }

         if (! $updatedAny) {
            return $this->sendError('All your live sessions have expired.', 409);
        }

        return $this->sendResponse(['updated_count' => $activeSchedules->count()], 'Location updated.');
    }

    public function activeBookerSession(): JsonResponse
    {
        $myPhone = getLoginUser()->phone_number ?? null;

        $activeSchedules = EventSchedule::where('phone_call', $myPhone)
            ->where('status', '!=', EventSchedule::CANCELLED)
            ->whereHas('location', fn ($q) => $q->where('location_type', Location::LIVE)->where('is_live_sharing_active', true))
            ->with('location')
            ->get();

        $activeIds = [];

        foreach ($activeSchedules as $eventSchedule) {

            if ($this->isLiveExpired($eventSchedule, $eventSchedule->location)) {
                $this->stopSharingInternal($eventSchedule->location);
                continue;
            }

            $activeIds[] = $eventSchedule->id;
        }

         return $this->sendResponse([
            'active' => count($activeIds) > 0,
            'event_schedule_ids' => $activeIds,
        ], count($activeIds) > 0 ? 'Active live sessions found.' : 'No active live session.');
    }

    public function resolveBookerAddress(EventSchedule $eventSchedule): JsonResponse
    {
        $this->authorizeOwnerViewer($eventSchedule);

        $location = $eventSchedule->location;

        if (! $location) {
            return $this->sendResponse(['address' => null, 'status' => 'no_location'], 'No location set.');
        }

        return $this->sendResponse($location->resolveAddress(), 'Resolved.');
    }

    private function authorizeBooker(EventSchedule $eventSchedule): void
    {
        $myPhone = getLoginUser()->phone_number ?? null;

        if (! $myPhone || $eventSchedule->phone_call !== $myPhone) {
            abort(403);
        }
    }

    private function authorizeOwnerViewer(EventSchedule $eventSchedule): void
    {
        if ((int) $eventSchedule->user_id !== (int) getLogInUserId()) {
            abort(403);
        }
    }
}