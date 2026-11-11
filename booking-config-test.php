<?php

/*
 * Meeting configuration checks: CFG-01 to CFG-13.
 *
 * ROLES IN THIS FILE: owner = user 101 (owns the events), booker = user 83 (books them and gets the
 * "booking changed" notifications). This is the reverse of the reminder and edit scripts.
 *
 * LOCAL DATABASE ONLY. Creates throwaway events and bookings, runs the real edit code
 * (EventRepository::update), jumps the clock with Carbon::setTestNow() only where a check needs it,
 * settles the pending "booking.changed" rows the way notifications:dispatch-due does
 * (BookingNotifier::sendChange), reads the real user_notifications / outbox rows, and deletes
 * everything it created (even if a check crashes). Pushes and mail are faked during the checks.
 *
 * Run from the Laravel project root, in a FRESH tinker session (type it, do not paste):
 *     php artisan tinker
 *     >>> include 'booking-config-test.php';
 *
 * Defaults: owner 101, booker 83. Optional before the include: $ownerId, $bookerId, $booker2Id.
 *
 * Real pushes to user 83's devices (small demo, runs after all the checks, NO clock jump):
 *     >>> $cfgKeep = true;
 *     >>> include 'booking-config-test.php';
 *     ... wait about 5 minutes, check the phones and the inbox ...
 *     >>> $cfgCleanup();
 *   The demo makes five events owned by 101, each booked by 83, and saves one meeting-configuration
 *   edit on each. The change notifications are due 3 minutes later in REAL time and your
 *   notifications:tick loop sends them. The script prints how many pushes to expect.
 *   Do not run $cfgCleanup() before the pushes arrived.
 *
 * Not verified (files not seen yet): how a Video Call meeting link is created, so CFG-13 only reports
 * what is stored on the booking; it does not claim to know whether a link is "usable".
 */

use App\Models\Event;
use App\Models\EventSchedule;
use App\Models\Location;
use App\Models\User;
use App\Modules\Notifications\Jobs\SendPushNotificationJob;
use App\Modules\Notifications\Models\NotificationOutbox;
use App\Modules\Notifications\Repositories\OutboxRepository;
use App\Modules\Notifications\Repositories\PreferenceRepository;
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
use Illuminate\Support\Facades\Schema;

$ownerId = (int) ($ownerId ?? 101);
$bookerId = (int) ($bookerId ?? 83);
$booker2Id = isset($booker2Id) ? (int) $booker2Id : null;
$cfgKeep = $cfgKeep ?? false;
$cfgDemoStarted = false;

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
    echo "SKIP  user $bookerId (the booker) needs a phone number that resolves to only that user".PHP_EOL;

    return;
}

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
$quiet = max(1, (int) config('notifications.rules.event_change_quiet_minutes', 3));
$sendNow = (int) config('notifications.rules.event_change_send_now_within_minutes', 60);

$S1 = '10:00 AM - 10:30 AM';
$bookingIds = [];
$eventIds = [];
$lastError = null;
$dayN = 3;

$eventRepo = app(EventRepository::class);
$bookingRepo = app(ScheduleEventRepository::class);
$notifier = app(BookingNotifier::class);
$outboxRepo = app(OutboxRepository::class);
$prefRepo = app(PreferenceRepository::class);
$prefCat = (string) (config('notifications.types')['booking.changed']['category'] ?? 'event_changes'); // not config('a.b.c'): the type name contains a dot

// The "Video Call" mode: found from the labels in Event::LOCATION_ARRAY (printed below so you can confirm it).
$modeLabel = fn ($k) => (string) (Event::LOCATION_ARRAY[$k] ?? $k);
$thirdModes = collect(array_keys(Event::LOCATION_ARRAY))
    ->filter(fn ($k) => (int) $k !== Event::PHONE_CALL && (int) $k !== Event::IN_PERSON_MEETING)->values();
$videoMode = $thirdModes->first(fn ($k) => (bool) preg_match('/video|meet|zoom|teams/i', $modeLabel($k))) ?? $thirdModes->first();
$videoMode = $videoMode === null ? null : (int) $videoMode;

$pushCount = fn () => Bus::dispatched(SendPushNotificationJob::class)->count();
$nextDate = function () use (&$dayN) {
    return Carbon::now()->addDays(++$dayN)->format('Y-m-d'); // 4+ days ahead, one new date per booking
};
$at = fn (Carbon $t) => Carbon::setTestNow($t);
$real = fn () => Carbon::setTestNow();
$startOf = fn ($b) => $notifier->startUtc($b->fresh());

$mkEvent = function (User $u, string $label, int $mode = Event::PHONE_CALL) use (&$eventIds, $eventRepo, $tag) {
    Auth::setUser($u);

    try {
        $e = $eventRepo->store([
            'name' => "CFG-TEST $tag $label",
            'event_location' => $mode,
            'event_link' => "cfg-test-$tag-$label",
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
$addLive = fn (Event $e) => $e->location()->create([
    'location_type' => Location::LIVE,
    'latitude' => 12.9716,
    'longitude' => 77.5946,
    'accuracy' => 10,
    'address' => null,
    'is_live_sharing_active' => false, // sharing OFF
    'live_started_at' => null,
]);

$book = function (Event $event, ?string $phone, string $slot, ?string $date = null) use (&$bookingIds, &$lastError, $bookingRepo, $nextDate) {
    try {
        $b = $bookingRepo->store([
            'name' => 'CFG Guest',
            'phone_call' => $phone,
            'email' => 'cfg-guest-'.uniqid().'@example.invalid',
            'schedule_date' => $date ?? $nextDate(),
            'description' => 'CFG test booking',
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

// One event owned by $owner (101) with one confirmed booking by $booker (83).
// $loc: null = no saved location, 'fixed' = fixed address, 'live' = Live location with sharing OFF.
$mkPair = function (string $label, int $mode = Event::PHONE_CALL, ?string $loc = null, ?string $slot = null, ?string $date = null) use ($mkEvent, $addFixed, $addLive, $book, $owner, $bookerPhone, $S1) {
    $e = $mkEvent($owner, $label, $mode);
    if ($loc === 'fixed') {
        $addFixed($e, 'Test Address A');
    } elseif ($loc === 'live') {
        $addLive($e);
    }

    return [$e, $book($e, $bookerPhone, $slot ?? $S1, $date)];
};

$setupFailed = function ($b, string $id) use ($report, &$lastError) {
    if ($b) {
        return false;
    }
    $report('INVESTIGATE', "$id could not create its test booking", (string) $lastError);

    return true;
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

// What the app sends from the edit screen for each meeting configuration.
$addrInput = fn (string $address) => [
    'event_location' => Event::IN_PERSON_MEETING,
    'location_meta' => '1',
    'new_location_type' => Location::FIXED,
    'new_location_address' => $address,
    'new_location_latitude' => 12.9716,
    'new_location_longitude' => 77.5946,
    'new_location_accuracy' => 10,
];
$liveInput = [
    'event_location' => Event::IN_PERSON_MEETING,
    'location_meta' => '1',
    'new_location_type' => Location::LIVE, // sharing is NOT switched on
];

$live = fn (array $bookings) => collect($bookings)->filter()->pluck('id');
$changeNotifs = function (array $bookings) use ($live) {
    $ids = $live($bookings);
    if ($ids->isEmpty()) {
        return collect();
    }

    return DB::table('user_notifications')->where('type', 'booking.changed')->where(function ($q) use ($ids) {
        foreach ($ids as $id) {
            $q->orWhere('dedupe_key', 'like', "booking.changed:{$id}:%");
        }
    })->orderBy('id')->get();
};
$locNotifs = function (array $bookings) use ($live) {
    $ids = $live($bookings);
    if ($ids->isEmpty()) {
        return collect();
    }

    return DB::table('user_notifications')->where('type', 'like', 'location.%')->where(function ($q) use ($ids) {
        foreach ($ids as $id) {
            $q->orWhere('dedupe_key', 'like', "location.%:{$id}:%");
        }
    })->get();
};
$pendingChange = fn (array $bookings) => NotificationOutbox::whereIn('event_schedule_id', $live($bookings))
    ->where('type', 'booking.changed')->where('status', NotificationOutbox::PENDING)->get();
$ctxKeys = fn ($row) => $row ? array_keys((array) ($row->context['changes'] ?? [])) : [];
$bodyOf = fn ($rows) => mb_substr((string) optional($rows->first())->body, 0, 90); // mb_: do not cut a curly quote in half

// Uses the REAL due() scope the worker uses, so the time zone handling is the app's own.
$dueChange = function (array $bookings) use ($live) {
    $q = method_exists(NotificationOutbox::class, 'scopeDue')
        ? NotificationOutbox::due(now())
        : NotificationOutbox::where('status', NotificationOutbox::PENDING)->where('notify_at', '<=', now()->utc());

    return $q->whereIn('event_schedule_id', $live($bookings))->where('type', 'booking.changed')->get();
};

// Same steps as notifications:dispatch-due, for one booking.changed row.
$dispatchChange = function (NotificationOutbox $row) use ($notifier, $outboxRepo) {
    $row->update(['status' => NotificationOutbox::CLAIMED, 'claimed_at' => now(), 'attempts' => $row->attempts + 1]);
    $result = $notifier->sendChange($row->fresh());
    $result === 'sent' ? $outboxRepo->markSent($row) : $outboxRepo->markCancelled($row);

    return $result;
};

// Jump past the quiet period (from the current, possibly already jumped, clock) and process the due rows.
$settle = function (array $bookings) use ($at, $real, $quiet, $dueChange, $dispatchChange) {
    $at(Carbon::now()->addMinutes($quiet)->addSeconds(30));
    $r = ['sent' => 0, 'cancelled' => 0];
    foreach ($dueChange($bookings) as $row) {
        $res = $dispatchChange($row);
        $r[$res] = ($r[$res] ?? 0) + 1;
    }
    $real();

    return $r;
};

// The common case: one edit, one pending location_changed row recording $keys, one notification to the booker, one push.
$case = function (string $id, Event $e, $b, array $input, array $keys) use (
    $edit, $pendingChange, $ctxKeys, $check, $settle, $changeNotifs, $pushCount, $bodyOf, &$lastError, $bookerId
) {
    $ok = $edit($e, $input);
    $pend = $pendingChange([$b]);
    $row = $pend->first();
    $got = $ctxKeys($row);
    $check("$id the edit was saved", $ok, (string) $lastError);
    $check("$id one pending change location_changed recording ".implode(', ', $keys),
        $pend->count() === 1 && $row->reason === 'location_changed' && count(array_diff($keys, $got)) === 0,
        'rows='.$pend->count().', reason='.optional($row)->reason.', keys='.implode(',', $got));
    $pushBefore = $pushCount();
    $res = $settle([$b]);
    $rows = $changeNotifs([$b]);
    $check("$id the booker (user $bookerId) gets exactly one notification and one push",
        $res['sent'] === 1 && $rows->count() === 1 && (int) $rows->first()->user_id === $bookerId && $rows->first()->reason === 'location_changed' && ($pushCount() - $pushBefore) === 1,
        'rows='.$rows->count().', push jobs='.($pushCount() - $pushBefore).', body="'.$bodyOf($rows).'"');

    return $row;
};

$cfgCleanup = function () use (&$bookingIds, &$eventIds) {
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

// leftovers from earlier kept or crashed runs (events named "CFG-TEST xxxxxx ...")
$oldEventIds = Event::where('name', 'like', 'CFG-TEST %')->pluck('id');
if ($oldEventIds->isNotEmpty()) {
    $oldBookingIds = EventSchedule::whereIn('event_id', $oldEventIds)->pluck('id');
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

echo "Owner $ownerId (owns the events), booker $bookerId (gets the notifications), second booker ".($booker2 ? $booker2->id : 'none found').PHP_EOL;
echo "Event change rules: quiet period $quiet min, send immediately when the slot starts within $sendNow min".PHP_EOL;
echo 'Meeting modes: '.json_encode(Event::LOCATION_ARRAY).PHP_EOL;
echo 'Video Call mode used below: '.($videoMode === null ? 'none found (video checks will SKIP)' : $videoMode.' ('.$modeLabel($videoMode).')  <- confirm this is the right one').PHP_EOL.PHP_EOL;

try {
    // ============ CFG-01 Fixed -> Live with sharing OFF ============
    [$e1, $b1] = $mkPair('C01', Event::IN_PERSON_MEETING, 'fixed');
    if (! $setupFailed($b1, 'CFG-01')) {
        $row = $case('CFG-01', $e1, $b1, $liveInput, ['location']);
        $loc = Event::find($e1->id)->location;
        $chg = $row ? ($row->context['changes']['location'] ?? null) : null;
        $check('CFG-01 the event is now Live with sharing OFF and no address', $loc && (int) $loc->location_type === Location::LIVE && ! (bool) $loc->is_live_sharing_active && $loc->address === null,
            'type='.optional($loc)->location_type.', sharing='.json_encode(optional($loc)->is_live_sharing_active).', address='.json_encode(optional($loc)->address));
        $check('CFG-01 the change says: old address -> live location', $chg && $chg['old'] === 'Test Address A' && stripos((string) $chg['new'], 'live') !== false, json_encode($chg));
        $check('CFG-01 no "sharing started" notification (sharing is off)', $locNotifs([$b1])->count() === 0, 'location rows='.$locNotifs([$b1])->count());
    }

    // ============ CFG-02 edit the static location while Live, sharing OFF ============
    [$e2, $b2] = $mkPair('C02', Event::IN_PERSON_MEETING, 'live');
    if (! $setupFailed($b2, 'CFG-02')) {
        $ok = $edit($e2, [
            'event_location' => Event::IN_PERSON_MEETING, 'location_meta' => '1', 'new_location_type' => Location::LIVE,
            'new_location_address' => 'Static Edit Address', 'new_location_latitude' => 13.0827, 'new_location_longitude' => 80.2707, 'new_location_accuracy' => 20,
        ]);
        $loc = Event::find($e2->id)->location;
        $check('CFG-02 the edit was saved: new coordinates stored, still Live, still no address',
            $ok && $loc && (int) $loc->location_type === Location::LIVE && abs((float) $loc->latitude - 13.0827) < 0.001 && $loc->address === null,
            'type='.optional($loc)->location_type.', lat='.optional($loc)->latitude.', address='.json_encode(optional($loc)->address).(string) $lastError);
        $pending = $pendingChange([$b2])->count();
        $settle([$b2]);
        $told = $changeNotifs([$b2])->count();
        $report($pending === 0 && $told === 0 ? 'PASS' : 'INVESTIGATE', 'CFG-02 editing the saved location while Live (sharing OFF)',
            $pending === 0 && $told === 0 ? 'no notification: a Live event shows bookers no address, so there is nothing to tell them'
                : "pending=$pending, notifications=$told. A notification was created for a Live event that shows no address; decide if that is wanted");
    }

    // ============ CFG-03 Live -> Fixed with sharing OFF ============
    [$e3, $b3] = $mkPair('C03', Event::IN_PERSON_MEETING, 'live');
    if (! $setupFailed($b3, 'CFG-03')) {
        $row = $case('CFG-03', $e3, $b3, $addrInput('Fixed After Live'), ['location']);
        $loc = Event::find($e3->id)->location;
        $chg = $row ? ($row->context['changes']['location'] ?? null) : null;
        $check('CFG-03 the event is now Fixed with the new address', $loc && (int) $loc->location_type === Location::FIXED && $loc->address === 'Fixed After Live', 'type='.optional($loc)->location_type.', address='.json_encode(optional($loc)->address));
        $check('CFG-03 the change records the new address', $chg && $chg['new'] === 'Fixed After Live', json_encode($chg));
        $check('CFG-03 no "sharing stopped" notification (sharing was off)', $locNotifs([$b3])->count() === 0, 'location rows='.$locNotifs([$b3])->count());
    }

    // ============ CFG-04 Fixed -> Phone Call ============
    [$e4, $b4] = $mkPair('C04', Event::IN_PERSON_MEETING, 'fixed');
    if (! $setupFailed($b4, 'CFG-04')) {
        $case('CFG-04', $e4, $b4, ['event_location' => Event::PHONE_CALL], ['event_location']);
        $check('CFG-04 the event is now a phone call and its saved address was removed', (int) Event::find($e4->id)->event_location === Event::PHONE_CALL && Event::find($e4->id)->location === null);
        $check('CFG-04 no location-sharing notification', $locNotifs([$b4])->count() === 0);
    }

    // ============ CFG-05 Fixed -> Video Call ============
    if ($videoMode === null) {
        $report('SKIP', 'CFG-05, CFG-07, CFG-08, CFG-09b and CFG-13 need a Video Call mode and none was found in Event::LOCATION_ARRAY');
    } else {
        [$e5, $b5] = $mkPair('C05', Event::IN_PERSON_MEETING, 'fixed');
        if (! $setupFailed($b5, 'CFG-05')) {
            $case('CFG-05', $e5, $b5, ['event_location' => $videoMode], ['event_location']);
            $check('CFG-05 the event is now Video Call and its saved address was removed', (int) Event::find($e5->id)->event_location === $videoMode && Event::find($e5->id)->location === null);
        }
    }

    // ============ CFG-06 Phone Call -> In Person ============
    [$e6, $b6] = $mkPair('C06');
    if (! $setupFailed($b6, 'CFG-06')) {
        $case('CFG-06', $e6, $b6, $addrInput('In Person Address 06'), ['event_location', 'location']);
        $loc = Event::find($e6->id)->location;
        $check('CFG-06 the event is now in person at the new address', (int) Event::find($e6->id)->event_location === Event::IN_PERSON_MEETING && $loc && $loc->address === 'In Person Address 06');
    }

    // ============ CFG-07 Phone Call -> Video Call ============
    if ($videoMode !== null) {
        [$e7, $b7] = $mkPair('C07');
        if (! $setupFailed($b7, 'CFG-07')) {
            $case('CFG-07', $e7, $b7, ['event_location' => $videoMode], ['event_location']);
        }

        // ============ CFG-08 Video Call -> Phone Call ============
        [$e8, $b8] = $mkPair('C08'); // booked while it is a phone call, then set to Video Call directly (no booking-time integrations)
        if (! $setupFailed($b8, 'CFG-08')) {
            Event::where('id', $e8->id)->update(['event_location' => $videoMode]);
            $case('CFG-08', $e8, $b8, ['event_location' => Event::PHONE_CALL], ['event_location']);
        }
    }

    // ============ CFG-09 several meeting-detail changes in one save ============
    [$e9, $b9] = $mkPair('C09a', Event::IN_PERSON_MEETING, 'fixed');
    if (! $setupFailed($b9, 'CFG-09')) {
        $case('CFG-09 (name + description + address)', $e9, $b9, ['name' => "CFG-TEST $tag renamed-09a", 'description' => 'CFG description 09a'] + $addrInput('Address 09a'), ['name', 'description', 'location']);
    }
    if ($videoMode !== null) {
        [$e9b, $b9b] = $mkPair('C09b');
        if (! $setupFailed($b9b, 'CFG-09b')) {
            $case('CFG-09 (name + description + Video Call)', $e9b, $b9b, ['name' => "CFG-TEST $tag renamed-09b", 'description' => 'CFG description 09b', 'event_location' => $videoMode], ['name', 'description', 'event_location']);
        }
    }

    // ============ CFG-10 restore the original configuration ============
    $restore = function (string $id, $b, string $what) use ($pendingChange, $settle, $changeNotifs, $check) {
        $row = $pendingChange([$b])->first();
        $check("$id $what: the pending change is empty", $row && ($row->context['changes'] ?? null) === [], 'changes='.json_encode(optional($row)->context['changes'] ?? 'no row'));
        $res = $settle([$b]);
        $check("$id $what: nothing is sent", $res['sent'] === 0 && $changeNotifs([$b])->count() === 0, 'sent='.$res['sent'].', cancelled='.$res['cancelled']);
    };

    [$e10a, $b10a] = $mkPair('C10a', Event::IN_PERSON_MEETING, 'fixed');
    if (! $setupFailed($b10a, 'CFG-10a')) {
        $edit($e10a, $addrInput('Address B'));
        $edit($e10a, $addrInput('Test Address A')); // back to the original
        $restore('CFG-10a', $b10a, 'address A -> B -> A');
    }

    if ($videoMode !== null) {
        [$e10b, $b10b] = $mkPair('C10b');
        if (! $setupFailed($b10b, 'CFG-10b')) {
            $edit($e10b, ['event_location' => $videoMode]);
            $edit($e10b, ['event_location' => Event::PHONE_CALL]);
            $restore('CFG-10b', $b10b, 'Phone -> Video -> Phone');
        }
    }

    [$e10c, $b10c] = $mkPair('C10c', Event::IN_PERSON_MEETING, 'fixed');
    if (! $setupFailed($b10c, 'CFG-10c')) {
        $edit($e10c, $liveInput);
        $edit($e10c, $addrInput('Test Address A'));
        $restore('CFG-10c', $b10c, 'Fixed -> Live -> same Fixed address');
    }

    [$e10d, $b10d] = $mkPair('C10d', Event::IN_PERSON_MEETING, 'fixed');
    if (! $setupFailed($b10d, 'CFG-10d')) {
        $edit($e10d, ['event_location' => Event::PHONE_CALL]);   // the saved address is removed here
        $edit($e10d, $addrInput('Test Address A'));              // back in person at the SAME address
        $row = $pendingChange([$b10d])->first();
        $keys = $ctxKeys($row);
        $res = $settle([$b10d]);
        $told = $changeNotifs([$b10d])->count();
        $report($told === 0 ? 'PASS' : 'INVESTIGATE', 'CFG-10d Fixed -> Phone -> same Fixed address',
            $told === 0 ? 'no notification, the final state matches the original'
                : "the booker is told about a change even though the final state equals the original (pending keys=".implode(',', $keys).', notifications='.$told.'). Going through a mode that has no saved address loses the old address, so the round trip looks like a new address');
    }

    // ============ CFG-11 no confirmed bookings ============
    $e11 = $mkEvent($owner, 'C11');
    $addFixed($e11, 'Test Address A');
    $snapN = (int) DB::table('user_notifications')->max('id');
    $snapO = (int) NotificationOutbox::max('id');
    $ok1 = $edit($e11, $liveInput);                                  // Fixed -> Live
    $ok2 = $edit($e11, ['event_location' => Event::PHONE_CALL]);     // Live -> Phone
    $newOut = NotificationOutbox::where('id', '>', $snapO)->where('type', 'booking.changed')->whereIn('user_id', [$ownerId, $bookerId])->count();
    $newNot = DB::table('user_notifications')->where('id', '>', $snapN)->whereIn('user_id', [$ownerId, $bookerId])
        ->where(function ($q) {
            $q->where('type', 'booking.changed')->orWhere('type', 'like', 'location.%');
        })->count();
    $check('CFG-11 two configuration edits with no bookings work and queue/send nothing', $ok1 && $ok2 && $newOut === 0 && $newNot === 0,
        'outbox rows='.$newOut.', notifications='.$newNot.' '.(string) $lastError);

    // ============ CFG-12 future and in-progress bookings ============
    $e12 = $mkEvent($owner, 'C12');
    $addFixed($e12, 'Test Address A');
    $twoBookers = $booker2Phone !== null;
    $p12 = $book($e12, $twoBookers ? $booker2Phone : $bookerPhone, $S1); // earlier date: will be in progress
    $f12 = $book($e12, $bookerPhone, $S1);                               // later date: still in the future
    if (! $setupFailed($p12, 'CFG-12') && ! $setupFailed($f12, 'CFG-12')) {
        $pushBefore = $pushCount();
        $at($startOf($p12)->copy()->addMinutes(10)); // the first booking started 10 minutes ago
        $edit($e12, $addrInput('Address 12'));
        $nowRows = $changeNotifs([$p12, $f12]);
        $pend = $pendingChange([$p12, $f12]);
        if ($twoBookers) {
            $check('CFG-12 the booker of the in-progress booking is told immediately', $nowRows->where('user_id', $booker2->id)->count() === 1 && $pend->where('user_id', $booker2->id)->count() === 0,
                'immediate rows='.$nowRows->count());
            $check('CFG-12 the booker of the future booking waits the quiet period', $pend->where('user_id', $bookerId)->count() === 1 && $nowRows->where('user_id', $bookerId)->count() === 0,
                'pending='.$pend->where('user_id', $bookerId)->count());
        } else {
            $report('INFO', 'CFG-12 only one booker available (no second user with a unique phone number; set $booker2Id), so both bookings are the same person');
            $check('CFG-12 that person is told once, immediately, via the in-progress booking', $nowRows->where('user_id', $bookerId)->count() === 1 && $pend->count() === 0, 'immediate rows='.$nowRows->count().', pending='.$pend->count());
        }
        $settle([$p12, $f12]); // continues from the jumped clock, then resets it
        $rows = $changeNotifs([$p12, $f12]);
        $expected = $twoBookers ? 2 : 1;
        $check('CFG-12 each eligible booker is told exactly once in total', $rows->count() === $expected && $rows->pluck('user_id')->unique()->count() === $expected, 'rows='.$rows->count().', expected='.$expected);
        $check('CFG-12 one push per notification', ($pushCount() - $pushBefore) === $expected, 'push jobs='.($pushCount() - $pushBefore));
    }
    $real();

    // ============ CFG-13 change to Video Call after bookings exist ============
    if ($videoMode === null) {
        $report('SKIP', 'CFG-13 (no Video Call mode found)');
    } else {
        [$e13, $b13] = $mkPair('C13'); // booked while it is a phone call
        if (! $setupFailed($b13, 'CFG-13')) {
            $edit($e13, ['event_location' => $videoMode]);
            $res = $settle([$b13]);
            $rows = $changeNotifs([$b13]);
            $check('CFG-13 the booker is told about the switch to Video Call', $res['sent'] === 1 && $rows->count() === 1, 'body="'.$bodyOf($rows).'"');

            $isLinky = fn ($c) => (bool) preg_match('/link|meet|url|zoom/i', $c);
            $schedTable = (new EventSchedule)->getTable();
            $linkCols = array_values(array_filter(Schema::getColumnListing($schedTable), $isLinky));
            $fresh = DB::table($schedTable)->where('id', $b13->id)->first();
            $values = [];
            foreach ($linkCols as $c) {
                $values[$c] = mb_substr((string) ($fresh->{$c} ?? ''), 0, 70);
            }
            $hasUrl = count(array_filter($values, fn ($v) => (bool) preg_match('#^https?://#i', $v))) > 0;

            if ($linkCols === []) {
                $report('SKIP', 'CFG-13 existing booking has a usable meeting link', "no link-like column found on $schedTable. Send the code that creates the Video Call / Meet link so I can test where it lives");
            } else {
                $report($hasUrl ? 'PASS' : 'INVESTIGATE', 'CFG-13 existing booking has a meeting link after the switch',
                    ($hasUrl ? 'a link URL is stored on the booking: ' : 'no link URL is stored on the booking: ').json_encode($values)
                    .($hasUrl ? '' : '. If links are created later (on first view, or by a job) this is expected; if not, bookers have no link. I have not seen the link-creation code'));
            }
        }
    }

    // ============ real-push demo for user 83 (only with $cfgKeep = true) ============
    $cfgCleanup(); // always remove the data from the checks above

    if ($cfgKeep) {
        $cfgDemoStarted = true;
        Carbon::setTestNow();
        $restoreBus(); // real dispatcher, so the push jobs go to your database queue

        if (app(Dispatcher::class) instanceof \Illuminate\Support\Testing\Fakes\BusFake) {
            echo PHP_EOL.'FAIL  the real dispatcher could not be restored, so no real pushes can be sent. Start a new tinker session and run again.'.PHP_EOL;
        } else {
            $demoStart = (int) DB::table('user_notifications')->max('id');
            [$d1, $db1] = $mkPair('D1', Event::IN_PERSON_MEETING, 'fixed');
            [$d2, $db2] = $mkPair('D2', Event::IN_PERSON_MEETING, 'fixed');
            [$d3, $db3] = $mkPair('D3');
            [$d4, $db4] = $mkPair('D4', Event::IN_PERSON_MEETING, 'fixed');
            [$d5, $db5] = $mkPair('D5', Event::IN_PERSON_MEETING, 'fixed');

            if ($db1 && $db2 && $db3 && $db4 && $db5) {
                // No clock jump here: each change row is due $quiet minutes of REAL time after its edit and your
                // notifications:tick loop sends it, exactly like a real edit.
                $edit($d1, $liveInput);                                                                                                  // CFG-01 Fixed -> Live
                $edit($d2, ['event_location' => Event::PHONE_CALL]);                                                                     // CFG-04 Fixed -> Phone Call
                if ($videoMode !== null) {
                    $edit($d3, ['event_location' => $videoMode]);                                                                        // CFG-07 Phone Call -> Video Call
                }
                $edit($d4, ['name' => "CFG-TEST $tag D4 renamed", 'description' => 'Demo description'] + $addrInput('Demo Street 9')); // CFG-09 several changes, one save
                $edit($d5, $addrInput('Demo Street 5'));
                $edit($d5, $addrInput('Test Address A'));                                                                                // CFG-10 changed and restored: NO notification
            } else {
                $report('INVESTIGATE', 'demo could not create its bookings', (string) $lastError);
            }

            $devices = (int) DB::table('device_tokens')->where('user_id', $bookerId)->whereNull('revoked_at')->count();
            $expectedNotifs = 3 + ($videoMode !== null ? 1 : 0);
            $pushOn = $prefRepo->isPushEnabled($bookerId, $prefCat, true);
            echo PHP_EOL.'--- Real push demo (no clock jump) ---'.PHP_EOL;
            echo 'Push setting of user '.$bookerId.' for "'.$prefCat.'": '.($pushOn ? 'on' : 'OFF (no push will be sent)').PHP_EOL;
            echo 'Active devices of user '.$bookerId.': '.$devices.PHP_EOL;
            echo 'Edits saved (event names are in the notification text):'.PHP_EOL;
            echo "  CFG-TEST $tag D1  CFG-01 Fixed -> Live (sharing off)        -> 1 notification".PHP_EOL;
            echo "  CFG-TEST $tag D2  CFG-04 Fixed -> Phone Call                -> 1 notification".PHP_EOL;
            echo "  CFG-TEST $tag D3  CFG-07 Phone Call -> Video Call           -> ".($videoMode !== null ? '1 notification' : 'not run (no Video Call mode)').PHP_EOL;
            echo "  CFG-TEST $tag D4  CFG-09 name + description + address       -> 1 notification (renamed, so the text shows the new name)".PHP_EOL;
            echo "  CFG-TEST $tag D5  CFG-10 address changed then restored      -> NO notification".PHP_EOL;
            echo PHP_EOL.'EXPECT in about '.($quiet + 2).' minutes: '.$expectedNotifs.' notifications on user '.$bookerId.', each on '.$devices.' device(s) = '.($expectedNotifs * $devices).' alerts in total, and nothing for D5.'.PHP_EOL;
            echo '(User '.$ownerId.'\'s "New booking" pushes go to user '.$ownerId.' and are not for your phones. User '.$bookerId.'\'s "Booking confirmed" rows are inbox only.)'.PHP_EOL;
            echo 'To see what was created (run after the wait):'.PHP_EOL;
            echo "  DB::table('user_notifications')->where('id', '>', $demoStart)->where('user_id', $bookerId)->where('type', 'booking.changed')->get(['id','reason','title','created_at']);".PHP_EOL;
            echo 'Do NOT run $cfgCleanup() before the pushes arrived. Then run:  $cfgCleanup();'.PHP_EOL;
        }
    }
} finally {
    Carbon::setTestNow();
    Auth::forgetUser();
    $restoreBus();
    app()->forgetInstance('mail.manager');
    Facade::clearResolvedInstance('mail.manager');

    if (! $cfgDemoStarted) {
        $cfgCleanup();
    }
}
