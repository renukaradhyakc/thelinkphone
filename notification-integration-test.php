<?php

/*
 * Cross-feature integration checks (INT series).
 *
 * COVERS: INT-01 to INT-12. Each scenario runs the REAL code paths of the app in one tinker process:
 *   - bookings:   ScheduleEventRepository::store / cancelScheduleEvent  -> BookingNotifier
 *   - event edit: EventRepository::update                               -> BookingNotifier / LocationNotifier
 *   - event delete: the Livewire Events::delete() component method      -> BookingNotifier::cancelled
 *   - live sharing: EventLocationController start / stop / activeSession -> LocationNotifier
 *   - reminders and held change notices: the outbox rows, processed like notifications:dispatch-due
 * Time passing is simulated with Carbon::setTestNow() (the clock is reset after every scenario).
 *
 * ROLES: owner = user 101 (owns the events), booker = user 83 (books them).
 *
 * LOCAL DATABASE ONLY. Creates throwaway events and bookings (names start with "NTF-TEST"), reads the real
 * user_notifications and notification_outbox rows, and deletes everything it created (even if a scenario
 * crashes). Pushes and mail are faked, nothing reaches Firebase or a real inbox.
 *
 * DUE ROWS: when the clock jumps, other users' real outbox rows can become due too. The script then does NOT
 * run notifications:dispatch-due (it would send and consume real reminders); it processes only its own rows
 * with the same BookingNotifier methods and marks them the same way. When nothing else is due it runs the
 * real command.
 *
 * NOT TRULY SIMULTANEOUS: INT-08, INT-10 and INT-11 are tested as interleavings in ONE process (one request
 * finishing before the other, a worker crash after sending, both paths holding the same session). Two PHP
 * processes hitting the same instant need a separate stress run.
 *
 * Run from the Laravel project root, in a FRESH tinker session (type it, do not paste):
 *     php artisan tinker
 *     >>> include 'notification-integration-test.php';
 *
 * Defaults: owner 101, booker 83. Optional before the include: $ownerId, $bookerId.
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
use App\Modules\Notifications\Services\NotificationRegistry;
use App\Modules\Notifications\Services\RecipientResolver;
use App\Repositories\EventRepository;
use App\Repositories\ScheduleEventRepository;
use Carbon\Carbon;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Mail;

$ownerId = (int) ($ownerId ?? 101);
$bookerId = (int) ($bookerId ?? 83);

$tally = [];
$report = function (string $status, string $name, string $detail = '') use (&$tally) {
    $tally[$status] = ($tally[$status] ?? 0) + 1;
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

// A real live-sharing event of the owner would make startSharing answer 409 (one live event at a time).
$ownerLiveElsewhere = Event::where('user_id', $ownerId)
    ->whereHas('location', fn ($q) => $q->where('location_type', Location::LIVE)->where('is_live_sharing_active', true))
    ->exists();

// ---- Fakes ------------------------------------------------------------------------------------
$origBus = app(Dispatcher::class);
$restoreBus = function () use ($origBus) {
    app()->instance(Dispatcher::class, $origBus);
    Facade::clearResolvedInstance(Dispatcher::class);
};
Mail::fake();
Bus::fake([SendPushNotificationJob::class]);

// ---- Services and helpers -----------------------------------------------------------------------
$tag = substr(uniqid(), -6);
$S1 = '10:00 AM - 10:30 AM';
$bookingIds = [];
$eventIds = [];
$lastError = null;
$dayN = 3;

$registry = app(NotificationRegistry::class);
$eventRepo = app(EventRepository::class);
$bookingRepo = app(ScheduleEventRepository::class);
$notifierB = app(BookingNotifier::class);
$notifierL = app(LocationNotifier::class);
$outboxRepo = app(OutboxRepository::class);

$offsets = (array) $registry->rule('reminder_offsets_minutes', []);
$quiet = max(1, (int) $registry->rule('event_change_quiet_minutes', 3));
$sendNow = (int) $registry->rule('event_change_send_now_within_minutes', 60);

$ownerTz = isset(User::TIME_ZONE_ARRAY[$owner->timezone]) ? User::TIME_ZONE_ARRAY[$owner->timezone] : config('app.timezone');

$pushCount = fn () => Bus::dispatched(SendPushNotificationJob::class)->count();
$nextDate = function () use (&$dayN) {
    return Carbon::now()->addDays(++$dayN)->format('Y-m-d'); // 4+ days ahead
};
// [date, slot] of a 30 minute slot starting $minutes from now, in the OWNER's timezone (that is how the app reads it)
$slotIn = function (int $minutes) use ($ownerTz) {
    $s = Carbon::now($ownerTz)->addMinutes($minutes)->second(0);

    return [$s->format('Y-m-d'), $s->format('h:i A').' - '.$s->copy()->addMinutes(30)->format('h:i A')];
};

$evQ = fn () => method_exists(Event::class, 'withTrashed') ? Event::withTrashed() : Event::query();

$mkEvent = function (User $u, string $label, int $mode = Event::PHONE_CALL) use (&$eventIds, $eventRepo, $tag) {
    Auth::setUser($u);

    try {
        $e = $eventRepo->store([
            'name' => "NTF-TEST $tag $label",
            'event_location' => $mode,
            'event_link' => "ntf-test-$tag-$label",
            'event_color' => '#63d867',
            'event_type' => Event::FREE,
        ]);
    } finally {
        Auth::forgetUser();
    }

    $eventIds[] = $e->id;

    return $e;
};

// An in-person event with a LIVE location, sharing on or off. Created directly (no start notification).
$mkLiveEvent = function (string $label, bool $active) use ($owner, $mkEvent) {
    $e = $mkEvent($owner, $label, Event::IN_PERSON_MEETING);
    $e->location()->create([
        'location_type' => Location::LIVE, 'latitude' => 12.9716, 'longitude' => 77.5946, 'accuracy' => 10, 'address' => null,
        'is_live_sharing_active' => $active, 'live_started_at' => $active ? now() : null,
    ]);

    if (! (bool) $e->fresh()->status) {
        Event::where('id', $e->id)->update(['status' => Event::ACTIVE]);
    }

    return $e->fresh();
};

$book = function (Event $event, ?string $phone, ?string $slot = null, ?string $date = null) use (&$bookingIds, &$lastError, $bookingRepo, $nextDate, $S1) {
    try {
        $b = $bookingRepo->store([
            'name' => 'NTF Guest',
            'phone_call' => $phone,
            'email' => 'ntf-guest-'.uniqid().'@example.invalid',
            'schedule_date' => $date ?? $nextDate(),
            'description' => 'NTF test booking',
            'user_id' => $event->user_id,
            'event_id' => $event->id,
            'slot_time' => $slot ?? $S1,
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

$asOwner = function (callable $fn) use ($owner) {
    Auth::setUser($owner);

    try {
        return $fn();
    } finally {
        Auth::forgetUser();
    }
};
$edit = fn (Event $e, array $input) => $asOwner(fn () => $eventRepo->update($input, $e->id));
$ctl = app(\App\Http\Controllers\EventLocationController::class);
$startShare = fn (Event $e) => $asOwner(fn () => $ctl->startSharing(Request::create('/', 'POST'), Event::find($e->id)));
$stopShare = fn (Event $e) => $asOwner(fn () => $ctl->stopSharing(Request::create('/', 'POST'), Event::find($e->id)));
$activeSession = fn () => $asOwner(fn () => $ctl->activeSession());
$respMsg = fn ($r) => (string) (($r->getData(true)['message'] ?? ''));

// Event deletion goes through the Livewire component, the same method the delete button calls. Returns the error or null.
$deleteEvent = function (Event $e) use ($asOwner) {
    $tz = date_default_timezone_get(); // the component calls date_default_timezone_set()

    try {
        $asOwner(function () use ($e) {
            $component = app()->makeWith(\App\Http\Livewire\Events::class, ['id' => 'int-test']);
            $component->delete($e->id);
        });

        return null;
    } catch (Throwable $x) {
        return $x;
    } finally {
        date_default_timezone_set($tz);
    }
};

// Switches off any live session left running on this script's events (no notifications), so one scenario
// can never block the next one through the "one live event at a time" rule.
$resetLive = function () use (&$eventIds) {
    foreach ($eventIds as $id) {
        $loc = optional(Event::find($id))->location;

        if ($loc && (bool) $loc->is_live_sharing_active) {
            $loc->update(['is_live_sharing_active' => false, 'live_started_at' => null]);
        }
    }
};

// Inbox rows of one user that belong to these bookings / events, oldest first.
$rowsOf = function (int $userId, array $bIds, array $eIds = []) {
    return DB::table('user_notifications')->where('user_id', $userId)->where(function ($q) use ($bIds, $eIds) {
        $q->whereRaw('1 = 0');
        foreach ($bIds as $id) {
            $q->orWhere('dedupe_key', 'like', "booking.%:$id:%");
        }
        foreach ($eIds as $id) {
            $q->orWhere('dedupe_key', 'like', "location.%:$id:%");
        }
    })->orderBy('id')->get();
};
$seq = fn ($rows) => $rows->map(fn ($r) => $r->type.'.'.$r->reason)->values()->all();
$count = fn ($rows, string $type, ?string $reason = null) => $rows->filter(fn ($r) => $r->type === $type && ($reason === null || $r->reason === $reason))->count();
$dupFree = fn ($rows) => $rows->pluck('dedupe_key')->unique()->count() === $rows->count();

$pending = fn (int $bid, ?string $type = null) => NotificationOutbox::where('event_schedule_id', $bid)->where('status', NotificationOutbox::PENDING)
    ->when($type, fn ($q) => $q->where('type', $type))->count();
$statusOf = fn (int $bid, ?string $type = null) => NotificationOutbox::where('event_schedule_id', $bid)
    ->when($type, fn ($q) => $q->where('type', $type))->pluck('status')->countBy()->all();

// Sends what is due. Real command when nothing else is due, otherwise only this script's own rows.
$processDue = function () use (&$bookingIds, $notifierB, $outboxRepo) {
    $ids = $bookingIds ?: [0];
    $other = NotificationOutbox::due(Carbon::now())->where(function ($q) use ($ids) {
        $q->whereNull('event_schedule_id')->orWhereNotIn('event_schedule_id', $ids);
    })->count();

    if ($other === 0) {
        Artisan::call('notifications:dispatch-due', ['--limit' => 200]);

        return trim(Artisan::output()).' [real command]';
    }

    $sent = $cancelled = 0;
    $rows = NotificationOutbox::due(Carbon::now())->whereIn('event_schedule_id', $ids)->orderBy('notify_at')->orderBy('id')->get();

    foreach ($rows as $row) {
        $result = match ($row->type) {
            'booking.reminder' => $notifierB->sendReminder($row),
            'booking.changed' => $notifierB->sendChange($row),
            default => null,
        };

        if ($result === 'sent') {
            $outboxRepo->markSent($row);
            $sent++;
        } else {
            $outboxRepo->markCancelled($row);
            $cancelled++;
        }
    }

    return "sent $sent, cancelled $cancelled [own rows only: $other other row(s) were due]";
};

$wipe = function (array $eIds, array $bIds) use ($evQ) {
    foreach ($bIds as $id) {
        DB::table('user_notifications')->where('dedupe_key', 'like', "booking.%:$id:%")->delete();
        NotificationOutbox::where('event_schedule_id', $id)->delete();
    }
    foreach ($eIds as $id) {
        DB::table('user_notifications')->where('dedupe_key', 'like', "location.%:$id:%")->delete();
    }
    if ($bIds) {
        EventSchedule::whereIn('id', $bIds)->delete();
    }
    if ($eIds) {
        $evQ()->whereIn('id', $eIds)->get()->each(function ($e) {
            $e->location()->delete();
            method_exists($e, 'forceDelete') ? $e->forceDelete() : $e->delete();
        });
    }
};
$cleanup = function () use (&$bookingIds, &$eventIds, $wipe) {
    $wipe($eventIds, $bookingIds);
    $bookingIds = [];
    $eventIds = [];
    echo 'Deleted the throwaway events, bookings, notifications and outbox rows.'.PHP_EOL;
};

// leftovers from earlier crashed runs
$oldEventIds = $evQ()->where('name', 'like', 'NTF-TEST %')->pluck('id')->all();
if ($oldEventIds) {
    $oldBookingIds = EventSchedule::whereIn('event_id', $oldEventIds)->pluck('id')->all();
    $wipe($oldEventIds, $oldBookingIds);
    echo 'Removed leftovers of earlier runs: '.count($oldEventIds).' event(s), '.count($oldBookingIds).' booking(s)'.PHP_EOL;
}

echo "Owner $ownerId, booker $bookerId, owner timezone $ownerTz, quiet window {$quiet} min, send-now window {$sendNow} min, reminders ".json_encode($offsets).PHP_EOL;
if ($ownerLiveElsewhere) {
    echo 'NOTE  the owner already shares live location for another event: INT-04, INT-08, INT-09 and INT-11 will be skipped'.PHP_EOL;
}
echo PHP_EOL;

try {
    // ============ INT-01 booking created, then the event edited ============
    try {
        do {
            $e = $mkEvent($owner, 'INT01');
            $b = $book($e, $bookerPhone);
            if ($setupFailed($b, 'INT-01')) {
                break;
            }
            $edit($e, ['name' => $e->name.' EDITED']);
            $rows = $rowsOf($bookerId, [$b->id]);
            $check('INT-01 right after the edit the booker has only the confirmation; the change notice is held for the quiet window',
                $seq($rows) === ['booking.created.booker'] && $pending($b->id, 'booking.changed') === 1, json_encode($seq($rows)).', held change rows='.$pending($b->id, 'booking.changed'));

            Carbon::setTestNow(Carbon::now()->addMinutes($quiet + 1));
            $msg = $processDue();
            $rows = $rowsOf($bookerId, [$b->id]);
            $check('INT-01 after the quiet window: confirmation first, then exactly one change notice', $seq($rows) === ['booking.created.booker', 'booking.changed.event_edited'], json_encode($seq($rows)).' | '.$msg);
            $check('INT-01 the change notice is newer than the confirmation and the held row is marked sent',
                $rows->count() === 2 && $rows[0]->id < $rows[1]->id && ($statusOf($b->id, 'booking.changed')[NotificationOutbox::SENT] ?? 0) === 1, json_encode($statusOf($b->id, 'booking.changed')));
        } while (false);
    } catch (Throwable $x) {
        $report('INVESTIGATE', 'INT-01 crashed', get_class($x).': '.substr($x->getMessage(), 0, 200));
    } finally {
        Carbon::setTestNow();
        Auth::forgetUser();
    }

    // ============ INT-02 booking created, cancelled before the reminders ============
    try {
        do {
            $e = $mkEvent($owner, 'INT02');
            $b = $book($e, $bookerPhone);
            if ($setupFailed($b, 'INT-02')) {
                break;
            }
            $startUtc = $notifierB->startUtc($b);
            $expectedReminders = count($offsets) * 2; // owner + booker, every offset (the booking is days away)
            $check('INT-02 setup: the reminders are queued for both people', $pending($b->id, 'booking.reminder') === $expectedReminders, 'pending='.$pending($b->id, 'booking.reminder').', expected='.$expectedReminders);

            $bookingRepo->cancelScheduleEvent('ntf integration test', $b->id);
            $rows = $rowsOf($bookerId, [$b->id]);
            $check('INT-02 the booker has the confirmation and the cancellation', $seq($rows) === ['booking.created.booker', 'booking.cancelled.owner_cancelled'], json_encode($seq($rows)));
            $check('INT-02 no reminder is left waiting', $pending($b->id) === 0, 'status='.json_encode($statusOf($b->id)));

            Carbon::setTestNow($startUtc->copy()->addMinutes(5));
            $msg = $processDue();
            $check('INT-02 after the start time the booker gets no reminder', $count($rowsOf($bookerId, [$b->id]), 'booking.reminder') === 0, $msg);
            $check('INT-02 after the start time the owner has only the confirmation', $seq($rowsOf($ownerId, [$b->id])) === ['booking.created.owner'], json_encode($seq($rowsOf($ownerId, [$b->id]))));
        } while (false);
    } catch (Throwable $x) {
        $report('INVESTIGATE', 'INT-02 crashed', get_class($x).': '.substr($x->getMessage(), 0, 200));
    } finally {
        Carbon::setTestNow();
        Auth::forgetUser();
    }

    // ============ INT-03 booking created, then the event deleted ============
    try {
        do {
            $e = $mkEvent($owner, 'INT03');
            $b = $book($e, $bookerPhone);
            if ($setupFailed($b, 'INT-03')) {
                break;
            }
            $startUtc = $notifierB->startUtc($b);
            $err = $deleteEvent($e);
            if ($err) {
                $report('INVESTIGATE', 'INT-03 could not run the Livewire delete', get_class($err).': '.substr($err->getMessage(), 0, 200));
                break;
            }
            $check('INT-03 the event is gone', Event::find($e->id) === null);
            $rows = $rowsOf($bookerId, [$b->id]);
            $check('INT-03 the booker has the confirmation, then the deletion notice', $seq($rows) === ['booking.created.booker', 'booking.cancelled.event_deleted'], json_encode($seq($rows)));
            $check('INT-03 no reminder is left waiting', $pending($b->id) === 0, 'status='.json_encode($statusOf($b->id)));

            Carbon::setTestNow($startUtc->copy()->addMinutes(5));
            $msg = $processDue();
            $check('INT-03 after the start time nothing more arrives for the booker', $seq($rowsOf($bookerId, [$b->id])) === ['booking.created.booker', 'booking.cancelled.event_deleted'], $msg);
        } while (false);
    } catch (Throwable $x) {
        $report('INVESTIGATE', 'INT-03 crashed', get_class($x).': '.substr($x->getMessage(), 0, 200));
    } finally {
        Carbon::setTestNow();
        Auth::forgetUser();
    }

    // ============ INT-04 booking created, live sharing starts, booking cancelled ============
    try {
        do {
            if ($ownerLiveElsewhere) {
                $report('SKIP', 'INT-04 (the owner already shares live location for another event)');
                break;
            }
            $e = $mkLiveEvent('INT04', false);
            $b = $book($e, $bookerPhone);
            if ($setupFailed($b, 'INT-04')) {
                break;
            }
            $startUtc = $notifierB->startUtc($b);
            $r = $startShare($e);
            if ($r->getStatusCode() !== 200) {
                $report('INVESTIGATE', 'INT-04 could not start sharing', $r->getStatusCode().' '.$respMsg($r));
                break;
            }
            $rows = $rowsOf($bookerId, [$b->id], [$e->id]);
            $check('INT-04 confirmation, then "sharing started"', $seq($rows) === ['booking.created.booker', 'location.started.default'], json_encode($seq($rows)));

            $bookingRepo->cancelScheduleEvent('ntf integration test', $b->id);
            $rows = $rowsOf($bookerId, [$b->id], [$e->id]);
            $check('INT-04 the cancellation arrives after the location notice', $seq($rows) === ['booking.created.booker', 'location.started.default', 'booking.cancelled.owner_cancelled'], json_encode($seq($rows)));

            $stopShare($e);
            $rows = $rowsOf($bookerId, [$b->id], [$e->id]);
            $check('INT-04 stopping the sharing after the cancel sends the booker nothing stale', $seq($rows) === ['booking.created.booker', 'location.started.default', 'booking.cancelled.owner_cancelled'], json_encode($seq($rows)));
            $loc = Event::find($e->id)->location;
            $check('INT-04 sharing is really off', $loc && ! (bool) $loc->is_live_sharing_active && $loc->live_started_at === null);

            Carbon::setTestNow($startUtc->copy()->addMinutes(5));
            $msg = $processDue();
            $check('INT-04 no reminder arrives after the cancelled booking should have started', $count($rowsOf($bookerId, [$b->id], [$e->id]), 'booking.reminder') === 0 && $pending($b->id) === 0, $msg);
        } while (false);
    } catch (Throwable $x) {
        $report('INVESTIGATE', 'INT-04 crashed', get_class($x).': '.substr($x->getMessage(), 0, 200));
    } finally {
        Carbon::setTestNow();
        Auth::forgetUser();
    }

    // ============ INT-05 booking in progress, event mode changes, live sharing stops ============
    try {
        do {
            $e = $mkLiveEvent('INT05', true);
            [$d, $s] = $slotIn(-10); // started 10 minutes ago, ends in 20
            $b = $book($e, $bookerPhone, $s, $d);
            if ($setupFailed($b, 'INT-05')) {
                break;
            }
            $check('INT-05 setup: the in-progress booking has no reminders left to send', $pending($b->id, 'booking.reminder') === 0, 'pending='.$pending($b->id, 'booking.reminder'));

            $edit($e, ['event_location' => Event::PHONE_CALL]); // meeting mode in person -> phone call, while the sharing is on
            $rows = $rowsOf($bookerId, [$b->id], [$e->id]);
            $check('INT-05 the mode change sends one "sharing stopped" and one "location changed", in that order',
                $seq($rows) === ['booking.created.booker', 'location.stopped.default', 'booking.changed.location_changed'], json_encode($seq($rows)));
            $check('INT-05 the change was sent at once (the booking is in progress), nothing is held', $pending($b->id, 'booking.changed') === 0);
            $check('INT-05 the live location is gone from the event', Event::find($e->id)->location === null);

            $stopShare($e); // the owner taps stop afterwards
            $rows2 = $rowsOf($bookerId, [$b->id], [$e->id]);
            $check('INT-05 a stop request after the mode change adds nothing (no duplicate)', $seq($rows2) === $seq($rows) && $dupFree($rows2), json_encode($seq($rows2)));
        } while (false);
    } catch (Throwable $x) {
        $report('INVESTIGATE', 'INT-05 crashed', get_class($x).': '.substr($x->getMessage(), 0, 200));
    } finally {
        Carbon::setTestNow();
        Auth::forgetUser();
    }

    // ============ INT-06 event edited while a reminder is due ============
    try {
        do {
            $startIn = $sendNow + 30; // far enough that the edit is HELD, close enough that the reminder comes due soon
            $e = $mkEvent($owner, 'INT06');
            [$d, $s] = $slotIn($startIn);
            $b = $book($e, $bookerPhone, $s, $d);
            if ($setupFailed($b, 'INT-06')) {
                break;
            }
            $queued = collect($offsets)->filter(fn ($m) => $m < $startIn)->count() * 2;
            $edit($e, ['name' => $e->name.' EDITED']);
            $check('INT-06 setup: the edit is held and the reminders are queued', $pending($b->id, 'booking.changed') === 1 && $pending($b->id, 'booking.reminder') === $queued,
                'held='.$pending($b->id, 'booking.changed').', reminders='.$pending($b->id, 'booking.reminder').' (expected '.$queued.')');

            $jump = $startIn - 5; // the held change (now + quiet) and the early reminders are due, the start reminder is not
            Carbon::setTestNow(Carbon::now()->addMinutes($jump));
            $dueVariants = collect($offsets)->filter(fn ($m) => ($startIn - $m) <= $jump && $m < $startIn)->count();
            $msg = $processDue();
            $bRows = $rowsOf($bookerId, [$b->id]);
            $oRows = $rowsOf($ownerId, [$b->id]);
            $check('INT-06 the booker gets the change notice once', $count($bRows, 'booking.changed', 'event_edited') === 1, json_encode($seq($bRows)).' | '.$msg);
            $check('INT-06 the booker and the owner each get the due reminder(s) once', $count($bRows, 'booking.reminder') === $dueVariants && $count($oRows, 'booking.reminder') === $dueVariants,
                'booker='.$count($bRows, 'booking.reminder').', owner='.$count($oRows, 'booking.reminder').', expected each='.$dueVariants);
            $check('INT-06 no duplicate rows', $dupFree($bRows) && $dupFree($oRows));
            $left = ($queued / 2 - $dueVariants) * 2;
            $check('INT-06 the reminders that are not due yet are still waiting', $pending($b->id, 'booking.reminder') === $left, 'pending='.$pending($b->id, 'booking.reminder').', expected='.$left);
        } while (false);
    } catch (Throwable $x) {
        $report('INVESTIGATE', 'INT-06 crashed', get_class($x).': '.substr($x->getMessage(), 0, 200));
    } finally {
        Carbon::setTestNow();
        Auth::forgetUser();
    }

    // ============ INT-07 event deleted while a held change and reminders are queued ============
    try {
        do {
            $e = $mkEvent($owner, 'INT07');
            $b = $book($e, $bookerPhone);
            if ($setupFailed($b, 'INT-07')) {
                break;
            }
            $startUtc = $notifierB->startUtc($b);
            $edit($e, ['name' => $e->name.' EDITED']);
            $check('INT-07 setup: a held change and the reminders are queued', $pending($b->id, 'booking.changed') === 1 && $pending($b->id, 'booking.reminder') > 0,
                'held='.$pending($b->id, 'booking.changed').', reminders='.$pending($b->id, 'booking.reminder'));

            $err = $deleteEvent($e);
            if ($err) {
                $report('INVESTIGATE', 'INT-07 could not run the Livewire delete', get_class($err).': '.substr($err->getMessage(), 0, 200));
                break;
            }
            $check('INT-07 nothing is left waiting after the delete', $pending($b->id) === 0, 'status='.json_encode($statusOf($b->id)));

            Carbon::setTestNow(Carbon::now()->addMinutes($quiet + 1));
            $m1 = $processDue();
            Carbon::setTestNow($startUtc->copy()->addMinutes(5));
            $m2 = $processDue();
            $check('INT-07 the booker only ever gets the confirmation and the deletion notice (no change notice, no reminder)',
                $seq($rowsOf($bookerId, [$b->id])) === ['booking.created.booker', 'booking.cancelled.event_deleted'], json_encode($seq($rowsOf($bookerId, [$b->id]))).' | '.$m1.' | '.$m2);
            $check('INT-07 the owner has no stale reminder either', $count($rowsOf($ownerId, [$b->id]), 'booking.reminder') === 0);
        } while (false);
    } catch (Throwable $x) {
        $report('INVESTIGATE', 'INT-07 crashed', get_class($x).': '.substr($x->getMessage(), 0, 200));
    } finally {
        Carbon::setTestNow();
        Auth::forgetUser();
    }

    // ============ INT-08 sharing started and stopped by competing requests ============
    try {
        do {
            if ($ownerLiveElsewhere) {
                $report('SKIP', 'INT-08 (the owner already shares live location for another event)');
                break;
            }
            // A: start, start again, stop, stop again
            $eA = $mkLiveEvent('INT08A', false);
            $bA = $book($eA, $bookerPhone);
            if ($setupFailed($bA, 'INT-08')) {
                break;
            }
            $s1 = $startShare($eA);
            $s2 = $startShare($eA);
            $check('INT-08 two start requests: both answered without error', $s1->getStatusCode() === 200 && $s2->getStatusCode() === 200, $s1->getStatusCode().'/'.$s2->getStatusCode().' "'.$respMsg($s2).'"');
            $rows = $rowsOf($bookerId, [$bA->id], [$eA->id]);
            $check('INT-08 two start requests give the booker one "started" notice', $count($rows, 'location.started') === 1, json_encode($seq($rows)));
            $stopShare($eA);
            $stopShare($eA);
            $rows = $rowsOf($bookerId, [$bA->id], [$eA->id]);
            $check('INT-08 two stop requests give the booker one "stopped" notice', $count($rows, 'location.stopped') === 1 && $count($rows, 'location.started') === 1 && $dupFree($rows), json_encode($seq($rows)));
            $loc = Event::find($eA->id)->location;
            $check('INT-08 final state A: sharing off, no session left', $loc && ! (bool) $loc->is_live_sharing_active && $loc->live_started_at === null);

            // B: a stop arrives before the start has happened
            $eB = $mkLiveEvent('INT08B', false);
            $bB = $book($eB, $bookerPhone);
            if ($setupFailed($bB, 'INT-08')) {
                break;
            }
            $stopShare($eB);
            $rowsB = $rowsOf($bookerId, [$bB->id], [$eB->id]);
            $check('INT-08 a stop that arrives first changes nothing and sends no notice', $count($rowsB, 'location.stopped') === 0 && $count($rowsB, 'location.started') === 0, json_encode($seq($rowsB)));
            $s3 = $startShare($eB);
            $rowsB = $rowsOf($bookerId, [$bB->id], [$eB->id]);
            $locB = Event::find($eB->id)->location;
            $check('INT-08 the start that follows wins: sharing on, one "started", no "stopped"',
                $s3->getStatusCode() === 200 && $locB && (bool) $locB->is_live_sharing_active && $count($rowsB, 'location.started') === 1 && $count($rowsB, 'location.stopped') === 0, json_encode($seq($rowsB)));
            $resetLive(); // B is still sharing here; end it so INT-09 and INT-11 start clean
        } while (false);
    } catch (Throwable $x) {
        $report('INVESTIGATE', 'INT-08 crashed', get_class($x).': '.substr($x->getMessage(), 0, 200));
    } finally {
        Carbon::setTestNow();
        Auth::forgetUser();
    }

    // ============ INT-09 conflicting event updates ============
    try {
        do {
            if ($ownerLiveElsewhere) {
                $report('SKIP', 'INT-09 (the owner already shares live location for another event)');
                break;
            }
            $resetLive();
            $e = $mkEvent($owner, 'INT09', Event::IN_PERSON_MEETING);
            $b = $book($e, $bookerPhone);
            if ($setupFailed($b, 'INT-09')) {
                break;
            }
            $live = ['location_meta' => 'ntf-test', 'new_location_type' => Location::LIVE, 'new_location_latitude' => 12.9716, 'new_location_longitude' => 77.5946,
                'new_location_accuracy' => 10, 'new_location_is_live_sharing_active' => 1];

            $edit($e, $live);
            $locA = Event::find($e->id)->location;
            $sessionA = optional(optional($locA)->live_started_at)->timestamp;
            $edit($e, $live); // the same save submitted twice
            $locB = Event::find($e->id)->location;
            $rows = $rowsOf($bookerId, [$b->id], [$e->id]);
            $check('INT-09 the same "start sharing" saved twice: one "started" notice and the session is kept',
                $count($rows, 'location.started') === 1 && $locB && (bool) $locB->is_live_sharing_active && optional($locB->live_started_at)->timestamp === $sessionA, json_encode($seq($rows)));

            $stop = array_merge($live, ['new_location_is_live_sharing_active' => 0]);
            $edit($e, $stop);
            $edit($e, $stop); // and the stop twice
            $loc = Event::find($e->id)->location;
            $rows = $rowsOf($bookerId, [$b->id], [$e->id]);
            $check('INT-09 the same "stop sharing" saved twice: one "stopped" notice, no session left',
                $count($rows, 'location.stopped') === 1 && $loc && ! (bool) $loc->is_live_sharing_active && $loc->live_started_at === null, json_encode($seq($rows)));
            $check('INT-09 location edits alone send no "event changed" notice', $count($rows, 'booking.changed') === 0 && $pending($b->id, 'booking.changed') === 0, json_encode($seq($rows)));

            // rename, rename again, rename back, all inside the quiet window: nothing net changed, so nothing is sent
            $orig = Event::find($e->id)->name;
            $edit($e, ['name' => $orig.' X']);
            $edit($e, ['name' => $orig.' Y']);
            $edit($e, ['name' => $orig]);
            $check('INT-09 three quick renames keep one held row', $pending($b->id, 'booking.changed') === 1, 'held='.$pending($b->id, 'booking.changed'));
            Carbon::setTestNow(Carbon::now()->addMinutes($quiet + 1));
            $msg = $processDue();
            $check('INT-09 renamed and renamed back: the held row is dropped and no change notice is sent',
                $count($rowsOf($bookerId, [$b->id], [$e->id]), 'booking.changed') === 0 && ($statusOf($b->id, 'booking.changed')[NotificationOutbox::CANCELLED] ?? 0) === 1, json_encode($statusOf($b->id, 'booking.changed')).' | '.$msg);
        } while (false);
    } catch (Throwable $x) {
        $report('INVESTIGATE', 'INT-09 crashed', get_class($x).': '.substr($x->getMessage(), 0, 200));
    } finally {
        Carbon::setTestNow();
        Auth::forgetUser();
    }

    // ============ INT-10 worker stopped and restarted ============
    try {
        // A: the worker sent a reminder and crashed before marking the row, then restarts
        do {
            $e = $mkEvent($owner, 'INT10A');
            [$d, $s] = $slotIn(30);
            $b = $book($e, $bookerPhone, $s, $d);
            if ($setupFailed($b, 'INT-10')) {
                break;
            }
            Carbon::setTestNow(Carbon::now()->addMinutes(16));
            $row = NotificationOutbox::where('event_schedule_id', $b->id)->where('user_id', $bookerId)->where('type', 'booking.reminder')->orderBy('notify_at')->first();
            if (! $row || $row->status !== NotificationOutbox::PENDING) {
                $report('INVESTIGATE', 'INT-10 no pending reminder row for the booker to work with');
                break;
            }
            $first = $notifierB->sendReminder($row); // sent ...
            NotificationOutbox::where('id', $row->id)->update([ // ... but the worker died before marking it
                'status' => NotificationOutbox::CLAIMED, 'claimed_at' => Carbon::now()->subHours(2)->utc()->format('Y-m-d H:i:s'), 'attempts' => 1,
            ]);
            $keyCount = fn () => DB::table('user_notifications')->where('user_id', $bookerId)->where('dedupe_key', $row->dedupe_key)->count();
            $check('INT-10 setup: the reminder was sent once before the crash', $first === 'sent' && $keyCount() === 1, 'rows='.$keyCount());

            $released = $outboxRepo->releaseStaleClaims();
            $check('INT-10 the restarted worker takes the stuck row back', $released >= 1 && NotificationOutbox::find($row->id)->status === NotificationOutbox::PENDING, 'released='.$released.', status='.NotificationOutbox::find($row->id)->status);
            $msg = $processDue();
            $check('INT-10 the retry does not create a second reminder (same booker, same key)', $keyCount() === 1 && NotificationOutbox::find($row->id)->status === NotificationOutbox::SENT, 'rows='.$keyCount().', status='.NotificationOutbox::find($row->id)->status.' | '.$msg);
            $processDue();
            $check('INT-10 another worker pass changes nothing', $keyCount() === 1 && $dupFree($rowsOf($bookerId, [$b->id])), 'rows='.$keyCount());
        } while (false);
    } catch (Throwable $x) {
        $report('INVESTIGATE', 'INT-10 (A) crashed', get_class($x).': '.substr($x->getMessage(), 0, 200));
    } finally {
        Carbon::setTestNow();
        Auth::forgetUser();
    }

    try {
        // B: the worker was down while the booking started and ended; nothing stale must go out when it comes back
        do {
            $startIn = 60;
            $e = $mkEvent($owner, 'INT10B');
            [$d, $s] = $slotIn($startIn);
            $b = $book($e, $bookerPhone, $s, $d);
            if ($setupFailed($b, 'INT-10')) {
                break;
            }
            Carbon::setTestNow(Carbon::now()->addMinutes($startIn + 10)); // 10 minutes after the start, no worker ran meanwhile
            $msg = $processDue();
            $bRows = $rowsOf($bookerId, [$b->id]);
            $oRows = $rowsOf($ownerId, [$b->id]);
            $check('INT-10 after the downtime no late reminder is sent to the booker or the owner', $count($bRows, 'booking.reminder') === 0 && $count($oRows, 'booking.reminder') === 0, json_encode($seq($bRows)).' | '.$msg);
            $check('INT-10 the missed reminder rows are closed, none stays pending', $pending($b->id) === 0, 'status='.json_encode($statusOf($b->id)));
        } while (false);
    } catch (Throwable $x) {
        $report('INVESTIGATE', 'INT-10 (B) crashed', get_class($x).': '.substr($x->getMessage(), 0, 200));
    } finally {
        Carbon::setTestNow();
        Auth::forgetUser();
    }

    // ============ INT-11 the 24 hour auto-stop and a manual stop ============
    try {
        do {
            if ($ownerLiveElsewhere) {
                $report('SKIP', 'INT-11 (the owner already shares live location for another event)');
                break;
            }
            $resetLive();
            // A: manual stop first, then the scheduler path runs
            $eA = $mkLiveEvent('INT11A', false);
            $bA = $book($eA, $bookerPhone);
            if ($setupFailed($bA, 'INT-11')) {
                break;
            }
            $startShare($eA);
            $stopShare($eA);
            $activeSession();
            $rows = $rowsOf($bookerId, [$bA->id], [$eA->id]);
            $check('INT-11 manual stop, then the auto-stop check: one "stopped", no "auto stopped"', $count($rows, 'location.stopped') === 1 && $count($rows, 'location.auto_stopped') === 0, json_encode($seq($rows)));

            // B: the auto-stop path runs first (24 hours passed), then the owner taps stop
            $eB = $mkLiveEvent('INT11B', false);
            $bB = $book($eB, $bookerPhone);
            if ($setupFailed($bB, 'INT-11')) {
                break;
            }
            $r = $startShare($eB);
            if ($r->getStatusCode() !== 200) {
                $report('INVESTIGATE', 'INT-11 could not start sharing', $r->getStatusCode().' '.$respMsg($r));
                break;
            }
            Event::find($eB->id)->location->update(['live_started_at' => Carbon::now()->subHours(25)]); // the session is 25 hours old
            $activeSession();
            $locB = Event::find($eB->id)->location;
            if ($locB && (bool) $locB->is_live_sharing_active) {
                $report('INVESTIGATE', 'INT-11 could not force the session to expire (liveExpiresAt gave a later time)');
                break;
            }
            $bRows = $rowsOf($bookerId, [$bB->id], [$eB->id]);
            $oRows = $rowsOf($ownerId, [$bB->id], [$eB->id]);
            $check('INT-11 the expired session ends once: the booker gets one "auto stopped" and the owner one', $count($bRows, 'location.auto_stopped') === 1 && $count($oRows, 'location.auto_stopped') === 1, 'booker='.json_encode($seq($bRows)).', owner='.json_encode($seq($oRows)));
            $stopShare($eB); // the manual stop arrives afterwards
            $bRows2 = $rowsOf($bookerId, [$bB->id], [$eB->id]);
            $check('INT-11 the manual stop after the auto-stop adds nothing: the booker has exactly one stop-type notice',
                $bRows2->filter(fn ($r) => str_starts_with($r->type, 'location.') && $r->type !== 'location.started')->count() === 1 && $dupFree($bRows2), json_encode($seq($bRows2)));

            // C: both paths read the SAME session before either one finished (what two parallel requests would do)
            $ev = Event::find($eB->id);
            $sess = Carbon::now()->subDays(2);
            $notifierL->stopped($ev, $sess);
            $notifierL->autoStopped($ev, $sess, true);
            $sameSession = DB::table('user_notifications')->where('user_id', $bookerId)->where('dedupe_key', 'like', "location.%:{$eB->id}:{$sess->getTimestamp()}:%")->get();
            $report($sameSession->count() === 1 ? 'PASS' : 'INVESTIGATE', 'INT-11 the manual stop and the auto-stop of the SAME session, both running: the booker gets one stop notice',
                $sameSession->count() === 1 ? 'one row' : $sameSession->count().' rows ('.implode(' + ', $sameSession->pluck('type')->all()).'): stopped and auto_stopped use different keys, so a real race can send both');
        } while (false);
    } catch (Throwable $x) {
        $report('INVESTIGATE', 'INT-11 crashed', get_class($x).': '.substr($x->getMessage(), 0, 200));
    } finally {
        Carbon::setTestNow();
        Auth::forgetUser();
    }

    // ============ INT-12 a job or hook retried after the transaction finished ============
    try {
        do {
            $e = $mkEvent($owner, 'INT12');
            [$d, $s] = $slotIn(30);
            $b = $book($e, $bookerPhone, $s, $d);
            if ($setupFailed($b, 'INT-12')) {
                break;
            }
            $outboxBefore = NotificationOutbox::where('event_schedule_id', $b->id)->count();
            $notifierB->created($b->fresh());
            DB::transaction(fn () => $notifierB->created($b->fresh())); // the hook runs again after the first transaction committed
            $bRows = $rowsOf($bookerId, [$b->id]);
            $oRows = $rowsOf($ownerId, [$b->id]);
            $check('INT-12 the confirmation hook run again: still one confirmation for the booker and one for the owner', $count($bRows, 'booking.created') === 1 && $count($oRows, 'booking.created') === 1, json_encode($seq($bRows)));
            $check('INT-12 the reminder rows were not scheduled twice', NotificationOutbox::where('event_schedule_id', $b->id)->count() === $outboxBefore, 'before='.$outboxBefore.', after='.NotificationOutbox::where('event_schedule_id', $b->id)->count());

            Carbon::setTestNow(Carbon::now()->addMinutes(16));
            $row = NotificationOutbox::where('event_schedule_id', $b->id)->where('user_id', $bookerId)->where('type', 'booking.reminder')->orderBy('notify_at')->first();
            if ($row) {
                $notifierB->sendReminder($row);
                $notifierB->sendReminder($row); // the reminder job retried
                $n = DB::table('user_notifications')->where('user_id', $bookerId)->where('dedupe_key', $row->dedupe_key)->count();
                $check('INT-12 the reminder sent twice gives one inbox row', $n === 1, 'rows='.$n);
            } else {
                $report('INVESTIGATE', 'INT-12 no reminder row to retry');
            }
            Carbon::setTestNow();

            $pushBefore = $pushCount();
            $notifierB->cancelled($b->fresh(), 'owner_cancelled');
            $notifierB->cancelled($b->fresh(), 'owner_cancelled'); // the cancel hook retried
            $bRows = $rowsOf($bookerId, [$b->id]);
            $check('INT-12 the cancellation run twice gives one inbox row and one push job', $count($bRows, 'booking.cancelled') === 1 && ($pushCount() - $pushBefore) === 1,
                'rows='.$count($bRows, 'booking.cancelled').', push jobs='.($pushCount() - $pushBefore));
            $check('INT-12 every inbox row of the booking has its own key', $dupFree($bRows) && $dupFree($oRows));
        } while (false);
    } catch (Throwable $x) {
        $report('INVESTIGATE', 'INT-12 crashed', get_class($x).': '.substr($x->getMessage(), 0, 200));
    } finally {
        Carbon::setTestNow();
        Auth::forgetUser();
    }

    $report('INFO', 'Simulated in one process, not truly simultaneous: INT-08, INT-10 and INT-11 (two PHP processes at the same instant need a separate stress run)');
} catch (Throwable $e) {
    echo PHP_EOL.'STOPPED  '.$e->getMessage().PHP_EOL;
} finally {
    Carbon::setTestNow();
    Auth::forgetUser();
    $restoreBus();
    app()->forgetInstance('mail.manager');
    Facade::clearResolvedInstance('mail.manager');
    $cleanup();
    echo PHP_EOL.'Summary: '.collect($tally)->map(fn ($n, $s) => "$s=$n")->implode(', ').PHP_EOL;
}