<?php

namespace App\Modules\Notifications\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Services\NotificationRegistry;

/**
 * Serves the web pages only. The JSON endpoints stay in NotificationController
 * and PreferenceController (same controllers as the mobile API).
 */
class NotificationPageController extends Controller
{
    /** Plain-language line shown under each category on the settings screen. */
    private const HINTS = [
        'bookings' => 'New and cancelled bookings.',
        'reminders' => 'Alerts shortly before a booking starts.',
        'event_changes' => 'When an event you booked is edited or moved.',
        'location' => 'When live location sharing starts, stops or is still on.',
        'phone_schedules' => 'When someone sets or changes the hours you can call them.',
        'loyalty' => 'Results of the bills you upload.',
        'subscription' => 'Trial and billing updates.',
        'security' => 'Password, email and phone changes on your account.',
    ];

    /** Categories that exist in the registry but are not part of v1. */
    private const HIDDEN = ['announcements'];

    public function index()
    {
        return view('notifications.index');
    }

    public function settings(NotificationRegistry $registry)
    {
        $types = collect(config('notifications.types', []));

        $categories = collect($registry->categories())
            ->except(self::HIDDEN)
            ->map(function (array $config, string $key) use ($types) {
                $inCategory = $types->where('category', $key);

                // locked = every type in the category ignores the user's toggle
                $locked = $inCategory->isNotEmpty()
                    && $inCategory->every(fn (array $type) => ! empty($type['mandatory']));

                return [
                    'label' => $config['label'] ?? ucfirst($key),
                    'hint' => self::HINTS[$key] ?? '',
                    'locked' => $locked,
                ];
            });

        return view('notifications.settings', ['categories' => $categories]);
    }
}