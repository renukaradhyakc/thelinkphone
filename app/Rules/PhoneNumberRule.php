<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use App\Services\PhoneNumberNormalizer;

class PhoneNumberRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */

    public function __construct(protected PhoneNumberNormalizer $normalizer) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $normalized = $this->normalizer->normalize($value);

        if (strlen($normalized) !== 10) {
            $fail('The :attribute is not a valid phone number.');
        }
    }
}
