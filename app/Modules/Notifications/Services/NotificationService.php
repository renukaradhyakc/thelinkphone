<?php

namespace App\Modules\Notifications\Services;

use App\Models\User;
use App\Modules\Notifications\Jobs\SendPushNotificationJob;
use App\Modules\Notifications\Models\UserNotification;
use App\Modules\Notifications\Repositories\PreferenceRepository;
use App\Modules\Notifications\Repositories\UserNotificationRepository;

class NotificationService
{
    public function __construct(
        private NotificationRegistry $registry,
        private TextBuilder $text,
        private LinkResolver $links,
        private UserNotificationRepository $inbox,
        private PreferenceRepository $preferences
    ) {
    }

    /**
     * Create the inbox row and queue the push.
     * Returns null when disabled, unknown, or already sent (same dedupe key).
     *
     * @param array $params  text placeholders (:event, :time, :name, :count)
     * @param array $context link data (event_schedule_id, audience) stored in `data` for the tap
     */
    public function notify(
        int $userId,
        string $type,
        string $variant,
        string $dedupeKey,
        array $params = [],
        array $context = []
    ): ?UserNotification {
        if (! $this->registry->has($type, $variant) || ! $this->registry->isEnabled($type)) {
            return null;
        }

        $user = User::find($userId);

        if ($user === null) {
            return null; // account deleted: nothing to notify
        }

        $channels = $this->registry->channels($type, $variant);
        $category = $this->registry->category($type);
        $text = $this->text->build($type, $variant, $params, $user->language ?? null);

        $row = $this->inbox->createUnique([
            'user_id' => $userId,
            'type' => $type,
            'reason' => $variant,
            'category' => $category,
            'title' => $text['title'],
            'body' => $text['body'],
            'data' => $context ?: null,
            'deep_link' => $this->links->resolve($type, $variant, $context),
            'dedupe_key' => $dedupeKey,
            'expires_at' => $this->expiry($type),
        ]);

        if ($row === null) {
            return null; // duplicate
        }

        if (in_array('push', $channels, true) && $this->pushAllowed($userId, $type, $variant, $category)) {
            $this->dispatchPush($row, $type);
        }

        return $row;
    }

    private function pushAllowed(int $userId, string $type, string $variant, string $category): bool
    {
        if ($this->registry->isMandatory($type, $variant)) {
            return true;
        }

        return $this->preferences->isPushEnabled(
            $userId,
            $category,
            $this->registry->categoryDefaultEnabled($category)
        );
    }

    private function dispatchPush(UserNotification $row, string $type): void
    {
        if ($this->registry->priority($type) === 'high' && ! app()->runningInConsole()) {
            SendPushNotificationJob::dispatchAfterResponse($row->id);

            return;
        }

        SendPushNotificationJob::dispatch($row->id)->onQueue('notifications');
    }

    private function expiry(string $type)
    {
        $days = $this->registry->inboxDays($type);

        return $days > 0 ? now()->addDays($days) : null;
    }
}