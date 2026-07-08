<?php

namespace App\Services;

use Carbon\Carbon;

class TimeParser
{
    /**
     * Parses time strings stored throughout the application.
     *
     * Supports both 12-hour and 24-hour formats because
     * legacy data in user_schedules is not stored consistently.
     *
     * Keeping this logic in a single service ensures all schedule
     * evaluations use identical parsing behaviour.
     */
    public function parseFlexibleTime(string $time, Carbon $now): ?Carbon
    {
        $time = strtoupper(trim($time));
        $time = preg_replace('/\s+/', ' ', $time);

        $formats = [
            'h:i A',
            'g:i A',
            'h:iA',
            'g:iA',
            'H:i',
            'G:i',
            'h:i',
            'g:i',
        ];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $time, 'Asia/Kolkata')
                    ->setDate($now->year, $now->month, $now->day);
            } catch (\Throwable $e) {
                // try next format
            }
        }

        return null;
    }
}