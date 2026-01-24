<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserTrial;
use Illuminate\Support\Facades\DB;
use DomainException;

class TrialService
{
    /**
     * Start trial (only once per user)
     */
    public function start(User $user): UserTrial
    {
        return DB::transaction(function () use ($user) {

            $trial = UserTrial::lockForUpdate()
                ->firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'started_at' => null,
                        'expires_at' => null,
                        'consumed' => false,
                    ]
                );

            // ❌ Trial already used and finished
            if ($trial->consumed) {
                throw new DomainException('TRIAL_ALREADY_CONSUMED');
            }

            // ❌ Trial already active
            if ($trial->started_at && $trial->expires_at && now()->lt($trial->expires_at)) {
                return $trial;
            }

            // ✅ Start trial
            $trial->update([
                'started_at' => now(),
                'expires_at' => now()->addDays(14),
                'consumed' => false,
            ]);

            return $trial;
        });
    }

    /**
     * Trial active right now?
     */
    public function isActive(User $user): bool
    {
        return UserTrial::where('user_id', $user->id)
            ->whereNotNull('started_at')
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * Get trial record
     */
    public function get(User $user): ?UserTrial
    {
        return UserTrial::where('user_id', $user->id)->first();
    }
}