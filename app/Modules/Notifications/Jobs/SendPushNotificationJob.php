<?php

namespace App\Modules\Notifications\Jobs;

use App\Modules\Notifications\Channels\FcmChannel;
use App\Modules\Notifications\Models\DeviceToken;
use App\Modules\Notifications\Models\UserNotification;
use App\Modules\Notifications\Repositories\DeviceTokenRepository;
use App\Modules\Notifications\Services\DeviceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use App\Modules\Notifications\Repositories\PreferenceRepository;
use App\Modules\Notifications\Services\NotificationRegistry;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Payload is only the notification id; everything else is re-read here.
 */
class SendPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(public int $notificationId)
    {
    }

    public function backoff(): array
    {
        return [30, 120];
    }

    public function failed(\Throwable $e): void
    {
        \Log::error('notifications.push_job_failed', [
            'notification_id' => $this->notificationId,
            'error' => $e->getMessage(),
        ]);
    }

    public function handle(FcmChannel $fcm, DeviceTokenRepository $devices, DeviceService $deviceService, PreferenceRepository $preferences, NotificationRegistry $registry): void
    {
        $notification = UserNotification::find($this->notificationId);

        if ($notification === null) {
            return; // deleted or user removed
        }

        if ($notification->expires_at && $notification->expires_at->isPast()) {
            return;
        }

        $retry = false;

        if (! $this->pushStillWanted($notification, $preferences, $registry)) {
            return; 
        }

        foreach ($devices->activeForUser($notification->user_id, [DeviceToken::ANDROID, DeviceToken::IOS]) as $device) {

            $sentKey = "push_sent:{$notification->id}:{$device->id}";

            if (Cache::has($sentKey)) {
                continue;
            }

            $result = $fcm->send($notification, $device);

            if ($result === FcmChannel::SENT) {
                Cache::put($sentKey, true, now()->addDay());
            } elseif ($result === FcmChannel::INVALID_TOKEN) {
                $deviceService->revokeInvalid($device);
            } elseif ($result === FcmChannel::RETRY) {
                $retry = true;
            }
        }

        if ($retry) {
            throw new RuntimeException('FCM temporary failure, retrying.');
        }
    }

    private function pushStillWanted(UserNotification $notification, PreferenceRepository $preferences, NotificationRegistry $registry): bool
    {
        if ((bool) (config('notifications.types')[$notification->type]['mandatory'] ?? false)) {
            return true; // mandatory types ignore the category switch
        }

        return $preferences->isPushEnabled(
            $notification->user_id,
            (string) $notification->category,
            $registry->categoryDefaultEnabled((string) $notification->category)
        );
    }
}