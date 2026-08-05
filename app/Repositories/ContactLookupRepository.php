<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Collection;

class ContactLookupRepository
{

    public function findByPhoneNumbers(array $numbers): Collection
    {
        return User::query()
            ->whereIn('phone_number', $numbers)
            ->select(['id', 'phone_number', 'first_name', 'last_name', 'domain_url'])
            ->get();
    }
}