<?php

return [
    'booking.created.owner' => ['title' => 'New booking', 'body' => ':name booked :event for :time.'],
    'booking.created.booker' => ['title' => 'Booking confirmed', 'body' => 'Your booking for :event on :time is confirmed.'],

    'booking.cancelled.owner_cancelled' => ['title' => 'Booking cancelled', 'body' => 'Your booking for :event on :time was cancelled.'],
    'booking.cancelled.booker_cancelled' => ['title' => 'Booking cancelled', 'body' => ':name cancelled their booking for :event on :time.'],
    'booking.cancelled.event_deleted' => ['title' => 'Event removed', 'body' => ':event was removed, so your booking on :time is cancelled.'],

    'booking.changed.event_edited' => ['title' => 'Event updated', 'body' => ':event was updated: :changes.'],
    'booking.changed.schedule_edited' => ['title' => 'Time changed', 'body' => 'Your booking for :event moved to :time.'],
    'booking.changed.location_changed' => ['title' => 'Meeting details changed', 'body' => ':event: :changes.'],

    'booking.reminder.15_min' => ['title' => 'Starting soon', 'body' => ':event starts at :time.'],
    'booking.reminder.start' => ['title' => 'Starting now', 'body' => ':event is starting now.'],

    'phone_schedule.changed.assigned' => ['title' => 'Calling hours set', 'body' => ':name set the hours when you can call them (:hours).'],
    'phone_schedule.changed.modified' => ['title' => 'Calling hours changed', 'body' => ':name changed the hours when you can call them (:hours).'],
    'phone_schedule.changed.revoked' => ['title' => 'Calling hours removed', 'body' => ':name removed the calling-hours limit for your number.'],

    'location.started.default' => ['title' => 'Location sharing started', 'body' => ':name started sharing their live location.'],
    'location.stopped.default' => ['title' => 'Location sharing stopped', 'body' => ':name stopped sharing their location.'],
    'location.auto_stopped.cap_24h_sharer' => ['title' => 'Location sharing stopped', 'body' => 'Sharing stopped automatically after 24 hours.'],
    'location.auto_stopped.cap_24h_other' => ['title' => 'Location sharing ended', 'body' => ':name\'s sharing stopped after 24 hours.'],
    'location.auto_stopped.schedule_ended' => ['title' => 'Location sharing ended', 'body' => 'Sharing ended because the schedule finished.'],
    'location.reminder.default' => ['title' => 'Still sharing your location', 'body' => 'Your live location is still being shared.'],

    'loyalty.result.success' => ['title' => 'Bill added', 'body' => 'You earned :count points.'],
    'loyalty.result.duplicate' => ['title' => 'Bill already scanned', 'body' => 'This bill was already added, so no points were given.'],
    'loyalty.result.failed' => ['title' => 'Bill not processed', 'body' => 'We could not read this bill. Please try again.'],

    'subscription.status.trial_ending' => ['title' => 'Trial ending', 'body' => 'Your trial ends on :time.'],
    'subscription.status.expired' => ['title' => 'Trial ended', 'body' => 'Your trial has ended.'],
    'subscription.status.billing_issue' => ['title' => 'Billing issue', 'body' => 'There is a problem with your payment.'],

    'account.security.password_changed' => ['title' => 'Password changed', 'body' => 'Your password was changed. If this was not you, reset it now.'],
    'account.security.password_reset' => ['title' => 'Password reset', 'body' => 'Your password was reset.'],
    'account.security.email_changed' => ['title' => 'Email changed', 'body' => 'Your account email was changed.'],
    'account.security.phone_changed' => ['title' => 'Phone number changed', 'body' => 'Your account phone number was changed.'],
];