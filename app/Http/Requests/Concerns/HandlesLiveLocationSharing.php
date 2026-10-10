<?php

namespace App\Http\Requests\Concerns;

use App\Models\Location;
use Illuminate\Http\Request;

trait HandlesLiveLocationSharing
{
    protected function stopSharingInternal(Location $location): void
    {
        $location->update([
            'is_live_sharing_active' => false,
            'live_started_at' => null,
        ]);
    }

    protected function isLiveExpired($eventOrSchedule, Location $location): bool
    {
        $expiresAt = $location->liveExpiresAt($eventOrSchedule);

        return $expiresAt !== null && now()->greaterThanOrEqualTo($expiresAt);
    }

    protected function validatedCoords(Request $request): array
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

    protected function distanceInMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;
        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);
        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lonDelta / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}