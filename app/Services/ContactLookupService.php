<?php

namespace App\Services;

use App\Repositories\ContactLookupRepository;

class ContactLookupService
{
    public function __construct(
        protected PhoneNumberNormalizer $normalizer,
        protected ContactLookupRepository $repository,
    ) {}

    /**
     * @param  array<string>  $rawNumbers
     * @return array<string, array{is_user: bool, id?: int, first_name?: string, last_name?: string, domain_url?: ?string}>
     */
    public function lookup(array $rawNumbers): array
    {
        $validNumbers = collect($rawNumbers)
            ->map(fn ($n) => $this->normalizer->normalize((string) $n))
            ->filter(fn ($n) => strlen($n) === 10) // silently drops malformed
            ->unique()
            ->values();

        if ($validNumbers->isEmpty()) {
            return [];
        }

        $matchedUsers = $this->repository
            ->findByPhoneNumbers($validNumbers->all())
            ->keyBy('phone_number');

        return $validNumbers
            ->mapWithKeys(function (string $number) use ($matchedUsers) {
                $user = $matchedUsers->get($number);

                if (! $user) {
                    return [$number => ['is_user' => false]];
                }

                return [$number => [
                    'is_user' => true,
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'domain_url' => $user->domain_url,
                ]];
            })
            ->all();
    }
}