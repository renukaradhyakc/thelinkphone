<?php

/*
 * Live location checks (the Section 6 cases the Postman suite could not do) + a real-device demo.
 * Owner = user 101 (shares location), booker = user 83 (gets the notifications).
 *
 * LOCAL DATABASE ONLY. It drives the REAL EventLocationController (start / stop / update / active
 * session) and EventController::changeStatus as the owner, reads the real user_notifications rows,
 * and deletes everything it created. Pushes and mail are faked during the checks.
 *
 *   php artisan tinker                       (fresh session, type it, do not paste)
 *   >>> include 'live-location-test.php';    checks only, no real pushes
 *
 * Real pushes to user 83's phones (run the checks first, then the demo is set up):
 *   >>> $livKeep = true;
 *   >>> include 'live-location-test.php';
 *   then run the steps one at a time, at your own pace (wait until each push arrives):
 *   >>> $livStart();            83 gets "Location sharing started"                       (push)
 *   >>> $livStop();             83 gets "Location sharing stopped"                       (push)
 *   >>> $livStart();            83 gets "Location sharing started" again, new session    (push)
 *   >>> $livExpire();           83 gets "Location sharing ended ... after 24 hours"      (push)
 *                               101 gets "Live Location sharing stopped automatically..." (push, on 101's devices)
 *   >>> $livScheduleEnded();    83 gets "Sharing ended because the schedule finished"    (inbox only, NO push)
 *   >>> $livRows();             prints the inbox rows the demo created for user 83
 *   >>> $livCleanup();          deletes the demo data
 *   Expected on user 83's devices: 4 pushes (steps 1-4), plus 1 inbox-only row (step 5).
 *
 * Optional before the include: $ownerId = 101; $bookerId = 83; $booker2Id = <another user>.
 *
 * Editing uses the real EventRepository::update(); deleting uses the real Livewire Events::delete().
 * The checks run in order LIV-07 ... LIV-34. LIV-12 was skipped by choice, LIV-31 is covered by the Postman suite.
 */

use App\Http\Controllers\EventController;
use App\Http\Controllers\EventLocationController;
use App\Models\Event;
use App\Models\EventSchedule;
use App\Models\Location;
use App\Models\User;
use App\Modules\Notifications\Jobs\SendPushNotificationJob;
use App\Modules\Notifications\Models\NotificationOutbox;
use App\Modules\Notifications\Services\BookingNotifier;
use App\Modules\Notifications\Services\LocationNotifier;
use App\Modules\Notifications\Services\RecipientResolver;
use App\Repositories\EventRepository;
use App\Repositories\ScheduleEventRepository;
use Carbon\Carbon;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

$ownerId = (int) ($ownerId ?? 101);
$bookerId = (int) ($bookerId ?? 83);
$booker2Id = isset($booker2Id) ? (int) $booker2Id : null;
$livKeep = $livKeep ?? false;

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

Auth::setUser($owner);
$authOk = (int) getLogInUserId() === $ownerId;
Auth::forgetUser();
if (! $authOk) {
    echo 'SKIP  getLogInUserId() does not follow Auth::setUser()'.PHP_EOL;

    return;
}

$realLive = Event::where('user_id', $ownerId)->where('name', 'not like', 'LIV-%')
    ->whereHas('location', fn ($q) => $q->where('location_type', Location::LIVE)->where('is_live_sharing_active', true))->first();
if ($realLive) {
    echo "SKIP  user $ownerId is really sharing live location for \"{$realLive->name}\". Stop that first: only one session can be active at a time.".PHP_EOL;

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

// ---- Fakes: pushes are faked during the checks; mail always -----------------------------------
$origBus = app(Dispatcher::class);
$restoreBus = function () use ($origBus) {
    app()->instance(Dispatcher::class, $origBus);
    Facade::clearResolvedInstance(Dispatcher::class);
};
Mail::fake();
Bus::fake([SendPushNotificationJob::class]);

// ---- Helpers ----------------------------------------------------------------------------------
$tag = substr(uniqid(), -6);
$tzOf = fn (User $u) => ($u->timezone !== null && isset(User::TIME_ZONE_ARRAY[$u->timezone])) ? User::TIME_ZONE_ARRAY[$u->timezone] : config('app.timezone');
$ownerTz = $tzOf($owner);
$eventIds = [];
$bookingIds = [];
$lastError = null;
$startId = (int) DB::table('user_notifications')->max('id');
$outboxStartId = (int) NotificationOutbox::max('id');

$eventRepo = app(EventRepository::class);
$bookingRepo = app(ScheduleEventRepository::class);
$notifier = app(BookingNotifier::class);
$locNotifier = app(LocationNotifier::class);
$pushCount = fn () => Bus::dispatched(SendPushNotificationJob::class)->count();

$slotFrom = fn (Carbon $s, int $min = 30) => $s->format('h:i A').' - '.$s->copy()->addMinutes($min)->format('h:i A');
$tomorrowAt = fn (int $hour) => Carbon::now($ownerTz)->addDay()->setTime($hour, 0, 0);

$mkLive = function (string $label, string $prefix = 'LIV-TEST') use (&$eventIds, $eventRepo, $owner, $tag) {
    // a check that left a session running (for example because it failed half way) must not block the next one
    Location::where('locationable_type', (new Event)->getMorphClass())->whereIn('locationable_id', $eventIds)->where('is_live_sharing_active', true)
        ->update(['is_live_sharing_active' => false, 'live_started_at' => null]);

    Auth::setUser($owner);
    try {
        $e = $eventRepo->store([
            'name' => "$prefix $tag $label",
            'event_location' => Event::IN_PERSON_MEETING,
            'event_link' => strtolower($prefix)."-$tag-$label",
            'event_color' => '#63d867',
            'event_type' => Event::FREE,
        ]);
    } finally {
        Auth::forgetUser();
    }
    $eventIds[] = $e->id;
    Event::where('id', $e->id)->update(['status' => Event::ACTIVE]);
    $e->location()->updateOrCreate([], [
        'location_type' => Location::LIVE,
        'latitude' => 12.9716,
        'longitude' => 77.5946,
        'accuracy' => 10,
        'address' => null,
        'is_live_sharing_active' => false,
        'live_started_at' => null,
    ]);

    return Event::find($e->id);
};

$book = function (Event $event, ?string $phone, Carbon $start, int $minutes = 30) use (&$bookingIds, &$lastError, $bookingRepo, $slotFrom) {
    try {
        $b = $bookingRepo->store([
            'name' => 'LIV Guest',
            'phone_call' => $phone,
            'email' => 'liv-guest-'.uniqid().'@example.invalid',
            'schedule_date' => $start->format('Y-m-d'),
            'description' => 'LIV test booking',
            'user_id' => $event->user_id,
            'event_id' => $event->id,
            'slot_time' => $slotFrom($start, $minutes),
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

// Calls the REAL controller as the owner. Returns ['code', 'msg', 'data'].
$call = function (string $method, Event $e, array $payload = []) use ($owner) {
    Auth::setUser($owner);
    try {
        $ctl = app(EventLocationController::class);
        $resp = $method === 'activeSession'
            ? $ctl->activeSession()
            : $ctl->$method(Request::create('/x', 'POST', $payload), Event::find($e->id));
        $body = $resp->getData(true);

        return ['code' => $resp->getStatusCode(), 'msg' => (string) ($body['message'] ?? ''), 'data' => $body['data'] ?? []];
    } catch (ValidationException $ex) {
        return ['code' => 422, 'msg' => 'validation failed', 'data' => []];
    } catch (HttpExceptionInterface $ex) {
        return ['code' => $ex->getStatusCode(), 'msg' => $ex->getMessage(), 'data' => []];
    } catch (Throwable $ex) {
        return ['code' => 0, 'msg' => get_class($ex).': '.$ex->getMessage(), 'data' => []];
    } finally {
        Auth::forgetUser();
    }
};
$callStatus = function (Event $e) use ($owner) {
    Auth::setUser($owner);
    try {
        return app(EventController::class)->changeStatus($e->id)->getStatusCode();
    } catch (Throwable $ex) {
        return 'error: '.$ex->getMessage();
    } finally {
        Auth::forgetUser();
    }
};

$loc = fn (Event $e) => Event::find($e->id)->location; // always a fresh read
$locRows = fn (Event $e, string $type) => DB::table('user_notifications')->where('type', $type)->where('dedupe_key', 'like', "$type:{$e->id}:%")->get();
$to = fn ($rows, int $uid) => $rows->where('user_id', $uid)->count();
$backdate = fn (Event $e, Carbon $when) => Location::where('id', $loc($e)->id)->update(['live_started_at' => $when]);
$pendingFor = fn ($b) => NotificationOutbox::where('event_schedule_id', $b->id)->where('type', 'booking.reminder')->where('status', NotificationOutbox::PENDING)->count();

$livCleanup = function () use (&$eventIds, &$bookingIds, $outboxStartId, $startId, $ownerId, $bookerId, $booker2) {
    $users = array_filter([$ownerId, $bookerId, optional($booker2)->id]);
    foreach ($bookingIds as $id) {
        DB::table('user_notifications')->where('dedupe_key', 'like', "booking.%:$id:%")->delete();
        NotificationOutbox::where('event_schedule_id', $id)->delete();
    }
    foreach ($eventIds as $id) {
        DB::table('user_notifications')->where('dedupe_key', 'like', "location.%:$id:%")->delete();
    }
    NotificationOutbox::where('id', '>', $outboxStartId)->where('type', 'like', 'location.%')->whereIn('user_id', $users)->delete();
    if ($bookingIds) {
        EventSchedule::whereIn('id', $bookingIds)->delete();
    }
    Event::whereIn('id', $eventIds)->get()->each(function ($e) {
        optional($e->location)->delete();
        $e->delete();
    });
    Location::where('locationable_type', (new Event)->getMorphClass())->whereIn('locationable_id', $eventIds)->delete(); // left behind by deleted events
    $bookingIds = [];
    $eventIds = [];
    echo 'Deleted the throwaway events, bookings, locations, notifications and outbox rows.'.PHP_EOL;
};

// leftovers of crashed or kept runs
$oldEvents = Event::where('name', 'like', 'LIV-TEST %')->orWhere('name', 'like', 'LIV-DEMO %')->get();
if ($oldEvents->isNotEmpty()) {
    $oldBookings = EventSchedule::whereIn('event_id', $oldEvents->pluck('id'))->pluck('id');
    foreach ($oldBookings as $oid) {
        DB::table('user_notifications')->where('dedupe_key', 'like', "booking.%:$oid:%")->delete();
        NotificationOutbox::where('event_schedule_id', $oid)->delete();
    }
    foreach ($oldEvents as $oe) {
        DB::table('user_notifications')->where('dedupe_key', 'like', "location.%:{$oe->id}:%")->delete();
    }
    EventSchedule::whereIn('id', $oldBookings)->delete();
    $oldEvents->each(function ($e) {
        optional($e->location)->delete();
        $e->delete();
    });
    echo 'Removed leftovers of earlier runs: '.$oldEvents->count().' event(s)'.PHP_EOL;
}

echo "Owner $ownerId (shares), booker $bookerId (receives), booker2 ".($booker2->id ?? 'none').", owner time zone $ownerTz".PHP_EOL.PHP_EOL;

try {
    // ---------- shared helpers for the checks below ----------
    $newLive = function (string $label, bool $fixed = false) use ($mkLive, $book, $bookerPhone, $tomorrowAt, $setupFailed, $loc) {
        $e = $mkLive($label);
        if ($fixed) {
            Location::where('id', $loc($e)->id)->update(['location_type' => Location::FIXED, 'address' => '1 Old Street']);
        }
        $b = $book($e, $bookerPhone, $tomorrowAt(10));

        return $setupFailed($b, "LIV-$label") ? [null, null] : [$e, $b];
    };
    $setupEdit = function (string $label, bool $fixed = false) use ($mkLive, $book, $bookerPhone, $ownerTz, $loc, $setupFailed) {
        $e = $mkLive($label);
        if ($fixed) {
            Location::where('id', $loc($e)->id)->update(['location_type' => Location::FIXED, 'address' => '1 Old Street']);
        }
        $b = $book($e, $bookerPhone, Carbon::now($ownerTz)->subMinutes(10)->second(0), 120); // in progress, so booking.changed is sent at once

        return $setupFailed($b, "LIV-$label") ? [null, null] : [$e, $b];
    };
    $edit = function (Event $e, array $over) use ($eventRepo, $owner) {
        $cur = Event::find($e->id);
        $input = array_merge([
            'name' => $cur->name, 'event_location' => $cur->event_location, 'event_link' => $cur->event_link,
            'event_color' => $cur->event_color, 'event_type' => $cur->event_type, 'description' => $cur->description,
        ], $over);
        Auth::setUser($owner);
        try {
            $eventRepo->update($input, $e->id);

            return null;
        } catch (Throwable $ex) {
            return get_class($ex).': '.$ex->getMessage();
        } finally {
            Auth::forgetUser();
        }
    };
    $liveForm = fn (array $o = []) => array_merge([
        'event_location' => Event::IN_PERSON_MEETING, 'location_meta' => json_encode([2, 'Test Street']),
        'new_location_type' => Location::LIVE, 'new_location_latitude' => 12.9716, 'new_location_longitude' => 77.5946, 'new_location_accuracy' => 10,
    ], $o);
    // the real delete code: App\Http\Livewire\Events::delete()
    $deleteEvent = function (Event $e) use ($owner) {
        $tzBefore = date_default_timezone_get();
        Auth::setUser($owner);
        try {
            app()->makeWith(\App\Http\Livewire\Events::class, ['id' => 'liv-test'])->delete($e->id); // Livewire components need an id

            return null;
        } catch (Throwable $ex) {
            return get_class($ex).': '.$ex->getMessage();
        } finally {
            Auth::forgetUser();
            date_default_timezone_set($tzBefore); // delete() changes the PHP time zone
        }
    };
    $changedRows = fn ($b) => DB::table('user_notifications')->where('type', 'booking.changed')->where('dedupe_key', 'like', "booking.changed:{$b->id}:%")->get();
    $cancelRows = fn ($b) => DB::table('user_notifications')->where('type', 'booking.cancelled')->where('dedupe_key', 'like', "booking.cancelled:{$b->id}:%")->get();
    $autoRows = fn (Event $e) => $locRows($e, 'location.auto_stopped');

    // ============ LIV-07 several eligible bookers ============
    if (! $booker2) {
        $report('SKIP', 'LIV-07 needs a second booker: set $booker2Id to another user with a unique phone number');
    } else {
        $e7 = $mkLive('07');
        $a = $book($e7, $bookerPhone, $tomorrowAt(10));
        $b = $book($e7, (string) $booker2->phone_number, $tomorrowAt(11));
        if (! $setupFailed($a, 'LIV-07') && ! $setupFailed($b, 'LIV-07')) {
            $r = $call('startSharing', $e7);
            $rows = $locRows($e7, 'location.started');
            $check('LIV-07 sharing starts', $r['code'] === 200, $r['code'].' '.$r['msg']);
            $check('LIV-07 each booker is notified exactly once', $to($rows, $bookerId) === 1 && $to($rows, $booker2->id) === 1, 'booker='.$to($rows, $bookerId).', booker2='.$to($rows, $booker2->id));
            $check('LIV-07 the owner is not notified about their own session', $to($rows, $ownerId) === 0);
            $call('stopSharing', $e7);
            $rows = $locRows($e7, 'location.stopped');
            $check('LIV-07 on stop each booker is told once', $to($rows, $bookerId) === 1 && $to($rows, $booker2->id) === 1);
        }
    }

    // ============ LIV-08 one booker, several bookings ============
    $e8 = $mkLive('08');
    $a = $book($e8, $bookerPhone, $tomorrowAt(10));
    $b = $book($e8, $bookerPhone, $tomorrowAt(12));
    if (! $setupFailed($a, 'LIV-08') && ! $setupFailed($b, 'LIV-08')) {
        $call('startSharing', $e8);
        $check('LIV-08 a booker with two bookings gets ONE started notification', $to($locRows($e8, 'location.started'), $bookerId) === 1, 'rows='.$to($locRows($e8, 'location.started'), $bookerId));
        $check('LIV-08 there is a single active session for the event', Location::where('locationable_id', $e8->id)->where('is_live_sharing_active', true)->count() === 1);
        $call('stopSharing', $e8);
        $check('LIV-08 on stop the booker is told once', $to($locRows($e8, 'location.stopped'), $bookerId) === 1);
    }

    // ============ LIV-09 ended booking excluded ============
    $e9 = $mkLive('09');
    $a = $book($e9, $bookerPhone, $tomorrowAt(10));
    if (! $setupFailed($a, 'LIV-09')) {
        EventSchedule::where('id', $a->id)->update(['schedule_date' => Carbon::now($ownerTz)->subDay()->format('Y-m-d')]); // the slot is now yesterday
        $call('startSharing', $e9);
        $check('LIV-09 a booking that already ended gets no "sharing started" notification', $to($locRows($e9, 'location.started'), $bookerId) === 0, 'rows='.$to($locRows($e9, 'location.started'), $bookerId));
        $call('stopSharing', $e9);
        $check('LIV-09 and no "stopped" notification either', $to($locRows($e9, 'location.stopped'), $bookerId) === 0);
    }

    // ============ LIV-10 in-progress booking included ============
    $e10 = $mkLive('10');
    $a = $book($e10, $bookerPhone, Carbon::now($ownerTz)->subMinutes(10)->second(0), 60);
    if (! $setupFailed($a, 'LIV-10')) {
        $call('startSharing', $e10);
        $check('LIV-10 a booking that is in progress right now is notified', $to($locRows($e10, 'location.started'), $bookerId) === 1, 'rows='.$to($locRows($e10, 'location.started'), $bookerId));
        $call('stopSharing', $e10);
    }

    // ============ LIV-11 future booking included ============
    $e11 = $mkLive('11');
    $a = $book($e11, $bookerPhone, $tomorrowAt(15));
    if (! $setupFailed($a, 'LIV-11')) {
        $call('startSharing', $e11);
        $check('LIV-11 a future booking is notified', $to($locRows($e11, 'location.started'), $bookerId) === 1, 'rows='.$to($locRows($e11, 'location.started'), $bookerId));
        $call('stopSharing', $e11);
    }

    // ============ LIV-12 ============
    $report('SKIP', 'LIV-12 booker cancels while sharing is active', 'skipped by choice (the OTP cancel page)');

    // ============ LIV-13 live -> fixed while sharing ============
    [$e13, $b13] = $setupEdit('13');
    if ($e13) {
        $call('startSharing', $e13);
        $err = $edit($e13, $liveForm(['new_location_type' => Location::FIXED, 'new_location_address' => '12 Test Street']));
        $l = $loc($e13);
        $ch = $changedRows($b13);
        $check('LIV-13 the edit goes through', $err === null, (string) $err);
        $check('LIV-13 the location is now fixed, with sharing off', $l && (int) $l->location_type === Location::FIXED && ! $l->is_live_sharing_active && $l->live_started_at === null);
        $check('LIV-13 the booker is told sharing stopped, once', $to($locRows($e13, 'location.stopped'), $bookerId) === 1, 'rows='.$to($locRows($e13, 'location.stopped'), $bookerId));
        $check('LIV-13 the booker is told the location changed, once', $ch->count() === 1 && $ch->first()->reason === 'location_changed', 'rows='.$ch->count().', reason='.($ch->first()->reason ?? 'none'));
        $check('LIV-13 the owner has no active session afterwards', ($call('activeSession', $e13)['data']['active'] ?? null) === false);
    }

    // ============ LIV-14 / LIV-15 live -> phone call / video call while sharing ============
    foreach ([['14', 'phone call', Event::PHONE_CALL], ['15', 'video call', Event::VIDEO_CALL]] as [$n, $label, $mode]) {
        [$ex, $bx] = $setupEdit($n);
        if ($ex) {
            $call('startSharing', $ex);
            $err = $edit($ex, ['event_location' => $mode]);
            $ch = $changedRows($bx);
            $check("LIV-$n the edit to $label goes through", $err === null, (string) $err);
            $check("LIV-$n the live location record is removed", Event::find($ex->id)->location === null);
            $check("LIV-$n the booker is told sharing stopped, once", $to($locRows($ex, 'location.stopped'), $bookerId) === 1, 'rows='.$to($locRows($ex, 'location.stopped'), $bookerId));
            $check("LIV-$n the booker is told the meeting mode changed, once", $ch->count() === 1 && $ch->first()->reason === 'location_changed', 'rows='.$ch->count().', reason='.($ch->first()->reason ?? 'none'));
            $check("LIV-$n the owner has no active session afterwards", ($call('activeSession', $ex)['data']['active'] ?? null) === false);
        }
    }

    // ============ LIV-16 fixed -> live, sharing started in the same save ============
    [$e16, $b16] = $setupEdit('16', true);
    if ($e16) {
        $err = $edit($e16, $liveForm(['new_location_is_live_sharing_active' => 1]));
        $l = $loc($e16);
        $ch = $changedRows($b16);
        $check('LIV-16 the edit goes through', $err === null, (string) $err);
        $check('LIV-16 the location is live and sharing is on', $l && (int) $l->location_type === Location::LIVE && (bool) $l->is_live_sharing_active);
        $check('LIV-16 the booker gets exactly one "sharing started"', $to($locRows($e16, 'location.started'), $bookerId) === 1, 'rows='.$to($locRows($e16, 'location.started'), $bookerId));
        $report($ch->count() === 0 ? 'PASS' : 'INFO', 'LIV-16 no extra "details changed" notice alongside "sharing started"', 'booking.changed rows='.$ch->count());
        $call('stopSharing', $e16);
    }

    // LIV-16 (one-session rule): the start endpoint refuses a second live event; does the edit path?
    $eRef = $mkLive('16ref');
    [$e16b, $b16b] = $setupEdit('16b', true);
    if ($e16b) {
        $r = $call('startSharing', $eRef);
        $edit($e16b, $liveForm(['new_location_is_live_sharing_active' => 1]));
        $activeCount = Location::whereIn('locationable_id', [$eRef->id, $e16b->id])->where('is_live_sharing_active', true)->count();
        $report($activeCount === 1 ? 'PASS' : 'INVESTIGATE', 'LIV-16 editing a second event to "live + sharing on" respects the one-session rule', "events sharing at once=$activeCount (start endpoint answered {$r['code']} for the first)".($activeCount === 2 ? '. The start endpoint refuses this with 409, but EventRepository::saveEventLocation does not check it' : ''));
        $call('stopSharing', $eRef);
        $call('stopSharing', $e16b);
    }

    // ============ LIV-17 static address edit, sharing off ============
    [$e17, $b17] = $setupEdit('17', true);
    if ($e17) {
        $err = $edit($e17, $liveForm(['new_location_type' => Location::FIXED, 'new_location_address' => '99 New Street']));
        $ch = $changedRows($b17);
        $check('LIV-17 the edit goes through', $err === null, (string) $err);
        $check('LIV-17 the booker is told the location changed, once', $ch->count() === 1 && $ch->first()->reason === 'location_changed', 'rows='.$ch->count().', reason='.($ch->first()->reason ?? 'none'));
        $check('LIV-17 no live-location notifications are sent', $locRows($e17, 'location.started')->count() === 0 && $locRows($e17, 'location.stopped')->count() === 0);
    }

    // ============ LIV-18 editing a live event while sharing is ON ============
    [$e18, $b18] = $setupEdit('18');
    if ($e18) {
        $call('startSharing', $e18);
        $before = $loc($e18)->live_started_at;
        $err = $edit($e18, $liveForm(['name' => $e18->name.' edited', 'new_location_is_live_sharing_active' => 1]));
        $l = $loc($e18);
        $ch = $changedRows($b18);
        $check('LIV-18 a name edit with the sharing flag kept ON leaves the session running', $err === null && (bool) $l->is_live_sharing_active && $before && $l->live_started_at && $before->equalTo($l->live_started_at), (string) $err);
        $check('LIV-18 no new "started" or "stopped" notice', $to($locRows($e18, 'location.started'), $bookerId) === 1 && $locRows($e18, 'location.stopped')->count() === 0);
        $check('LIV-18 the booker gets one "event edited" notice', $ch->count() === 1 && $ch->first()->reason === 'event_edited', 'rows='.$ch->count().', reason='.($ch->first()->reason ?? 'none'));
        $call('stopSharing', $e18);
    }
    [$e18b, $b18b] = $setupEdit('18b');
    if ($e18b) {
        $call('startSharing', $e18b);
        $edit($e18b, $liveForm(['name' => $e18b->name.' edited']));   // no new_location_is_live_sharing_active
        $l = $loc($e18b);
        $stopped = $to($locRows($e18b, 'location.stopped'), $bookerId);
        $report('INFO', 'LIV-18 an edit that leaves out the sharing flag', ($l && ! $l->is_live_sharing_active)
            ? "ends the live session and tells the booker it stopped (stopped rows=$stopped). Check that your edit form (web and app) always sends new_location_is_live_sharing_active=1 while sharing"
            : 'the session keeps running');
        $call('stopSharing', $e18b);
    }

    // ============ LIV-19 delete the event while sharing is ON ============
    $e19 = $mkLive('19');
    $b19a = $book($e19, $bookerPhone, Carbon::now($ownerTz)->subMinutes(10)->second(0), 120); // in progress
    $b19b = $book($e19, $bookerPhone, $tomorrowAt(10));                                       // future, has reminders
    if (! $setupFailed($b19a, 'LIV-19') && ! $setupFailed($b19b, 'LIV-19')) {
        $call('startSharing', $e19);
        $pendingBefore = $pendingFor($b19b);
        $err = $deleteEvent($e19);
        $rowsA = $cancelRows($b19a);
        $rowsB = $cancelRows($b19b);
        $notices = $to($rowsA, $bookerId) + $to($rowsB, $bookerId);
        $check('LIV-19 the event is deleted', $err === null && Event::find($e19->id) === null, (string) $err);
        $check('LIV-19 the booker is told the event was deleted', $notices >= 1 && ($rowsA->first()->reason ?? $rowsB->first()->reason) === 'event_deleted', 'cancellation notices='.$notices);
        $report($notices === 1 ? 'PASS' : 'INFO', 'LIV-19 a booker with two bookings on the deleted event', "gets $notices notice(s): one per booking");
        $check('LIV-19 the owner gets no cancellation notice for their own delete', $to($rowsA, $ownerId) + $to($rowsB, $ownerId) === 0);
        $check('LIV-19 the future booking\'s reminders are cancelled', $pendingBefore > 0 && $pendingFor($b19b) === 0, "pending $pendingBefore -> ".$pendingFor($b19b));
        $check('LIV-19 the owner has no active session afterwards', ($call('activeSession', $e19)['data']['active'] ?? null) === false);
        $stopNotice = $to($locRows($e19, 'location.stopped'), $bookerId);
        $report('INFO', 'LIV-19 "sharing stopped" notice on delete', $stopNotice === 0 ? 'none: the booker only gets the "event deleted" notice' : "rows=$stopNotice");
        $orphan = Location::where('locationable_id', $e19->id)->where('locationable_type', (new Event)->getMorphClass())->first();
        $report($orphan === null ? 'PASS' : ($orphan->is_live_sharing_active ? 'INVESTIGATE' : 'INFO'), 'LIV-19 the live location record goes away with the event', $orphan === null ? 'removed' : 'a location row is left behind'.($orphan->is_live_sharing_active ? ' and it is still marked as sharing' : ''));
    }

    // ============ LIV-20 delete the event while sharing is OFF ============
    [$e20, $b20] = $newLive('20');
    if ($e20) {
        $err = $deleteEvent($e20);
        $rows = $cancelRows($b20);
        $check('LIV-20 the event is deleted', $err === null && Event::find($e20->id) === null, (string) $err);
        $check('LIV-20 the booker gets exactly one "event deleted" notice', $to($rows, $bookerId) === 1 && ($rows->first()->reason ?? '') === 'event_deleted', 'rows='.$to($rows, $bookerId));
        $check('LIV-20 no live-location notice is sent', $locRows($e20, 'location.started')->count() === 0 && $locRows($e20, 'location.stopped')->count() === 0);
    }

    // ============ LIV-21 delete during an in-progress booking ============
    $e21 = $mkLive('21');
    $b21 = $book($e21, $bookerPhone, Carbon::now($ownerTz)->subMinutes(10)->second(0), 120);
    if (! $setupFailed($b21, 'LIV-21')) {
        $err = $deleteEvent($e21);
        $check('LIV-21 the booker of a slot that is running right now is told', $err === null && $to($cancelRows($b21), $bookerId) === 1, 'rows='.$to($cancelRows($b21), $bookerId).' '.$err);
    }

    // ============ LIV-22 delete after the booking ended ============
    [$e22, $b22] = $newLive('22');
    if ($e22) {
        EventSchedule::where('id', $b22->id)->update(['schedule_date' => Carbon::now($ownerTz)->subDay()->format('Y-m-d')]);
        $err = $deleteEvent($e22);
        $check('LIV-22 a booking that already ended gets no notice when the event is deleted', $err === null && $cancelRows($b22)->count() === 0, 'rows='.$cancelRows($b22)->count().' '.$err);
    }

    // ============ LIV-23 delete with a cancelled booking ============
    [$e23, $b23] = $newLive('23');
    if ($e23) {
        EventSchedule::where('id', $b23->id)->update(['status' => EventSchedule::CANCELLED]);
        $err = $deleteEvent($e23);
        $check('LIV-23 a booking that was already cancelled gets no notice when the event is deleted', $err === null && $cancelRows($b23)->count() === 0, 'rows='.$cancelRows($b23)->count().' '.$err);
    }

    // ============ LIV-24 disable the event while sharing ============
    [$e24, $b24] = $newLive('24');
    if ($e24) {
        $call('startSharing', $e24);
        $code = $callStatus($e24);
        $check('LIV-24 the owner can disable the event', $code === 200, (string) $code);
        $active = $call('activeSession', $e24)['data']['active'] ?? null;
        $check('LIV-24 the owner\'s active-session endpoint no longer reports the disabled event', $active === false, 'active='.json_encode($active));
        $flag = (bool) optional($loc($e24))->is_live_sharing_active;
        $report($flag === false ? 'PASS' : 'INVESTIGATE', 'LIV-24 the sharing flag is cleared when the event is disabled', $flag ? 'the session flag stays ON in the database' : 'cleared');
        $told = $to($locRows($e24, 'location.stopped'), $bookerId);
        $report($told >= 1 ? 'PASS' : 'INVESTIGATE', 'LIV-24 the booker is told that sharing stopped', $told >= 1 ? "rows=$told" : 'nobody is told: the booker\'s map just stops moving');
        $r = $call('startSharing', $e24);
        $check('LIV-24 starting sharing on the disabled event is refused', $r['code'] === 422, $r['code'].' '.$r['msg']);
        $callStatus($e24); // enable again
        $active2 = $call('activeSession', $e24)['data']['active'] ?? null;
        $report($active2 === false ? 'PASS' : 'INVESTIGATE', 'LIV-24 re-enabling does not bring the old session back to life', 'active after re-enable='.json_encode($active2));
        $call('stopSharing', $e24);
    }

    // ============ LIV-25 disable the event with future bookings ============
    [$e25, $b25] = $newLive('25');
    if ($e25) {
        $callStatus($e25); // disable
        $st = (int) EventSchedule::where('id', $b25->id)->value('status');
        $check('LIV-25 the existing future booking is still confirmed after the event is disabled', $st === EventSchedule::BOOKED, 'status='.$st);
        $report('INFO', 'LIV-25 reminders of a disabled event', 'pending reminders='.$pendingFor($b25).' (they are still sent; cancel them if a disabled event should stop sending reminders)');
        $callStatus($e25); // enable again
        $call('startSharing', $e25);
        $check('LIV-25 after re-enabling, the booking still counts: the booker is told when sharing starts', $to($locRows($e25, 'location.started'), $bookerId) === 1, 'rows='.$to($locRows($e25, 'location.started'), $bookerId));
        $call('stopSharing', $e25);
    }

    // ============ LIV-26 the 24-hour cap ============
    [$e26, $b26] = $newLive('26');
    if ($e26) {
        $call('startSharing', $e26);
        $backdate($e26, Carbon::now()->subHours(25));
        $r = $call('activeSession', $e26);
        $rowsB = $autoRows($e26)->where('user_id', $bookerId);
        $rowsO = $autoRows($e26)->where('user_id', $ownerId);
        $check('LIV-26 after 25 hours the session is reported as ended', ($r['data']['active'] ?? null) === false, $r['msg']);
        $check('LIV-26 the sharing flag is cleared', ! (bool) optional($loc($e26))->is_live_sharing_active);
        $check('LIV-26 the booker is told once', $rowsB->count() === 1, 'rows='.$rowsB->count());
        $check('LIV-26 the owner is told once', $rowsO->count() === 1, 'rows='.$rowsO->count());
        $vb = $rowsB->first()->reason ?? 'none';
        $vo = $rowsO->first()->reason ?? 'none';
        $report($vb === 'cap_24h_other' && $vo === 'cap_24h_sharer' ? 'PASS' : 'INVESTIGATE', 'LIV-26 the reason is the 24-hour cap', "booker=$vb, owner=$vo");
    }

    // ============ LIV-27 schedule ended (notifier level) ============
    [$e27, $b27] = $newLive('27');
    if ($e27) {
        $call('startSharing', $e27);
        $startedAt = $loc($e27)->live_started_at;
        $pushBefore = $pushCount();
        $locNotifier->autoStopped($e27->fresh(), $startedAt, false);
        $rows = $autoRows($e27);
        $check('LIV-27 booker and owner each get a "schedule_ended" notification', $to($rows, $bookerId) === 1 && $to($rows, $ownerId) === 1 && $rows->pluck('reason')->unique()->implode(',') === 'schedule_ended', 'reasons='.$rows->pluck('reason')->implode(','));
        $extra = $pushCount() - $pushBefore;
        $report($extra === 0 ? 'PASS' : 'INFO', 'LIV-27 "schedule_ended" is inbox only (no push)', 'push jobs queued='.$extra);
        $report('INFO', 'LIV-27 note', 'checked by calling the notifier directly: I have not seen Location::liveExpiresAt, so I cannot make the real code pick "schedule ended" on its own');
        $call('stopSharing', $e27);
    }

    // ============ LIV-28 reconnect after expiry ============
    [$e28, $b28] = $newLive('28');
    if ($e28) {
        $call('startSharing', $e28);
        $backdate($e28, Carbon::now()->subHours(25));
        $call('activeSession', $e28); // the expiry is detected here
        $before = DB::table('user_notifications')->where('id', '>', $startId)->where('type', 'location.auto_stopped')->count();
        $u = $call('updateLive', $e28, ['latitude' => 13.0, 'longitude' => 77.6]);
        $a2 = $call('activeSession', $e28);
        $after = DB::table('user_notifications')->where('id', '>', $startId)->where('type', 'location.auto_stopped')->count();
        $check('LIV-28 a location update after the auto-stop is refused', $u['code'] === 409, $u['code'].' '.$u['msg']);
        $check('LIV-28 the active-session check after the auto-stop says not active', ($a2['data']['active'] ?? null) === false);
        $check('LIV-28 reconnecting sends no further notifications', $after === $before, "before=$before, after=$after");
    }

    // ============ LIV-29 reconnect near the 24-hour limit ============
    [$e29, $b29] = $newLive('29');
    if ($e29) {
        $call('startSharing', $e29);
        $backdate($e29, Carbon::now()->subHours(24)->addSeconds(30)); // 30 seconds of life left
        $u1 = $call('updateLive', $e29, ['latitude' => 12.9716, 'longitude' => 77.5946]);
        $check('LIV-29 with 30 seconds left an update is still accepted', $u1['code'] === 200, $u1['code'].' '.$u1['msg']);
        Carbon::setTestNow(Carbon::now()->addMinute());
        $u2 = $call('updateLive', $e29, ['latitude' => 12.9716, 'longitude' => 77.5946]);
        Carbon::setTestNow();
        $v = $autoRows($e29)->where('user_id', $bookerId)->first()->reason ?? 'none';
        $check('LIV-29 one minute later the update is refused as expired', $u2['code'] === 409, $u2['code'].' '.$u2['msg']);
        $check('LIV-29 the stop reason is the 24-hour cap', $v === 'cap_24h_other', 'booker reason='.$v);
    }

    // ============ LIV-30 update after expiry ============
    [$e30, $b30] = $newLive('30');
    if ($e30) {
        $call('startSharing', $e30);
        $backdate($e30, Carbon::now()->subHours(25));
        $u = $call('updateLive', $e30, ['latitude' => 13.5, 'longitude' => 77.9]);
        $l = $loc($e30);
        $check('LIV-30 an update on an expired session is refused', $u['code'] === 409, $u['code'].' '.$u['msg']);
        $check('LIV-30 the refused update did not move the stored location', abs((float) $l->latitude - 12.9716) < 0.0001, 'latitude='.$l->latitude);
        $check('LIV-30 and the session is closed', ! (bool) $l->is_live_sharing_active);
    }

    // ============ LIV-31 ============
    $report('INFO', 'LIV-31 two live events for one owner', 'covered by the Postman suite (start endpoint answers 409); see LIV-16 above for the edit path');

    // ============ LIV-32 stop and restart immediately ============
    [$e32, $b32] = $newLive('32');
    if ($e32) {
        $call('startSharing', $e32);
        $call('stopSharing', $e32);
        $call('startSharing', $e32);   // same second, most likely
        $started = $to($locRows($e32, 'location.started'), $bookerId);
        $report($started === 2 ? 'PASS' : 'INVESTIGATE', 'LIV-32 start, stop, start with no pause gives two "started" notifications', "rows=$started".($started === 1 ? ' (the session id is the start time in whole seconds, so a restart inside the same second reuses the id and its notification is suppressed)' : ''));
        $call('stopSharing', $e32);
        sleep(1);
        $call('startSharing', $e32);
        sleep(1);
        $call('stopSharing', $e32);
        $startedKeys = $locRows($e32, 'location.started')->where('user_id', $bookerId)->pluck('dedupe_key')->unique()->count();
        $check('LIV-32 with at least a second between restarts every session gets its own id', $startedKeys >= 2, 'distinct session keys='.$startedKeys);
    }

    // ============ LIV-33 owner loses connectivity ============
    [$e33, $b33] = $newLive('33');
    if ($e33) {
        $call('startSharing', $e33);
        Location::where('id', $loc($e33)->id)->update(['updated_at' => Carbon::now()->subHours(3)]); // no update for 3 hours
        $r = $call('activeSession', $e33);
        $report('INFO', 'LIV-33 owner sent nothing for 3 hours', ($r['data']['active'] ?? false) ? 'the server still treats the session as ACTIVE: there is no "no signal" timeout; it only ends at the 24-hour cap or when the owner stops it' : 'the server ended the session');
        $call('stopSharing', $e33);
    }

    // ============ LIV-34 auto-stop more than once ============
    [$e34, $b34] = $newLive('34');
    if ($e34) {
        $call('startSharing', $e34);
        $backdate($e34, Carbon::now()->subHours(25));
        $startedAt = $loc($e34)->live_started_at;
        $call('activeSession', $e34);
        $call('activeSession', $e34);                              // a second request right behind the first
        $locNotifier->autoStopped($e34->fresh(), $startedAt, true); // and the notifier fired again for the same session
        $locNotifier->autoStopped($e34->fresh(), $startedAt, true);
        $rb = $autoRows($e34);
        $check('LIV-34 running the auto-stop again for the same session adds no duplicate', $to($rb, $bookerId) === 1 && $to($rb, $ownerId) === 1, 'booker='.$to($rb, $bookerId).', owner='.$to($rb, $ownerId));
    }

    echo PHP_EOL.'Pushes queued by these checks (faked, nothing was sent): '.$pushCount().PHP_EOL;

    // ============ the checks are done: always remove their data ============
    $livCleanup();

    // ============ real-push demo (only with $livKeep = true) ============
    if ($livKeep) {
        $restoreBus();

        if (app(Dispatcher::class) instanceof \Illuminate\Support\Testing\Fakes\BusFake) {
            echo PHP_EOL.'FAIL  the real dispatcher could not be restored, so no real pushes can be sent. Start a new tinker session and run again.'.PHP_EOL;
        } else {
            $demoEvent = $mkLive('demo', 'LIV-DEMO');
            $demoBooking = $book($demoEvent, $bookerPhone, Carbon::now($ownerTz)->subMinutes(10)->second(0), 120); // in progress for the next 110 minutes

            if (! $demoBooking) {
                echo 'FAIL  could not create the demo booking: '.$lastError.PHP_EOL;
            } else {
                $demoStartId = (int) DB::table('user_notifications')->max('id');

                $livStart = function () use ($call, $demoEvent) {
                    $r = $call('startSharing', $demoEvent);
                    echo "start: {$r['code']} {$r['msg']}".PHP_EOL;
                };
                $livStop = function () use ($call, $demoEvent) {
                    $r = $call('stopSharing', $demoEvent);
                    echo "stop: {$r['code']} {$r['msg']}".PHP_EOL;
                };
                $livExpire = function () use ($call, $demoEvent, $loc, $backdate) {
                    if (! optional($loc($demoEvent))->is_live_sharing_active) {
                        echo 'No active session: run $livStart() first.'.PHP_EOL;

                        return;
                    }
                    $backdate($demoEvent, Carbon::now()->subHours(25)); // pretend it started 25 hours ago
                    $r = $call('activeSession', $demoEvent);
                    echo 'expire: '.$r['msg'].PHP_EOL;
                };
                $livScheduleEnded = function () use ($call, $demoEvent, $locNotifier, $loc) {
                    $startedAt = now()->subSeconds(random_int(1, 50)); // a fresh session id so the dedupe does not hide it
                    $locNotifier->autoStopped($demoEvent->fresh(), $startedAt, false);
                    echo 'schedule-ended notice sent (inbox only, no push)'.PHP_EOL;
                };
                $livRows = function () use ($demoStartId, $bookerId, $ownerId) {
                    DB::table('user_notifications')->where('id', '>', $demoStartId)->where('type', 'like', 'location.%')->orderBy('id')
                        ->get(['id', 'user_id', 'type', 'reason', 'title'])->each(function ($r) use ($bookerId, $ownerId) {
                            $who = (int) $r->user_id === $bookerId ? 'booker '.$bookerId : ((int) $r->user_id === $ownerId ? 'owner '.$ownerId : 'user '.$r->user_id);
                            echo "  #{$r->id}  $who  {$r->type}.{$r->reason}  \"{$r->title}\"".PHP_EOL;
                        });
                };

                echo PHP_EOL.'--- Real push demo is ready (event owned by user '.$ownerId.', user '.$bookerId.' is the booker) ---'.PHP_EOL;
                echo 'Run the steps one at a time and wait for each push to arrive before the next:'.PHP_EOL;
                echo '  $livStart();          -> user '.$bookerId.': "Location sharing started"                  [push]'.PHP_EOL;
                echo '  $livStop();           -> user '.$bookerId.': "Location sharing stopped"                  [push]'.PHP_EOL;
                echo '  $livStart();          -> user '.$bookerId.': "Location sharing started" (new session)    [push]'.PHP_EOL;
                echo '  $livExpire();         -> user '.$bookerId.': "Location sharing ended" (24 hours)         [push]'.PHP_EOL;
                echo '                           user '.$ownerId.': "Location sharing stopped" (auto, 24 hours)  [push, on '.$ownerId.'\'s devices]'.PHP_EOL;
                echo '  $livScheduleEnded();  -> user '.$bookerId.': "Sharing ended because the schedule finished" [inbox only, no push]'.PHP_EOL;
                echo '  $livRows();           shows what was created;   $livCleanup();   removes the demo data'.PHP_EOL;
                echo 'Expected on user '.$bookerId.'\'s devices: 4 pushes (the first four steps), plus 1 inbox-only row.'.PHP_EOL;
                echo 'Your notifications:tick loop must be running to send them.'.PHP_EOL;
            }
        }
    }
} finally {
    Carbon::setTestNow();
    Auth::forgetUser();
    $restoreBus(); // also in keep mode: the demo needs the real dispatcher
    app()->forgetInstance('mail.manager');
    Facade::clearResolvedInstance('mail.manager');

    if (! empty($eventIds) && ! $livKeep) {
        $livCleanup();
    }
}
