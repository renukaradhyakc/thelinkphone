<?php

namespace App\Modules\Notifications\Repositories;

use App\Modules\Notifications\Models\NotificationOutbox;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Scheduled sends (reminders and similar). notify_at is UTC (the model handles it).
 */
class OutboxRepository
{
    /**
     * Schedule a send, or move an existing one.
     *
     * - no row yet: create it as pending
     * - pending or cancelled row: set it back to pending with the new time and context
     *   (this is how an edit pushes a pending send later and how a re-booked slot is revived)
     * - sent or failed row: only rescheduled when the new time is in the future and different
     * - claimed row (in flight): left alone
     */
    public function schedule(
        int $userId,
        ?int $bookingId,
        string $type,
        string $reason,
        CarbonInterface $notifyAt,
        string $dedupeKey,
        array $context = []
    ): NotificationOutbox {
        $row = NotificationOutbox::where('dedupe_key', $dedupeKey)->first();

        if ($row === null) {
            try {
                return NotificationOutbox::create([
                    'user_id' => $userId,
                    'event_schedule_id' => $bookingId,
                    'type' => $type,
                    'reason' => $reason,
                    'notify_at' => $notifyAt,
                    'dedupe_key' => $dedupeKey,
                    'status' => NotificationOutbox::PENDING,
                    'attempts' => 0,
                    'context' => $context,
                ]);
            } catch (QueryException $exception) {
                if (! $this->isDuplicateKey($exception)) {
                    throw $exception;
                }

                $row = NotificationOutbox::where('dedupe_key', $dedupeKey)->firstOrFail();
            }
        }

        if ($row->status === NotificationOutbox::CLAIMED) {
            return $row;
        }

        $revive = in_array($row->status, [NotificationOutbox::PENDING, NotificationOutbox::CANCELLED], true)
            || (
                in_array($row->status, [NotificationOutbox::SENT, NotificationOutbox::FAILED], true)
                && $notifyAt->greaterThan(now())
                && ! $notifyAt->equalTo($row->notify_at)
            );

        if ($revive) {
            $row->fill([
                'user_id' => $userId,
                'event_schedule_id' => $bookingId,
                'notify_at' => $notifyAt,
                'status' => NotificationOutbox::PENDING,
                'attempts' => 0,
                'claimed_at' => null,
                'context' => $context,
            ])->save();
        }

        return $row;
    }

    /**
     * Claim due rows one by one with a conditional update, so two overlapping
     * cron runs can never claim the same row.
     */
    public function claimDue(int $limit = 50): Collection
    {
        $ids = NotificationOutbox::due(now())
            ->orderBy('notify_at')
            ->limit($limit)
            ->pluck('id');

        $claimed = [];

        foreach ($ids as $id) {
            $updated = NotificationOutbox::where('id', $id)
                ->where('status', NotificationOutbox::PENDING)
                ->update([
                    'status' => NotificationOutbox::CLAIMED,
                    'claimed_at' => now(),
                    'attempts' => DB::raw('attempts + 1'),
                ]);

            if ($updated === 1) {
                $claimed[] = $id;
            }
        }

        return NotificationOutbox::whereIn('id', $claimed)->get();
    }

    public function markSent(NotificationOutbox $row): void
    {
        $row->update(['status' => NotificationOutbox::SENT]);
    }

    public function markFailed(NotificationOutbox $row): void
    {
        $row->update(['status' => NotificationOutbox::FAILED]);
    }

    public function markCancelled(NotificationOutbox $row): void
    {
        $row->update(['status' => NotificationOutbox::CANCELLED]);
    }

    /**
     * Cancel everything still pending for a booking (cancelled, moved or deleted).
     */
    public function cancelPendingForBooking(int $bookingId): int
    {
        return NotificationOutbox::where('event_schedule_id', $bookingId)
            ->where('status', NotificationOutbox::PENDING)
            ->update(['status' => NotificationOutbox::CANCELLED]);
    }

    /**
     * A claimed row older than $minutes belongs to a crashed run. Put it back to
     * pending, or mark it failed once it has been tried $maxAttempts times.
     */
    public function releaseStaleClaims(int $minutes = 5, int $maxAttempts = 3): int
    {
        $cutoff = now()->subMinutes($minutes); // claimed_at is written with now(), same timezone

        $failed = NotificationOutbox::where('status', NotificationOutbox::CLAIMED)
            ->where('claimed_at', '<', $cutoff)
            ->where('attempts', '>=', $maxAttempts)
            ->update(['status' => NotificationOutbox::FAILED]);

        $reset = NotificationOutbox::where('status', NotificationOutbox::CLAIMED)
            ->where('claimed_at', '<', $cutoff)
            ->where('attempts', '<', $maxAttempts)
            ->update(['status' => NotificationOutbox::PENDING, 'claimed_at' => null]);

        return $failed + $reset;
    }

    /**
     * Delete finished rows (sent, cancelled, failed) last touched before $before.
     */
    public function prune(CarbonInterface $before): int
    {
        return NotificationOutbox::whereIn('status', [
            NotificationOutbox::SENT,
            NotificationOutbox::CANCELLED,
            NotificationOutbox::FAILED,
        ])->where('updated_at', '<', $before)->delete();
    }

    private function isDuplicateKey(QueryException $exception): bool
    {
        return (int) ($exception->errorInfo[1] ?? 0) === 1062;
    }
}