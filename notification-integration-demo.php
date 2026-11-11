<?php

/*
 * REAL-DEVICE DEMO of the INT-01..INT-12 scenarios. Unlike the test, pushes are NOT faked: they go to the
 * devices of the owner (101) and the booker (83). Booking e-mails are faked (nothing is mailed).
 *
 * LOCAL DATABASE ONLY. Run from the Laravel root in a FRESH tinker session (type it, do not paste):
 *     php artisan tinker
 *     >>> include 'notification-integration-demo.php';
 *     >>> $int01();            // one scenario at a time, wait for the pushes, then the next
 *     >>> $intRows();          // what was created for 83 / 101
 *     >>> $intCleanup();       // removes every NTF-DEMO event, booking, notification and outbox row
 *
 * Your notifications:tick loop (queue worker + due-reminder dispatch) MUST be running, or nothing is sent.
 * Optional before the include: $ownerId, $bookerId, $gap (seconds to wait between steps, default 10),
 * $dayStart (first booking date offset in days, default 3; use 3, 20, 40, 60 in parallel terminals).
 * The gap matters: pushes about the same booking share a collapse key, so two pushes sent in the same second
 * can show up as one on the phone. Do not shorten it.
 *
 * Do not run $intCleanup() until the pushes of the last scenario have arrived (the queued push needs its row).
 */

use App\Models\Event;
use App\Models\EventSchedule;
use App\Models\Location;
use App\Models\User;
use App\Modules\Notifications\Models\NotificationOutbox;
use App\Modules\Notifications\Repositories\OutboxRepository;
use App\Modules\Notifications\Services\BookingNotifier;
use App\Modules\Notifications\Services\LocationNotifier;
use App\Modules\Notifications\Services\RecipientResolver;
use App\Repositories\EventRepository;
use App\Repositories\ScheduleEventRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

$ownerId = (int) ($ownerId ?? 101);
$bookerId = (int) ($bookerId ?? 83);
$gap = (int) ($gap ?? 10);

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

Mail::fake(); // booking mails go nowhere; pushes are real

$S1 = '10:00 AM - 10:30 AM';
$bookingIds = [];
$eventIds = [];
$lastError = null;
$dayN = (int) ($dayStart ?? 3); // use a different $dayStart in each terminal so bookings never share a date
$demo = [];

$eventRepo = app(EventRepository::class);
$bookingRepo = app(ScheduleEventRepository::class);
$notifierB = app(BookingNotifier::class);
$notifierL = app(LocationNotifier::class);
$outboxRepo = app(OutboxRepository::class);
$ctl = app(\App\Http\Controllers\EventLocationController::class);

$ownerTz = isset(User::TIME_ZONE_ARRAY[$owner->timezone]) ? User::TIME_ZONE_ARRAY[$owner->timezone] : config('app.timezone');
$nextDate = function () use (&$dayN) {
    return Carbon::now()->addDays(++$dayN)->format('Y-m-d');
};
$slotIn = function (int $minutes) use ($ownerTz) {
    $s = Carbon::now($ownerTz)->addMinutes($minutes)->second(0);

    return [$s->format('Y-m-d'), $s->format('h:i A').' - '.$s->copy()->addMinutes(30)->format('h:i A')];
};
$pause = function () use ($gap) {
    sleep($gap);
};
$evQ = fn () => method_exists(Event::class, 'withTrashed') ? Event::withTrashed() : Event::query();

$mkEvent = function (User $u, string $label, int $mode = Event::PHONE_CALL) use (&$eventIds, $eventRepo) {
    Auth::setUser($u);

    try {
        $e = $eventRepo->store([
            'name' => "NTF-DEMO $label",
            'event_location' => $mode,
            'event_link' => 'ntf-demo-'.strtolower($label).'-'.uniqid(),
            'event_color' => '#63d867',
            'event_type' => Event::FREE,
        ]);
    } finally {
        Auth::forgetUser();
    }

    $eventIds[] = $e->id;

    return $e;
};

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

$book = function (Event $event, ?string $slot = null, ?string $date = null) use (&$bookingIds, &$lastError, $bookingRepo, $nextDate, $S1, $bookerPhone) {
    try {
        $b = $bookingRepo->store([
            'name' => 'NTF Demo Guest',
            'phone_call' => $bookerPhone,
            'email' => 'ntf-demo-'.uniqid().'@example.invalid',
            'schedule_date' => $date ?? $nextDate(),
            'description' => 'NTF demo booking',
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

$asOwner = function (callable $fn) use ($owner) {
    Auth::setUser($owner);

    try {
        return $fn();
    } finally {
        Auth::forgetUser();
    }
};
$edit = fn (Event $e, array $input) => $asOwner(fn () => $eventRepo->update($input, $e->id));
$startShare = fn (Event $e) => $asOwner(fn () => $ctl->startSharing(Request::create('/', 'POST'), Event::find($e->id)));
$stopShare = fn (Event $e) => $asOwner(fn () => $ctl->stopSharing(Request::create('/', 'POST'), Event::find($e->id)));

$deleteEvent = function (Event $e) use ($asOwner) {
    $tz = date_default_timezone_get();

    try {
        $asOwner(function () use ($e) {
            app()->makeWith(\App\Http\Livewire\Events::class, ['id' => 'int-demo'])->delete($e->id);
        });

        return null;
    } catch (Throwable $x) {
        return $x;
    } finally {
        date_default_timezone_set($tz);
    }
};

// Switches off a live session left on a demo event (no notification), so the one-live-event rule never blocks a demo.
$resetLive = function () use (&$eventIds) {
    foreach ($eventIds as $id) {
        $loc = optional(Event::find($id))->location;

        if ($loc && (bool) $loc->is_live_sharing_active) {
            $loc->update(['is_live_sharing_active' => false, 'live_started_at' => null]);
        }
    }
};

$fail = function (string $what) use (&$lastError) {
    echo "Could not set up $what: ".($lastError ?? 'unknown error').PHP_EOL;
};
$head = function (string $title) {
    echo PHP_EOL.'=== '.$title.' ==='.PHP_EOL;
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

$intCleanup = function () use (&$bookingIds, &$eventIds, $wipe, $evQ) {
    $oldE = $evQ()->where('name', 'like', 'NTF-DEMO %')->pluck('id')->all();
    $oldB = EventSchedule::whereIn('event_id', $oldE)->pluck('id')->all();
    $wipe(array_unique(array_merge($eventIds, $oldE)), array_unique(array_merge($bookingIds, $oldB)));
    $bookingIds = [];
    $eventIds = [];
    echo 'Removed every NTF-DEMO event, booking, notification and outbox row.'.PHP_EOL;
};

$intRows = function () use (&$bookingIds, &$eventIds, $bookerId, $ownerId) {
    foreach ([$bookerId => 'booker', $ownerId => 'owner'] as $uid => $role) {
        $rows = DB::table('user_notifications')->where('user_id', $uid)->where(function ($q) use ($bookingIds, $eventIds) {
            $q->whereRaw('1 = 0');
            foreach ($bookingIds as $id) {
                $q->orWhere('dedupe_key', 'like', "booking.%:$id:%");
            }
            foreach ($eventIds as $id) {
                $q->orWhere('dedupe_key', 'like', "location.%:$id:%");
            }
        })->orderBy('id')->get();
        echo "User $uid ($role): ".$rows->count().' inbox row(s): '.$rows->map(fn ($r) => $r->type.'.'.$r->reason)->implode(', ').PHP_EOL;
    }
    echo 'Reminders / held changes still waiting: '.NotificationOutbox::whereIn('event_schedule_id', $bookingIds ?: [0])->where('status', NotificationOutbox::PENDING)->count().PHP_EOL;
};

// leftovers of earlier demo runs
$old = $evQ()->where('name', 'like', 'NTF-DEMO %')->where('created_at', '<', now()->subHours(3))->pluck('id')->all(); // only OLD demos: another terminal may be running one right now
if ($old) {
    $wipe($old, EventSchedule::whereIn('event_id', $old)->pluck('id')->all());
    echo 'Removed demo leftovers older than 3 hours: '.count($old).' event(s)'.PHP_EOL;
}

// ============ the scenarios ============

$int01 = function () use (&$demo, $mkEvent, $owner, $book, $edit, $pause, $fail, $head) {
    $head('INT-01 booking created, then the event edited');
    $e = $mkEvent($owner, 'INT01');
    $b = $book($e);
    if (! $b) {
        return $fail('INT-01');
    }
    $pause();
    $edit($e, ['name' => $e->name.' EDITED']);
    echo 'Booker 83 expects: 1 push now (booking confirmed), then 1 push about 3-4 minutes later (event edited).'.PHP_EOL;
    echo 'The edit is held for the quiet window, so the second push must NOT arrive straight away. Total 2.'.PHP_EOL;
    echo 'Owner 101 expects: 1 push (new booking).'.PHP_EOL;
};

$int02 = function () use (&$demo, $mkEvent, $owner, $book, $bookingRepo, $pause, $fail, $head) {
    $head('INT-02 booking created, then cancelled before its reminders');
    $e = $mkEvent($owner, 'INT02');
    $b = $book($e);
    if (! $b) {
        return $fail('INT-02');
    }
    $pause();
    $bookingRepo->cancelScheduleEvent('ntf demo', $b->id);
    echo 'Booker 83 expects: 2 pushes (booking confirmed, booking cancelled). No reminder ever follows.'.PHP_EOL;
    echo 'Owner 101 expects: 1 push (new booking).'.PHP_EOL;
};

$int03 = function () use ($mkEvent, $owner, $book, $deleteEvent, $pause, $fail, $head) {
    $head('INT-03 booking created, then the event deleted');
    $e = $mkEvent($owner, 'INT03');
    $b = $book($e);
    if (! $b) {
        return $fail('INT-03');
    }
    $pause();
    $err = $deleteEvent($e);
    if ($err) {
        echo 'Could not run the delete: '.$err->getMessage().PHP_EOL;

        return;
    }
    echo 'Booker 83 expects: 2 pushes (booking confirmed, event deleted). No reminder follows.'.PHP_EOL;
    echo 'Owner 101 expects: 1 push (new booking). The owner gets no notice for their own delete.'.PHP_EOL;
};

$int04 = function () use ($mkLiveEvent, $book, $startShare, $stopShare, $bookingRepo, $resetLive, $pause, $fail, $head) {
    $head('INT-04 booking created, live sharing starts, booking cancelled');
    $resetLive();
    $e = $mkLiveEvent('INT04', false);
    $b = $book($e);
    if (! $b) {
        return $fail('INT-04');
    }
    $pause();
    $r = $startShare($e);
    if ($r->getStatusCode() !== 200) {
        echo 'Could not start sharing: '.$r->getStatusCode().PHP_EOL;

        return;
    }
    $pause();
    $bookingRepo->cancelScheduleEvent('ntf demo', $b->id);
    $pause();
    $stopShare($e);
    echo 'Booker 83 expects: 3 pushes (booking confirmed, sharing started, booking cancelled).'.PHP_EOL;
    echo 'The final stop must send NOTHING (the booking is cancelled). Owner 101 expects: 1 push (new booking).'.PHP_EOL;
};

$int05 = function () use ($mkLiveEvent, $book, $edit, $slotIn, $resetLive, $pause, $fail, $head) {
    $head('INT-05 booking in progress, event mode changes, live sharing stops');
    $resetLive();
    $e = $mkLiveEvent('INT05', true);
    [$d, $s] = $slotIn(-10);
    $b = $book($e, $s, $d);
    if (! $b) {
        return $fail('INT-05');
    }
    $pause();
    $edit($e, ['event_location' => Event::PHONE_CALL]);
    echo 'Booker 83 expects: 3 pushes in this order: booking confirmed, sharing stopped, meeting details changed.'.PHP_EOL;
    echo 'The change is sent at once because the booking is in progress. Owner 101 expects: 1 push (new booking).'.PHP_EOL;
};

$int06 = function () use ($mkEvent, $owner, $book, $edit, $slotIn, $pause, $fail, $head) {
    $head('INT-06 event edited while a reminder is about to be due');
    $e = $mkEvent($owner, 'INT06');
    [$d, $s] = $slotIn(19);
    $b = $book($e, $s, $d);
    if (! $b) {
        return $fail('INT-06');
    }
    $pause();
    $edit($e, ['name' => $e->name.' EDITED']);
    echo 'The booking starts in 19 minutes. Booker 83 expects 4 pushes: booking confirmed, event edited (at once, the'.PHP_EOL;
    echo 'booking is within an hour), the 15-minute reminder in about 4 minutes, the start reminder in about 19 minutes.'.PHP_EOL;
    echo 'Owner 101 expects 3 pushes: new booking and the same two reminders.'.PHP_EOL;
    echo 'Note: this is the near-start version. The version where the edit is held and a reminder falls due during the hold'.PHP_EOL;
    echo 'needs a 90 minute wait, so that part stays in the automated test.'.PHP_EOL;
};

$int07 = function () use ($mkEvent, $owner, $book, $edit, $deleteEvent, $pause, $fail, $head) {
    $head('INT-07 event deleted while a held change and reminders are queued');
    $e = $mkEvent($owner, 'INT07');
    $b = $book($e);
    if (! $b) {
        return $fail('INT-07');
    }
    $pause();
    $edit($e, ['name' => $e->name.' EDITED']);
    $err = $deleteEvent($e);
    if ($err) {
        echo 'Could not run the delete: '.$err->getMessage().PHP_EOL;

        return;
    }
    echo 'Booker 83 expects: 2 pushes (booking confirmed, event deleted).'.PHP_EOL;
    echo 'WAIT 5 MINUTES: the "event edited" push that was held must NEVER arrive. Owner 101 expects: 1 push.'.PHP_EOL;
};

$int08 = function () use ($mkLiveEvent, $book, $startShare, $stopShare, $resetLive, $pause, $fail, $head) {
    $head('INT-08 two start requests, then two stop requests');
    $resetLive();
    $e = $mkLiveEvent('INT08', false);
    $b = $book($e);
    if (! $b) {
        return $fail('INT-08');
    }
    $pause();
    $startShare($e);
    $startShare($e);
    $pause();
    $stopShare($e);
    $stopShare($e);
    echo 'Booker 83 expects: 3 pushes (booking confirmed, sharing started ONCE, sharing stopped ONCE), not 5.'.PHP_EOL;
    echo 'Owner 101 expects: 1 push (new booking).'.PHP_EOL;
};

$int09 = function () use ($mkEvent, $owner, $book, $edit, $resetLive, $pause, $fail, $head) {
    $head('INT-09 the same live-sharing save twice, then three quick renames');
    $resetLive();
    $e = $mkEvent($owner, 'INT09', Event::IN_PERSON_MEETING);
    $b = $book($e);
    if (! $b) {
        return $fail('INT-09');
    }
    $live = ['location_meta' => 'ntf-demo', 'new_location_type' => Location::LIVE, 'new_location_latitude' => 12.9716, 'new_location_longitude' => 77.5946,
        'new_location_accuracy' => 10, 'new_location_is_live_sharing_active' => 1];
    $pause();
    $edit($e, $live);
    $edit($e, $live);
    $pause();
    $stop = array_merge($live, ['new_location_is_live_sharing_active' => 0]);
    $edit($e, $stop);
    $edit($e, $stop);
    $pause();
    $orig = Event::find($e->id)->name;
    $edit($e, ['name' => $orig.' X']);
    $edit($e, ['name' => $orig.' Y']);
    $edit($e, ['name' => $orig]);
    echo 'Booker 83 expects: 3 pushes (booking confirmed, sharing started ONCE, sharing stopped ONCE).'.PHP_EOL;
    echo 'WAIT 5 MINUTES: no "event edited" push may arrive (renamed and renamed back = nothing changed). Owner 101 expects: 1 push.'.PHP_EOL;
};

$int10 = function () use (&$demo, $mkEvent, $owner, $book, $slotIn, $fail, $head) {
    $head('INT-10 worker restart: step 1 of 2');
    $e = $mkEvent($owner, 'INT10');
    [$d, $s] = $slotIn(17);
    $b = $book($e, $s, $d);
    if (! $b) {
        return $fail('INT-10');
    }
    $demo['b10'] = $b->id;
    echo 'The booking starts in 17 minutes. Booker 83 expects: booking confirmed now, the 15-minute reminder in about 2 minutes.'.PHP_EOL;
    echo 'When that reminder has arrived, run  $int10Retry();  to pretend the worker crashed right after sending it.'.PHP_EOL;
    echo 'Then 83 must get NO second 15-minute reminder; the start reminder (about 17 minutes in) still arrives. Total 3 pushes.'.PHP_EOL;
    echo 'Owner 101 expects: new booking and the same two reminders (3).'.PHP_EOL;
};

$int10Retry = function () use (&$demo, $bookerId, $outboxRepo, $head) {
    $head('INT-10 worker restart: step 2 of 2');
    $row = NotificationOutbox::where('event_schedule_id', $demo['b10'] ?? 0)->where('user_id', $bookerId)->where('type', 'booking.reminder')
        ->where('status', NotificationOutbox::SENT)->orderBy('notify_at')->first();
    if (! $row) {
        echo 'The 15-minute reminder has not been sent yet. Wait for the push, then run this again.'.PHP_EOL;

        return;
    }
    NotificationOutbox::where('id', $row->id)->update([
        'status' => NotificationOutbox::CLAIMED, 'claimed_at' => Carbon::now()->subHours(2)->utc()->format('Y-m-d H:i:s'), 'attempts' => 1,
    ]);
    $released = $outboxRepo->releaseStaleClaims();
    echo "Marked the sent reminder as 'worker died' and released it ($released row(s)). The next tick sends it again:".PHP_EOL;
    echo '83 must NOT get a second 15-minute push.'.PHP_EOL;
};

$int11 = function () use ($mkLiveEvent, $book, $startShare, $notifierL, $resetLive, $pause, $fail, $head) {
    $head('INT-11 manual stop and auto-stop of the SAME session, both running');
    $resetLive();
    $e = $mkLiveEvent('INT11', false);
    $b = $book($e);
    if (! $b) {
        return $fail('INT-11');
    }
    $pause();
    $r = $startShare($e);
    if ($r->getStatusCode() !== 200) {
        echo 'Could not start sharing: '.$r->getStatusCode().PHP_EOL;

        return;
    }
    $pause();
    $ev = Event::find($e->id);
    $session = $ev->location->live_started_at;
    $notifierL->stopped($ev, $session);
    $notifierL->autoStopped($ev, $session, true);
    echo 'Booker 83 expects: 3 pushes (booking confirmed, sharing started, ONE stop notice), not 4.'.PHP_EOL;
    echo 'Owner 101 expects: 2 pushes (new booking, "sharing stopped automatically after 24 hours").'.PHP_EOL;
};

$int12 = function () use ($mkEvent, $owner, $book, $notifierB, $pause, $fail, $head) {
    $head('INT-12 hooks retried: confirmation and cancellation run twice');
    $e = $mkEvent($owner, 'INT12');
    $b = $book($e);
    if (! $b) {
        return $fail('INT-12');
    }
    $notifierB->created($b->fresh());
    $notifierB->created($b->fresh());
    $pause();
    $notifierB->cancelled($b->fresh(), 'owner_cancelled');
    $notifierB->cancelled($b->fresh(), 'owner_cancelled');
    echo 'Booker 83 expects: 2 pushes (booking confirmed ONCE, booking cancelled ONCE), not 6.'.PHP_EOL;
    echo 'Owner 101 expects: 1 push (new booking).'.PHP_EOL;
};

echo PHP_EOL.'Real push demo is ready. Owner '.$ownerId.', booker '.$bookerId.', gap between steps '.$gap.' seconds.'.PHP_EOL;
echo 'Run ONE scenario, wait until its pushes have arrived (and for the "wait" notes), then the next:'.PHP_EOL;
echo '  $int01()  confirm + edit held ~3 min                 83: 2 pushes'.PHP_EOL;
echo '  $int02()  confirm + cancel                           83: 2'.PHP_EOL;
echo '  $int03()  confirm + event deleted                    83: 2'.PHP_EOL;
echo '  $int04()  confirm, sharing started, cancelled        83: 3'.PHP_EOL;
echo '  $int05()  confirm, sharing stopped, details changed  83: 3'.PHP_EOL;
echo '  $int06()  confirm, edit, 15-min and start reminders  83: 4 (takes ~19 min)'.PHP_EOL;
echo '  $int07()  confirm + deleted, held edit never comes   83: 2 (wait 5 min to see nothing else)'.PHP_EOL;
echo '  $int08()  double start / double stop                 83: 3'.PHP_EOL;
echo '  $int09()  double save, quick renames                 83: 3 (wait 5 min to see nothing else)'.PHP_EOL;
echo '  $int10()  then $int10Retry() after the 15-min push   83: 3 (takes ~17 min)'.PHP_EOL;
echo '  $int11()  stop + auto-stop of the same session       83: 3, owner 101: 2'.PHP_EOL;
echo '  $int12()  hooks run twice                            83: 2'.PHP_EOL;
echo 'Also: $intRows() shows what was created, $intCleanup() removes it (after the last push has arrived).'.PHP_EOL;