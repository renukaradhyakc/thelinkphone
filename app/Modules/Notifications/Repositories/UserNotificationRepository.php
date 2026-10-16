<?php

namespace App\Modules\Notifications\Repositories;

use App\Modules\Notifications\Models\UserNotification;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

/**
 * The inbox. Every query is scoped to one user.
 */
class UserNotificationRepository
{
    /**
     * Create the inbox row. Returns null when the same (user, dedupe_key)
     * already exists, which means "already notified".
     */
    public function createUnique(array $attributes): ?UserNotification
    {
        try {
            return UserNotification::create($attributes);
        } catch (QueryException $exception) {
            if ($this->isDuplicateKey($exception)) {
                return null;
            }

            throw $exception;
        }
    }

    public function findForUser(int $userId, string $uuid): ?UserNotification
    {
        return UserNotification::forUser($userId)->where('uuid', $uuid)->first();
    }

    /**
     * Newest first. Returns up to $limit + 1 rows so the caller can tell whether
     * there is a next page. $beforeId is the internal id of the last row seen.
     */
    public function page(int $userId, ?int $beforeId, int $limit, bool $unreadOnly = false): Collection
    {
        $query = UserNotification::forUser($userId)
            ->notExpired()
            ->orderByDesc('id')
            ->limit($limit + 1);

        if ($beforeId !== null) {
            $query->where('id', '<', $beforeId);
        }

        if ($unreadOnly) {
            $query->unread();
        }

        return $query->get();
    }

    public function unreadCount(int $userId): int
    {
        return UserNotification::forUser($userId)->notExpired()->unread()->count();
    }

    public function markRead(int $userId, string $uuid): bool
    {
        return UserNotification::forUser($userId)
            ->where('uuid', $uuid)
            ->whereNull('read_at')
            ->update(['read_at' => now()]) > 0;
    }

    public function markAllRead(int $userId): int
    {
        return UserNotification::forUser($userId)->unread()->update(['read_at' => now()]);
    }

    public function delete(int $userId, string $uuid): bool
    {
        return UserNotification::forUser($userId)->where('uuid', $uuid)->delete() > 0;
    }

    /**
     * Remove rows of one type created before $before (inbox_days retention).
     */
    public function pruneOlderThan(string $type, CarbonInterface $before): int
    {
        return UserNotification::where('type', $type)->where('created_at', '<', $before)->delete();
    }

    /**
     * Remove rows whose expires_at passed before $before.
     */
    public function pruneExpiredBefore(CarbonInterface $before): int
    {
        return UserNotification::whereNotNull('expires_at')->where('expires_at', '<', $before)->delete();
    }

    private function isDuplicateKey(QueryException $exception): bool
    {
        return (int) ($exception->errorInfo[1] ?? 0) === 1062;
    }
}