<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class LiveSession
{
    public static function startedAt(int $eventId)
    {
        return Cache::lock("live_session.lock.$eventId", 5)->block(3, function () use ($eventId) {
            $key = "live_session.last.$eventId";
            $now = now();
            $last = (int) Cache::get($key, 0);

            if ($now->getTimestamp() <= $last) {
                $now = $now->copy()->setTimestamp($last + 1); 
            }

            Cache::put($key, $now->getTimestamp(), now()->addDays(2));

            return $now;
        });
    }
}