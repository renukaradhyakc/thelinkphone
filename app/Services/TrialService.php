<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserTrial;
use Illuminate\Support\Facades\DB;
use DomainException;

class TrialService
{
    public function start(User $user): UserTrial
    {
        return DB::transaction(function () use ($user) {
            $trial = UserTrial::lockForUpdate()->firstOrCreate(['user_id' => $user->id]);

            if ($trial->consumed) {
                throw new DomainException('TRIAL_ALREADY_CONSUMED');
            }

            $trial->update([
                'started_at' => now(),
                'expires_at' => now()->addDays(14),
                'consumed' => true,
            ]);

            return $trial;
        });
    }

    public function isActive(User $user): bool
    {
        return UserTrial::where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->exists();
    }

    public function get(User $user): ?UserTrial
    {
        return UserTrial::where('user_id', $user->id)->first();
    }
}
