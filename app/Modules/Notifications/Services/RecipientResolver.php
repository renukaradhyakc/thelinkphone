<?php

namespace App\Modules\Notifications\Services;

use App\Models\User;

class RecipientResolver
{
    /**
     * Rule from the contract: zero or several matches means nobody is notified.
     */
    public function uniqueUserByPhone(string $phoneNumber): ?User
    {
        $matches = User::where('phone_number', $phoneNumber)->limit(2)->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    public function find(int $userId): ?User
    {
        return User::find($userId);
    }
}