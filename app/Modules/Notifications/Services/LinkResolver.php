<?php

namespace App\Modules\Notifications\Services;

use App\Models\EventSchedule;
use App\Models\UserSubscription;
use Illuminate\Support\Facades\Log;

/**
 * Where a tap on a notification opens (the same URL works in web and in the WebViews).
 */
class LinkResolver
{
    private const APPLE_SUBSCRIPTIONS = 'https://apps.apple.com/account/subscriptions';
    private const PLAY_SUBSCRIPTIONS = 'https://play.google.com/store/account/subscriptions';

    public function resolve(string $type, string $variant, array $context = [], ?int $userId = null): ?string
    {
        $schedule = isset($context['event_schedule_id'])
            ? EventSchedule::find($context['event_schedule_id'])
            : null;

        switch ($type) {
            case 'booking.created':
                if ($schedule === null) {
                    return route('scheduled-events.index');
                }

                return $variant === 'booker'
                    ? getSlotConfirmPageUrl($schedule)
                    : route('scheduled-events.show', $schedule);

            case 'booking.cancelled':
                return route('scheduled-events.index');
            case 'booking.changed':
            case 'booking.reminder':
                if ($schedule === null) {
                    return route('scheduled-events.index');
                }

                return ($context['audience'] ?? 'owner') === 'booker'
                    ? getSlotConfirmPageUrl($schedule)
                    : route('scheduled-events.show', $schedule);

            case 'phone_schedule.changed':
            case 'location.started':
            case 'location.stopped':
            case 'location.auto_stopped':
            case 'location.reminder':
                return route('scheduled-events.index');

            case 'loyalty.result':
                $billId = $context['bill_id'] ?? null;
                return $billId !== null
                    ? url('/bills/'.(int) $billId)
                    : route('loyalty.dashboard');

            case 'subscription.status':
                return $this->subscriptionLink($userId);

            case 'account.security':
                return route('profile.setting');
        }

        return null;
    }

    
    /**
     * The store that bills the user manages the subscription: Google Play or the App Store.
     *
     * Returns null when the user has no store subscription (for example a CallALink-side trial)
     * or the store is not one we know. A null link just opens the app on a push tap, and the
     * app shows its paywall by itself when the user has no access. We never guess a store.
     */
    private function subscriptionLink(?int $userId): ?string
    {
        if ($userId === null) {
            return null;
        }

        $store = strtoupper(trim((string) UserSubscription::where('user_id', $userId)->value('store')));

        switch ($store) {
            case 'PLAY_STORE':
                return self::PLAY_SUBSCRIPTIONS
                    .'?package='.config('notifications.android_package', 'com.thelinkphone.app');

            case 'APP_STORE':
            case 'MAC_APP_STORE':
                return self::APPLE_SUBSCRIPTIONS;

            case '':
                return null; // no store subscription: normal for trial-only users

            default:
                Log::warning('notifications.link.unknown_store', ['user_id' => $userId, 'store' => $store]);

                return null;
        }
    }
}