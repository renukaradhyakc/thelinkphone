<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Support\Facades\Cache;

class AccessResolver
{
    public function hasPremiumAccess(User $user): bool
    {
        return Cache::remember(
            "user.{$user->id}.premium_access",
            now()->addMinutes(5),
            function () use ($user) {
                if (app(TrialService::class)->isActive($user)) {
                    return true;
                }

                return UserSubscription::query()
                    ->where('user_id', $user->id)
                    ->where('active', true)
                    ->where('expires_at', '>', now())
                    ->exists();
            }
        );
    }

    public function clearCache(User $user): void
    {
        Cache::forget("user.{$user->id}.premium_access");
    }
}
