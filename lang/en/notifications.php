<?php

return [
    'booking.created.owner' => ['title' => 'New booking', 'body' => ':name booked :event for :time.'],
    'booking.created.booker' => ['title' => 'Booking confirmed', 'body' => 'Your booking for :event with :with on :time is confirmed.'],

    'booking.cancelled.owner_cancelled' => ['title' => 'Booking cancelled', 'body' => ':owner cancelled your booking for :event on :time.'],
    'booking.cancelled.booker_cancelled' => ['title' => 'Booking cancelled', 'body' => ':name cancelled their booking for :event on :time.'],
    'booking.cancelled.event_deleted' => ['title' => 'Event removed', 'body' => ':owner removed :event, so your booking on :time is cancelled.'],

    'booking.changed.event_edited' => ['title' => 'Event updated', 'body' => ':owner updated :event: :changes.'],
    'booking.changed.schedule_edited' => ['title' => 'Time changed', 'body' => 'Your booking for :event moved to :time.'],
    'booking.changed.location_changed' => ['title' => 'Meeting details changed', 'body' => ':owner changed the meeting details for :event: :changes.'],

    'booking.reminder.15_min' => ['title' => 'Starting soon', 'body' => ':event with :with starts at :time.'],
    'booking.reminder.start' => ['title' => 'Starting now', 'body' => ':event with :with is starting now.'],

    'phone_schedule.changed.assigned' => ['title' => 'Calling hours set', 'body' => ':name set the hours when you can call them (:hours).'],
    'phone_schedule.changed.modified' => ['title' => 'Calling hours changed', 'body' => ':name changed the hours when you can call them (:hours).'],
    'phone_schedule.changed.revoked' => ['title' => 'Calling hours removed', 'body' => ':name removed the calling-hours limit for your number.'],

    'location.started.default' => ['title' => 'Location sharing started', 'body' => ':name started sharing their live location.'],
    'location.stopped.default' => ['title' => 'Location sharing stopped', 'body' => ':name stopped sharing their location.'],
    'location.auto_stopped.cap_24h_sharer' => ['title' => 'Location sharing stopped', 'body' => 'Live Location sharing stopped automatically after 24 hours.'],
    'location.auto_stopped.cap_24h_other' => ['title' => 'Location sharing ended', 'body' => ':name\'s Live Location sharing stopped after 24 hours.'],
    'location.auto_stopped.schedule_ended' => ['title' => 'Location sharing ended', 'body' => 'Sharing ended because the schedule finished.'],
    'location.reminder.default' => ['title' => 'Still sharing your location', 'body' => 'Your live location is still being shared.'],

    'loyalty.result.success' => ['title' => 'Bill added', 'body' => 'You earned :count points. Your total is :total.'],
    'loyalty.result.duplicate' => ['title' => 'Bill already scanned', 'body' => 'This bill was already added, so no points were given.'],
    'loyalty.result.failed' => ['title' => 'Bill not processed', 'body' => 'We could not read this bill. Please try again.'],
    'loyalty.result.invalid' => ['title' => 'Not a bill', 'body' => 'This photo does not look like a bill. Please try again with a clear photo.'],
    'loyalty.result.review' => ['title' => 'Bill under review', 'body' => 'Your bill is being checked. We will tell you when it is done.'],
    'loyalty.result.rejected' => ['title' => 'Bill not accepted', 'body' => 'This bill could not be accepted, so no points were given.'],

    'subscription.status.trial_started' => ['title' => 'Free trial started', 'body' => 'Your free trial ends on :time.'],
    'subscription.status.trial_ending' => ['title' => 'Trial ending soon', 'body' => 'Your free trial ends on :time. Subscribe to keep using CallALink.'],
    'subscription.status.trial_ending_auto' => ['title' => 'Trial ending soon', 'body' => 'Your free trial ends on :time. Your subscription starts then unless you cancel in your subscription settings.'],
    'subscription.status.trial_ending_cancelled' => ['title' => 'Trial ending soon', 'body' => 'Your free trial ends on :time and you will not be charged. Subscribe to keep using CallALink.'],
    'subscription.status.trial_ended' => ['title' => 'Trial ended', 'body' => 'Your free trial has ended. Subscribe to keep using CallALink.'],
    'subscription.status.subscription_started' => ['title' => 'Subscription active', 'body' => 'Your CallALink Premium subscription is active.'],
    'subscription.status.renewed' => ['title' => 'Subscription renewed', 'body' => 'Your subscription was renewed. It now runs until :time.'],
    'subscription.status.subscription_expiring' => ['title' => 'Subscription ending soon', 'body' => 'Your subscription ends on :time. Renew to keep using CallALink.'],
    'subscription.status.subscription_ended' => ['title' => 'Subscription ended', 'body' => 'Your subscription has ended. Subscribe again to keep using CallALink.'],
    'subscription.status.subscription_cancelled' => ['title' => 'Subscription cancelled', 'body' => 'Your subscription has been cancelled and will not renew. You can continue using your subscription until :time.',],
    'subscription.status.billing_issue' => ['title' => 'Billing issue', 'body' => 'We could not process your payment. Update your payment method to keep your subscription.'],

    'account.security.password_changed' => ['title' => 'Password changed', 'body' => 'Your password was changed. If this was not you, reset it now.'],
    'account.security.password_reset' => ['title' => 'Password reset', 'body' => 'Your password was reset.'],
    'account.security.email_changed' => ['title' => 'Email changed', 'body' => 'Your account email was changed.'],
    'account.security.phone_changed' => ['title' => 'Phone number changed', 'body' => 'Your account phone number was changed.'],
];