<?php

namespace App\Modules\Notifications\Services;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Trial and subscription events -> notifications. Never throws: a notification
 * problem must not break the webhook or the trial API.
 *
 * Dedupe keys decide how often a user hears about something:
 *  - trial_started is one per user, so the web hook, the trial API and a retry never double it
 *  - trial ending / ended / expiring include the end time, so a repeated run sends nothing new
 *  - webhook events (renewed, billing issue, ...) use the RevenueCat event id
 */
class SubscriptionNotifier
{
    private const TYPE = 'subscription.status';

    public function __construct(private NotificationService $notifications)
    {
    }

    public function trialStarted(int $userId, ?CarbonInterface $endsAt): bool
    {
        return $this->send($userId, 'trial_started', "subscription.status:{$userId}:trial_started", $endsAt);
    }

    /** $variant: trial_ending (own trial), trial_ending_auto (renews unless cancelled), trial_ending_cancelled */
    public function trialEnding(int $userId, string $variant, CarbonInterface $endsAt, int $daysBefore): bool
    {
        return $this->send(
            $userId,
            $variant,
            "subscription.status:{$userId}:trial_ending:{$endsAt->timestamp}:{$daysBefore}",
            $endsAt
        );
    }

    public function trialEnded(int $userId, CarbonInterface $endedAt): bool
    {
        return $this->send($userId, 'trial_ended', "subscription.status:{$userId}:trial_ended:{$endedAt->timestamp}", $endedAt);
    }

    public function subscriptionExpiring(int $userId, CarbonInterface $endsAt, int $daysBefore): bool
    {
        return $this->send(
            $userId,
            'subscription_expiring',
            "subscription.status:{$userId}:subscription_expiring:{$endsAt->timestamp}:{$daysBefore}",
            $endsAt
        );
    }

    /** renewed, subscription_started, subscription_ended, billing_issue */
    public function fromWebhook(int $userId, string $variant, string $eventId, ?CarbonInterface $at): bool
    {
        return $this->send($userId, $variant, "subscription.status:{$userId}:{$variant}:{$eventId}", $at);
    }

    private function send(int $userId, string $variant, string $dedupeKey, ?CarbonInterface $at = null): bool
    {
        try {
            $user = User::find($userId);

            if ($user === null) {
                return false;
            }

            $row = $this->notifications->notify(
                $userId,
                self::TYPE,
                $variant,
                $dedupeKey,
                ['time' => $at ? $at->copy()->setTimezone($this->timezone($user))->format('d M, h:i A') : ''],
                ['audience' => 'self']
            );

            return $row !== null;
        } catch (Throwable $e) {
            Log::error('notifications.subscription.failed', [
                'user_id' => $userId,
                'variant' => $variant,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function timezone(User $user): string
    {
        $key = $user->timezone ?? null;

        return ($key !== null && isset(User::TIME_ZONE_ARRAY[$key]))
            ? User::TIME_ZONE_ARRAY[$key]
            : config('app.timezone');
    }
}