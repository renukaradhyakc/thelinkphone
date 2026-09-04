<?php

namespace App\Utils;

use App\Models\PhoneSchedule;
use App\Services\TimeParser;
use Carbon\Carbon;

class PhoneScheduleTimeFormatter
{
    public static function todayWeekdayCode(): int
    {
        $dow = Carbon::now()->dayOfWeek; // 0=Sun..6=Sat
        return $dow === 0 ? 7 : $dow;    // schema: 1=Mon..7=Sun
    }

    public static function ranges(PhoneSchedule $phoneSchedule): array
    {
        $slots = $phoneSchedule->schedule_id
            ? optional($phoneSchedule->schedule)->userSchedules ?? collect()
            : $phoneSchedule->userSchedules ?? collect();

        $today = self::todayWeekdayCode();

        $parser = app(TimeParser::class);
        $now = Carbon::now('Asia/Kolkata');

        $todaySlots = $slots
            ->filter(fn ($s) => (int) $s->day_of_week === $today)
            ->sortBy(fn ($s) => self::toMinutes($s->from_time, $parser, $now))
            ->values();

        if ($todaySlots->isEmpty()) {
            return [];
        }

        $nowMinutes = $now->hour * 60 + $now->minute;

        $ranges = [];
        $rangeStart = $todaySlots[0];
        $rangeEnd = $todaySlots[0];
        $rangeEndMin = self::toMinutes($rangeEnd->to_time, $parser, $now);

        for ($i = 1; $i < $todaySlots->count(); $i++) {
            $next = $todaySlots[$i];
            $nextFromMin = self::toMinutes($next->from_time, $parser, $now);
            $nextToMin = self::toMinutes($next->to_time, $parser, $now);

            if ($nextFromMin <= $rangeEndMin) {
                if ($nextToMin > $rangeEndMin) {
                    $rangeEnd = $next;
                    $rangeEndMin = $nextToMin;
                }
            } else {
                if ($range = self::buildRange($rangeStart, $rangeEnd, $nowMinutes, $parser, $now)) {
                    $ranges[] = $range;
                }
                $rangeStart = $next;
                $rangeEnd = $next;
                $rangeEndMin = $nextToMin;
            }
        }
        if ($range = self::buildRange($rangeStart, $rangeEnd, $nowMinutes, $parser, $now)) {
            $ranges[] = $range;
        }

        return $ranges;
    }

    private static function buildRange($start, $end, int $nowMinutes, TimeParser $parser, Carbon $now): ?array
    {
        $fromMin = self::toMinutes($start->from_time, $parser, $now);
        $toMin = self::toMinutes($end->to_time, $parser, $now);

        if ($toMin <= $nowMinutes) {
            return null;
        }

        return [
            'from' => $start->from_time,
            'to' => $end->to_time,
            'active' => $fromMin <= $nowMinutes && $nowMinutes < $toMin,
        ];
    }

    private static function toMinutes(?string $time, TimeParser $parser, Carbon $now): int
    {
        if (! $time) return -1;
        $parsed = $parser->parseFlexibleTime($time, $now);
        return $parsed ? ($parsed->hour * 60 + $parsed->minute) : -1;
    }
}