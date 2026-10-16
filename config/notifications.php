<?php

/*
|--------------------------------------------------------------------------
| CallALink notification registry: v1 (12 types, 9 categories)
|--------------------------------------------------------------------------
| Place at: config/notifications.php
|
| channels      inbox | push | email    ("push" = FCM/APNs and browser push)
| priority      low | normal | high      (maps to FCM / APNs priority)
| mandatory     true = ignores the user's category toggle
| link          route key, resolved to an https universal link in code
| push_ttl_min  how long the provider keeps trying; stale pushes are dropped
| inbox_days    how long the inbox row is kept before pruning
| collapse      Android tag / APNs collapse-id and thread-id; null = never collapse
| dedupe        unique key; the same key can never create a second notification
| enabled       optional; false = type is defined but never sent (waiting on a prerequisite)
| variants      key = reason (or audience). Text lives in lang/en/notifications.php
|               as "type.variant". Optional flags pick a text tweak, for example
|               no_meeting_link, android, ios_will_charge, ios_cancelled.
|
| Placeholders: {ref} = booking id (event_schedules.id). One submission creates one booking.
|
| Rules that apply to every type:
|  - Never put a phone number in any notification text or data.
|  - Write the inbox row first; a failed push never loses a notification.
|  - Re-validate the source at SEND time (booking still exists and unchanged,
|    both accounts still exist, iOS trial still set to renew), not only when
|    the notification is created.
|  - Times in the text use the recipient's time zone; stored times are UTC.
*/

return [

    'categories' => [
        'bookings'        => ['label' => 'Bookings',         'android_importance' => 'default', 'default_enabled' => true],
        'reminders'       => ['label' => 'Reminders',        'android_importance' => 'high',    'default_enabled' => true],
        'event_changes'   => ['label' => 'Event changes',    'android_importance' => 'default', 'default_enabled' => true],
        'location'        => ['label' => 'Location sharing', 'android_importance' => 'high',    'default_enabled' => true],
        'phone_schedules' => ['label' => 'Phone schedules',  'android_importance' => 'default', 'default_enabled' => true],
        'loyalty'         => ['label' => 'Loyalty',          'android_importance' => 'low',     'default_enabled' => true],
        'subscription'    => ['label' => 'Subscription',     'android_importance' => 'default', 'default_enabled' => true],
        'security'        => ['label' => 'Security',         'android_importance' => 'high',    'default_enabled' => true],
        'announcements'   => ['label' => 'Announcements',    'android_importance' => 'low',     'default_enabled' => false],
    ],

    'rules' => [
        // key = variant name of booking.reminder, value = minutes before the slot starts
        'reminder_offsets_minutes'             => ['15_min' => 15, 'start' => 0],
        'location_reminder_minutes'            => 15,
        // event edits: bump one pending row for 3 quiet minutes, or send now if the slot is close
        'event_change_quiet_minutes'           => 3,
        'event_change_send_now_within_minutes' => 60,
        'trial_ending_days_before'             => [3, 1],
        'live_location_cap_hours'              => 24,
        'revalidate_before_send'               => true,
    ],

    'types' => [

        'booking.created' => [
            'category' => 'bookings', 'priority' => 'normal',
            'link' => 'booking', 'push_ttl_min' => 1440, 'inbox_days' => 90,
            'collapse' => 'booking:{ref}',
            'dedupe' => 'booking.created:{ref}:{recipient}',
            'variants' => [
                'owner'  => ['channels' => ['inbox', 'push']],
                'booker' => ['channels' => ['inbox']],
            ],
        ],

        'booking.cancelled' => [
            'category' => 'bookings', 'priority' => 'high', 'mandatory' => true,
            'link' => 'scheduled_tab', 'push_ttl_min' => 1440, 'inbox_days' => 90,
            'collapse' => 'booking:{ref}',
            'dedupe' => 'booking.cancelled:{booking_id}:{recipient}',
            'variants' => [
                'owner_cancelled' => ['channels' => ['inbox', 'push']],
                'event_deleted'   => ['channels' => ['inbox', 'push']],
                'booker_cancelled' => ['channels' => ['inbox', 'push']],
            ],
        ],

        'booking.changed' => [
            'category' => 'event_changes', 'priority' => 'high',
            'link' => 'booking', 'push_ttl_min' => 1440, 'inbox_days' => 90,
            'collapse' => 'booking:{ref}',
            'dedupe' => 'booking.changed:{ref}:{recipient}:{change_batch}',
            'variants' => [
                'event_edited'     => ['channels' => ['inbox', 'push']],
                'schedule_edited'  => ['channels' => ['inbox', 'push']],
                'location_changed' => ['channels' => ['inbox', 'push']],
            ],
        ],

        'booking.reminder' => [
            'category' => 'reminders', 'priority' => 'high',
            'link' => 'booking', 'push_ttl_min' => 15, 'inbox_days' => 3,
            'collapse' => 'booking:{ref}',
            'dedupe' => 'booking.reminder:{booking_id}:{variant}:{recipient}',
            'variants' => [
                '15_min' => ['channels' => ['inbox', 'push']],
                'start'  => ['channels' => ['inbox', 'push']],
            ],
        ],

        'phone_schedule.changed' => [
            'category' => 'phone_schedules', 'priority' => 'normal',
            'link' => 'phone_schedules', 'push_ttl_min' => 1440, 'inbox_days' => 90,
            'collapse' => 'phone_schedule:{schedule_id}',
            'dedupe' => 'phone_schedule.changed:{schedule_id}:{variant}:{version}',
            'variants' => [
                'assigned' => ['channels' => ['inbox', 'push']],
                'modified' => ['channels' => ['inbox', 'push']],
                'revoked'  => ['channels' => ['inbox']],
            ],
        ],

        'location.started' => [
            'category' => 'location', 'priority' => 'high',
            'link' => 'booking', 'push_ttl_min' => 30, 'inbox_days' => 7,
            'collapse' => 'location:{booking_id}',
            'dedupe' => 'location.started:{booking_id}:{session}',
            'variants' => [
                'default' => ['channels' => ['inbox', 'push']],
            ],
        ],

        'location.stopped' => [
            'category' => 'location', 'priority' => 'low',
            'link' => 'booking', 'push_ttl_min' => 0, 'inbox_days' => 7,
            'collapse' => 'location:{booking_id}',
            'dedupe' => 'location.stopped:{booking_id}:{session}',
            'variants' => [
                'default' => ['channels' => ['inbox']],
            ],
        ],

        'location.auto_stopped' => [
            'category' => 'location', 'priority' => 'normal',
            'link' => 'booking', 'push_ttl_min' => 1440, 'inbox_days' => 7,
            'collapse' => 'location:{booking_id}',
            'dedupe' => 'location.auto_stopped:{booking_id}:{session}:{recipient}',
            'variants' => [
                'cap_24h_sharer' => ['channels' => ['inbox', 'push']],
                'cap_24h_other'  => ['channels' => ['inbox']],
                'schedule_ended' => ['channels' => ['inbox']],
            ],
        ],

        'location.reminder' => [
            'category' => 'location', 'priority' => 'high',
            'link' => 'booking', 'push_ttl_min' => 15, 'inbox_days' => 3,
            'collapse' => 'location:{booking_id}',
            'dedupe' => 'location.reminder:{booking_id}:{recipient}',
            'variants' => [
                'default' => ['channels' => ['inbox', 'push']],
            ],
        ],

        'loyalty.result' => [
            'category' => 'loyalty', 'priority' => 'normal',
            'link' => 'loyalty', 'push_ttl_min' => 1440, 'inbox_days' => 90,
            'collapse' => null,
            'dedupe' => 'loyalty.result:{bill_id}',
            'variants' => [
                'success'   => ['channels' => ['inbox', 'push']],
                'duplicate' => ['channels' => ['inbox', 'push']],
                'failed'    => ['channels' => ['inbox', 'push']],
            ],
        ],

        // Text flags: android (trial has no auto-conversion), ios_will_charge,
        // ios_cancelled (trial ends, no charge). Wording stays informational.
        'subscription.status' => [
            'enabled' => false, // needs RevenueCat webhooks; today the server only knows trials
            'category' => 'subscription', 'priority' => 'normal',
            'link' => 'subscription', 'push_ttl_min' => 1440, 'inbox_days' => 90,
            'collapse' => 'subscription',
            'dedupe' => 'subscription.status:{user_id}:{variant}:{period_end}',
            'variants' => [
                'trial_ending'  => ['channels' => ['inbox', 'push']],
                'expired'       => ['channels' => ['inbox', 'push']],
                'billing_issue' => ['channels' => ['inbox', 'push'], 'mandatory' => true, 'link' => 'manage_subscription'],
            ],
        ],

        'account.security' => [
            'category' => 'security', 'priority' => 'high', 'mandatory' => true,
            'link' => 'profile', 'push_ttl_min' => 1440, 'inbox_days' => 180,
            'collapse' => null,
            'dedupe' => 'account.security:{user_id}:{variant}:{event_id}',
            'variants' => [
                'password_changed' => ['channels' => ['inbox', 'push', 'email']],
                'password_reset'   => ['channels' => ['inbox', 'push', 'email']],
                'email_changed'    => ['channels' => ['inbox', 'push', 'email']], // email also goes to the OLD address
                'phone_changed'    => ['channels' => ['inbox', 'push', 'email']], // new number is never shown
            ],
        ],

    ],
];