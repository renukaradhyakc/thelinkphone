<?php

namespace App\Rules;

use Closure;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidScheduleSlotRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    /**
     * Validate a single schedule slot.
     *
     * Expected input:
     * [
     *     'day_of_week' => 1,
     *     'from_time'   => '09:00 AM',
     *     'to_time'     => '05:00 PM',
     * ]
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_array($value)) {
            $fail('Invalid schedule slot.');
            return;
        }

        if (
            !isset($value['from_time']) ||
            !isset($value['to_time'])
        ) {
            return;
        }

        try {
            $from = Carbon::parse($value['from_time']);
            $to   = Carbon::parse($value['to_time']);
        } catch (\Throwable $e) {
            $fail('Invalid time format.');
            return;
        }

        if ($to->lessThanOrEqualTo($from)) {
            $fail('End time must be later than start time. Overnight schedules are not supported.');
        }
    }
}
