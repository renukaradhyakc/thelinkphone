<?php

/*
 * Booking cancellation notification checks: CAN-01 to CAN-09.
 *
 * LOCAL DATABASE ONLY. It creates throwaway events and bookings, runs the real cancel code,
 * reads the real user_notifications / outbox rows, then deletes everything it created
 * (even if a test crashes). Pushes are faked during the checks, mail is always faked.
 *
 * Run from the Laravel project root, in a FRESH tinker session (type it, do not paste):
 *     php artisan tinker
 *     >>> include 'booking-cancel-test.php';
 *
 * Do NOT set $ownerId / $bookerId unless you mean to. Defaults: owner 83, booker 101.
 *
 * Real pushes to user 83's devices (small demo, runs after all the checks):
 *     >>> $canKeep = true;
 *     >>> include 'booking-cancel-test.php';
 *     ... check the phones and the inbox ...
 *     >>> $canCleanup();
 *   The demo makes two bookings and cancels them:
 *     - event owned by 83, booker 101 cancels (OTP page steps)  -> 83 gets "booker cancelled"
 *     - event owned by 101, booker 83, owner cancels            -> 83 gets "owner cancelled"
 *   plus 83 gets the "booking created" push for the first booking.
 *   Pushes are sent by the queue worker you already run (queue: database).
 *
 * Real code paths used:
 *   - ScheduleEventRepository::store()            booking creation (fires BookingNotifier::created)
 *   - ScheduleEventRepository::cancelScheduleEvent()   owner cancel (what the owner's cancel endpoint calls)
 *   - ScheduleEventController::checkGivenOTP()   booker cancel on the OTP page (the real method)
 *   - BookingNotifier::sendReminder() and the outbox, run the way notifications:dispatch-due does
 *   - Carbon::setTestNow() to jump time forward for the reminder checks (reset at the end)
 *
 * The OTP-page checks call the real ScheduleEventController::checkGivenOTP() (mail is faked, so no email goes out).
 *
 * Not verified (files not seen yet): OutboxRepository::cancelPendingForBooking, LocationNotifier.
 * CAN-07/CAN-08 and CAN-09 read their behaviour from the database, they do not assume it.
 */

use App\Http\Controllers\ScheduleEventController;
use App\Http\Requests\CreateVerifyOtpRequest;
use App\Mail\CancelEventScheduleMail;
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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Mail;

$ownerId = (int) ($ownerId ?? 83);
$bookerId = (int) ($bookerId ?? 101);
$booker2Id = isset($booker2Id) ? (int) $booker2Id : null;
$canKeep = $canKeep ?? false;

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

$booker2 = $booker2Id
    ? User::find($booker2Id)
    : User::whereNotIn('id', [$ownerId, $bookerId])
        ->whereNotNull('phone_number')->where('phone_number', '!=', '')
        ->limit(300)->get()
        ->first(fn ($u) => $u->phone_number !== $owner->phone_number
            && $u->phone_number !== $booker->phone_number
            && optional($resolver->uniqueUserByPhone($u->phone_number))->id === $u->id);

if (! $booker2) {
    echo 'SKIP  no second booker found. Set $booker2Id to another user with a unique phone number'.PHP_EOL;

    return;
}

$booker2Phone = (string) $booker2->phone_number;
$testUserIds = [$ownerId, $bookerId, $booker2->id];

Auth::setUser($owner);
$authOk = (int) getLogInUserId() === $ownerId;
Auth::forgetUser();

if (! $authOk) {
    echo 'SKIP  getLogInUserId() does not follow Auth::setUser()'.PHP_EOL;

    return;
}

// ---- Fakes: no real mail; pushes are faked while the checks run -----------------------------
$origBus = app(Dispatcher::class);
$restoreBus = function () use ($origBus) {
    app()->instance(Dispatcher::class, $origBus);
    Facade::clearResolvedInstance(Dispatcher::class);
};
Mail::fake();
Bus::fake([SendPushNotificationJob::class]);

// ---- Helpers --------------------------------------------------------------------------------
$tag = substr(uniqid(), -6);
$bookDate = Carbon::now()->addDays(3)->format('Y-m-d');
$tzKey = $owner->timezone;
$tz = ($tzKey !== null && isset(User::TIME_ZONE_ARRAY[$tzKey])) ? User::TIME_ZONE_ARRAY[$tzKey] : config('app.timezone');

$startId = (int) DB::table('user_notifications')->max('id');
$outboxStartId = (int) NotificationOutbox::max('id');
$bookingIds = [];
$eventIds = [];
$lastError = null;
$slotN = 0;
$canDemoStarted = false;

$eventRepo = app(EventRepository::class);
$bookingRepo = app(ScheduleEventRepository::class);
$notifier = app(BookingNotifier::class);
$outboxRepo = app(OutboxRepository::class);

$pushCount = fn () => Bus::dispatched(SendPushNotificationJob::class)->count();

$nextSlot = function () use (&$slotN) {
    $s = Carbon::createFromTime(8, 0)->addMinutes(30 * $slotN++);

    return $s->format('h:i A').' - '.$s->copy()->addMinutes(30)->format('h:i A');
};

$mkEvent = function (User $u, string $label, int $mode = Event::PHONE_CALL) use (&$eventIds, $eventRepo, $tag) {
    Auth::setUser($u);

    try {
        $e = $eventRepo->store([
            'name' => "CAN-TEST $tag $label",
            'event_location' => $mode,
            'event_link' => "can-test-$tag-$label",
            'event_color' => '#63d867',
            'event_type' => Event::FREE,
        ]);
    } finally {
        Auth::forgetUser();
    }

    $eventIds[] = $e->id;

    return $e;
};

$book = function (Event $event, ?string $phone, ?string $slot = null, ?string $date = null) use (
    &$bookingIds, &$lastError, $bookingRepo, $nextSlot, $bookDate
) {
    try {
        $b = $bookingRepo->store([
            'name' => 'CAN Guest',
            'phone_call' => $phone,
            'email' => 'can-guest-'.uniqid().'@example.invalid',
            'schedule_date' => $date ?? $bookDate,
            'description' => 'CAN test booking',
            'user_id' => $event->user_id,
            'event_id' => $event->id,
            'slot_time' => $slot ?? $nextSlot(),
        ]);
        $bookingIds[] = $b->id;
        $lastError = null;

        return $b->fresh();
    } catch (Throwable $e) {
        $lastError = $e->getMessage();

        return null;
    }
};

$setupFailed = function ($b, string $id) use ($report, &$lastError) {
    if ($b) {
        return false;
    }
    $report('INVESTIGATE', "$id could not create its test booking", (string) $lastError);

    return true;
};

// Owner cancels: the real repository method.
$ownerCancel = function ($b) use ($bookingRepo, &$lastError) {
    try {
        $bookingRepo->cancelScheduleEvent('CAN test', $b->id);
        $lastError = null;

        return true;
    } catch (Throwable $e) {
        $lastError = $e->getMessage();

        return false;
    }
};

// Booker cancels on the OTP page: calls the REAL ScheduleEventController::checkGivenOTP(). No copied logic.
$otpError = null;
$otpCancel = function ($b) use (&$otpError) {
    if (empty($b->fresh()->otp)) {
        $b->update(['otp' => (string) random_int(100000, 999999)]);
    }

    $request = CreateVerifyOtpRequest::create('/verify-otp', 'POST', ['otp' => $b->fresh()->otp]);

    try {
        app(ScheduleEventController::class)->checkGivenOTP($request);
        $otpError = null;
    } catch (Throwable $e) {
        $otpError = $e->getMessage();

        return null;
    }

    return EventSchedule::find($b->id);
};
$cancelMails = fn () => Mail::sent(CancelEventScheduleMail::class)->count();

$cancelRows = fn ($b) => DB::table('user_notifications')->where('type', 'booking.cancelled')
    ->where('dedupe_key', 'like', "booking.cancelled:{$b->id}:%")->orderBy('id')->get();
$reminderRows = fn ($b) => DB::table('user_notifications')->where('type', 'booking.reminder')
    ->where('dedupe_key', 'like', "booking.reminder:{$b->id}:%")->get();
$outboxFor = fn ($b) => NotificationOutbox::where('event_schedule_id', $b->id)->where('type', 'booking.reminder')->get();
$pendingFor = fn ($b) => NotificationOutbox::where('event_schedule_id', $b->id)->where('type', 'booking.reminder')
    ->where('status', NotificationOutbox::PENDING)->count();
$status = fn ($b) => (int) EventSchedule::where('id', $b->id)->value('status');

// Same steps as notifications:dispatch-due, for one reminder row.
$dispatch = function (NotificationOutbox $row) use ($notifier, $outboxRepo) {
    $row->update(['status' => NotificationOutbox::CLAIMED, 'claimed_at' => now(), 'attempts' => $row->attempts + 1]);
    $result = $notifier->sendReminder($row->fresh());
    $result === 'sent' ? $outboxRepo->markSent($row) : $outboxRepo->markCancelled($row);

    return $result;
};

$dueRows = fn ($b) => NotificationOutbox::where('event_schedule_id', $b->id)->where('type', 'booking.reminder')
    ->where('status', NotificationOutbox::PENDING)->where('notify_at', '<=', now())->get();

$canCleanup = function () use (&$bookingIds, &$eventIds, $outboxStartId, $testUserIds) {
    foreach ($bookingIds as $id) {
        DB::table('user_notifications')->where(function ($q) use ($id) {
            $q->where('dedupe_key', 'like', "booking.%:$id:%")->orWhere('dedupe_key', 'like', "location.%:$id:%");
        })->delete();
        NotificationOutbox::where('event_schedule_id', $id)->delete();
    }

    NotificationOutbox::where('id', '>', $outboxStartId)->where('type', 'like', 'location.%')
        ->whereIn('user_id', $testUserIds)->delete();

    if ($bookingIds) {
        EventSchedule::whereIn('id', $bookingIds)->delete();
    }

    if ($eventIds) {
        Event::whereIn('id', $eventIds)->get()->each->delete();
    }

    $bookingIds = [];
    $eventIds = [];
    echo 'Deleted the throwaway events, bookings, notifications and outbox rows.'.PHP_EOL;
};

// leftovers from earlier kept or crashed runs (events named "CAN-TEST xxxxxx ...")
$oldEventIds = Event::where('name', 'like', 'CAN-TEST %')->pluck('id');
if ($oldEventIds->isNotEmpty()) {
    $oldBookingIds = EventSchedule::whereIn('event_id', $oldEventIds)->pluck('id');
    foreach ($oldBookingIds as $oldId) {
        DB::table('user_notifications')->where(function ($q) use ($oldId) {
            $q->where('dedupe_key', 'like', "booking.%:$oldId:%")->orWhere('dedupe_key', 'like', "location.%:$oldId:%");
        })->delete();
        NotificationOutbox::where('event_schedule_id', $oldId)->delete();
    }
    EventSchedule::whereIn('id', $oldBookingIds)->delete();
    Event::whereIn('id', $oldEventIds)->get()->each->delete();
    echo 'Removed leftovers of earlier runs: '.$oldEventIds->count().' event(s), '.$oldBookingIds->count().' booking(s)'.PHP_EOL;
}

echo "Owner $ownerId, booker $bookerId (…".substr($bookerPhone, -4)."), booker2 {$booker2->id} (…".substr($booker2Phone, -4).'), date '.$bookDate.', owner time zone '.$tz.PHP_EOL.PHP_EOL;

try {
    $eventA = $mkEvent($owner, 'A');          // owned by $ownerId, booker is $bookerId
    $eventB = $mkEvent($booker, 'B');         // owned by $bookerId, booker is $ownerId (83 as booker)

    // ============ CAN-01 owner cancels a confirmed booking ============
    $a1 = $book($eventA, $bookerPhone);
    if (! $setupFailed($a1, 'CAN-01')) {
        $check('CAN-01 setup: the booking is confirmed', (int) $a1->status === EventSchedule::BOOKED);
        $ok = $ownerCancel($a1);
        $rows = $cancelRows($a1);
        $check('CAN-01 the booking is cancelled', $ok && $status($a1) === EventSchedule::CANCELLED, (string) $lastError);
        $check('CAN-01 booker receives booking.cancelled.owner_cancelled',
            $rows->count() === 1 && (int) $rows->first()->user_id === $bookerId && $rows->first()->reason === 'owner_cancelled',
            'rows='.$rows->count().', reason='.($rows->first()->reason ?? 'none'));
        $check('CAN-01 the owner is not notified about their own cancellation', $rows->where('user_id', $ownerId)->count() === 0);
    }

    if ($ownerPhoneOk) {
        $b1 = $book($eventB, $ownerPhone);
        if (! $setupFailed($b1, 'CAN-01 (user '.$ownerId.' as booker)')) {
            $ownerCancel($b1);
            $rows = $cancelRows($b1);
            $check('CAN-01 same check with user '.$ownerId.' as the booker (event owned by '.$bookerId.')',
                $rows->count() === 1 && (int) $rows->first()->user_id === $ownerId && $rows->first()->reason === 'owner_cancelled',
                'rows='.$rows->count());
        }
    } else {
        $report('SKIP', 'CAN-01 variant with user '.$ownerId.' as booker (its phone does not resolve to only that user)');
    }

    // ============ CAN-02 booker cancels through the OTP page ============
    $a2 = $book($eventA, $bookerPhone);
    if (! $setupFailed($a2, 'CAN-02')) {
        $found = $otpCancel($a2);
        $rows = $cancelRows($a2);
        $check('CAN-02 the real checkGivenOTP ran without error', $otpError === null, (string) $otpError);
        $check('CAN-02 the OTP lookup finds the booking and cancels it', $found && $status($a2) === EventSchedule::CANCELLED);
        $check('CAN-02 owner receives booking.cancelled.booker_cancelled',
            $rows->count() === 1 && (int) $rows->first()->user_id === $ownerId && $rows->first()->reason === 'booker_cancelled',
            'rows='.$rows->count().', reason='.($rows->first()->reason ?? 'none'));
        $check('CAN-02 the booker is not notified about their own cancellation', $rows->where('user_id', $bookerId)->count() === 0);
    }

    // ============ CAN-03 booker cancels an already-cancelled booking ============
    $a3 = $book($eventA, $bookerPhone);
    if (! $setupFailed($a3, 'CAN-03')) {
        $otpCancel($a3);
        $first = $cancelRows($a3)->count();
        $mailsBefore = $cancelMails();
        $otpCancel($a3);
        $check('CAN-03 cancelling again on the OTP page adds no second notification',
            $first === 1 && $cancelRows($a3)->count() === 1, 'rows after 1st='.$first.', after 2nd='.$cancelRows($a3)->count());
        $check('CAN-03 the second submit sends no second cancellation email', $cancelMails() === $mailsBefore,
            'emails sent by the 2nd submit='.($cancelMails() - $mailsBefore).(strlen((string) $otpError) ? ', error: '.$otpError : ''));
    }

    $a3b = $book($eventA, $bookerPhone);
    if (! $setupFailed($a3b, 'CAN-03')) {
        $ownerCancel($a3b);
        $otpCancel($a3b);
        $toOwner = $cancelRows($a3b)->where('user_id', $ownerId)->count();
        $report($toOwner === 0 ? 'PASS' : 'INVESTIGATE',
            'CAN-03 booker cancels on the OTP page after the owner already cancelled',
            $toOwner === 0 ? 'owner was not told again' : 'the owner gets a "booker cancelled" notification for a booking they cancelled themselves; consider skipping the notification when the booking was already cancelled');
    }

    // ============ CAN-04 cancellation request submitted twice ============
    $a4 = $book($eventA, $bookerPhone);
    if (! $setupFailed($a4, 'CAN-04')) {
        $pushBefore = $pushCount();
        $ownerCancel($a4);
        $ownerCancel($a4);
        $rows = $cancelRows($a4);
        $check('CAN-04 owner cancels twice: only one notification', $rows->count() === 1, 'rows='.$rows->count().(strlen((string) $lastError) ? ', last error: '.$lastError : ''));
        $check('CAN-04 only one push job is queued', ($pushCount() - $pushBefore) === 1, 'push jobs='.($pushCount() - $pushBefore));
    }

    // ============ CAN-05 one of several bookers cancels ============
    $a5 = $book($eventA, $bookerPhone);
    $a6 = $book($eventA, $booker2Phone);
    if (! $setupFailed($a5, 'CAN-05') && ! $setupFailed($a6, 'CAN-05')) {
        $pendingBefore = $pendingFor($a6);
        $otpCancel($a5);
        $rows5 = $cancelRows($a5);
        $check('CAN-05 only the owner is notified when booker 1 cancels',
            $rows5->count() === 1 && (int) $rows5->first()->user_id === $ownerId, 'rows='.$rows5->count());
        $check('CAN-05 the other booker gets nothing', $cancelRows($a6)->count() === 0 && $rows5->where('user_id', $booker2->id)->count() === 0);
        $check('CAN-05 the other booking is still confirmed', $status($a6) === EventSchedule::BOOKED);
        if ($pendingBefore > 0) {
            $check('CAN-05 the other booking keeps its pending reminders', $pendingFor($a6) === $pendingBefore, "pending $pendingBefore -> ".$pendingFor($a6));
        } else {
            $report('SKIP', 'CAN-05 reminder check (the other booking had no pending reminders to begin with)');
        }
    }

    // ============ CAN-06 booking cancelled after its scheduled end ============
    $a7 = $book($eventA, $bookerPhone);
    if (! $setupFailed($a7, 'CAN-06')) {
        EventSchedule::where('id', $a7->id)->update(['schedule_date' => Carbon::now()->subDay()->format('Y-m-d')]); // the slot is now in the past
        $ownerCancel($a7->fresh());
        $check('CAN-06 no pending reminder is left for an ended booking', $pendingFor($a7) === 0, 'pending='.$pendingFor($a7));
        $check('CAN-06 no reminder notification was created', $reminderRows($a7)->count() === 0);
        $told = $cancelRows($a7)->where('user_id', $bookerId)->count();
        $report($told === 0 ? 'PASS' : 'INVESTIGATE', 'CAN-06 cancelling a booking whose slot already ended',
            $told === 0 ? 'booker was not notified'
                : 'the booker still gets "booking cancelled" for a slot that already took place. The event-delete path skips ended bookings; the cancel path does not. Decide if that is wanted');
    }

    // ============ CAN-07 booking cancelled before reminder execution ============
    $a8 = $book($eventA, $bookerPhone);
    if (! $setupFailed($a8, 'CAN-07')) {
        $pendingBefore = $pendingFor($a8);
        $check('CAN-07 setup: reminders are queued for the booking', $pendingBefore > 0, 'pending='.$pendingBefore);
        $ownerCancel($a8);
        $allRows = $outboxFor($a8);
        $check('CAN-07 cancelling leaves no pending reminder', $pendingFor($a8) === 0,
            'statuses='.$allRows->pluck('status')->unique()->implode(','));
        $start = $notifier->startUtc($a8->fresh());
        if ($start) {
            Carbon::setTestNow($start->copy()->subMinutes(15)->addSeconds(10));
            $due = $dueRows($a8)->count();
            Carbon::setTestNow();
            $check('CAN-07 when the 15-minute reminder time arrives nothing is due for this booking', $due === 0, 'due rows='.$due);
        }
        $leftover = 0;
        foreach ($allRows as $row) {
            if ($dispatch($row) === 'sent') {
                $leftover++;
            }
        }
        $check('CAN-07 even if a worker picked an old row up, nothing is sent', $leftover === 0 && $reminderRows($a8)->count() === 0,
            'sent='.$leftover.', reminder notifications='.$reminderRows($a8)->count());
    }

    // ============ CAN-08 booking cancelled close to reminder execution ============
    $mkNear = function (int $minutes) use ($tz) {
        $s = Carbon::now($tz)->addMinutes($minutes)->second(0);

        return [$s->format('h:i A').' - '.$s->copy()->addMinutes(30)->format('h:i A'), $s->format('Y-m-d')];
    };

    [$slotC, $dateC] = $mkNear(16);
    [$slotX, $dateX] = $mkNear(17);
    [$slotY, $dateY] = $mkNear(18);
    $ctrl = $book($eventA, $bookerPhone, $slotC, $dateC);      // control: stays booked
    $cancelled = $book($eventA, $bookerPhone, $slotX, $dateX); // cancelled while its 15-minute reminder is a minute or two away
    $claimed = $book($eventA, $bookerPhone, $slotY, $dateY);   // already "claimed" by a worker when it is cancelled

    if (! $setupFailed($ctrl, 'CAN-08') && ! $setupFailed($cancelled, 'CAN-08') && ! $setupFailed($claimed, 'CAN-08')) {
        $report($pendingFor($ctrl) > 0 ? 'OK' : 'INVESTIGATE', 'CAN-08 setup: reminders queued for the near-term bookings',
            'control='.$pendingFor($ctrl).', to-cancel='.$pendingFor($cancelled).', claimed='.$pendingFor($claimed));

        NotificationOutbox::where('event_schedule_id', $claimed->id)->where('type', 'booking.reminder')
            ->update(['status' => NotificationOutbox::CLAIMED, 'claimed_at' => now()]);

        $ownerCancel($cancelled);
        $ownerCancel($claimed);

        Carbon::setTestNow(Carbon::now()->addMinutes(3)); // past every 15-minute reminder time, before the slots start

        $sentCtrl = 0;
        foreach ($dueRows($ctrl) as $row) {
            if ($dispatch($row) === 'sent') {
                $sentCtrl++;
            }
        }
        $dueCancelled = $dueRows($cancelled)->count();
        $sentCancelled = 0;
        foreach ($outboxFor($cancelled)->merge($outboxFor($claimed)) as $row) {
            if ($dispatch($row) === 'sent') {
                $sentCancelled++;
            }
        }

        Carbon::setTestNow();

        $check('CAN-08 control: a booking that was not cancelled does get its reminder (the test is meaningful)', $sentCtrl > 0, 'sent='.$sentCtrl);
        $check('CAN-08 cancelled booking: nothing is due once its reminder time passes', $dueCancelled === 0, 'due='.$dueCancelled);
        $check('CAN-08 no reminder after cancellation, even for a row a worker had already claimed',
            $sentCancelled === 0 && $reminderRows($cancelled)->count() === 0 && $reminderRows($claimed)->count() === 0,
            'sent='.$sentCancelled.', notifications='.($reminderRows($cancelled)->count() + $reminderRows($claimed)->count()));
    }

    // ============ CAN-09 booking cancelled while live location is active ============
    try {
        $eventL = $mkEvent($owner, 'L', Event::IN_PERSON_MEETING);
        $eventL->location()->create([
            'location_type' => Location::LIVE,
            'latitude' => 12.9716,
            'longitude' => 77.5946,
            'accuracy' => 10,
            'address' => null,
            'is_live_sharing_active' => true,
            'live_started_at' => now(),
        ]);

        $l1 = $book($eventL, $bookerPhone);
        $l2 = $book($eventL, $booker2Phone);

        if (! $setupFailed($l1, 'CAN-09') && ! $setupFailed($l2, 'CAN-09')) {
            $loc = app(LocationNotifier::class);
            $startedAt = now();
            $loc->started($eventL->fresh(), $startedAt);

            $locRows = fn (string $type) => DB::table('user_notifications')->where('id', '>', $startId)->where('type', $type)->get();
            $started = $locRows('location.started');
            $report($started->where('user_id', $bookerId)->count() >= 1 && $started->where('user_id', $booker2->id)->count() >= 1 ? 'OK' : 'INVESTIGATE',
                'CAN-09 setup: both bookers were told sharing started', 'started rows='.$started->count());

            $ownerCancel($l1);
            $session = optional($eventL->fresh()->location);
            $check('CAN-09 cancelling one booking does not stop the owner\'s live session', (bool) $session->is_live_sharing_active);
            $check('CAN-09 the cancelled booker gets the cancellation notification',
                $cancelRows($l1)->where('user_id', $bookerId)->where('reason', 'owner_cancelled')->count() === 1);
            $check('CAN-09 the other booker gets no cancellation notification', $cancelRows($l1)->where('user_id', $booker2->id)->count() === 0);

            $loc->stopped($eventL->fresh(), $startedAt);
            $stopped = $locRows('location.stopped');
            $toCancelled = $stopped->where('user_id', $bookerId)->count();
            $toOther = $stopped->where('user_id', $booker2->id)->count();
            $report($toCancelled === 0 && $toOther >= 1 ? 'PASS' : 'INVESTIGATE',
                'CAN-09 when sharing stops later, only the booker who is still booked is told',
                "cancelled booker=$toCancelled, other booker=$toOther".($toCancelled > 0 ? ' (the cancelled booker should not be notified; send LocationNotifier to confirm)' : ''));
        }
    } catch (Throwable $e) {
        $report('INVESTIGATE', 'CAN-09 could not run against LocationNotifier / Location', substr($e->getMessage(), 0, 160));
    }

    // ============ real-push demo for user 83 (only with $canKeep = true) ============
    $canCleanup(); // always remove the data from the checks above

    if ($canKeep) {
        $canDemoStarted = true;
        $restoreBus();

        if (app(Dispatcher::class) instanceof \Illuminate\Support\Testing\Fakes\BusFake) {
            echo PHP_EOL.'FAIL  the real dispatcher could not be restored, so no real pushes can be sent. Start a new tinker session and run again.'.PHP_EOL;
        } else {
            $demoStart = (int) DB::table('user_notifications')->max('id');
            $dA = $mkEvent($owner, 'DA');
            $dB = $mkEvent($booker, 'DB');

            $d1 = $book($dA, $bookerPhone);
            if ($d1) {
                $otpCancel($d1);
            }

            if ($ownerPhoneOk) {
                $d2 = $book($dB, $ownerPhone);
                if ($d2) {
                    $ownerCancel($d2);
                }
            }

            echo PHP_EOL.'--- Real push demo: inbox rows for user '.$ownerId.' ---'.PHP_EOL;
            $demoRows = DB::table('user_notifications')->where('id', '>', $demoStart)->where('user_id', $ownerId)
                ->where('type', 'like', 'booking.%')->orderBy('id')->get(['id', 'type', 'reason', 'title']);
            foreach ($demoRows as $r) {
                $push = ($r->type === 'booking.cancelled' || $r->reason === 'owner') ? 'push' : 'inbox only';
                echo "  #{$r->id}  {$r->type}.{$r->reason}  [$push]  \"{$r->title}\"".PHP_EOL;
            }
            echo PHP_EOL.'Queue: '.config('queue.default').'. Your notifications:tick loop sends them within a minute.'.PHP_EOL;
            echo 'Expect up to 3 pushes on user '.$ownerId.'\'s phones. Then run:  $canCleanup();'.PHP_EOL;
        }
    }
} finally {
    Carbon::setTestNow();
    Auth::forgetUser();
    $restoreBus();
    app()->forgetInstance('mail.manager');
    Facade::clearResolvedInstance('mail.manager');

    if (! $canDemoStarted) {
        $canCleanup();
    }
}
