<?php

namespace App\Modules\Notifications\Services;

use App\Models\EventSchedule;

/**
 * Where a tap on a notification opens (the same URL works in web and in the WebViews).
 */
class LinkResolver
{
    public function resolve(string $type, string $variant, array $context = []): ?string
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
                return route('loyalty.dashboard');

            case 'subscription.status':
            case 'account.security':
                return route('profile.setting');
        }

        return null;
    }
}