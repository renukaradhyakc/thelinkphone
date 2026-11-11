<?php

/*
 * Event deletion notification checks: DEL-01 to DEL-12.
 *
 * LOCAL DATABASE ONLY. Creates throwaway events and bookings, runs the REAL delete code
 * (App\Http\Livewire\Events::delete(), the method the owner's "delete event" button calls),
 * jumps the clock with Carbon::setTestNow(), reads the real user_notifications / outbox rows,
 * and deletes everything it created (even if a check crashes). Pushes and mail are faked
 * during the checks.
 *
 * Run from the Laravel project root, in a FRESH tinker session (type it, do not paste):
 *     php artisan tinker
 *     >>> include 'booking-delete-test.php';
 *
 * Defaults: owner 83, booker 101. Optional before the include: $ownerId, $bookerId, $booker2Id.
 *
 * Real pushes to user 83's devices (small demo, runs after all the checks):
 *     >>> $delKeep = true;
 *     >>> include 'booking-delete-test.php';
 *     ... check the phones and the inbox ...
 *     >>> $delCleanup();
 *   The demo makes two events owned by 101 that user 83 has booked (one booking on the first,
 *   two on the second) and deletes both. User 83 should get one "event deleted" push per
 *   booking (3 pushes). Pushes are sent by the queue worker you already run (queue: database).
 *
 * Real code paths used:
 *   - Livewire Events::delete()               owner deletes an event (cancels each upcoming booking with 'event_deleted')
 *   - EventRepository::update()               an edit that leaves a pending change row (DEL-10)
 *   - ScheduleEventRepository::store() / cancelScheduleEvent()   bookings and cancellations
 *   - BookingNotifier::sendReminder() / sendChange() and the outbox, run the way notifications:dispatch-due does
 *   - LocationNotifier::started() for the live-sharing cases (DEL-07, DEL-11)
 *
 * If the Livewire component cannot be created outside a web request, the script repeats the same
 * steps by hand (transaction, cancel each upcoming booking, delete the event) and says so.
 *
 * Not verified (code not seen yet): the live-location auto-stop command (DEL-11 reports what it can see),
 * OutboxRepository::cancelPendingForBooking, LocationNotifier.
 */

use App\Models\Event;
use App\Models\EventSchedule;
use App\Models\Location;
use App\Models\User;
use App\Modules\Notifications\Jobs\SendPushNotificationJob;
use App\Modules\Notifications\Models\NotificationOutbox;
use App\Modules\Notifications\Repositories\OutboxRepository;
use App\Modules\Notifications\Services\BookingNotifier;
use App\Modules\Notifications\Services\LocationNotifier;
use App\Modules\Notifications\Services\RecipientResolver;
use App\Repositories\EventRepository;
use App\Repositories\ScheduleEventRepository;
use Carbon\Carbon;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Mail;

$ownerId = (int) ($ownerId ?? 83);
$bookerId = (int) ($bookerId ?? 101);
$booker2Id = isset($booker2Id) ? (int) $booker2Id : null;
$delKeep = $delKeep ?? false;
$delDemoStarted = false;

$report = function (string $status, string $name, string $detail = '') {
    echo str_pad($status, 13).$name.($detail !== '' ? '  ('.$detail.')' : '').PHP_EOL;
};
$check = fn ($name, $ok, $detail = '') => $report($ok ? 'PASS' : 'FAIL', $name, $detail);

// ---- Preconditions --------------------------------------------------------------------------
$resolver = app(RecipientResolver::class);
$owner = User::find($ownerId);
$booker = User::find($bookerId);

if (! $owner || ! $booker || $ownerId === $bookerId) {
    echo 'SKIP  owner and booker must be two different existing users'.PHP_EOL;

    return;
}

$bookerPhone = (string) $booker->phone_number;
$ownerPhone = (string) $owner->phone_number;

if (trim($bookerPhone) === '' || optional($resolver->uniqueUserByPhone($bookerPhone))->id !== $bookerId) {
    echo "SKIP  user $bookerId needs a phone number that resolves to only that user".PHP_EOL;

    return;
}

$ownerPhoneOk = trim($ownerPhone) !== '' && optional($resolver->uniqueUserByPhone($ownerPhone))->id === $ownerId;

Auth::setUser($owner);
$authOk = (int) getLogInUserId() === $ownerId;
Auth::forgetUser();

if (! $authOk) {
    echo 'SKIP  getLogInUserId() does not follow Auth::setUser()'.PHP_EOL;

    return;
}

$booker2 = $booker2Id
    ? User::find($booker2Id)
    : User::whereNotIn('id', [$ownerId, $bookerId])
        ->whereNotNull('phone_number')->where('phone_number', '!=', '')
        ->limit(300)->get()
        ->first(fn ($u) => $u->phone_number !== $owner->phone_number
            && $u->phone_number !== $booker->phone_number
            && optional($resolver->uniqueUserByPhone($u->phone_number))->id === $u->id);
$booker2Phone = $booker2 ? (string) $booker2->phone_number : null;

// Can the real delete button code run outside a web request?
$lwOk = class_exists(\App\Http\Livewire\Events::class);
if ($lwOk) {
    try {
        (new ReflectionClass(\App\Http\Livewire\Events::class))->newInstanceWithoutConstructor(); 
    } catch (Throwable $e) {
        $lwOk = false;
        $lwErr = $e->getMessage();
    }
}

// ---- Fakes ------------------------------------------------------------------------------------
$origBus = app(Dispatcher::class);
$restoreBus = function () use ($origBus) {
    app()->instance(Dispatcher::class, $origBus);
    Facade::clearResolvedInstance(Dispatcher::class);
};
Mail::fake();
Bus::fake([SendPushNotificationJob::class]);

// ---- Helpers ----------------------------------------------------------------------------------
$tag = substr(uniqid(), -6);
$tzBefore = date_default_timezone_get(); // Events::delete() changes the PHP default time zone; restored after every call
$quiet = max(1, (int) config('notifications.rules.event_change_quiet_minutes', 3));

$S1 = '10:00 AM - 10:30 AM';
$bookingIds = [];
$eventIds = [];
$lastError = null;
$dayN = 3;

$eventRepo = app(EventRepository::class);
$bookingRepo = app(ScheduleEventRepository::class);
$notifier = app(BookingNotifier::class);
$outboxRepo = app(OutboxRepository::class);

$pushCount = fn () => Bus::dispatched(SendPushNotificationJob::class)->count();
$mailCount = function () {
    try {
        return Mail::sent(\App\Mail\DeleteEventMail::class)->count();
    } catch (Throwable $e) {
        return null;
    }
};
$nextDate = function () use (&$dayN) {
    return Carbon::now()->addDays(++$dayN)->format('Y-m-d'); // 4+ days ahead, one new date per booking
};
$at = fn (Carbon $t) => Carbon::setTestNow($t);
$real = fn () => Carbon::setTestNow();
$startOf = function ($b) use ($notifier) {
    $row = $b ? $b->fresh() : null;

    return $row ? $notifier->startUtc($row) : null; // null once the booking was deleted with its event
};
$endOf = function ($b) use ($notifier) {
    $row = $b ? $b->fresh() : null;

    return $row ? $notifier->endUtc($row) : null;
};

$mkEvent = function (User $u, string $label, int $mode = Event::PHONE_CALL) use (&$eventIds, $eventRepo, $tag) {
    Auth::setUser($u);

    try {
        $e = $eventRepo->store([
            'name' => "DEL-TEST $tag $label",
            'event_location' => $mode,
            'event_link' => "del-test-$tag-$label",
            'event_color' => '#63d867',
            'event_type' => Event::FREE,
        ]);
    } finally {
        Auth::forgetUser();
    }

    $eventIds[] = $e->id;

    return $e;
};

$addFixed = fn (Event $e, string $address) => $e->location()->create([
    'location_type' => Location::FIXED,
    'latitude' => 12.9716,
    'longitude' => 77.5946,
    'accuracy' => 10,
    'address' => $address,
    'is_live_sharing_active' => false,
    'live_started_at' => null,
]);

$addLive = fn (Event $e, bool $active) => $e->location()->create([
    'location_type' => Location::LIVE,
    'latitude' => 12.9716,
    'longitude' => 77.5946,
    'accuracy' => 10,
    'address' => null,
    'is_live_sharing_active' => $active,
    'live_started_at' => $active ? now() : null,
]);

$book = function (Event $event, ?string $phone, string $slot, ?string $date = null) use (&$bookingIds, &$lastError, $bookingRepo, $nextDate) {
    try {
        $b = $bookingRepo->store([
            'name' => 'DEL Guest',
            'phone_call' => $phone,
            'email' => 'del-guest-'.uniqid().'@example.invalid',
            'schedule_date' => $date ?? $nextDate(),
            'description' => 'DEL test booking',
            'user_id' => $event->user_id,
            'event_id' => $event->id,
            'slot_time' => $slot,
        ]);
        $bookingIds[] = $b->id;
        $lastError = null;

        return $b->fresh();
    } catch (Throwable $e) {
        $lastError = $e->getMessage();

        return null;
    }
};

// One event owned by $owner with one confirmed booking by $booker.
$mkPair = function (string $label, int $mode = Event::PHONE_CALL) use ($mkEvent, $book, $owner, $bookerPhone, $S1) {
    $e = $mkEvent($owner, $label, $mode);

    return [$e, $book($e, $bookerPhone, $S1)];
};

$setupFailed = function ($b, string $id) use ($report, &$lastError) {
    if ($b) {
        return false;
    }
    $report('INVESTIGATE', "$id could not create its test booking", (string) $lastError);

    return true;
};

$ownerCancel = function ($b) use ($bookingRepo, &$lastError) {
    try {
        $bookingRepo->cancelScheduleEvent('DEL test', $b->id);
        $lastError = null;

        return true;
    } catch (Throwable $e) {
        $lastError = $e->getMessage();

        return false;
    }
};

// The owner edits an event: the real EventRepository::update().
$edit = function (Event $event, array $input) use ($eventRepo, &$lastError) {
    Auth::setUser(User::find($event->user_id));

    try {
        $eventRepo->update($input, $event->id);
        $lastError = null;

        return true;
    } catch (Throwable $e) {
        $lastError = $e->getMessage();

        return false;
    } finally {
        Auth::forgetUser();
    }
};

// Same steps as App\Http\Livewire\Events::delete(), used only if the component cannot be created.
$replicaDelete = function (Event $event) use ($notifier) {
    DB::transaction(function () use ($event, $notifier) {
        $upcoming = EventSchedule::where('event_id', $event->id)
            ->where('status', EventSchedule::BOOKED)
            ->whereDate('schedule_date', '>=', now()->subDay()->toDateString())
            ->get();

        $told = [];

        foreach ($upcoming->sortBy(fn ($b) => $notifier->startUtc($b)?->timestamp ?? PHP_INT_MAX) as $upcomingBooking) {
            $start = $notifier->startUtc($upcomingBooking);
            $end = $start ? ($notifier->endUtc($upcomingBooking) ?? $start->copy()->addMinutes(30)) : null;

            if ($start === null || ! $end->gt(now())) {
                continue;
            }

            $phone = trim((string) $upcomingBooking->phone_call);
            $person = $phone === '' ? null : app(RecipientResolver::class)->uniqueUserByPhone($phone);

            if ($person !== null && isset($told[$person->id])) {
                app(OutboxRepository::class)->cancelPendingForBooking($upcomingBooking->id);

                continue;
            }

            if ($person !== null) {
                $told[$person->id] = true;
            }
            
            $notifier->cancelled($upcomingBooking, 'event_deleted');

        }

        $event->delete();
    });
};

// The owner deletes an event. Returns true when no exception came out.
$deleteEvent = function (Event $event, ?User $as = null) use (&$lastError, $tzBefore, $lwOk, $replicaDelete) {
    Auth::setUser($as ?? User::find($event->user_id));

    try {
        if ($lwOk) {
            (new ReflectionClass(\App\Http\Livewire\Events::class))->newInstanceWithoutConstructor()->delete($event->id);
        } else {
            $replicaDelete($event);
        }
        $lastError = null;

        return true;
    } catch (Throwable $e) {
        $lastError = $e->getMessage();

        return false;
    } finally {
        Auth::forgetUser();
        date_default_timezone_set($tzBefore);
    }
};

$live = fn (array $bookings) => collect($bookings)->filter()->pluck('id');
$notifsFor = function (string $type, array $bookings) use ($live) {
    $ids = $live($bookings);
    if ($ids->isEmpty()) {
        return collect();
    }

    return DB::table('user_notifications')->where('type', $type)->where(function ($q) use ($ids, $type) {
        foreach ($ids as $id) {
            $q->orWhere('dedupe_key', 'like', "{$type}:{$id}:%");
        }
    })->orderBy('id')->get();
};
$delRows = fn (array $bookings) => $notifsFor('booking.cancelled', $bookings);
$outboxOf = fn (array $bookings, string $type) => NotificationOutbox::whereIn('event_schedule_id', $live($bookings))
    ->where('type', 'like', $type)->get();
$pendingOf = fn (array $bookings, string $type) => NotificationOutbox::whereIn('event_schedule_id', $live($bookings))
    ->where('type', 'like', $type)->where('status', NotificationOutbox::PENDING)->count();
$locationRowExists = fn (Event $e) => Location::where('locationable_id', $e->id)->where('locationable_type', $e->getMorphClass())->exists();
$locNotifsSince = fn (int $sinceId) => DB::table('user_notifications')->where('id', '>', $sinceId)->where('type', 'like', 'location.%')->get();
$maxNotifId = fn () => (int) DB::table('user_notifications')->max('id');
$bodyOf = fn ($rows) => substr((string) optional($rows->first())->title.' / '.optional($rows->first())->body, 0, 110);

// Rows that are due now, using the app's own scope when it has one.
$dueRows = function (array $bookings, string $type) use ($live) {
    $q = method_exists(NotificationOutbox::class, 'scopeDue')
        ? NotificationOutbox::due(now())
        : NotificationOutbox::where('status', NotificationOutbox::PENDING)->where('notify_at', '<=', now()->utc());

    return $q->whereIn('event_schedule_id', $live($bookings))->where('type', $type)->get();
};

// Same steps as notifications:dispatch-due, for one outbox row.
$dispatchRow = function (NotificationOutbox $row, string $method) use ($notifier, $outboxRepo) {
    $row->update(['status' => NotificationOutbox::CLAIMED, 'claimed_at' => now(), 'attempts' => $row->attempts + 1]);
    $result = $notifier->{$method}($row->fresh());
    $result === 'sent' ? $outboxRepo->markSent($row) : $outboxRepo->markCancelled($row);

    return $result;
};

// Arms a one-off failure inside the event delete, for the rollback check (DEL-12). The state lives in the
// container so the listener is registered only once per PHP process and does nothing unless armed.
$arm = app()->bound('del.test.arm') ? app('del.test.arm') : app()->instance('del.test.arm', new \stdClass());
$arm->id = null;
if (empty($arm->registered)) {
    Event::deleting(function (Event $e) use ($arm) {
        if ($arm->id !== null && (int) $e->id === (int) $arm->id) {
            throw new \RuntimeException('forced failure for the DEL-12 rollback test');
        }
    });
    $arm->registered = true;
}

$delCleanup = function () use (&$bookingIds, &$eventIds) {
    foreach ($bookingIds as $id) {
        DB::table('user_notifications')->where(function ($q) use ($id) {
            $q->where('dedupe_key', 'like', "booking.%:$id:%")->orWhere('dedupe_key', 'like', "location.%:$id:%");
        })->delete();
        NotificationOutbox::where('event_schedule_id', $id)->delete();
    }
    if ($bookingIds) {
        EventSchedule::whereIn('id', $bookingIds)->delete();
    }
    if ($eventIds) {
        Event::whereIn('id', $eventIds)->get()->each(function ($e) {
            $e->location()->delete();
            $e->delete();
        });
    }
    $bookingIds = [];
    $eventIds = [];
    echo 'Deleted the throwaway events, bookings, notifications and outbox rows.'.PHP_EOL;
};

// leftovers from earlier kept or crashed runs. Bookings are found by their guest e-mail, because the
// event they belonged to may already be gone.
$oldBookingIds = EventSchedule::where('email', 'like', 'del-guest-%@example.invalid')->pluck('id');
$oldEventIds = Event::where('name', 'like', 'DEL-TEST %')->pluck('id');
if ($oldBookingIds->isNotEmpty() || $oldEventIds->isNotEmpty()) {
    foreach ($oldBookingIds as $oldId) {
        DB::table('user_notifications')->where(function ($q) use ($oldId) {
            $q->where('dedupe_key', 'like', "booking.%:$oldId:%")->orWhere('dedupe_key', 'like', "location.%:$oldId:%");
        })->delete();
        NotificationOutbox::where('event_schedule_id', $oldId)->delete();
    }
    EventSchedule::whereIn('id', $oldBookingIds)->delete();
    Event::whereIn('id', $oldEventIds)->get()->each(function ($e) {
        $e->location()->delete();
        $e->delete();
    });
    echo 'Removed leftovers of earlier runs: '.$oldEventIds->count().' event(s), '.$oldBookingIds->count().' booking(s)'.PHP_EOL;
}

$tzKey = $owner->timezone;
$ownerTz = ($tzKey !== null && isset(User::TIME_ZONE_ARRAY[$tzKey])) ? User::TIME_ZONE_ARRAY[$tzKey] : config('app.timezone');
echo "Owner $ownerId, booker $bookerId, second booker ".($booker2 ? $booker2->id : 'none found').", owner time zone $ownerTz".PHP_EOL;
echo 'Delete path: '.($lwOk ? 'the real Livewire Events::delete()' : 'the same steps repeated by hand (the Livewire component could not be created: '.substr((string) ($lwErr ?? ''), 0, 80).')').PHP_EOL.PHP_EOL;

try {
    // ============ DEL-01 delete event with future confirmed bookings ============
    [$e1, $b1] = $mkPair('D01');
    if (! $setupFailed($b1, 'DEL-01')) {
        $pendBefore = $pendingOf([$b1], 'booking.reminder');
        $pushBefore = $pushCount();
        $ok = $deleteEvent($e1);
        $rows = $delRows([$b1]);
        $check('DEL-01 the event is deleted', $ok && Event::find($e1->id) === null, (string) $lastError);
        $check('DEL-01 booker receives booking.cancelled.event_deleted',
            $rows->count() === 1 && (int) $rows->first()->user_id === $bookerId && $rows->first()->reason === 'event_deleted',
            'rows='.$rows->count().', reason='.optional($rows->first())->reason.', text="'.$bodyOf($rows).'"');
        $check('DEL-01 the owner is not notified about their own deletion', $rows->where('user_id', $ownerId)->count() === 0);
        $check('DEL-01 one push job is queued', ($pushCount() - $pushBefore) === 1, 'push jobs='.($pushCount() - $pushBefore));
        $check('DEL-01 the booking\'s pending reminders are cancelled', $pendBefore > 0 && $pendingOf([$b1], 'booking.reminder') === 0,
            'pending '.$pendBefore.' -> '.$pendingOf([$b1], 'booking.reminder'));
        $row = EventSchedule::find($b1->id);
        $report('INFO', 'DEL-01 what happens to the booking row when its event is deleted',
            $row === null ? 'the booking row is deleted with the event (database cascade)'
                : 'the booking row stays: status='.$row->status.', event_id='.var_export($row->event_id, true).' (it is not marked cancelled)');
    }

    // ============ DEL-02 in-progress booking ============
    [$e2, $b2] = $mkPair('D02');
    if (! $setupFailed($b2, 'DEL-02')) {
        $at($startOf($b2)->copy()->addMinutes(10)); // 10 minutes into a 30-minute slot
        $ok = $deleteEvent($e2);
        $rows = $delRows([$b2]);
        $real();
        $check('DEL-02 an in-progress booking: the event is deleted and the booker is notified',
            $ok && Event::find($e2->id) === null && $rows->count() === 1 && (int) $rows->first()->user_id === $bookerId && $rows->first()->reason === 'event_deleted',
            'rows='.$rows->count().(string) $lastError);
    }

    // ============ DEL-03 only ended bookings ============
    [$e3, $b3] = $mkPair('D03');
    if (! $setupFailed($b3, 'DEL-03')) {
        $at($endOf($b3)->copy()->addMinutes(10)); // the slot ended 10 minutes ago
        $ok = $deleteEvent($e3);
        $rows = $delRows([$b3]);
        $real();
        $check('DEL-03 only an ended booking: the event is deleted and nobody is notified', $ok && Event::find($e3->id) === null && $rows->count() === 0, 'rows='.$rows->count().(string) $lastError);
    }
    $e3b = $mkEvent($owner, 'D03b');
    $ended3 = $book($e3b, $bookerPhone, $S1);                        // will have ended
    $future3 = $booker2Phone ? $book($e3b, $booker2Phone, $S1) : $book($e3b, $bookerPhone, $S1); // still upcoming
    if (! $setupFailed($ended3, 'DEL-03b') && ! $setupFailed($future3, 'DEL-03b')) {
        $at($endOf($ended3)->copy()->addMinutes(10));
        $deleteEvent($e3b);
        $rowsEnded = $delRows([$ended3]);
        $rowsFuture = $delRows([$future3]);
        $real();
        $check('DEL-03 with one ended and one upcoming booking, only the upcoming one is notified', $rowsEnded->count() === 0 && $rowsFuture->count() === 1,
            'ended rows='.$rowsEnded->count().', upcoming rows='.$rowsFuture->count());
    }

    // ============ DEL-04 cancelled bookings excluded ============
    $e4 = $mkEvent($owner, 'D04');
    $c4 = $book($e4, $bookerPhone, $S1);
    $a4 = $book($e4, $bookerPhone, $S1);
    if (! $setupFailed($c4, 'DEL-04') && ! $setupFailed($a4, 'DEL-04')) {
        $ownerCancel($c4);
        $before = $delRows([$c4])->count();
        $deleteEvent($e4);
        $rowsC = $delRows([$c4]);
        $rowsA = $delRows([$a4]);
        $check('DEL-04 the already-cancelled booking gets nothing new when the event is deleted',
            $before === 1 && $rowsC->count() === 1 && $rowsC->first()->reason === 'owner_cancelled', 'rows='.$rowsC->count().', reason='.optional($rowsC->first())->reason);
        $check('DEL-04 the confirmed booking is told the event was deleted', $rowsA->count() === 1 && $rowsA->first()->reason === 'event_deleted', 'rows='.$rowsA->count());
    }

    // ============ DEL-05 several bookers ============
    $e5 = $mkEvent($owner, 'D05');
    $b5a = $book($e5, $bookerPhone, $S1);
    $b5b = $booker2Phone ? $book($e5, $booker2Phone, $S1) : null;
    $b5c = $book($e5, $bookerPhone, $S1); // booker 1 has a second booking on the same event
    if (! $setupFailed($b5a, 'DEL-05') && ! $setupFailed($b5c, 'DEL-05')) {
        $pushBefore = $pushCount();
        $deleteEvent($e5);
        $rows = $delRows([$b5a, $b5b, $b5c]);
        $n1 = $rows->where('user_id', $bookerId)->count();
        $check('DEL-05 every booker is told the event was deleted', $n1 >= 1 && (! $b5b || $rows->where('user_id', $booker2->id)->count() >= 1),
            'booker '.$bookerId.'='.$n1.($b5b ? ', booker '.$booker2->id.'='.$rows->where('user_id', $booker2->id)->count() : ''));
        $check('DEL-05 the owner is not notified', $rows->where('user_id', $ownerId)->count() === 0);
        if ($b5b) {
            $check('DEL-05 a booker with one booking gets exactly one notification', $rows->where('user_id', $booker2->id)->count() === 1);
        } else {
            $report('SKIP', 'DEL-05 single-booking booker (no second user with a unique phone number; set $booker2Id)');
        }
        $report($n1 === 1 ? 'PASS' : 'INVESTIGATE', 'DEL-05 a booker with two bookings on the deleted event',
            $n1 === 1 ? 'told once' : 'gets '.$n1.' notifications and '.($pushCount() - $pushBefore).' push jobs in total: one per booking, not one per person. The edit flow tells each person once; decide whether deletion should do the same');
    }

    // ============ DEL-06 duplicate deletion ============
    [$e6, $b6] = $mkPair('D06');
    if (! $setupFailed($b6, 'DEL-06')) {
        $snap = EventSchedule::find($b6->id); // what a second, slightly late request would still hold
        $pushBefore = $pushCount();
        $deleteEvent($e6);
        $firstRows = $delRows([$b6])->count();
        $secondOk = $deleteEvent($e6); // the event is gone: the real code ignores it
        $afterSecond = $delRows([$b6])->count();
        $notifier->cancelled($snap, 'event_deleted');
        $notifier->cancelled($snap, 'event_deleted');
        $afterReplay = $delRows([$b6])->count();
        $check('DEL-06 deleting an event that is already gone does nothing and sends nothing', $firstRows === 1 && $secondOk && $afterSecond === 1,
            'rows after 1st='.$firstRows.', after 2nd='.$afterSecond.(string) $lastError);
        $check('DEL-06 even a second request that was already past the check adds no notification', $afterReplay === 1, 'rows='.$afterReplay);
        $check('DEL-06 only one push job in total', ($pushCount() - $pushBefore) === 1, 'push jobs='.($pushCount() - $pushBefore));
    }

    // ============ DEL-07 delete while live sharing is active ============
    $e7 = $mkEvent($owner, 'D07', Event::IN_PERSON_MEETING);
    $b7a = $book($e7, $bookerPhone, $S1);
    $b7b = $booker2Phone ? $book($e7, $booker2Phone, $S1) : null;
    if (! $setupFailed($b7a, 'DEL-07')) {
        try {
            $addLive($e7, true);
            app(LocationNotifier::class)->started($e7->fresh(), now());
            $started = DB::table('user_notifications')->where('type', 'location.started')->where('user_id', $bookerId)->where('dedupe_key', 'like', "location.started:{$e7->id}:%")->count(); // the notifier keys sessions by event id, not booking id
            $report($started >= 1 ? 'OK' : 'INVESTIGATE', 'DEL-07 setup: the booker was told that sharing started', 'location.started rows='.$started);
            $snapId = $maxNotifId();
            $pendLocBefore = $pendingOf([$b7a, $b7b], 'location.%');

            $deleteEvent($e7);
            $rows = $delRows([$b7a, $b7b]);
            $loc = $locNotifsSince($snapId);

            $check('DEL-07 the booker gets the event_deleted notification', $rows->where('user_id', $bookerId)->where('reason', 'event_deleted')->count() === 1, 'rows='.$rows->count());
            $check('DEL-07 no stopped / auto-stopped location notification is created', $loc->count() === 0, 'location rows='.$loc->pluck('type')->implode(','));
            $check('DEL-07 the live session is gone (the location row is deleted with the event)', ! $locationRowExists($e7));
            $check('DEL-07 no location job is left pending for these bookings', $pendingOf([$b7a, $b7b], 'location.%') === 0, 'pending '.$pendLocBefore.' -> '.$pendingOf([$b7a, $b7b], 'location.%'));
            $report('INFO', 'DEL-07 the booker was told sharing started and is never told it stopped', 'by design the deletion notice is the only one; the inbox row for "started" now points at an event that no longer exists');
        } catch (Throwable $e) {
            $report('INVESTIGATE', 'DEL-07 could not run against Location / LocationNotifier', substr($e->getMessage(), 0, 160));
        }
    }

    // ============ DEL-08 delete while sharing is inactive ============
    foreach ([['fixed address', false], ['live, sharing off', true]] as [$label, $isLive]) {
        $e8 = $mkEvent($owner, $isLive ? 'D08b' : 'D08a', Event::IN_PERSON_MEETING);
        $b8 = $book($e8, $bookerPhone, $S1);
        if ($setupFailed($b8, 'DEL-08')) {
            continue;
        }
        try {
            $isLive ? $addLive($e8, false) : $addFixed($e8, 'Test Address D08');
            $snapId = $maxNotifId();
            $deleteEvent($e8);
            $rows = $delRows([$b8]);
            $loc = $locNotifsSince($snapId);
            $check("DEL-08 ($label) booker gets the event_deleted notification only",
                $rows->count() === 1 && $rows->first()->reason === 'event_deleted' && $loc->count() === 0, 'rows='.$rows->count().', location rows='.$loc->pluck('type')->implode(','));
        } catch (Throwable $e) {
            $report('INVESTIGATE', "DEL-08 ($label) could not run", substr($e->getMessage(), 0, 160));
        }
    }

    // ============ DEL-09 pending reminders suppressed ============
    [$e9, $b9] = $mkPair('D09');
    if (! $setupFailed($b9, 'DEL-09')) {
        $pendBefore = $pendingOf([$b9], 'booking.reminder');
        $check('DEL-09 setup: reminders are queued for the booking', $pendBefore > 0, 'pending='.$pendBefore);
        $start = $startOf($b9); // read it now: the booking row is deleted together with the event
        $deleteEvent($e9);
        $check('DEL-09 deleting the event leaves no pending reminder', $pendingOf([$b9], 'booking.reminder') === 0, 'pending='.$pendingOf([$b9], 'booking.reminder'));
        $due = 0;
        $sent = 0;
        if ($start) {
            $at($start->copy()->subMinutes(15)->addSeconds(10)); // the 15-minute reminder time
            $due = $dueRows([$b9], 'booking.reminder')->count();
            $real();
            foreach ($outboxOf([$b9], 'booking.reminder') as $row) { // even a row a worker already held
                $sent += $dispatchRow($row, 'sendReminder') === 'sent' ? 1 : 0;
            }
        }
        $reminders = DB::table('user_notifications')->where('type', 'booking.reminder')->where('dedupe_key', 'like', "booking.reminder:{$b9->id}:%")->count();
        $check('DEL-09 nothing is due when the reminder time arrives, and nothing is sent', $due === 0 && $sent === 0 && $reminders === 0,
            'due='.$due.', sent='.$sent.', reminder notifications='.$reminders);
    }

    // ============ DEL-10 pending event-change (debounce) notification ============
    [$e10, $b10] = $mkPair('D10');
    if (! $setupFailed($b10, 'DEL-10')) {
        $edit($e10, ['name' => "DEL-TEST $tag renamed-10"]);
        $pendBefore = $pendingOf([$b10], 'booking.changed');
        $check('DEL-10 setup: the edit queued a pending change for the booker', $pendBefore === 1, 'pending='.$pendBefore);
        $deleteEvent($e10);
        $check('DEL-10 deleting the event cancels the pending change', $pendingOf([$b10], 'booking.changed') === 0, 'pending='.$pendingOf([$b10], 'booking.changed'));
        $at(Carbon::now()->addMinutes($quiet)->addSeconds(30)); // the quiet period passes
        $due = $dueRows([$b10], 'booking.changed')->count();
        foreach ($dueRows([$b10], 'booking.changed') as $row) {
            $dispatchRow($row, 'sendChange');
        }
        $real();
        $changed = $notifsFor('booking.changed', [$b10])->count();
        $check('DEL-10 after the quiet period no change notification arrives', $due === 0 && $changed === 0, 'due='.$due.', change notifications='.$changed);

        $row = NotificationOutbox::where('event_schedule_id', $b10->id)->where('type', 'booking.changed')->first();
        if ($row) {
            $row->update(['status' => NotificationOutbox::PENDING, 'notify_at' => now()->subMinute(), 'attempts' => 0]); // pretend it was never cancelled
            $res = $dispatchRow($row->fresh(), 'sendChange');
            $check('DEL-10 even if the old row were still pending, the send-time check refuses it', $res !== 'sent' && $notifsFor('booking.changed', [$b10])->count() === 0, 'result='.$res);
        }
        $check('DEL-10 the booker has the deletion notice and nothing else', $delRows([$b10])->count() === 1);
    }

    // ============ DEL-11 pending auto-stop of live sharing ============
    $e11 = $mkEvent($owner, 'D11', Event::IN_PERSON_MEETING);
    $b11 = $book($e11, $bookerPhone, $S1);
    if (! $setupFailed($b11, 'DEL-11')) {
        try {
            $addLive($e11, true);
            $startedAt = now();
            app(LocationNotifier::class)->started($e11->fresh(), $startedAt);
            $snapId = $maxNotifId();
            $pendBefore = $pendingOf([$b11], 'location.%');
            $deleteEvent($e11);
            $check('DEL-11 the live session row no longer exists, so a scanner for sessions past 24h or past the schedule finds nothing', ! $locationRowExists($e11));
            $check('DEL-11 no location job is left pending for the booking', $pendingOf([$b11], 'location.%') === 0, 'pending '.$pendBefore.' -> '.$pendingOf([$b11], 'location.%'));
            $check('DEL-11 no location notification was created by the deletion', $locNotifsSince($snapId)->count() === 0);

            $cmds = collect(array_keys(Artisan::all()))->filter(fn ($n) => str_starts_with($n, 'notifications:'))->values()->implode(', ');
            $methods = collect(get_class_methods(LocationNotifier::class))->reject(fn ($m) => str_starts_with($m, '__'))->implode(', ');
            $report('INVESTIGATE', 'DEL-11 the auto-stop runner itself was not executed',
                'running a global command here would touch other users\' live sessions. Commands found: ['.$cmds.']. LocationNotifier methods: ['.$methods.']. Send the auto-stop command and I will run it against these fixtures only');
        } catch (Throwable $e) {
            $report('INVESTIGATE', 'DEL-11 could not run against Location / LocationNotifier', substr($e->getMessage(), 0, 160));
        }
    }

    // ============ DEL-12 transaction rolls back ============
    $e12 = $mkEvent($owner, 'D12', Event::IN_PERSON_MEETING);
    $b12 = $book($e12, $bookerPhone, $S1);
    if (! $setupFailed($b12, 'DEL-12')) {
        $addFixed($e12, 'Test Address D12');
        $pendBefore = $pendingOf([$b12], 'booking.reminder');
        $pushBefore = $pushCount();
        $mailBefore = $mailCount();

        $arm->id = $e12->id;          // the event delete is made to fail after the bookings were processed
        $ok = $deleteEvent($e12);
        $arm->id = null;
        $err = (string) $lastError;

        $rows = $delRows([$b12]);
        $check('DEL-12 the forced failure stopped the deletion', ! $ok && str_contains($err, 'forced failure'), substr($err, 0, 80));
        $check('DEL-12 the event and its booking are still there', Event::find($e12->id) !== null && (int) EventSchedule::where('id', $b12->id)->value('status') === EventSchedule::BOOKED);
        $check('DEL-12 the location was restored too (rolled back with the transaction)', $locationRowExists($e12));
        $check('DEL-12 no cancellation notification exists for the uncommitted deletion', $rows->count() === 0, 'rows='.$rows->count());
        $check('DEL-12 the booking\'s pending reminders were not cancelled', $pendingOf([$b12], 'booking.reminder') === $pendBefore, 'pending '.$pendBefore.' -> '.$pendingOf([$b12], 'booking.reminder'));

        $pushed = $pushCount() - $pushBefore;
        $queue = config('queue.default');
        $report($pushed === 0 ? 'PASS' : 'INVESTIGATE', 'DEL-12 no push job is dispatched for the uncommitted deletion',
            $pushed === 0 ? 'none' : $pushed.' push job(s) were dispatched inside the transaction (queue: '.$queue.'). On the database queue they roll back with it, but on a queue connection that is not part of this transaction (redis, sqs, a separate database connection) a push for a deletion that never happened could still be delivered. Dispatch after commit (DB::afterCommit) to be safe');

        $mailAfter = $mailCount();
        if ($mailAfter === null || ! $lwOk) {
            $report('SKIP', 'DEL-12 deletion e-mail check', $lwOk ? 'DeleteEventMail could not be inspected' : 'the hand-repeated delete does not send e-mails');
        } elseif ($mailAfter - $mailBefore > 0) {
            $report('INVESTIGATE', 'DEL-12 the "event deleted" e-mail is sent before the transaction', ($mailAfter - $mailBefore).' e-mail(s) went out even though the deletion rolled back (Events::delete() sends them before DB::transaction). Move the mail after the commit');
        } else {
            $report('INFO', 'DEL-12 no deletion e-mail was sent in this run', 'the owner may have e-mail notifications off, so the order of mail and transaction could not be checked');
        }
    }

    // ============ real-push demo for user 83 (only with $delKeep = true) ============
    $delCleanup(); // always remove the data from the checks above

    if ($delKeep) {
        $delDemoStarted = true;
        Carbon::setTestNow();
        $restoreBus(); // real dispatcher, so the push jobs go to your database queue

        if (app(Dispatcher::class) instanceof \Illuminate\Support\Testing\Fakes\BusFake) {
            echo PHP_EOL.'FAIL  the real dispatcher could not be restored, so no real pushes can be sent. Start a new tinker session and run again.'.PHP_EOL;
        } elseif (! $ownerPhoneOk) {
            echo PHP_EOL.'SKIP  demo: user '.$ownerId.'\'s phone does not resolve to only that user, so it cannot be the booker'.PHP_EOL;
        } else {
            $demoStart = $maxNotifId();
            $d1 = $mkEvent($booker, 'DM1');                  // owned by 101, booked by 83
            $d2 = $mkEvent($booker, 'DM2');
            $d1a = $book($d1, $ownerPhone, $S1);
            $d2a = $book($d2, $ownerPhone, $S1);
            $d2b = $book($d2, $ownerPhone, $S1);

            if ($d1a && $d2a && $d2b) {
                $deleteEvent($d1);
                $deleteEvent($d2);
            } else {
                $report('INVESTIGATE', 'demo could not create its bookings', (string) $lastError);
            }

            echo PHP_EOL.'--- Real push demo: inbox rows for user '.$ownerId.' ---'.PHP_EOL;
            $demoRows = DB::table('user_notifications')->where('id', '>', $demoStart)->where('user_id', $ownerId)
                ->where('type', 'booking.cancelled')->orderBy('id')->get(['id', 'type', 'reason', 'title']);
            foreach ($demoRows as $r) {
                echo "  #{$r->id}  {$r->type}.{$r->reason}  [push]  \"{$r->title}\"".PHP_EOL;
            }
            echo PHP_EOL.'Queue: '.config('queue.default').'. Your notifications:tick loop sends them within a minute.'.PHP_EOL;
            echo 'Expect up to 3 pushes on user '.$ownerId.'\'s phones (one per booking). Then run:  $delCleanup();'.PHP_EOL;
        }
    }
} finally {
    $arm->id = null;
    Carbon::setTestNow();
    Auth::forgetUser();
    date_default_timezone_set($tzBefore);
    $restoreBus();
    app()->forgetInstance('mail.manager');
    Facade::clearResolvedInstance('mail.manager');

    if (! $delDemoStarted) {
        $delCleanup();
    }
}