<?php

/*
 * Event editing notification checks: EDT-01 to EDT-20.
 *
 * LOCAL DATABASE ONLY. Creates throwaway events and bookings, runs the real edit code
 * (EventRepository::update, the same method the owner's edit endpoint calls), jumps the clock with
 * Carbon::setTestNow(), settles the pending "booking.changed" rows the way notifications:dispatch-due
 * does (BookingNotifier::sendChange), reads the real user_notifications / outbox rows, and deletes
 * everything it created (even if a check crashes). Pushes and mail are faked during the checks.
 *
 * WORKER NOTE: every booking is 4+ days ahead, but an edit queues a change row that is due 3 minutes
 * after the edit. The script settles those rows within seconds, and the cleanup deletes any row left
 * behind by a crash, so your notifications:tick loop will not see them in practice. Rows are only
 * processed per booking by this script, never with the global dispatch-due command.
 *
 * Run from the Laravel project root, in a FRESH tinker session (type it, do not paste):
 *     php artisan tinker
 *     >>> include 'booking-edit-test.php';
 *
 * Defaults: owner 83, booker 101. Optional before the include: $ownerId, $bookerId, $booker2Id.
 *
 * Real pushes to user 83's devices (small demo, runs after all the checks):
 *     >>> $editKeep = true;
 *     >>> include 'booking-edit-test.php';
 *     ... check the phones and the inbox ...
 *     >>> $editCleanup();
 *   The demo makes two events owned by 101 with a booking by 83, edits one (name + description) and
 *   moves the other to an in-person address, then settles the change notifications for real.
 *   Pushes are sent by the queue worker you already run (queue: database).
 *
 * Real code paths used:
 *   - EventRepository::update()            owner edits an event (fires BookingNotifier::eventChanged)
 *   - EventRepository::storeEventSchedule() availability / slot / gap changes (EDT-20)
 *   - BookingNotifier::sendChange() and the outbox, run the way notifications:dispatch-due does
 *   - ScheduleEventRepository::store() / cancelScheduleEvent()  bookings and cancellations
 *
 * EDT-17 turns the booker's push preference for the "event changes" category off with the real
 * PreferenceRepository, and puts it back to exactly what it was (also if the script crashes).
 */

use App\Models\Event;
use App\Models\EventSchedule;
use App\Models\Location;
use App\Models\User;
use App\Models\UserSchedule;
use App\Modules\Notifications\Jobs\SendPushNotificationJob;
use App\Modules\Notifications\Models\NotificationOutbox;
use App\Modules\Notifications\Models\NotificationPreference;
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

$ownerId = (int) ($ownerId ?? 83);
$bookerId = (int) ($bookerId ?? 101);
$booker2Id = isset($booker2Id) ? (int) $booker2Id : null;
$editKeep = $editKeep ?? false;
$editDemoStarted = false;

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
$tzBefore = date_default_timezone_get(); // storeEventSchedule() changes the PHP default time zone; restored at the end
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

// The booker's push preference for the category of booking.changed, remembered so it can be put back exactly.
$prefCat = (string) (config('notifications.types')['booking.changed']['category'] ?? 'event_changes'); // not config('a.b.c'): the type name itself contains a dot
$prefBefore = NotificationPreference::where('user_id', $bookerId)->where('category', $prefCat)->first();
$prefExisted = $prefBefore !== null;
$prefValue = $prefBefore ? (bool) $prefBefore->push_enabled : null;
$prefRestore = function () use ($prefRepo, $bookerId, $prefCat, $prefExisted, $prefValue) {
    if ($prefExisted) {
        $prefRepo->set($bookerId, $prefCat, (bool) $prefValue);
    } else {
        NotificationPreference::where('user_id', $bookerId)->where('category', $prefCat)->delete();
    }
};

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
            'name' => "EDT-TEST $tag $label",
            'event_location' => $mode,
            'event_link' => "edt-test-$tag-$label",
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

$book = function (Event $event, ?string $phone, string $slot, ?string $date = null) use (&$bookingIds, &$lastError, $bookingRepo, $nextDate) {
    try {
        $b = $bookingRepo->store([
            'name' => 'EDT Guest',
            'phone_call' => $phone,
            'email' => 'edt-guest-'.uniqid().'@example.invalid',
            'schedule_date' => $date ?? $nextDate(),
            'description' => 'EDT test booking',
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
$mkPair = function (string $label, int $mode = Event::PHONE_CALL, bool $fixed = false, ?string $slot = null, ?string $date = null) use ($mkEvent, $addFixed, $book, $owner, $bookerPhone, $S1) {
    $e = $mkEvent($owner, $label, $mode);
    if ($fixed) {
        $addFixed($e, 'Test Address A');
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

$ownerCancel = function ($b) use ($bookingRepo, &$lastError) {
    try {
        $bookingRepo->cancelScheduleEvent('EDT test', $b->id);
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

// Input that puts an event in person at a fixed address (what the app sends from the edit screen).
$addrInput = fn (string $address) => [
    'event_location' => Event::IN_PERSON_MEETING,
    'location_meta' => '1',
    'new_location_type' => Location::FIXED,
    'new_location_address' => $address,
    'new_location_latitude' => 12.9716,
    'new_location_longitude' => 77.5946,
    'new_location_accuracy' => 10,
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
$pendingChange = fn (array $bookings) => NotificationOutbox::whereIn('event_schedule_id', $live($bookings))
    ->where('type', 'booking.changed')->where('status', NotificationOutbox::PENDING)->get();
$ctxKeys = fn ($row) => $row ? array_keys((array) ($row->context['changes'] ?? [])) : [];
$bodyOf = fn ($rows) => substr((string) optional($rows->first())->body, 0, 90);

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

$editCleanup = function () use (&$bookingIds, &$eventIds) {
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

// leftovers from earlier kept or crashed runs (events named "EDT-TEST xxxxxx ...")
$oldEventIds = Event::where('name', 'like', 'EDT-TEST %')->pluck('id');
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

$tzKey = $owner->timezone;
$ownerTz = ($tzKey !== null && isset(User::TIME_ZONE_ARRAY[$tzKey])) ? User::TIME_ZONE_ARRAY[$tzKey] : config('app.timezone');
echo "Owner $ownerId, booker $bookerId, second booker ".($booker2 ? $booker2->id : 'none found').", owner time zone $ownerTz".PHP_EOL;
echo "Event change rules: quiet period $quiet min, send immediately when the slot starts within $sendNow min".PHP_EOL.PHP_EOL;

try {
    // ============ EDT-01 change event name ============
    [$e1, $b1] = $mkPair('E01');
    if (! $setupFailed($b1, 'EDT-01')) {
        $newName = "EDT-TEST $tag renamed-01";
        $ok = $edit($e1, ['name' => $newName]);
        $pend = $pendingChange([$b1]);
        $row = $pend->first();
        $check('EDT-01 the edit was saved', $ok && Event::find($e1->id)->name === $newName, (string) $lastError);
        $check('EDT-01 one pending change for the booker, variant event_edited',
            $pend->count() === 1 && (int) $row->user_id === $bookerId && $row->reason === 'event_edited',
            'rows='.$pend->count().', reason='.optional($row)->reason);
        $check('EDT-01 it waits the quiet period and nothing is sent yet',
            $row && abs($row->notify_at->timestamp - Carbon::now()->addMinutes($quiet)->timestamp) <= 30 && $changeNotifs([$b1])->count() === 0,
            'notify_at='.optional($row)->notify_at);
        $check('EDT-01 the pending change records the name', in_array('name', $ctxKeys($row), true), implode(',', $ctxKeys($row)));

        $pushBefore = $pushCount();
        $res = $settle([$b1]);
        $rows = $changeNotifs([$b1]);
        $check('EDT-01 after the quiet period the booker gets exactly one notification',
            $res['sent'] === 1 && $rows->count() === 1 && (int) $rows->first()->user_id === $bookerId && $rows->first()->reason === 'event_edited',
            'rows='.$rows->count().', body="'.$bodyOf($rows).'"');
        $check('EDT-01 one push job is queued', ($pushCount() - $pushBefore) === 1, 'push jobs='.($pushCount() - $pushBefore));
    }

    // ============ EDT-02 change description ============
    [$e2, $b2] = $mkPair('E02');
    if (! $setupFailed($b2, 'EDT-02')) {
        $edit($e2, ['description' => 'EDT description changed']);
        $row = $pendingChange([$b2])->first();
        $check('EDT-02 pending change event_edited that records the description', $row && $row->reason === 'event_edited' && in_array('description', $ctxKeys($row), true),
            'keys='.implode(',', $ctxKeys($row)));
        $res = $settle([$b2]);
        $rows = $changeNotifs([$b2]);
        $check('EDT-02 the booker gets exactly one notification', $res['sent'] === 1 && $rows->count() === 1 && (int) $rows->first()->user_id === $bookerId,
            'rows='.$rows->count().', body="'.$bodyOf($rows).'"');
    }

    // ============ EDT-03 change meeting mode ============
    $otherMode = collect(array_keys(Event::LOCATION_ARRAY))->first(fn ($k) => (int) $k !== Event::PHONE_CALL && (int) $k !== Event::IN_PERSON_MEETING);
    if ($otherMode === null) {
        $report('SKIP', 'EDT-03 (no third meeting mode found in Event::LOCATION_ARRAY)');
    } else {
        [$e3, $b3] = $mkPair('E03');
        if (! $setupFailed($b3, 'EDT-03')) {
            $edit($e3, ['event_location' => (int) $otherMode]);
            $row = $pendingChange([$b3])->first();
            $check('EDT-03 pending change location_changed that records the mode',
                $row && $row->reason === 'location_changed' && in_array('event_location', $ctxKeys($row), true), 'reason='.optional($row)->reason.', keys='.implode(',', $ctxKeys($row)));
            $res = $settle([$b3]);
            $rows = $changeNotifs([$b3]);
            $check('EDT-03 the booker gets exactly one notification', $res['sent'] === 1 && $rows->count() === 1 && $rows->first()->reason === 'location_changed',
                'rows='.$rows->count().', body="'.$bodyOf($rows).'"');
        }
    }

    // ============ EDT-04 change fixed address ============
    [$e4, $b4] = $mkPair('E04', Event::IN_PERSON_MEETING, true);
    if (! $setupFailed($b4, 'EDT-04')) {
        $ok = $edit($e4, $addrInput('EDT New Address'));
        $row = $pendingChange([$b4])->first();
        $loc = $row ? ($row->context['changes']['location'] ?? null) : null;
        $check('EDT-04 the address edit was saved', $ok && optional(Event::find($e4->id)->location)->address === 'EDT New Address', (string) $lastError);
        $check('EDT-04 pending change location_changed with old and new address',
            $row && $row->reason === 'location_changed' && $loc && $loc['old'] === 'Test Address A' && $loc['new'] === 'EDT New Address',
            'reason='.optional($row)->reason.', location='.json_encode($loc));
        $res = $settle([$b4]);
        $rows = $changeNotifs([$b4]);
        $check('EDT-04 the booker gets exactly one notification', $res['sent'] === 1 && $rows->count() === 1, 'rows='.$rows->count().', body="'.$bodyOf($rows).'"');
    }

    // ============ EDT-05 name and description together ============
    [$e5, $b5] = $mkPair('E05');
    if (! $setupFailed($b5, 'EDT-05')) {
        $edit($e5, ['name' => "EDT-TEST $tag renamed-05", 'description' => 'EDT description 05']);
        $pend = $pendingChange([$b5]);
        $keys = $ctxKeys($pend->first());
        $check('EDT-05 one pending change that records both fields', $pend->count() === 1 && in_array('name', $keys, true) && in_array('description', $keys, true), 'rows='.$pend->count().', keys='.implode(',', $keys));
        $pushBefore = $pushCount();
        $res = $settle([$b5]);
        $rows = $changeNotifs([$b5]);
        $check('EDT-05 exactly one notification and one push', $res['sent'] === 1 && $rows->count() === 1 && ($pushCount() - $pushBefore) === 1,
            'rows='.$rows->count().', push jobs='.($pushCount() - $pushBefore).', body="'.$bodyOf($rows).'"');
    }

    // ============ EDT-06 mode and address together ============
    [$e6, $b6] = $mkPair('E06'); // phone call -> in person at an address
    if (! $setupFailed($b6, 'EDT-06')) {
        $edit($e6, $addrInput('EDT Address 06'));
        $pend = $pendingChange([$b6]);
        $keys = $ctxKeys($pend->first());
        $check('EDT-06 one pending change location_changed that records mode and address',
            $pend->count() === 1 && $pend->first()->reason === 'location_changed' && in_array('event_location', $keys, true) && in_array('location', $keys, true),
            'rows='.$pend->count().', keys='.implode(',', $keys));
        $res = $settle([$b6]);
        $rows = $changeNotifs([$b6]);
        $check('EDT-06 exactly one notification', $res['sent'] === 1 && $rows->count() === 1, 'rows='.$rows->count().', body="'.$bodyOf($rows).'"');
    }

    // ============ EDT-07 all meeting details together ============
    [$e7, $b7] = $mkPair('E07');
    if (! $setupFailed($b7, 'EDT-07')) {
        $edit($e7, ['name' => "EDT-TEST $tag renamed-07", 'description' => 'EDT description 07'] + $addrInput('EDT Address 07'));
        $pend = $pendingChange([$b7]);
        $keys = $ctxKeys($pend->first());
        $check('EDT-07 one pending change that records name, description, mode and address',
            $pend->count() === 1 && count(array_intersect(['name', 'description', 'event_location', 'location'], $keys)) === 4, 'rows='.$pend->count().', keys='.implode(',', $keys));
        $res = $settle([$b7]);
        $rows = $changeNotifs([$b7]);
        $check('EDT-07 exactly one notification', $res['sent'] === 1 && $rows->count() === 1, 'rows='.$rows->count().', body="'.$bodyOf($rows).'"');
    }

    // ============ EDT-08 several saves within the quiet period ============
    [$e8, $b8] = $mkPair('E08');
    if (! $setupFailed($b8, 'EDT-08')) {
        $t0 = Carbon::now();
        $edit($e8, ['name' => "EDT-TEST $tag renamed-08"]);
        $at($t0->copy()->addMinutes(max(1, $quiet - 1))); // second save inside the quiet period
        $edit($e8, ['description' => 'EDT second save']);
        $pend = $pendingChange([$b8]);
        $row = $pend->first();
        $keys = $ctxKeys($row);
        $check('EDT-08 the two saves are merged into ONE pending row', $pend->count() === 1 && in_array('name', $keys, true) && in_array('description', $keys, true),
            'rows='.$pend->count().', keys='.implode(',', $keys));
        $expectedAt = $t0->copy()->addMinutes(max(1, $quiet - 1))->addMinutes($quiet);
        $check('EDT-08 the second save restarts the quiet period', $row && abs($row->notify_at->timestamp - $expectedAt->timestamp) <= 30, 'notify_at='.optional($row)->notify_at.', expected='.$expectedAt);
        $at($t0->copy()->addMinutes($quiet + 1)); // after the ORIGINAL notify time, before the bumped one
        $dueEarly = $dueChange([$b8])->count();
        $at($expectedAt->copy()->addSeconds(30));
        $sent = 0;
        foreach ($dueChange([$b8]) as $r) {
            $sent += $dispatchChange($r) === 'sent' ? 1 : 0;
        }
        $real();
        $rows = $changeNotifs([$b8]);
        $check('EDT-08 nothing is due at the original time', $dueEarly === 0, 'due='.$dueEarly);
        $check('EDT-08 one merged notification is sent after the bumped time', $sent === 1 && $rows->count() === 1, 'sent='.$sent.', rows='.$rows->count().', body="'.$bodyOf($rows).'"');
    }

    // 8b: a name edit and a location edit inside the quiet period (different variants)
    [$e8b, $b8b] = $mkPair('E08b');
    if (! $setupFailed($b8b, 'EDT-08b')) {
        $edit($e8b, ['name' => "EDT-TEST $tag renamed-08b"]);
        $at(Carbon::now()->addMinute()); // a minute apart: two edits in the same second would share one notification key by accident
        $edit($e8b, $addrInput('EDT Address 08b'));
        $pend = $pendingChange([$b8b]);
        $res = $settle([$b8b]);
        $rows = $changeNotifs([$b8b]);
        $report($rows->count() === 1 ? 'PASS' : 'INVESTIGATE', 'EDT-08 a name edit and a location edit inside the quiet period',
            $rows->count() === 1 ? 'merged into one notification'
                : 'the booker got '.$rows->count().' notifications (pending rows='.$pend->count().'). The variant (event_edited / location_changed) is part of the outbox key, so edits of different kinds are not merged. Decide if that is wanted');
    }

    // ============ EDT-09 change and restore the original value ============
    [$e9, $b9] = $mkPair('E09');
    if (! $setupFailed($b9, 'EDT-09')) {
        $orig = Event::find($e9->id)->name;
        $edit($e9, ['name' => "EDT-TEST $tag changed-09"]);
        $edit($e9, ['name' => $orig]);
        $row = $pendingChange([$b9])->first();
        $check('EDT-09 the pending change is empty after the value was restored', $row && ($row->context['changes'] ?? null) === [], 'changes='.json_encode(optional($row)->context['changes'] ?? 'n/a'));
        $res = $settle([$b9]);
        $check('EDT-09 nothing is sent', $res['sent'] === 0 && $changeNotifs([$b9])->count() === 0, 'sent='.$res['sent'].', cancelled='.$res['cancelled']);
        $check('EDT-09 the empty row is closed, not left pending', $pendingChange([$b9])->count() === 0);
    }

    // ============ EDT-10 save without changing anything ============
    [$e10, $b10] = $mkPair('E10');
    if (! $setupFailed($b10, 'EDT-10')) {
        $cur = Event::find($e10->id);
        $ok = $edit($e10, ['name' => $cur->name, 'description' => $cur->description, 'event_location' => $cur->event_location, 'event_color' => $cur->event_color]);
        $check('EDT-10 the no-op save worked', $ok, (string) $lastError);
        $check('EDT-10 no pending change is queued', $pendingChange([$b10])->count() === 0, 'pending='.$pendingChange([$b10])->count());
        $res = $settle([$b10]);
        $check('EDT-10 no notification', $changeNotifs([$b10])->count() === 0);
    }
    [$e10b, $b10b] = $mkPair('E10b', Event::IN_PERSON_MEETING, true);
    if (! $setupFailed($b10b, 'EDT-10b')) {
        $edit($e10b, $addrInput('Test Address A')); // the same address it already has
        $check('EDT-10 saving the same fixed address again queues nothing', $pendingChange([$b10b])->count() === 0, 'pending='.$pendingChange([$b10b])->count());
    }

    // ============ EDT-11 edit with no confirmed bookings ============
    $e11 = $mkEvent($owner, 'E11');
    $snapN = (int) DB::table('user_notifications')->max('id');
    $snapO = (int) NotificationOutbox::max('id');
    $ok = $edit($e11, ['name' => "EDT-TEST $tag renamed-11"]);
    $newOut = NotificationOutbox::where('id', '>', $snapO)->where('type', 'booking.changed')->whereIn('user_id', [$ownerId, $bookerId])->count();
    $newNot = DB::table('user_notifications')->where('id', '>', $snapN)->where('type', 'booking.changed')->whereIn('user_id', [$ownerId, $bookerId])->count();
    $check('EDT-11 the edit works with no bookings and queues nothing', $ok && $newOut === 0 && $newNot === 0, 'outbox rows='.$newOut.', notifications='.$newNot.(string) $lastError);

    // ============ EDT-12 several confirmed bookers, each told once ============
    $e12 = $mkEvent($owner, 'E12');
    $b12a = $book($e12, $bookerPhone, $S1);
    $b12b = $book($e12, $bookerPhone, $S1); // same booker, later date
    $b12c = $booker2Phone ? $book($e12, $booker2Phone, $S1) : null;
    if (! $setupFailed($b12a, 'EDT-12') && ! $setupFailed($b12b, 'EDT-12')) {
        $edit($e12, ['name' => "EDT-TEST $tag renamed-12"]);
        $pend = $pendingChange([$b12a, $b12b, $b12c]);
        $expectedBookers = $b12c ? 2 : 1;
        $check('EDT-12 one pending change per person (not per booking)', $pend->count() === $expectedBookers, 'rows='.$pend->count().', expected='.$expectedBookers);
        $check('EDT-12 a person with two bookings is told via the EARLIEST one', optional($pend->firstWhere('user_id', $bookerId))->event_schedule_id === $b12a->id);
        $pushBefore = $pushCount();
        $res = $settle([$b12a, $b12b, $b12c]);
        $rows = $changeNotifs([$b12a, $b12b, $b12c]);
        $check('EDT-12 booker 1 gets exactly one notification', $rows->where('user_id', $bookerId)->count() === 1, 'rows='.$rows->where('user_id', $bookerId)->count());
        if ($b12c) {
            $check('EDT-12 booker 2 gets exactly one notification', $rows->where('user_id', $booker2->id)->count() === 1, 'rows='.$rows->where('user_id', $booker2->id)->count());
        } else {
            $report('SKIP', 'EDT-12 second booker part (no second user with a unique phone number; set $booker2Id)');
        }
        $check('EDT-12 one push per person', ($pushCount() - $pushBefore) === $expectedBookers, 'push jobs='.($pushCount() - $pushBefore));
    }

    // ============ EDT-13 cancelled bookings excluded ============
    [$e13a, $b13a] = $mkPair('E13a');
    if (! $setupFailed($b13a, 'EDT-13')) {
        $ownerCancel($b13a);
        $edit($e13a, ['name' => "EDT-TEST $tag renamed-13a"]);
        $settle([$b13a]);
        $check('EDT-13 an event whose only booking is cancelled queues and sends nothing', $pendingChange([$b13a])->count() === 0 && $changeNotifs([$b13a])->count() === 0);
    }
    $e13b = $mkEvent($owner, 'E13b');
    $c13 = $book($e13b, $bookerPhone, $S1);  // earlier date, will be cancelled
    $a13 = $book($e13b, $bookerPhone, $S1);  // later date, stays confirmed
    if (! $setupFailed($c13, 'EDT-13') && ! $setupFailed($a13, 'EDT-13')) {
        $ownerCancel($c13);
        $edit($e13b, ['name' => "EDT-TEST $tag renamed-13b"]);
        $pend = $pendingChange([$c13, $a13]);
        $check('EDT-13 the change is attached to the confirmed booking, not the cancelled one', $pend->count() === 1 && $pend->first()->event_schedule_id === $a13->id,
            'rows='.$pend->count().', booking='.optional($pend->first())->event_schedule_id);
        $settle([$c13, $a13]);
        $rows = $changeNotifs([$c13, $a13]);
        $check('EDT-13 the booker is still told once', $rows->count() === 1 && (int) $rows->first()->user_id === $bookerId, 'rows='.$rows->count());
    }

    // ============ EDT-14 ended bookings excluded ============
    $e14 = $mkEvent($owner, 'E14');
    $p14 = $book($e14, $bookerPhone, $S1);  // will have ended
    $f14 = $book($e14, $bookerPhone, $S1);  // a later date, still upcoming
    if (! $setupFailed($p14, 'EDT-14') && ! $setupFailed($f14, 'EDT-14')) {
        $pEnd = $notifier->endUtc($p14->fresh());
        $at($pEnd->copy()->addMinutes(10)); // the first booking ended 10 minutes ago
        $edit($e14, ['name' => "EDT-TEST $tag renamed-14"]);
        $pend = $pendingChange([$p14, $f14]);
        $check('EDT-14 the ended booking is skipped and the upcoming one carries the change', $pend->count() === 1 && $pend->first()->event_schedule_id === $f14->id,
            'rows='.$pend->count().', booking='.optional($pend->first())->event_schedule_id);
        $settle([$p14, $f14]);
        $check('EDT-14 the booker is told once', $changeNotifs([$p14, $f14])->count() === 1);
    }
    [$e14b, $b14b] = $mkPair('E14b');
    if (! $setupFailed($b14b, 'EDT-14b')) {
        $at($notifier->endUtc($b14b->fresh())->copy()->addDays(2)); // two days after it ended
        $edit($e14b, ['name' => "EDT-TEST $tag renamed-14b"]);
        $pending = $pendingChange([$b14b])->count();
        $settle([$b14b]);
        $check('EDT-14 a booking that ended two days ago is not notified', $pending === 0 && $changeNotifs([$b14b])->count() === 0, 'pending='.$pending);
    }

    // ============ EDT-15 booking in progress is included ============
    [$e15, $b15] = $mkPair('E15');
    if (! $setupFailed($b15, 'EDT-15')) {
        $at($startOf($b15)->copy()->addMinutes(10)); // 10 minutes into a 30-minute slot
        $edit($e15, ['name' => "EDT-TEST $tag renamed-15"]);
        $rows = $changeNotifs([$b15]);
        $pending = $pendingChange([$b15])->count();
        $real();
        $check('EDT-15 an in-progress booking is notified immediately', $rows->count() === 1 && (int) $rows->first()->user_id === $bookerId && $pending === 0,
            'rows='.$rows->count().', pending='.$pending.', body="'.$bodyOf($rows).'"');
    }

    // ============ EDT-16 edit shortly before the start (immediate under the 1-hour rule) ============
    [$e16, $b16] = $mkPair('E16');
    if (! $setupFailed($b16, 'EDT-16')) {
        $pushBefore = $pushCount();
        $at($startOf($b16)->copy()->subMinutes(max(1, $sendNow - 1)));
        $edit($e16, ['name' => "EDT-TEST $tag renamed-16"]);
        $rows = $changeNotifs([$b16]);
        $pending = $pendingChange([$b16])->count();
        $real();
        $check('EDT-16 '.($sendNow - 1).' minutes before the start: sent immediately, nothing waits', $rows->count() === 1 && $pending === 0,
            'rows='.$rows->count().', pending='.$pending);
        $check('EDT-16 one push job is queued', ($pushCount() - $pushBefore) === 1, 'push jobs='.($pushCount() - $pushBefore));
    }
    [$e16b, $b16b] = $mkPair('E16b');
    if (! $setupFailed($b16b, 'EDT-16b')) {
        $at($startOf($b16b)->copy()->subMinutes($sendNow + 1));
        $edit($e16b, ['name' => "EDT-TEST $tag renamed-16b"]);
        $rowsNow = $changeNotifs([$b16b])->count();
        $pending = $pendingChange([$b16b])->count();
        $settle([$b16b]);
        $check('EDT-16 '.($sendNow + 1).' minutes before the start: waits the quiet period, then one notification',
            $rowsNow === 0 && $pending === 1 && $changeNotifs([$b16b])->count() === 1, 'sent now='.$rowsNow.', pending='.$pending);
    }

    // ============ EDT-17 push preference disabled ============
    [$e17, $b17] = $mkPair('E17');
    if (! $setupFailed($b17, 'EDT-17')) {
        $prefRepo->set($bookerId, $prefCat, false);
        $edit($e17, ['name' => "EDT-TEST $tag renamed-17"]);
        $pushBefore = $pushCount();
        $res = $settle([$b17]);
        $rows = $changeNotifs([$b17]);
        $check('EDT-17 push off: the inbox row is still created', $res['sent'] === 1 && $rows->count() === 1 && (int) $rows->first()->user_id === $bookerId,
            'rows='.$rows->count().', category='.optional($rows->first())->category);
        $check('EDT-17 push off: no push job is queued', ($pushCount() - $pushBefore) === 0, 'push jobs='.($pushCount() - $pushBefore));

        // control: the same edit with push on does queue a push, so the check above is meaningful
        $prefRepo->set($bookerId, $prefCat, true);
        [$e17b, $b17b] = $mkPair('E17b');
        if (! $setupFailed($b17b, 'EDT-17')) {
            $edit($e17b, ['name' => "EDT-TEST $tag renamed-17b"]);
            $pushBefore = $pushCount();
            $settle([$b17b]);
            $check('EDT-17 control: with push on, the same edit queues one push', ($pushCount() - $pushBefore) === 1, 'push jobs='.($pushCount() - $pushBefore));
        }

        $prefRestore();
        $now = NotificationPreference::where('user_id', $bookerId)->where('category', $prefCat)->first();
        $check('EDT-17 the booker\'s original preference was put back',
            $prefExisted ? ($now && (bool) $now->push_enabled === (bool) $prefValue) : $now === null);
    }

    // ============ EDT-18 the owner does not get their own change notification ============
    [$e18, $b18] = $mkPair('E18');
    if (! $setupFailed($b18, 'EDT-18')) {
        $self18 = $ownerPhoneOk ? $book($e18, $ownerPhone, $S1) : null; // the owner books their own event
        $edit($e18, ['name' => "EDT-TEST $tag renamed-18"]);
        $pend = $pendingChange([$b18, $self18]);
        $settle([$b18, $self18]);
        $rows = $changeNotifs([$b18, $self18]);
        $check('EDT-18 the owner gets no change notification', $rows->where('user_id', $ownerId)->count() === 0 && $pend->where('user_id', $ownerId)->count() === 0,
            'owner rows='.$rows->where('user_id', $ownerId)->count());
        $check('EDT-18 the booker still gets one', $rows->where('user_id', $bookerId)->count() === 1);
        if ($ownerPhoneOk) {
            $check('EDT-18 a booking the owner made on their own event creates no change row', $self18 === null ? false : $pend->where('event_schedule_id', $self18->id)->count() === 0,
                $self18 === null ? 'could not create the owner booking: '.$lastError : 'ok');
        } else {
            $report('SKIP', 'EDT-18 owner-booked-own-event variant (user '.$ownerId.'\'s phone does not resolve to only that user)');
        }
    }

    // ============ EDT-19 colour-only change ============
    [$e19, $b19] = $mkPair('E19');
    if (! $setupFailed($b19, 'EDT-19')) {
        $ok = $edit($e19, ['event_color' => '#112233']);
        $check('EDT-19 the colour was saved', $ok && Event::find($e19->id)->event_color === '#112233', (string) $lastError);
        $check('EDT-19 no pending change is queued', $pendingChange([$b19])->count() === 0, 'pending='.$pendingChange([$b19])->count());
        $settle([$b19]);
        $check('EDT-19 no notification', $changeNotifs([$b19])->count() === 0);
    }

    // ============ EDT-20 availability / slot / gap / date-range changes ============
    [$e20, $b20] = $mkPair('E20');
    if (! $setupFailed($b20, 'EDT-20')) {
        Auth::setUser($owner);

        try {
            $result = $eventRepo->storeEventSchedule([
                'event_id' => $e20->id,
                'timezone' => $owner->timezone,
                'schedule_name' => Event::find($e20->id)->schedule_id,
                'gap_slot' => 15,
                'slot_time' => 45,
                'date_range' => 1,
                'schedule_days' => 45,
                'check_tab' => UserSchedule::CUSTOM_SCHEDULE,
            ]);
            $lastError = null;
        } catch (Throwable $e) {
            $result = null;
            $lastError = $e->getMessage();
        } finally {
            Auth::forgetUser();
            date_default_timezone_set($tzBefore);
        }

        if ($result === null) {
            $report('INVESTIGATE', 'EDT-20 storeEventSchedule() could not run with the minimal input', substr((string) $lastError, 0, 160));
        } else {
            $fresh = Event::find($e20->id);
            $check('EDT-20 the schedule change was saved (slot length 45, gap 15, 45 days)',
                (int) $fresh->slot_time === 45 && (int) $fresh->gap_slot === 15 && (int) $fresh->schedule_days === 45,
                'slot_time='.$fresh->slot_time.', gap='.$fresh->gap_slot.', days='.$fresh->schedule_days);
            $check('EDT-20 no pending change is queued', $pendingChange([$b20])->count() === 0, 'pending='.$pendingChange([$b20])->count());
            $settle([$b20]);
            $check('EDT-20 no notification under the current policy', $changeNotifs([$b20])->count() === 0);
        }
    }

    // ============ real-push demo for user 83 (only with $editKeep = true) ============
    $editCleanup(); // always remove the data from the checks above

    if ($editKeep) {
        $editDemoStarted = true;
        Carbon::setTestNow();
        $restoreBus(); // real dispatcher, so the push jobs go to your database queue

        if (app(Dispatcher::class) instanceof \Illuminate\Support\Testing\Fakes\BusFake) {
            echo PHP_EOL.'FAIL  the real dispatcher could not be restored, so no real pushes can be sent. Start a new tinker session and run again.'.PHP_EOL;
        } elseif (! $ownerPhoneOk) {
            echo PHP_EOL.'SKIP  demo: user '.$ownerId.'\'s phone does not resolve to only that user, so it cannot be the booker'.PHP_EOL;
        } else {
            if (! $prefRepo->isPushEnabled($ownerId, $prefCat, true)) {
                echo PHP_EOL.'NOTE  user '.$ownerId.' has push turned off for "'.$prefCat.'", so the demo will create inbox rows but no pushes.'.PHP_EOL;
            }
            $demoStart = (int) DB::table('user_notifications')->max('id');
            $dA = $mkEvent($booker, 'DA');   // event owned by 101, booked by 83
            $dB = $mkEvent($booker, 'DB');
            $dA1 = $book($dA, $ownerPhone, $S1);
            $dB1 = $book($dB, $ownerPhone, $S1);

            if ($dA1 && $dB1) {
                $edit($dA, ['name' => "EDT-TEST $tag demo renamed", 'description' => 'Demo description change']); // event_edited
                $edit($dB, $addrInput('Demo Street 1'));                                                        // location_changed
                // No clock jump here: the two change rows are due in $quiet minutes of REAL time and your
                // notifications:tick loop (dispatch-due) sends them, exactly like a real edit.
            } else {
                $report('INVESTIGATE', 'demo could not create its bookings', (string) $lastError);
            }

            $pushPref = $prefRepo->isPushEnabled($ownerId, $prefCat, true);
            echo PHP_EOL.'--- Real push demo (no clock jump) ---'.PHP_EOL;
            echo 'Push setting of user '.$ownerId.' for "'.$prefCat.'": '.($pushPref ? 'on' : 'OFF (no push will be sent)').PHP_EOL;
            echo 'Two edits were saved. Their change notifications are due in '.$quiet.' minutes; your notifications:tick loop sends them.'.PHP_EOL;
            echo 'Wait about '.($quiet + 2).' minutes. Expect 2 pushes (event_edited and location_changed) on each active device of user '.$ownerId.'.'.PHP_EOL;
            echo 'To see what was created (run after the wait):'.PHP_EOL;
            echo "  DB::table('user_notifications')->where('id', '>', $demoStart)->where('user_id', $ownerId)->where('type', 'booking.changed')->get(['id','reason','title','created_at']);".PHP_EOL;
            echo 'Do NOT run $editCleanup() before the pushes arrived. Then run:  $editCleanup();'.PHP_EOL;
        }
    }
} finally {
    Carbon::setTestNow();
    Auth::forgetUser();
    date_default_timezone_set($tzBefore);
    $prefRestore();
    $restoreBus();
    app()->forgetInstance('mail.manager');
    Facade::clearResolvedInstance('mail.manager');

    if (! $editDemoStarted) {
        $editCleanup();
    }
}
