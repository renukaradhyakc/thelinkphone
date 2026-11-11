<?php

/*
 * Event creation and free-booking notification checks: EVT-01 to EVT-10.
 *
 * LOCAL DATABASE ONLY. It creates a throwaway event and bookings for existing users,
 * runs the real code paths, reads the real user_notifications rows, then deletes
 * everything it created (even if a test crashes).
 *
 * Run from the Laravel project root, in a fresh tinker session:
 *     php artisan tinker
 *     >>> include 'booking-created-test.php';
 *
 * Optional settings, set before the include:
 *     $ownerId = 83;       // the event owner (default 83)
 *     $bookerId = 101;     // first booker, a CallALink user (default 101)
 *     $booker2Id = 102;    // second booker; if omitted, one is picked automatically
 *     $evtKeep = true;     // keep the rows; run $evtCleanup(); when done
 *
 * Mail is always faked (no emails). The push job is faked in a normal run, so no pushes go out.
 * With $evtKeep = true the push job is REAL: the notifications are sent to the devices and the
 * rows stay in place. If QUEUE_CONNECTION is not "sync", run a worker in a second terminal:
 *     php artisan queue:work --queue=notifications --stop-when-empty
 * Check the phones, then run  $evtCleanup();  in the same tinker session to delete everything.
 *
 * Real code paths used:
 *   - EventRepository::store()                    (what EventController::store calls)
 *   - ScheduleEventRepository::store()            (what ScheduleEventController::store calls;
 *                                                  it also fires BookingNotifier::created())
 *   - RecipientResolver::uniqueUserByPhone()      (how the booker is found from event_schedules.phone_call)
 *
 * Resolver rule (confirmed from the source): users.phone_number is compared as an exact
 * string, and zero or several matches mean nobody is notified. PhoneNumberNormalizer is not used.
 *
 * Limits: the "slot already booked" guard lives in the controller, not the repository,
 * so EVT-10 replays that query and reports what the repository itself does.
 */

use App\Models\Event;
use App\Models\EventSchedule;
use App\Models\User;
use App\Modules\Notifications\Jobs\SendPushNotificationJob;
use App\Modules\Notifications\Models\NotificationOutbox;
use App\Modules\Notifications\Services\BookingNotifier;
use App\Modules\Notifications\Services\RecipientResolver;
use App\Repositories\EventRepository;
use App\Repositories\ScheduleEventRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

$ownerId = (int) ($ownerId ?? 83);
$bookerId = (int) ($bookerId ?? 101);
$booker2Id = isset($booker2Id) ? (int) $booker2Id : null;
$evtKeep = $evtKeep ?? false;

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

if (trim($bookerPhone) === '' || optional($resolver->uniqueUserByPhone($bookerPhone))->id !== $bookerId) {
    echo "SKIP  user $bookerId needs a phone number that resolves to only that user".PHP_EOL;

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

if (! $booker2) {
    echo 'SKIP  no second booker found. Set $booker2Id to another user with a unique phone number'.PHP_EOL;

    return;
}

$booker2Phone = (string) $booker2->phone_number;

Auth::setUser($owner);

if ((int) getLogInUserId() !== $ownerId) {
    echo 'SKIP  getLogInUserId() does not follow Auth::setUser()'.PHP_EOL;
    Auth::forgetUser();

    return;
}

// ---- Fakes: no real mail, no real push -----------------------------------------------------
$fakePush = ! $evtKeep; // $evtKeep = true means: real pushes, rows kept
$origBus = app(\Illuminate\Contracts\Bus\Dispatcher::class); // restored in the finally block
Mail::fake();
if ($fakePush) {
    Bus::fake([SendPushNotificationJob::class]);
}

// ---- Helpers --------------------------------------------------------------------------------
$tag = substr(uniqid(), -6);
$bookDate = Carbon::now()->addDays(3)->format('Y-m-d');
$slots = ['10:00 AM - 10:30 AM', '11:00 AM - 11:30 AM', '12:00 PM - 12:30 PM', '01:00 PM - 01:30 PM',
    '02:00 PM - 02:30 PM', '03:00 PM - 03:30 PM', '04:00 PM - 04:30 PM', '05:00 PM - 05:30 PM'];

$startId = (int) DB::table('user_notifications')->max('id');
$outboxStartId = (int) NotificationOutbox::max('id');
$bookingIds = [];
$eventIds = [];
$extraUserIds = [];
$lastError = null;

$eventRepo = app(EventRepository::class);
$bookingRepo = app(ScheduleEventRepository::class);
$notifier = app(BookingNotifier::class);

$pushCount = fn () => $fakePush ? Bus::dispatched(SendPushNotificationJob::class)->count() : 0;
$rowsFor = fn ($b) => DB::table('user_notifications')
    ->where('type', 'booking.created')
    ->where('dedupe_key', 'like', "booking.created:{$b->id}:%")
    ->orderBy('id')->get();
$allBookingRowsSince = fn () => DB::table('user_notifications')
    ->where('id', '>', $startId)->where('type', 'like', 'booking.%')->count();

// Same call ScheduleEventController::store makes after its checks.
$book = function (Event $event, string $slot, ?string $phone, string $name = 'EVT Guest', ?string $email = null) use (
    &$bookingIds, &$lastError, $bookingRepo, $ownerId, $bookDate
) {
    $input = [
        'name' => $name,
        'phone_call' => $phone,
        'email' => $email ?? 'evt-guest-'.uniqid().'@example.invalid',
        'schedule_date' => $bookDate,
        'description' => 'EVT test booking',
        'user_id' => $ownerId,
        'event_id' => $event->id,
        'slot_time' => $slot,
    ];

    for ($attempt = 0; $attempt < 2; $attempt++) {
        try {
            $b = $bookingRepo->store($input);
            $bookingIds[] = $b->id;
            $lastError = null;

            return $b->fresh();
        } catch (Throwable $e) {
            $lastError = $e->getMessage();

            if ($attempt === 0 && $input['phone_call'] === null) {
                $input['phone_call'] = ''; // column may be NOT NULL
                continue;
            }

            return null;
        }
    }

    return null;
};

$evtCleanup = function () use (&$bookingIds, &$eventIds, &$extraUserIds, $outboxStartId, $startId, $evtKeep) {
    foreach ($bookingIds as $id) {
        DB::table('user_notifications')->where('dedupe_key', 'like', "booking.%:$id:%")->delete();
        NotificationOutbox::where('event_schedule_id', $id)->delete();
    }

    NotificationOutbox::where('id', '>', $outboxStartId)->whereIn('event_schedule_id', $bookingIds ?: [0])->delete();
    EventSchedule::whereIn('id', $bookingIds)->delete();
    Event::whereIn('id', $eventIds)->get()->each->delete();

    if ($extraUserIds) {
        DB::table('user_notifications')->whereIn('user_id', $extraUserIds)->where('id', '>', $startId)->delete();
        User::whereIn('id', $extraUserIds)->delete();
    }
};

// leftovers from earlier kept or crashed runs (events named "EVT-TEST xxxxxx")
$oldEventIds = Event::where('name', 'like', 'EVT-TEST %')->pluck('id');
if ($oldEventIds->isNotEmpty()) {
    $oldBookingIds = EventSchedule::whereIn('event_id', $oldEventIds)->pluck('id');
    foreach ($oldBookingIds as $oldId) {
        DB::table('user_notifications')->where('dedupe_key', 'like', "booking.%:$oldId:%")->delete();
        NotificationOutbox::where('event_schedule_id', $oldId)->delete();
    }
    EventSchedule::whereIn('id', $oldBookingIds)->delete();
    Event::whereIn('id', $oldEventIds)->get()->each->delete();
    echo 'Removed leftovers of earlier runs: '.$oldEventIds->count().' event(s), '.$oldBookingIds->count().' booking(s)'.PHP_EOL;
}

echo "Owner $ownerId, booker $bookerId (…".substr($bookerPhone, -4)."), booker2 {$booker2->id} (…".substr($booker2Phone, -4).'), date '.$bookDate.PHP_EOL.PHP_EOL;

try {
    // ============ EVT-01 owner creates an event ============
    $created = null;
    try {
        $created = $eventRepo->store([
            'name' => "EVT-TEST $tag",
            'event_location' => Event::PHONE_CALL,
            'event_link' => "evt-test-$tag",
            'event_color' => '#63d867',
            'event_type' => Event::FREE,
        ]);
        $eventIds[] = $created->id;
    } catch (Throwable $e) {
        $report('INVESTIGATE', 'EVT-01 could not create the event through EventRepository::store', $e->getMessage());
    }

    if ($created) {
        $eventA = $created;
        $check('EVT-01 event is created for the owner', (int) $eventA->user_id === $ownerId);
        $check('EVT-01 creating an event sends no booking notification',
            $allBookingRowsSince() === 0 && NotificationOutbox::where('id', '>', $outboxStartId)->count() === 0,
            'booking rows='.$allBookingRowsSince());
    } else {
        // fall back to a direct row so the booking tests can still run
        $eventA = Event::create([
            'name' => "EVT-TEST $tag", 'event_location' => Event::PHONE_CALL, 'event_link' => "evt-test-$tag",
            'event_color' => '#63d867', 'user_id' => $ownerId, 'slot_time' => '30', 'status' => 1,
            'event_type' => Event::FREE, 'date_range' => 1, 'schedule_days' => '60',
        ]);
        $eventIds[] = $eventA->id;
        $report('SKIP', 'EVT-01 assertions skipped (fallback event used for the booking tests)');
    }

    // ============ EVT-02 free booking by a CallALink user ============
    $pushBefore = $pushCount();
    $b1 = $book($eventA, $slots[0], $bookerPhone, 'EVT Booker One');

    if (! $b1) {
        $report('INVESTIGATE', 'EVT-02 booking could not be created', (string) $lastError);
        $report('SKIP', 'EVT-03 to EVT-10 need a working booking, stopping here');

        return;
    }

    $rows1 = $rowsFor($b1);
    $ownerRow = $rows1->firstWhere('user_id', $ownerId);
    $bookerRow = $rows1->firstWhere('user_id', $bookerId);

    $check('EVT-02 the free booking is confirmed (status BOOKED)', (int) $b1->status === EventSchedule::BOOKED, 'status='.$b1->status);
    $check('EVT-02 owner receives booking.created.owner', $ownerRow && $ownerRow->reason === 'owner', 'reason='.($ownerRow->reason ?? 'none'));
    $check('EVT-02 booker receives booking.created.booker', $bookerRow && $bookerRow->reason === 'booker', 'reason='.($bookerRow->reason ?? 'none'));
    $check('EVT-02 exactly two inbox rows for the booking', $rows1->count() === 2, 'rows='.$rows1->count());
    if ($fakePush) {
        $check('EVT-02 owner row has a push job, booker row is inbox-only',
            ($pushCount() - $pushBefore) === 1, 'push jobs='.($pushCount() - $pushBefore).' (0 means the owner turned bookings push off)');
    } else {
        $report('SKIP', 'EVT-02 push job count (keep mode sends real pushes, check the phones instead)');
    }
    $check('EVT-02 rows point at this booking',
        $rows1->every(fn ($r) => (int) (json_decode((string) $r->data, true)['event_schedule_id'] ?? 0) === $b1->id));

    // ============ EVT-03 two different bookers on the same event ============
    $b2 = $book($eventA, $slots[1], $booker2Phone, 'EVT Booker Two');
    $rows2 = $b2 ? $rowsFor($b2) : collect();
    $check('EVT-03 second booker receives only their own confirmation',
        $b2 && $rows2->where('user_id', $booker2->id)->where('reason', 'booker')->count() === 1
            && $rows2->where('user_id', $bookerId)->count() === 0,
        'rows='.$rows2->count().($b2 ? '' : ', '.$lastError));
    $ownerBookingRows = $b2 ? DB::table('user_notifications')->where('user_id', $ownerId)->where('type', 'booking.created')
        ->whereIn('dedupe_key', ["booking.created:{$b1->id}:$ownerId", "booking.created:{$b2->id}:$ownerId"])->count() : 0;
    $check('EVT-03 owner receives both booking notifications', $ownerBookingRows === 2, 'owner rows='.$ownerBookingRows);
    $check('EVT-03 first booker was not notified about the second booking',
        $b2 && DB::table('user_notifications')->where('user_id', $bookerId)->where('dedupe_key', 'like', "booking.created:{$b2->id}:%")->count() === 0);

    // ============ EVT-04 same user books multiple slots ============
    $b3 = $book($eventA, $slots[2], $bookerPhone, 'EVT Booker One');
    $b4 = $book($eventA, $slots[3], $bookerPhone, 'EVT Booker One');
    $okLink = true;
    foreach ([$b1, $b3, $b4] as $bk) {
        $r = $bk ? DB::table('user_notifications')->where('user_id', $bookerId)->where('dedupe_key', "booking.created:{$bk->id}:$bookerId")->first() : null;
        $okLink = $okLink && $r && (int) (json_decode((string) $r->data, true)['event_schedule_id'] ?? 0) === $bk->id;
    }
    $check('EVT-04 each of the booker\'s three bookings has its own notification tied to that booking id',
        $b3 && $b4 && $okLink);
    $keys = DB::table('user_notifications')->where('user_id', $bookerId)->where('type', 'booking.created')
        ->whereIn('dedupe_key', array_map(fn ($b) => "booking.created:{$b->id}:$bookerId", array_filter([$b1, $b3, $b4])))->pluck('dedupe_key');
    $check('EVT-04 the dedupe keys are all different', $keys->count() === 3 && $keys->unique()->count() === 3, 'keys='.$keys->count());

    // ============ EVT-05 booking by someone without a CallALink account (no phone at all) ============
    $before = DB::table('user_notifications')->where('id', '>', $startId)->where('type', 'booking.created')->count();
    $b5 = $book($eventA, $slots[4], null, 'EVT Walk-in Guest');
    $rows5 = $b5 ? $rowsFor($b5) : collect();
    $check('EVT-05 guest without a phone is booked, only the owner gets an inbox row',
        $b5 && $rows5->count() === 1 && (int) $rows5->first()->user_id === $ownerId,
        'rows='.$rows5->count().($b5 ? '' : ', '.$lastError));
    $orphans = DB::table('user_notifications')->where('id', '>', $startId)->where('type', 'booking.created')
        ->whereNotIn('user_id', User::pluck('id'))->count();
    $check('EVT-05 no notification row points at a missing user', $orphans === 0, 'orphans='.$orphans);

    // ============ EVT-06 phone matches exactly one user ============
    $check('EVT-06 the resolver finds exactly the booker for the stored phone',
        optional($resolver->uniqueUserByPhone($bookerPhone))->id === $bookerId);
    $check('EVT-06 the notification went to that user and not to the other booker',
        $rows1->where('user_id', $bookerId)->count() === 1 && $rows1->where('user_id', $booker2->id)->count() === 0);

    // ============ EVT-07 phone matches nobody ============
    $nonUser = '9000000007';
    $skipNonUser = $resolver->uniqueUserByPhone($nonUser) !== null;
    if ($skipNonUser) {
        $report('SKIP', 'EVT-07 test number '.$nonUser.' belongs to a real user, change it in the script');
    } else {
        $b7 = $book($eventA, $slots[5], $nonUser, 'EVT No Account');
        $rows7 = $b7 ? $rowsFor($b7) : collect();
        $check('EVT-07 unknown number: booking is saved and only the owner is notified',
            $b7 && $rows7->count() === 1 && (int) $rows7->first()->user_id === $ownerId,
            'rows='.$rows7->count().($b7 ? '' : ', '.$lastError));
    }

    // ============ EVT-08 phone matches more than one user ============
    $dup = null;
    try {
        $dup = $booker->replicate();
        $dup->email = "evt-dup-$tag@example.invalid";
        $dup->phone_number = $bookerPhone;
        $dup->save();
        $extraUserIds[] = $dup->id;
    } catch (Throwable $e) {
        $dup = null;
        $msg = $e->getMessage();
        $isUnique = stripos($msg, 'duplicate') !== false || stripos($msg, 'unique') !== false;
        $report($isUnique && stripos($msg, 'phone') !== false ? 'PASS' : 'INVESTIGATE',
            'EVT-08 could not create a second user with the same phone',
            ($isUnique && stripos($msg, 'phone') !== false ? 'the database already forbids it: ' : 'check why: ').substr($msg, 0, 120));
    }

    if ($dup) {
        $ambiguous = $resolver->uniqueUserByPhone($bookerPhone);
        $b8 = $book($eventA, $slots[6], $bookerPhone, 'EVT Ambiguous');
        $rows8 = $b8 ? $rowsFor($b8) : collect();
        $check('EVT-08 the resolver returns nobody when two users share the number', $ambiguous === null, 'resolved='.($ambiguous->id ?? 'none'));
        $check('EVT-08 neither user gets a booker notification; the owner still does',
            $b8 && $rows8->whereIn('user_id', [$bookerId, $dup->id])->count() === 0 && $rows8->where('user_id', $ownerId)->count() === 1,
            'rows='.$rows8->count().($b8 ? '' : ', '.$lastError));
        User::where('id', $dup->id)->delete();
        $extraUserIds = array_diff($extraUserIds, [$dup->id]);
    }

    // ============ EVT-09 invalid or missing phone data ============
    $junk = ['abc', '123', '   ', '0000000000', substr($bookerPhone, 0, 5), substr($bookerPhone, 0, -1)];
    $leaks = [];
    foreach ($junk as $i => $bad) {
        $bx = $book($eventA, $slots[7], $bad, 'EVT Bad Phone');
        if ($bx === null) {
            $leaks[] = "'".$bad."' rejected: ".substr((string) $lastError, 0, 60);
            continue;
        }
        $rx = $rowsFor($bx);
        if ($rx->where('user_id', '!=', $ownerId)->count() > 0) {
            $leaks[] = "'".$bad."' notified user(s) ".$rx->where('user_id', '!=', $ownerId)->pluck('user_id')->implode(',');
        }
        // free the slot so the next junk value can reuse it
        EventSchedule::where('id', $bx->id)->update(['status' => EventSchedule::CANCELLED]);
        DB::table('user_notifications')->where('dedupe_key', 'like', "booking.created:{$bx->id}:%")->delete();
    }
    $wrong = array_filter($leaks, fn ($l) => str_contains($l, 'notified'));
    $check('EVT-09 invalid, partial or empty phone values notify nobody except the owner', count($wrong) === 0, implode(' | ', $leaks) ?: 'all junk values handled');

    // ============ EVT-06/09 extra: same number, different formatting ============
    // RecipientResolver matches users.phone_number EXACTLY and does not use PhoneNumberNormalizer,
    // so "+91..." or "98765 43210" for a user stored as "9876543210" resolves to nobody.
    $last10 = app(\App\Services\PhoneNumberNormalizer::class)->normalize($bookerPhone);
    $variants = array_values(array_unique(array_filter([
        '+91'.$last10,
        '91'.$last10,
        substr($last10, 0, 5).' '.substr($last10, 5),
    ], fn ($v) => $v !== $bookerPhone)));
    $missed = [];
    foreach ($variants as $variant) {
        $bv = $book($eventA, $slots[7], $variant, 'EVT Format Variant');
        if ($bv === null) {
            $missed[] = "'$variant' rejected: ".substr((string) $lastError, 0, 50);
            continue;
        }
        if ($rowsFor($bv)->where('user_id', $bookerId)->count() === 0) {
            $missed[] = "'$variant' did not reach the booker";
        }
        EventSchedule::where('id', $bv->id)->update(['status' => EventSchedule::CANCELLED]);
        DB::table('user_notifications')->where('dedupe_key', 'like', "booking.created:{$bv->id}:%")->delete();
    }
    if ($missed) {
        $report('INVESTIGATE', 'EVT-06 the same number written in another format does not reach the booker',
            implode(' | ', $missed).'. Safe (nobody wrong is notified), but a booking whose phone_call differs in format from users.phone_number gets no booker notification. The web flow copies the logged-in user\'s own phone_number, so it matches; any other path (API, imports, old rows) may not. Decide whether the resolver should compare normalized numbers');
    } else {
        $check('EVT-06 the same number in other formats still reaches the booker', true);
    }

    // ============ EVT-10 duplicate booking request ============
    $beforeRows = $rowsFor($b1)->count();
    $notifier->created($b1->fresh());
    $notifier->created($b1->fresh());
    $check('EVT-10 re-running the booking notification for the same booking adds no rows',
        $rowsFor($b1)->count() === $beforeRows, 'rows '.$beforeRows.' -> '.$rowsFor($b1)->count());

    $stillBooked = EventSchedule::whereEventId($eventA->id)->whereDate('schedule_date', '=', $bookDate)
        ->where('slot_time', '=', $slots[0])->where('status', '!=', EventSchedule::CANCELLED)->exists();
    $check('EVT-10 the controller\'s "already booked" check would reject the same slot', $stillBooked);

    $countBefore = EventSchedule::whereEventId($eventA->id)->where('slot_time', $slots[0])->where('status', '!=', EventSchedule::CANCELLED)->count();
    $dupBooking = $book($eventA, $slots[0], $bookerPhone, 'EVT Booker One');
    $countAfter = EventSchedule::whereEventId($eventA->id)->where('slot_time', $slots[0])->where('status', '!=', EventSchedule::CANCELLED)->count();

    if ($dupBooking === null) {
        $check('EVT-10 the database / repository itself rejects a second booking of the same slot', true, substr((string) $lastError, 0, 100));
    } else {
        $report('INVESTIGATE', 'EVT-10 the repository accepted a second booking of the same slot',
            "active bookings for the slot: $countBefore -> $countAfter. Only the controller checks for this, so two simultaneous requests could both pass. Consider a unique index on (event_id, schedule_date, slot_time) for non-cancelled bookings");
        $second = $rowsFor($dupBooking);
        $check('EVT-10 even so, each booking has its own notifications (no cross-wiring)',
            $second->count() === 2 && $second->pluck('dedupe_key')->intersect($rowsFor($b1)->pluck('dedupe_key'))->isEmpty());
    }
} finally {
    Auth::forgetUser();

    // put the real mailer and bus back
    app()->instance(\Illuminate\Contracts\Bus\Dispatcher::class, $origBus);
    \Illuminate\Support\Facades\Facade::clearResolvedInstance(\Illuminate\Contracts\Bus\Dispatcher::class);
    app()->forgetInstance('mail.manager');
    \Illuminate\Support\Facades\Facade::clearResolvedInstance('mail.manager');

    if ($evtKeep) {
        echo PHP_EOL.'KEPT the test rows and sent REAL pushes (queue: '.config('queue.default').').'.PHP_EOL;
        if (config('queue.default') !== 'sync') {
            echo 'Run in another terminal:  php artisan queue:work --queue=notifications --stop-when-empty'.PHP_EOL;
        }
        echo 'Check the phones, then run:  $evtCleanup();'.PHP_EOL;
    } else {
        $evtCleanup();
        echo PHP_EOL.'Deleted the throwaway event, bookings, notifications, outbox rows and extra user.'.PHP_EOL;
    }
}
