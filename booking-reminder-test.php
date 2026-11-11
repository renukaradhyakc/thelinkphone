<?php

/*
 * Booking reminder checks: REM-01 to REM-14.
 *
 * LOCAL DATABASE ONLY. Creates throwaway events and bookings, runs the real reminder code
 * (BookingNotifier::sendReminder via the same steps notifications:dispatch-due uses), jumps the
 * clock with Carbon::setTestNow(), reads the real user_notifications / outbox rows, and deletes
 * everything it created (even if a check crashes). Pushes and mail are faked during the checks.
 *
 * SAFE FOR YOUR WORKER: every booking is 4+ days ahead, so your running notifications:tick loop can
 * never pick these reminders up. Reminders are only processed per booking by this script, never with
 * the global dispatch-due command (a jumped clock would otherwise fire other people's reminders).
 *
 * Run from the Laravel project root, in a FRESH tinker session (type it, do not paste):
 *     php artisan tinker
 *     >>> include 'booking-reminder-test.php';
 *
 * Defaults: owner 83, booker 101. Optional before the include: $ownerId, $bookerId, $booker2Id.
 *
 * Real pushes to user 83's devices (small demo, runs after all the checks):
 *     >>> $remKeep = true;
 *     >>> include 'booking-reminder-test.php';
 *     ... check the phones and the inbox ...
 *     >>> $remCleanup();
 *   The demo makes one booking (event owned by 83, booker 101), jumps the clock and runs the real
 *   reminder code for the 15_min reminder and the start reminder. The push jobs are dispatched for
 *   real and sent by the queue worker you already run (queue: database).
 */

use App\Models\Event;
use App\Models\EventSchedule;
use App\Models\User;
use App\Modules\Notifications\Jobs\SendPushNotificationJob;
use App\Modules\Notifications\Models\NotificationOutbox;
use App\Modules\Notifications\Repositories\OutboxRepository;
use App\Modules\Notifications\Services\BookingNotifier;
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
$remKeep = $remKeep ?? false;
$remDemoStarted = false;

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
$appTz = config('app.timezone');
$tzName = fn (User $u) => ($u->timezone !== null && isset(User::TIME_ZONE_ARRAY[$u->timezone])) ? User::TIME_ZONE_ARRAY[$u->timezone] : $appTz;
$ownerTz = $tzName($owner);
$origBookerTimezone = $booker->timezone;

$bookingIds = [];
$eventIds = [];
$lastError = null;
$dayN = 3;

$eventRepo = app(EventRepository::class);
$bookingRepo = app(ScheduleEventRepository::class);
$notifier = app(BookingNotifier::class);
$outboxRepo = app(OutboxRepository::class);

$pushCount = fn () => Bus::dispatched(SendPushNotificationJob::class)->count();
$nextDate = function () use (&$dayN) {
    return Carbon::now()->addDays(++$dayN)->format('Y-m-d'); // 4+ days ahead, one new date per booking
};

$mkEvent = function (User $u, string $label, int $mode = Event::PHONE_CALL) use (&$eventIds, $eventRepo, $tag) {
    Auth::setUser($u);

    try {
        $e = $eventRepo->store([
            'name' => "REM-TEST $tag $label",
            'event_location' => $mode,
            'event_link' => "rem-test-$tag-$label",
            'event_color' => '#63d867',
            'event_type' => Event::FREE,
        ]);
    } finally {
        Auth::forgetUser();
    }

    $eventIds[] = $e->id;

    return $e;
};

$book = function (Event $event, ?string $phone, string $slot, ?string $date = null) use (&$bookingIds, &$lastError, $bookingRepo, $nextDate) {
    try {
        $b = $bookingRepo->store([
            'name' => 'REM Guest',
            'phone_call' => $phone,
            'email' => 'rem-guest-'.uniqid().'@example.invalid',
            'schedule_date' => $date ?? $nextDate(),
            'description' => 'REM test booking',
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

$setupFailed = function ($b, string $id) use ($report, &$lastError) {
    if ($b) {
        return false;
    }
    $report('INVESTIGATE', "$id could not create its test booking", (string) $lastError);

    return true;
};

$ownerCancel = function ($b) use ($bookingRepo, &$lastError) {
    try {
        $bookingRepo->cancelScheduleEvent('REM test', $b->id);
        $lastError = null;

        return true;
    } catch (Throwable $e) {
        $lastError = $e->getMessage();

        return false;
    }
};

$at = fn (Carbon $t) => Carbon::setTestNow($t);
$real = fn () => Carbon::setTestNow();
$startOf = fn ($b) => $notifier->startUtc($b->fresh());

$pendingFor = fn ($b) => NotificationOutbox::where('event_schedule_id', $b->id)->where('type', 'booking.reminder')
    ->where('status', NotificationOutbox::PENDING)->count();
// Uses the REAL due() scope the worker uses (claimDue), so the time zone handling is the app's own, not mine.
$dueRows = function ($b) {
    $q = method_exists(NotificationOutbox::class, 'scopeDue')
        ? NotificationOutbox::due(now())
        : NotificationOutbox::where('status', NotificationOutbox::PENDING)->where('notify_at', '<=', now()->utc());

    return $q->where('event_schedule_id', $b->id)->where('type', 'booking.reminder')->get();
};
$outboxRow = fn ($b, $variant, $userId) => NotificationOutbox::where('dedupe_key', "booking.reminder:{$b->id}:{$variant}:{$userId}")->first();
$remRows = fn ($b, $variant = null) => DB::table('user_notifications')->where('type', 'booking.reminder')
    ->where('dedupe_key', 'like', "booking.reminder:{$b->id}:".($variant ? $variant.':' : '').'%')->get();
$cancelRows = fn ($b) => DB::table('user_notifications')->where('type', 'booking.cancelled')
    ->where('dedupe_key', 'like', "booking.cancelled:{$b->id}:%")->get();
$ids = fn ($rows) => $rows->pluck('user_id')->map(fn ($x) => (int) $x)->sort()->values()->all();

// Same steps as notifications:dispatch-due, for one reminder row.
$dispatch = function (NotificationOutbox $row) use ($notifier, $outboxRepo) {
    $row->update(['status' => NotificationOutbox::CLAIMED, 'claimed_at' => now(), 'attempts' => $row->attempts + 1]);
    $result = $notifier->sendReminder($row->fresh());
    $result === 'sent' ? $outboxRepo->markSent($row) : $outboxRepo->markCancelled($row);

    return $result;
};
// Process every due reminder row of ONE booking at the current (possibly jumped) time.
$runDue = function ($b) use ($dueRows, $dispatch) {
    $r = ['sent' => 0, 'cancelled' => 0];
    foreach ($dueRows($b) as $row) {
        $res = $dispatch($row);
        $r[$res] = ($r[$res] ?? 0) + 1;
    }

    return $r;
};

$remCleanup = function () use (&$bookingIds, &$eventIds, $origBookerTimezone, $bookerId) {
    foreach ($bookingIds as $id) {
        DB::table('user_notifications')->where('dedupe_key', 'like', "booking.%:$id:%")->delete();
        NotificationOutbox::where('event_schedule_id', $id)->delete();
    }
    if ($bookingIds) {
        EventSchedule::whereIn('id', $bookingIds)->delete();
    }
    if ($eventIds) {
        Event::whereIn('id', $eventIds)->get()->each->delete();
    }
    User::where('id', $bookerId)->update(['timezone' => $origBookerTimezone]); // REM-13 may change it
    $bookingIds = [];
    $eventIds = [];
    echo 'Deleted the throwaway events, bookings, notifications and outbox rows (and restored user '.$bookerId.'\'s time zone).'.PHP_EOL;
};

// leftovers from crashed or kept runs
$oldEventIds = Event::where('name', 'like', 'REM-TEST %')->pluck('id');
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

$booker2 = $booker2Id
    ? User::find($booker2Id)
    : User::whereNotIn('id', [$ownerId, $bookerId])
        ->whereNotNull('phone_number')->where('phone_number', '!=', '')
        ->limit(300)->get()
        ->first(fn ($u) => $u->phone_number !== $owner->phone_number
            && $u->phone_number !== $booker->phone_number
            && optional($resolver->uniqueUserByPhone($u->phone_number))->id === $u->id);

echo "Owner $ownerId, booker $bookerId, owner time zone $ownerTz, app time zone $appTz".PHP_EOL;
echo 'Reminder offsets: '.json_encode(config('notifications.rules.reminder_offsets_minutes') ?? config('notifications.reminder_offsets_minutes') ?? 'see config').PHP_EOL.PHP_EOL;

$S1 = '10:00 AM - 10:30 AM';
$S2 = '11:00 AM - 11:30 AM';
$S3 = '03:00 PM - 03:30 PM';

try {
    $eventA = $mkEvent($owner, 'A');   // owned by $ownerId; booker is $bookerId

    // ============ REM-01 / REM-02 reminders reach every eligible party ============
    $r1 = $book($eventA, $bookerPhone, $S1);
    if (! $setupFailed($r1, 'REM-01')) {
        $start = $startOf($r1);
        $check('REM-01 setup: 4 reminders queued (15_min + start, for owner and booker)', $pendingFor($r1) === 4, 'pending='.$pendingFor($r1));

        $at($start->copy()->subMinutes(15)->addSeconds(10));
        $due = $dueRows($r1);
        $dueVariants = $due->pluck('reason')->unique()->implode(',');
        $res = $runDue($r1);
        $real();
        $rows = $remRows($r1, '15_min');
        $check('REM-01 at start-15 min only the 15_min reminders are due', $due->count() === 2 && $dueVariants === '15_min', 'due='.$due->count().' ['.$dueVariants.']');
        $check('REM-01 owner and booker each get exactly one 15_min reminder', $ids($rows) === collect([$ownerId, $bookerId])->sort()->values()->all() && $res['sent'] === 2,
            'rows='.$rows->count().', sent='.$res['sent']);
        $check('REM-01 no start reminder yet', $remRows($r1, 'start')->count() === 0);

        $at($start->copy()->addSeconds(10));
        $res = $runDue($r1);
        $real();
        $rows = $remRows($r1, 'start');
        $check('REM-02 at the start time owner and booker each get exactly one start reminder', $ids($rows) === collect([$ownerId, $bookerId])->sort()->values()->all() && $res['sent'] === 2,
            'rows='.$rows->count().', sent='.$res['sent']);
        $check('REM-02 the 15_min reminders were not duplicated', $remRows($r1, '15_min')->count() === 2);
    }

    // ============ REM-03 scheduler runs twice in the same minute ============
    $r3 = $book($eventA, $bookerPhone, $S1);
    if (! $setupFailed($r3, 'REM-03')) {
        $start = $startOf($r3);
        $at($start->copy()->subMinutes(15)->addSeconds(10));
        $first = $runDue($r3);
        $second = $runDue($r3);
        $check('REM-03 the second scheduler run finds nothing to send', $first['sent'] === 2 && ($second['sent'] + $second['cancelled']) === 0,
            'run1 sent='.$first['sent'].', run2 sent='.$second['sent']);

        // worst case: a row somehow goes back to pending and is processed again
        $pushBefore = $pushCount();
        NotificationOutbox::where('event_schedule_id', $r3->id)->where('reason', '15_min')->update(['status' => NotificationOutbox::PENDING]);
        $third = $runDue($r3);
        $real();
        $check('REM-03 even a re-processed row creates no second notification', $remRows($r3, '15_min')->count() === 2, 'rows='.$remRows($r3, '15_min')->count().', re-run sent='.$third['sent']);
        $check('REM-03 and queues no extra push', $pushCount() === $pushBefore, 'extra push jobs='.($pushCount() - $pushBefore));
    }

    // ============ REM-04 / REM-05 late scheduler ============
    // implemented rule (BookingNotifier::sendReminder): a reminder is dropped once the slot started more than 2 minutes ago
    $r4a = $book($eventA, $bookerPhone, $S1);
    if (! $setupFailed($r4a, 'REM-04')) {
        $start = $startOf($r4a);
        $at($start->copy()->subMinutes(5));
        $res = $runDue($r4a);
        $real();
        $check('REM-04 15_min reminder 10 minutes late but before the slot starts: still sent (implemented rule)', $res['sent'] === 2 && $remRows($r4a, '15_min')->count() === 2,
            'sent='.$res['sent'].', cancelled='.$res['cancelled']);
    }

    $r4b = $book($eventA, $bookerPhone, $S1);
    if (! $setupFailed($r4b, 'REM-04')) {
        $start = $startOf($r4b);
        $at($start->copy()->addSeconds(110)); // 1 min 50 s after the start, inside the 2-minute window
        $res = $runDue($r4b);
        $real();
        $check('REM-04 start reminder 110 seconds late: still sent (inside the 2-minute window)', $remRows($r4b, 'start')->count() === 2,
            'start rows='.$remRows($r4b, 'start')->count().', sent='.$res['sent'].', cancelled='.$res['cancelled']);
    }

    $r5 = $book($eventA, $bookerPhone, $S1);
    if (! $setupFailed($r5, 'REM-05')) {
        $start = $startOf($r5);
        $at($start->copy()->addSeconds(130)); // 2 min 10 s after the start
        $res = $runDue($r5);
        $real();
        $check('REM-05 start reminder more than 2 minutes late is dropped', $remRows($r5, 'start')->count() === 0, 'start rows='.$remRows($r5, 'start')->count());
        $check('REM-05 the 15_min reminder is dropped too once the slot has started', $remRows($r5, '15_min')->count() === 0 && $res['sent'] === 0,
            'sent='.$res['sent'].', cancelled='.$res['cancelled']);
        $check('REM-05 dropped rows are closed, not left pending', $pendingFor($r5) === 0, 'pending='.$pendingFor($r5));
    }

    // ============ REM-06 cancel before the 15-minute reminder ============
    $r6 = $book($eventA, $bookerPhone, $S1);
    if (! $setupFailed($r6, 'REM-06')) {
        $start = $startOf($r6);
        $ownerCancel($r6);
        $check('REM-06 cancelling leaves no pending reminder', $pendingFor($r6) === 0, 'pending='.$pendingFor($r6));
        $at($start->copy()->subMinutes(15)->addSeconds(10));
        $res = $runDue($r6);
        $at($start->copy()->addSeconds(10));
        $res2 = $runDue($r6);
        $real();
        $check('REM-06 no reminder of either kind is sent', ($res['sent'] + $res2['sent']) === 0 && $remRows($r6)->count() === 0, 'reminder rows='.$remRows($r6)->count());
    }

    // ============ REM-07 cancel between the two reminders ============
    $r7 = $book($eventA, $bookerPhone, $S1);
    if (! $setupFailed($r7, 'REM-07')) {
        $start = $startOf($r7);
        $at($start->copy()->subMinutes(15)->addSeconds(10));
        $runDue($r7);
        $before = $remRows($r7, '15_min')->count();
        $ownerCancel($r7);
        $at($start->copy()->addSeconds(10));
        $res = $runDue($r7);
        $real();
        $check('REM-07 the 15_min reminder was sent before the cancellation', $before === 2, 'rows='.$before);
        $check('REM-07 the start reminder is suppressed after the cancellation', $remRows($r7, 'start')->count() === 0 && $res['sent'] === 0,
            'start rows='.$remRows($r7, 'start')->count().', due at start='.$res['sent']);
    }

    // ============ REM-08 booking time changed ============
    // 8a: time edited, nothing re-schedules. The old reminder must not fire.
    $r8a = $book($eventA, $bookerPhone, $S1);
    if (! $setupFailed($r8a, 'REM-08a')) {
        $oldStart = $startOf($r8a);
        EventSchedule::where('id', $r8a->id)->update(['schedule_date' => Carbon::parse($r8a->schedule_date)->addDays(40)->format('Y-m-d')]);
        $at($oldStart->copy()->subMinutes(15)->addSeconds(10));
        $res = $runDue($r8a);
        $real();
        $check('REM-08 after the time was edited, the old reminder is suppressed', $res['sent'] === 0 && $remRows($r8a)->count() === 0,
            'sent='.$res['sent'].', cancelled='.$res['cancelled'].', reminder rows='.$remRows($r8a)->count());
    }

    // 8b: time edited and the reminders re-queued (BookingNotifier::created re-runs scheduleReminders)
    $r8b = $book($eventA, $bookerPhone, $S1);
    if (! $setupFailed($r8b, 'REM-08b')) {
        $oldStart = $startOf($r8b);
        EventSchedule::where('id', $r8b->id)->update(['schedule_date' => Carbon::parse($r8b->schedule_date)->addDays(40)->format('Y-m-d')]);
        $notifier->created($r8b->fresh());
        $newStart = $startOf($r8b);
        $row = $outboxRow($r8b, '15_min', $ownerId);
        $check('REM-08 re-queueing moves the pending reminder to the new time', $row && $row->status === NotificationOutbox::PENDING && $row->notify_at->equalTo($newStart->copy()->subMinutes(15)),
            'notify_at='.optional($row)->notify_at);
        $check('REM-08 no duplicate reminder rows were created', NotificationOutbox::where('event_schedule_id', $r8b->id)->where('type', 'booking.reminder')->count() === 4);

        $at($oldStart->copy()->subMinutes(15)->addSeconds(10));
        $res = $runDue($r8b);
        $check('REM-08 nothing fires at the OLD time', $res['sent'] === 0 && $remRows($r8b)->count() === 0);
        $at($newStart->copy()->subMinutes(15)->addSeconds(10));
        $res = $runDue($r8b);
        $real();
        $check('REM-08 the reminder fires at the NEW time', $res['sent'] === 2 && $remRows($r8b, '15_min')->count() === 2, 'sent='.$res['sent']);
        $report('INFO', 'REM-08 note', 'the script re-queues with BookingNotifier::created(); I have not seen the code that edits a booking, so check that it calls created() (or equivalent) when the slot changes');
    }

    // ============ REM-09 one person, several bookings ============
    $r9a = $book($eventA, $bookerPhone, $S1, $d9 = $nextDate());
    $r9b = $book($eventA, $bookerPhone, $S2, $d9);
    if (! $setupFailed($r9a, 'REM-09') && ! $setupFailed($r9b, 'REM-09')) {
        $s1 = $startOf($r9a);
        $s2 = $startOf($r9b);
        $at($s1->copy()->subMinutes(15)->addSeconds(10));
        $dueB = $dueRows($r9b)->count();
        $runDue($r9a);
        $check('REM-09 booking 1\'s reminder time does not make booking 2\'s reminders due', $dueB === 0, 'booking 2 due='.$dueB);
        $at($s2->copy()->subMinutes(15)->addSeconds(10));
        $runDue($r9b);
        $real();
        $mine = DB::table('user_notifications')->where('user_id', $bookerId)->where('type', 'booking.reminder')
            ->where(function ($q) use ($r9a, $r9b) {
                $q->where('dedupe_key', 'like', "booking.reminder:{$r9a->id}:15_min:%")->orWhere('dedupe_key', 'like', "booking.reminder:{$r9b->id}:15_min:%");
            })->get();
        $check('REM-09 the booker gets one independent 15_min reminder per booking', $mine->count() === 2 && $mine->pluck('dedupe_key')->unique()->count() === 2, 'rows='.$mine->count());
    }

    // ============ REM-10 two bookings at exactly the same time ============
    $eventA2 = $mkEvent($owner, 'A2'); // second event, same owner
    $d10 = $nextDate();
    $r10a = $book($eventA, $bookerPhone, $S3, $d10);
    $r10b = $book($eventA2, $bookerPhone, $S3, $d10);
    if (! $setupFailed($r10a, 'REM-10') && ! $setupFailed($r10b, 'REM-10')) {
        $start = $startOf($r10a);
        $at($start->copy()->subMinutes(15)->addSeconds(10));
        $runDue($r10a);
        $runDue($r10b);
        $real();
        $for = fn ($uid) => DB::table('user_notifications')->where('user_id', $uid)->where('type', 'booking.reminder')
            ->where(function ($q) use ($r10a, $r10b) {
                $q->where('dedupe_key', 'like', "booking.reminder:{$r10a->id}:15_min:%")->orWhere('dedupe_key', 'like', "booking.reminder:{$r10b->id}:15_min:%");
            })->count();
        $check('REM-10 the booker gets a reminder for EACH of the two same-time bookings', $for($bookerId) === 2, 'rows='.$for($bookerId));
        $check('REM-10 the owner gets a reminder for EACH of the two same-time bookings', $for($ownerId) === 2, 'rows='.$for($ownerId));
    }

    // ============ REM-11 booking crosses midnight ============
    $r11 = $book($eventA, $bookerPhone, '11:45 PM - 12:15 AM');
    if (! $setupFailed($r11, 'REM-11')) {
        $start = $startOf($r11);
        $end = $notifier->endUtc($r11->fresh());
        $check('REM-11 the end time rolls over to the next day (30-minute slot)', $end && $end->diffInMinutes($start) === 30, 'minutes='.($end ? $end->diffInMinutes($start) : 'null'));
        $row = $outboxRow($r11, '15_min', $ownerId);
        $check('REM-11 reminders are scheduled from the 11:45 PM start, not from midnight', $row && $row->notify_at->equalTo($start->copy()->subMinutes(15)), 'notify_at='.optional($row)->notify_at);
        $at($start->copy()->addMinutes(20)); // past midnight, slot still running
        $stillRunning = $end && $end->isFuture();
        $ownerCancel($r11);
        $told = $cancelRows($r11)->where('user_id', $bookerId)->count();
        $real();
        $check('REM-11 at 20 minutes in (after midnight) the slot still counts as running', $stillRunning);
        $check('REM-11 cancelling that running overnight booking still tells the booker', $told === 1, 'rows='.$told);
    }

    // ============ REM-12 overnight booking, long ============
    $r12 = $book($eventA, $bookerPhone, '10:00 PM - 02:00 AM');
    if (! $setupFailed($r12, 'REM-12')) {
        $start = $startOf($r12);
        $end = $notifier->endUtc($r12->fresh());
        $check('REM-12 a 10 PM - 2 AM slot is 4 hours long', $end && $end->diffInMinutes($start) === 240, 'minutes='.($end ? $end->diffInMinutes($start) : 'null'));
        $at($start->copy()->addHours(3));
        $runningAt3h = $end && $end->isFuture();
        $at($start->copy()->addHours(4)->addMinute());
        $endedAfter = $end && $end->isPast();
        $real();
        $check('REM-12 still running 3 hours in, ended after 4 hours', $runningAt3h && $endedAfter);
    }

    // ============ REM-13 time zone differs from the server ============
    if (! $ownerPhoneOk) {
        $report('SKIP', 'REM-13 (user '.$ownerId.'\'s phone does not resolve to only that user)');
    } else {
        $otherKey = collect(User::TIME_ZONE_ARRAY)->filter(fn ($v) => ! in_array($v, [$appTz, $ownerTz], true))->keys()->first();
        if ($otherKey === null) {
            $report('SKIP', 'REM-13 no spare time zone found');
        } else {
            User::where('id', $bookerId)->update(['timezone' => $otherKey]); // restored in cleanup
            $evTz = User::TIME_ZONE_ARRAY[$otherKey];
            $eventZ = $mkEvent($booker->fresh(), 'Z'); // owned by $bookerId, whose zone is now $evTz
            $dz = $nextDate();
            $rz = $book($eventZ, $ownerPhone, $S3, $dz);
            if (! $setupFailed($rz, 'REM-13')) {
                $expected = Carbon::parse("$dz 03:00 PM", $evTz)->utc();
                $start = $startOf($rz);
                $check("REM-13 the event owner's 3:00 PM is read in their own zone ($evTz), not the server's ($appTz)", $start && $start->equalTo($expected), 'start UTC='.optional($start)->toDateTimeString().', expected='.$expected->toDateTimeString());
                $row = $outboxRow($rz, '15_min', $bookerId);
                $check('REM-13 the stored reminder time is start-15 minutes in UTC', $row && $row->notify_at->equalTo($expected->copy()->subMinutes(15)), 'notify_at='.optional($row)->notify_at);

                $at($expected->copy()->subMinutes(15)->addSeconds(10));
                $runDue($rz);
                $real();
                $ownerRow = $remRows($rz, '15_min')->firstWhere('user_id', $bookerId);
                $guestRow = $remRows($rz, '15_min')->firstWhere('user_id', $ownerId);
                $fmtHost = $expected->copy()->setTimezone($evTz)->format('d M, h:i A');
                $fmtGuest = $expected->copy()->setTimezone($ownerTz)->format('d M, h:i A');
                $check("REM-13 the event owner sees the time in their zone ($fmtHost)", $ownerRow && str_contains((string) $ownerRow->body, $fmtHost), 'body='.optional($ownerRow)->body);
                $check("REM-13 the booker sees the same moment in their zone ($fmtGuest)", $guestRow && str_contains((string) $guestRow->body, $fmtGuest), 'body='.optional($guestRow)->body);
            }
        }
    }

    // ============ REM-14 scheduler was down, then restarts ============
    $d14 = $nextDate();
    $p14 = $book($eventA, $bookerPhone, $S1, $d14);   // started long before the restart
    $q14 = $book($eventA, $bookerPhone, $S3, $d14);   // starts shortly after the restart
    if (! $setupFailed($p14, 'REM-14') && ! $setupFailed($q14, 'REM-14')) {
        $pStart = $startOf($p14);
        $qStart = $startOf($q14);
        $at($pStart->copy()->addHours(3)); // worker was down since before P; it restarts 3 hours after P started
        $qDue = $dueRows($q14)->count();
        $res = $runDue($p14);
        $check('REM-14 after the restart, reminders of a slot that started hours ago are NOT sent (no stale backlog)', $res['sent'] === 0 && $remRows($p14)->count() === 0,
            'sent='.$res['sent'].', cancelled='.$res['cancelled']);
        $check('REM-14 a later booking is not touched yet', $qDue === 0, 'due='.$qDue);
        $at($qStart->copy()->subMinutes(10)); // 5 minutes after Q's 15_min time
        $res = $runDue($q14);
        $real();
        $check('REM-14 an upcoming booking still gets its (late) 15_min reminder after the restart', $res['sent'] === 2 && $remRows($q14, '15_min')->count() === 2, 'sent='.$res['sent']);
        $check('REM-14 its start reminder is still waiting for its time', $pendingFor($q14) === 2, 'pending='.$pendingFor($q14));
    }

    // ============ real-push demo for user $ownerId (only with $remKeep = true) ============
    $remCleanup(); // always remove the data from the checks above

    if ($remKeep) {
        $remDemoStarted = true;
        Carbon::setTestNow();
        $restoreBus(); // real dispatcher, so the push jobs go to your database queue

        if (app(Dispatcher::class) instanceof \Illuminate\Support\Testing\Fakes\BusFake) {
            echo PHP_EOL.'FAIL  the real dispatcher could not be restored, so no real pushes can be sent. Start a new tinker session and run again.'.PHP_EOL;
        } else {
            $demoStart = (int) DB::table('user_notifications')->max('id');
            $dA = $mkEvent($owner, 'DA');
            $d1 = $book($dA, $bookerPhone, $S1);

            if ($d1) {
                $start = $startOf($d1);

                $at($start->copy()->subMinutes(15)->addSeconds(10));
                $runDue($d1);                       // 15_min reminder -> owner and booker
                $at($start->copy()->addSeconds(10));
                $runDue($d1);                       // start reminder -> owner and booker
                $real();

                // Jobs queued while the clock was jumped are stamped "available" days in the future, so the
                // worker would not run them. Make only those future-dated push jobs runnable now.
                if (config('queue.default') === 'database') {
                    DB::table('jobs')->where('queue', 'notifications')->where('available_at', '>', time())->update(['available_at' => time()]);
                }
            } else {
                $report('INVESTIGATE', 'demo could not create its booking', (string) $lastError);
            }

            echo PHP_EOL.'--- Real push demo: inbox rows for user '.$ownerId.' ---'.PHP_EOL;
            $demoRows = DB::table('user_notifications')->where('id', '>', $demoStart)->where('user_id', $ownerId)
                ->where('type', 'like', 'booking.%')->orderBy('id')->get(['id', 'type', 'reason', 'title']);
            foreach ($demoRows as $r) {
                echo "  #{$r->id}  {$r->type}.{$r->reason}  \"{$r->title}\"".PHP_EOL;
            }
            echo PHP_EOL.'Queue: '.config('queue.default').'. Your queue worker sends them within a minute.'.PHP_EOL;
            echo 'Then run:  $remCleanup();'.PHP_EOL;
        }
    }
} finally {
    Carbon::setTestNow();
    Auth::forgetUser();
    $restoreBus();
    app()->forgetInstance('mail.manager');
    Facade::clearResolvedInstance('mail.manager');

    if (! $remDemoStarted) {
        $remCleanup();
    }
}
