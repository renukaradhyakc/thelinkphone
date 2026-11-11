<?php

/*
 * Notification inbox, push and deduplication checks (NTF series).
 *
 * COVERS: NTF-01 to NTF-20. Everything that touches Firebase (NTF-11 to NTF-19) runs the real
 * SendPushNotificationJob, FcmChannel, DeviceService and DeviceTokenRepository against a FAKE Firebase
 * (Http::fake): nothing reaches Google or a real phone, and only throwaway device tokens named "ntf-test-..."
 * on a user with no active device are used. NTF-07 and NTF-08 test the inbox repository (the controller and
 * routes are only checked for existence, the HTTP layer itself is not exercised).
 *
 * ROLES: owner = user 101 (owns the event, has NO active device), booker = user 83 (two active devices).
 *
 * LOCAL DATABASE ONLY. Creates a throwaway event and bookings, calls the real NotificationService /
 * LocationNotifier / LinkResolver / UserNotificationRepository, reads the real user_notifications rows and
 * deletes everything it created (even if a check crashes). Pushes and mail are faked during the checks.
 * The booker's push preferences are put back exactly as they were.
 *
 * Run from the Laravel project root, in a FRESH tinker session (type it, do not paste):
 *     php artisan tinker
 *     >>> include 'notification-core-test.php';
 *
 * Defaults: owner 101, booker 83, booker2 107. Optional before the include: $ownerId, $bookerId, $booker2Id
 * ($booker2Id is the second user for the NTF-14 shared-phone variant; it must be an existing user, otherwise
 * that variant is skipped).
 *
 * Real push demo to user 83's devices (runs after all the checks, NO clock jump):
 *     >>> $ntfKeep = true;
 *     >>> include 'notification-core-test.php';
 *     ... wait about a minute, check the phones ...
 *     >>> $ntfCleanup();
 *   It sends ONE inbox+push notification (the same call is made twice, so you must get only one), plus
 *   one inbox-only notification (no push). The script prints how many alerts to expect.
 *   Do not run $ntfCleanup() before the push arrived.
 */

use App\Models\Event;
use App\Models\EventSchedule;
use App\Models\Location;
use App\Models\User;
use App\Modules\Notifications\Jobs\SendPushNotificationJob;
use App\Modules\Notifications\Repositories\DeviceTokenRepository;
use App\Modules\Notifications\Services\DeviceService;
use App\Modules\Notifications\Models\NotificationPreference;
use App\Modules\Notifications\Repositories\PreferenceRepository;
use App\Modules\Notifications\Repositories\UserNotificationRepository;
use App\Modules\Notifications\Services\LinkResolver;
use App\Modules\Notifications\Services\LocationNotifier;
use App\Modules\Notifications\Services\NotificationRegistry;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Notifications\Services\RecipientResolver;
use App\Repositories\EventRepository;
use App\Repositories\ScheduleEventRepository;
use Carbon\Carbon;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

$ownerId = (int) ($ownerId ?? 101);
$bookerId = (int) ($bookerId ?? 83);
$booker2Id = isset($booker2Id) ? (int) $booker2Id : 107;
$booker2 = $booker2Id ? User::find($booker2Id) : null;
$ntfKeep = $ntfKeep ?? false;
$ntfDemoStarted = false;

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

// ---- Fakes ------------------------------------------------------------------------------------
$origBus = app(Dispatcher::class);
$restoreBus = function () use ($origBus) {
    app()->instance(Dispatcher::class, $origBus);
    Facade::clearResolvedInstance(Dispatcher::class);
};
Mail::fake();
Bus::fake([SendPushNotificationJob::class]);

// ---- Firebase fake: in the device tests nothing reaches Google or a real phone ------------------------------
$fcmSent = [];   // every request the sender made: token, notification uuid, tag, collapse id, priority, ttl
$fcmPlan = [];   // token => replies in order (the last one repeats): ok | unregistered | badtoken | sender | unavailable | ratelimit | denied
$fcmKey = 'fcm_access_token';
Cache::put($fcmKey, 'ntf-fake-token', 600); // so the sender never mints a real OAuth token while the fake is on (forgotten again at the end)
$fcmReply = function (string $kind) {
    $err = fn ($code, $status, $msg, $codes = []) => Http::response(['error' => [
        'code' => $code, 'message' => $msg, 'status' => $status,
        'details' => array_map(fn ($c) => ['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => $c], $codes),
    ]], $code);

    return match ($kind) {
        'unregistered' => $err(404, 'NOT_FOUND', 'Requested entity was not found.', ['UNREGISTERED']),
        'badtoken' => $err(400, 'INVALID_ARGUMENT', 'The registration token is not a valid FCM registration token'),
        'sender' => $err(403, 'PERMISSION_DENIED', 'SenderId mismatch', ['SENDER_ID_MISMATCH']),
        'unavailable' => $err(503, 'UNAVAILABLE', 'The service is currently unavailable.'),
        'ratelimit' => $err(429, 'RESOURCE_EXHAUSTED', 'Quota exceeded.'),
        'denied' => $err(400, 'INVALID_ARGUMENT', 'Invalid JSON payload received.'),
        default => Http::response(['name' => 'projects/ntf-test/messages/1'], 200),
    };
};
Http::fake(function (\Illuminate\Http\Client\Request $request) use (&$fcmSent, &$fcmPlan, $fcmReply) {
    $msg = $request->data()['message'] ?? null;
    if (! is_array($msg)) {
        return Http::response([], 200);
    }
    $token = (string) ($msg['token'] ?? '');
    $plan = $fcmPlan[$token] ?? ['ok'];
    $kind = count($plan) > 1 ? array_shift($fcmPlan[$token]) : $plan[0];
    $fcmSent[] = [
        'token' => $token, 'kind' => $kind,
        'nid' => $msg['data']['notification_id'] ?? null,
        'tag' => $msg['android']['notification']['tag'] ?? null,
        'collapse' => $msg['apns']['headers']['apns-collapse-id'] ?? null,
        'a_priority' => $msg['android']['priority'] ?? null,
        'ttl' => $msg['android']['ttl'] ?? null,
        'apns_priority' => $msg['apns']['headers']['apns-priority'] ?? null,
        'thread' => $msg['apns']['payload']['aps']['thread-id'] ?? null,
    ];

    return $fcmReply($kind);
});
$fcmCredPath = (string) config('app.fcm_credentials_path');
$fcmCredFile = $fcmCredPath === '' ? '' : ((str_starts_with($fcmCredPath, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $fcmCredPath)) ? $fcmCredPath : storage_path($fcmCredPath));
$fcmReady = $fcmCredFile !== '' && is_file($fcmCredFile); // the sender reads project_id from this file even when Firebase is faked
$fcmReset = function () use ($fcmKey) {
    app()->forgetInstance(\Illuminate\Http\Client\Factory::class);
    Facade::clearResolvedInstance(\Illuminate\Http\Client\Factory::class);
    Cache::forget($fcmKey);
};

// ---- Helpers ----------------------------------------------------------------------------------
$tag = substr(uniqid(), -6);
$S1 = '10:00 AM - 10:30 AM';
$bookingIds = [];
$eventIds = [];
$lastError = null;
$dayN = 3;

$svc = app(NotificationService::class);
$registry = app(NotificationRegistry::class);
$linkResolver = app(LinkResolver::class);
$inbox = app(UserNotificationRepository::class);
$prefRepo = app(PreferenceRepository::class);
$eventRepo = app(EventRepository::class);
$bookingRepo = app(ScheduleEventRepository::class);

$pushCount = fn () => Bus::dispatched(SendPushNotificationJob::class)->count();
$nextDate = function () use (&$dayN) {
    return Carbon::now()->addDays(++$dayN)->format('Y-m-d'); // 4+ days ahead
};

// Category of a type. Not config('a.b.c'): the type names contain a dot.
$catOf = fn (string $type) => (string) (config('notifications.types')[$type]['category'] ?? '');

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

$book = function (Event $event, ?string $phone, string $slot) use (&$bookingIds, &$lastError, $bookingRepo, $nextDate) {
    try {
        $b = $bookingRepo->store([
            'name' => 'NTF Guest',
            'phone_call' => $phone,
            'email' => 'ntf-guest-'.uniqid().'@example.invalid',
            'schedule_date' => $nextDate(),
            'description' => 'NTF test booking',
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

// Run the real push job the way the worker does (method injection), and return the exception it threw, if any.
$runJob = function (int $notificationId) {
    $job = new SendPushNotificationJob($notificationId);

    try {
        app()->call([$job, 'handle']);

        return null;
    } catch (Throwable $e) {
        return $e;
    }
};
$logFile = storage_path('logs/laravel.log');
$logMark = fn () => is_file($logFile) ? (int) filesize($logFile) : 0;
$logSince = fn (int $mark) => (is_file($logFile) && filesize($logFile) > $mark) ? (string) file_get_contents($logFile, false, null, $mark) : '';

$rowsByKey = fn (string $key) => DB::table('user_notifications')->where('dedupe_key', $key)->get();
$activeTokens = fn (int $userId) => (int) DB::table('device_tokens')->where('user_id', $userId)->whereNull('revoked_at')->count();

// Text placeholders, the same set BookingNotifier gives to booking notifications.
$paramsFor = fn (Event $e, string $with = 'the host') => [
    'event' => $e->name,
    'name' => 'NTF Guest',
    'owner' => 'The host',
    'with' => $with,
    'time' => '05 Nov, 10:00 AM',
    'changes' => 'name updated',
];

// The booker's push preferences for the two categories used below, remembered so they can be put back exactly.
$prefCats = array_values(array_unique([$catOf('booking.changed'), $catOf('booking.cancelled')]));
$prefBase = [];
foreach ($prefCats as $c) {
    $r = NotificationPreference::where('user_id', $bookerId)->where('category', $c)->first();
    $prefBase[$c] = $r ? (bool) $r->push_enabled : null; // null = no row (registry default)
}
$prefRestore = function () use ($prefBase, $prefRepo, $bookerId) {
    foreach ($prefBase as $c => $v) {
        if ($v === null) {
            NotificationPreference::where('user_id', $bookerId)->where('category', $c)->delete();
        } else {
            $prefRepo->set($bookerId, $c, $v);
        }
    }
};

$ntfCleanup = function () use (&$bookingIds, &$eventIds) {
    DB::table('user_notifications')->where('dedupe_key', 'like', 'ntf-test:%')->delete();
    DB::table('device_tokens')->where('token', 'like', 'ntf-test-%')->delete();
    foreach ($bookingIds as $id) {
        DB::table('user_notifications')->where(function ($q) use ($id) {
            $q->where('dedupe_key', 'like', "booking.%:$id:%")->orWhere('dedupe_key', 'like', "location.%:$id:%");
        })->delete();
        \App\Modules\Notifications\Models\NotificationOutbox::where('event_schedule_id', $id)->delete();
    }
    if ($bookingIds) {
        EventSchedule::whereIn('id', $bookingIds)->delete();
    }
    if ($eventIds) {
        // location notification keys are built from the EVENT id: location.started:{event}:{session}:{user}
        foreach ($eventIds as $id) {
            DB::table('user_notifications')->where('dedupe_key', 'like', "location.%:$id:%")->delete();
        }
        Event::whereIn('id', $eventIds)->get()->each(function ($e) {
            $e->location()->delete();
            $e->delete();
        });
    }
    $bookingIds = [];
    $eventIds = [];
    echo 'Deleted the throwaway events, bookings, notifications and outbox rows.'.PHP_EOL;
};

// leftovers from earlier kept or crashed runs
$oldEventIds = Event::where('name', 'like', 'NTF-TEST %')->pluck('id');
DB::table('user_notifications')->where('dedupe_key', 'like', 'ntf-test:%')->delete();
DB::table('device_tokens')->where('token', 'like', 'ntf-test-%')->delete();
if ($oldEventIds->isNotEmpty()) {
    foreach ($oldEventIds as $oldEventId) {
        DB::table('user_notifications')->where('dedupe_key', 'like', "location.%:$oldEventId:%")->delete();
    }
    $oldBookingIds = EventSchedule::whereIn('event_id', $oldEventIds)->pluck('id');
    foreach ($oldBookingIds as $oldId) {
        DB::table('user_notifications')->where(function ($q) use ($oldId) {
            $q->where('dedupe_key', 'like', "booking.%:$oldId:%")->orWhere('dedupe_key', 'like', "location.%:$oldId:%");
        })->delete();
        \App\Modules\Notifications\Models\NotificationOutbox::where('event_schedule_id', $oldId)->delete();
    }
    EventSchedule::whereIn('id', $oldBookingIds)->delete();
    Event::whereIn('id', $oldEventIds)->get()->each(function ($e) {
        $e->location()->delete();
        $e->delete();
    });
    echo 'Removed leftovers of earlier runs: '.$oldEventIds->count().' event(s), '.$oldBookingIds->count().' booking(s)'.PHP_EOL;
}

echo "Owner $ownerId (active devices: ".$activeTokens($ownerId)."), booker $bookerId (active devices: ".$activeTokens($bookerId).')'.PHP_EOL.PHP_EOL;

try {
    $e0 = $mkEvent($owner, 'N00');
    $b0 = $book($e0, $bookerPhone, $S1);
    if ($setupFailed($b0, 'NTF setup')) {
        throw new RuntimeException('no test booking, cannot run the checks');
    }
    $ctx = ['event_schedule_id' => $b0->id, 'audience' => 'booker'];
    $p0 = $paramsFor($e0);

    // ============ NTF-01 inbox + push ============
    $chans = $registry->channels('booking.changed', 'event_edited');
    $pushBefore = $pushCount();
    $n1 = $svc->notify($bookerId, 'booking.changed', 'event_edited', "ntf-test:$tag:01", $p0, $ctx);
    $jobs = Bus::dispatched(SendPushNotificationJob::class);
    $check('NTF-01 setup: booking.changed.event_edited is an inbox + push type', in_array('inbox', $chans, true) && in_array('push', $chans, true), json_encode($chans));
    $check('NTF-01 the inbox row is created for the booker', $n1 !== null && (int) $n1->user_id === $bookerId && $n1->type === 'booking.changed' && $n1->reason === 'event_edited',
        'row='.optional($n1)->id);
    $check('NTF-01 the row has category, title, body, deep link and an expiry',
        $n1 && $n1->category === $catOf('booking.changed') && trim((string) $n1->title) !== '' && trim((string) $n1->body) !== '' && trim((string) $n1->deep_link) !== '' && $n1->expires_at !== null,
        'category='.optional($n1)->category.', title="'.optional($n1)->title.'"');
    $check('NTF-01 exactly one push job is queued', ($pushCount() - $pushBefore) === 1, 'push jobs='.($pushCount() - $pushBefore).', queue='.optional($jobs->last())->queue);

    // ============ NTF-02 inbox only ============
    $chans = $registry->channels('booking.created', 'booker');
    $pushBefore = $pushCount();
    $n2 = $svc->notify($bookerId, 'booking.created', 'booker', "ntf-test:$tag:02", $p0, $ctx);
    $check('NTF-02 setup: booking.created.booker is inbox only', in_array('inbox', $chans, true) && ! in_array('push', $chans, true), json_encode($chans));
    $check('NTF-02 the inbox row is created', $n2 !== null && (int) $n2->user_id === $bookerId, 'row='.optional($n2)->id);
    $check('NTF-02 no push job is queued', ($pushCount() - $pushBefore) === 0, 'push jobs='.($pushCount() - $pushBefore));

    // ============ NTF-03 push preference OFF ============
    $catChanged = $catOf('booking.changed');
    $catCancelled = $catOf('booking.cancelled');
    $prefRepo->set($bookerId, $catChanged, false);
    $pushBefore = $pushCount();
    $n3 = $svc->notify($bookerId, 'booking.changed', 'event_edited', "ntf-test:$tag:03", $p0, $ctx);
    $check('NTF-03 push OFF ('.$catChanged.'): the inbox row is still created', $n3 !== null && (int) $n3->user_id === $bookerId, 'row='.optional($n3)->id);
    $check('NTF-03 push OFF: no push job is queued', ($pushCount() - $pushBefore) === 0, 'push jobs='.($pushCount() - $pushBefore));

    // control: a mandatory type (booking.cancelled) still pushes when its category is OFF
    $isMandatory = (bool) ((config('notifications.types')['booking.cancelled']['mandatory'] ?? false));
    $prefRepo->set($bookerId, $catCancelled, false);
    $pushBefore = $pushCount();
    $n3b = $svc->notify($bookerId, 'booking.cancelled', 'owner_cancelled', "ntf-test:$tag:03b", $p0, $ctx);
    $check('NTF-03 control: a mandatory type still pushes when its category is OFF', $isMandatory && $n3b !== null && ($pushCount() - $pushBefore) === 1,
        'mandatory='.json_encode($isMandatory).', push jobs='.($pushCount() - $pushBefore));
    $prefRestore();

    // ============ NTF-04 same dedupe key twice ============
    $pushBefore = $pushCount();
    $first = $svc->notify($bookerId, 'booking.changed', 'event_edited', "ntf-test:$tag:04", $p0, $ctx);
    $second = $svc->notify($bookerId, 'booking.changed', 'event_edited', "ntf-test:$tag:04", $p0, $ctx);
    $check('NTF-04 the second call returns nothing (duplicate)', $first !== null && $second === null, 'first='.optional($first)->id.', second='.json_encode($second === null ? null : $second->id));
    $check('NTF-04 exactly one inbox row', $rowsByKey("ntf-test:$tag:04")->count() === 1, 'rows='.$rowsByKey("ntf-test:$tag:04")->count());
    $check('NTF-04 exactly one push job', ($pushCount() - $pushBefore) === 1, 'push jobs='.($pushCount() - $pushBefore));

    // ============ NTF-05 same event, two users ============
    $nA = $svc->notify($bookerId, 'booking.changed', 'event_edited', "ntf-test:$tag:05:$bookerId", $p0, $ctx);
    $nB = $svc->notify($ownerId, 'booking.changed', 'event_edited', "ntf-test:$tag:05:$ownerId", $p0, ['event_schedule_id' => $b0->id, 'audience' => 'owner']);
    $users = DB::table('user_notifications')->where('dedupe_key', 'like', "ntf-test:$tag:05:%")->pluck('user_id')->map(fn ($x) => (int) $x)->sort()->values()->all();
    $check('NTF-05 two users get two separate rows, one each', $nA !== null && $nB !== null && $users === collect([$bookerId, $ownerId])->sort()->values()->all(), 'users='.json_encode($users));
    $svc->notify($bookerId, 'booking.changed', 'event_edited', "ntf-test:$tag:05same", $p0, $ctx);
    $svc->notify($ownerId, 'booking.changed', 'event_edited', "ntf-test:$tag:05same", $p0, $ctx);
    $sameKeyRows = $rowsByKey("ntf-test:$tag:05same")->count();
    $report($sameKeyRows === 2 ? 'PASS' : 'INVESTIGATE', 'NTF-05 the SAME dedupe key for two different users gives each user a row (uniqueness is per user and key)',
        $sameKeyRows === 2 ? 'rows=2, as the repository documents' : "rows=$sameKeyRows, but createUnique is documented as unique per (user, dedupe_key)");

    // ============ NTF-06 separate live sessions ============
    // LocationNotifier builds its key from the EVENT id: location.started:{event}:{session}:{user}
    $e6 = $mkEvent($owner, 'N06', Event::IN_PERSON_MEETING);
    $e6->location()->create([
        'location_type' => Location::LIVE, 'latitude' => 12.9716, 'longitude' => 77.5946, 'accuracy' => 10, 'address' => null,
        'is_live_sharing_active' => true, 'live_started_at' => now(),
    ]);
    $b6 = $book($e6, $bookerPhone, $S1);
    if (! $setupFailed($b6, 'NTF-06')) {
        $loc = app(LocationNotifier::class);
        $t1 = Carbon::now();
        $t2 = Carbon::now()->addHour(); // a second, later live session
        $e6->location()->update(['live_started_at' => $t1]); // the saved session start matches the first session
        $countStarted = fn () => DB::table('user_notifications')->where('type', 'location.started')->where('user_id', $bookerId)
            ->where('dedupe_key', 'like', "location.started:{$e6->id}:%")->get();
        $loc->started($e6->fresh(), $t1);
        $loc->started($e6->fresh(), $t1); // the same session again
        $afterSame = $countStarted();
        $e6->location()->update(['live_started_at' => $t2]); // a real second session also stores its own start time
        $loc->started($e6->fresh(), $t2); // a different session
        $afterTwo = $countStarted();
        $check('NTF-06 the same live session twice gives one notification', $afterSame->count() === 1, 'rows='.$afterSame->count());
        $check('NTF-06 a separate live session gives its own notification with a different key', $afterTwo->count() === 2 && $afterTwo->pluck('dedupe_key')->unique()->count() === 2,
            'rows='.$afterTwo->count().', keys='.$afterTwo->pluck('dedupe_key')->implode(' | '));
    }

    // ============ NTF-09 deep links ============
    $matchRoute = function (string $url) {
        try {
            app('router')->getRoutes()->match(Request::create($url, 'GET'));

            return [true, ''];
        } catch (Throwable $e) {
            return [false, class_basename($e)];
        }
    };
    $cases = [
        ['booking.created', 'booker'], ['booking.changed', 'event_edited'], ['booking.changed', 'location_changed'],
        ['booking.cancelled', 'owner_cancelled'], ['booking.reminder', '15_min'],
    ];
    foreach ($cases as [$t, $v]) {
        $url = (string) $linkResolver->resolve($t, $v, $ctx, $bookerId);
        [$routed, $why] = $matchRoute($url);
        $linkKey = (string) (config('notifications.types')[$t]['link'] ?? '');
        $needsSlug = $linkKey === 'booking';
        $check("NTF-09 $t.$v ($linkKey) opens a real route", (bool) preg_match('#^https?://#', $url) && $routed && (! $needsSlug || str_contains($url, (string) $e0->event_link)),
            'path='.parse_url($url, PHP_URL_PATH).($routed ? '' : ', no route: '.$why));
    }
    $check('NTF-09 the deep link stored on a notification equals what LinkResolver returns', $n1 && $n1->deep_link === $linkResolver->resolve('booking.changed', 'event_edited', $ctx, $bookerId));
    $report('INFO', 'NTF-09 marking a notification read when it is opened is NTF-07 (repository level); the app WebView itself is not tested here');

    // ============ NTF-10 no device token ============
    $noTokenUser = collect([$ownerId])->merge(User::limit(60)->pluck('id'))->first(fn ($id) => $activeTokens((int) $id) === 0);
    $devUser = $noTokenUser === null ? null : (int) $noTokenUser; // also the user the device tests below use
    if ($devUser === null) {
        $report('SKIP', 'NTF-10 and the device tests (every user has an active device token)');
    } else {
        $n10 = $svc->notify($devUser, 'booking.changed', 'event_edited', "ntf-test:$tag:10", $p0, ['event_schedule_id' => $b0->id, 'audience' => 'owner']);
        $check("NTF-10 user $devUser has no active device and still gets the inbox row", $n10 !== null && (int) $n10->user_id === $devUser && $activeTokens($devUser) === 0,
            'row='.optional($n10)->id.', active devices='.$activeTokens($devUser));
        if ($n10) {
            $fcmSent = [];
            $fcmPlan = [];
            $ex = $runJob($n10->id);
            $check('NTF-10 the push job runs for that user without error and calls Firebase zero times', $ex === null && count($fcmSent) === 0, 'error='.($ex ? $ex->getMessage() : 'none').', FCM requests='.count($fcmSent));
        }
    }

    // ============ NTF-20 concurrent creation: the database must refuse a duplicate ============
    $rawRow = fn (int $userId, string $key) => [
        'uuid' => (string) Str::uuid(), 'user_id' => $userId, 'type' => 'booking.changed', 'reason' => 'event_edited', 'category' => $catOf('booking.changed'),
        'title' => 'NTF test', 'body' => 'NTF test', 'data' => null, 'deep_link' => null, 'dedupe_key' => $key, 'read_at' => null,
        'expires_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now(),
    ];
    $key20 = "ntf-test:$tag:20";
    try {
        DB::table('user_notifications')->insert($rawRow($bookerId, $key20));
        $dupCode = null;
        $dupMsg = '';

        try {
            DB::table('user_notifications')->insert($rawRow($bookerId, $key20)); // what a second parallel request would try
        } catch (QueryException $e) {
            $dupCode = (int) ($e->errorInfo[1] ?? 0);
            $dupMsg = substr($e->getMessage(), 0, 90);
        }

        $check('NTF-20 the database itself rejects a second row with the same dedupe key (duplicate-key error 1062)', $dupCode === 1062, 'error='.json_encode($dupCode).' '.$dupMsg);
        $check('NTF-20 only one row exists for that key', $rowsByKey($key20)->count() === 1, 'rows='.$rowsByKey($key20)->count());

        $viaRepo = $inbox->createUnique([
            'user_id' => $bookerId, 'type' => 'booking.changed', 'reason' => 'event_edited', 'category' => $catOf('booking.changed'),
            'title' => 'NTF test', 'body' => 'NTF test', 'data' => null, 'deep_link' => null, 'dedupe_key' => $key20, 'expires_at' => now()->addDay(),
        ]);
        $check('NTF-20 createUnique on a key that already exists returns null and does not throw', $viaRepo === null && $rowsByKey($key20)->count() === 1, 'rows='.$rowsByKey($key20)->count());

        try {
            DB::table('user_notifications')->insert($rawRow($ownerId, $key20));
            $report('PASS', 'NTF-20 the same key for ANOTHER user is accepted (the unique index is per user and key, as documented)');
        } catch (QueryException $e) {
            $report('INVESTIGATE', 'NTF-20 the same key for another user was refused: the unique index covers the key alone, but createUnique documents (user, dedupe_key)');
        }
        $report('INFO', 'NTF-20 this proves the database constraint. Two truly parallel PHP processes would need a separate stress run');
    } catch (Throwable $e) {
        $report('INVESTIGATE', 'NTF-20 could not insert its test row directly into user_notifications', substr($e->getMessage(), 0, 160));
    }

    // ============ NTF-07 mark as read ============
    $n7 = $svc->notify($bookerId, 'booking.changed', 'event_edited', "ntf-test:$tag:07", $p0, $ctx);
    $unread0 = $inbox->unreadCount($bookerId);
    $inUnread0 = $inbox->page($bookerId, null, 50, true)->contains('id', $n7->id);
    $otherUser = $inbox->markRead($ownerId, $n7->uuid); // someone else must not be able to read it
    $stillUnread = optional($inbox->findForUser($bookerId, $n7->uuid))->read_at === null;
    $r1 = $inbox->markRead($bookerId, $n7->uuid);
    $after = $inbox->findForUser($bookerId, $n7->uuid);
    $r2 = $inbox->markRead($bookerId, $n7->uuid);
    $check('NTF-07 a new notification starts unread and is in the unread list', $n7->read_at === null && $inUnread0 === true);
    $check('NTF-07 another user cannot mark it read', $otherUser === false && $stillUnread, 'markRead by user '.$ownerId.'='.json_encode($otherUser));
    $check('NTF-07 the owner of the notification marks it read: read_at is set', $r1 === true && $after && $after->read_at !== null, 'read_at='.optional($after)->read_at);
    $check('NTF-07 the unread count goes down by one and it leaves the unread list', $inbox->unreadCount($bookerId) === $unread0 - 1 && ! $inbox->page($bookerId, null, 50, true)->contains('id', $n7->id),
        'unread '.$unread0.' -> '.$inbox->unreadCount($bookerId));
    $check('NTF-07 marking it read again changes nothing (returns false)', $r2 === false);

    // ============ NTF-08 mark as unread ============
    $notifRoutes = collect(app('router')->getRoutes()->getRoutes())
        ->map(fn ($r) => ['methods' => $r->methods(), 'uri' => $r->uri()])
        ->filter(fn ($r) => stripos($r['uri'], 'notification') !== false)->values();
    $routeList = substr($notifRoutes->map(fn ($r) => implode('|', array_diff($r['methods'], ['HEAD'])).' '.$r['uri'])->implode(' ; '), 0, 400);
    if (! method_exists($inbox, 'markUnread')) {
        $check('NTF-08 a notification can be marked unread', false,
            'UserNotificationRepository has no markUnread method yet. Add markUnread() to the repository, unread() to NotificationController and POST api/v1/notifications/{uuid}/unread to the API routes');
    } else {
        $n8 = $svc->notify($bookerId, 'booking.changed', 'event_edited', "ntf-test:$tag:08", $p0, $ctx);
        $inbox->markRead($bookerId, $n8->uuid);
        $unreadBefore8 = $inbox->unreadCount($bookerId);
        $otherUser8 = $inbox->markUnread($ownerId, $n8->uuid); // someone else must not be able to
        $stillRead8 = optional($inbox->findForUser($bookerId, $n8->uuid))->read_at !== null;
        $u1 = $inbox->markUnread($bookerId, $n8->uuid);
        $row8 = $inbox->findForUser($bookerId, $n8->uuid);
        $u2 = $inbox->markUnread($bookerId, $n8->uuid);
        $hasUnreadRoute = $notifRoutes->contains(fn ($r) => stripos($r['uri'], '{uuid}/unread') !== false && in_array('POST', $r['methods'], true));
        $check('NTF-08 another user cannot mark it unread', $otherUser8 === false && $stillRead8, 'markUnread by user '.$ownerId.'='.json_encode($otherUser8));
        $check('NTF-08 the owner marks it unread: read_at is cleared', $u1 === true && $row8 && $row8->read_at === null, 'read_at='.json_encode(optional($row8)->read_at));
        $check('NTF-08 the unread count goes up by one and it is back in the unread list',
            $inbox->unreadCount($bookerId) === $unreadBefore8 + 1 && $inbox->page($bookerId, null, 50, true)->contains('id', $n8->id),
            'unread '.$unreadBefore8.' -> '.$inbox->unreadCount($bookerId));
        $check('NTF-08 marking it unread again changes nothing (returns false)', $u2 === false);
        $check('NTF-08 the route POST api/v1/notifications/{uuid}/unread exists', $hasUnreadRoute, $routeList);
    }

    // ============ device tests (NTF-11 to NTF-19): real job + real sender + real device code, Firebase faked ============
    $deviceNames = 'NTF-11 to NTF-19';
    if ($devUser === null || ! $fcmReady) {
        $report('SKIP', $deviceNames, $devUser === null ? 'no user without an active device to use as the test user' : 'the FCM credentials file was not found ('.$fcmCredFile.'); the sender reads project_id from it');
    } else {
        $deviceRepo = app(DeviceTokenRepository::class);
        $regDevice = fn (int $uid, string $platform, string $tok, ?string $devId) => app(DeviceService::class)->register($uid, $platform, $tok, ['device_id' => $devId, 'app_version' => 'ntf-test', 'web_keys' => null]);
        $clearDev = fn () => DB::table('device_tokens')->where('token', 'like', 'ntf-test-%')->delete();
        $tokRow = fn (string $tok) => DB::table('device_tokens')->where('token', $tok)->first();
        $mkNotif = fn (string $k) => $svc->notify($devUser, 'booking.changed', 'event_edited', "ntf-test:$tag:$k", $p0, ['event_schedule_id' => $b0->id, 'audience' => 'owner']);
        $sentTokens = function () use (&$fcmSent) {
            return collect($fcmSent)->pluck('token')->sort()->values()->all();
        };
        $tA = "ntf-test-and-A-$tag";
        $tB = "ntf-test-ios-B-$tag";
        $tC = "ntf-test-and-C-$tag";
        $dA = "ntf-test-dev-A-$tag";
        $dB = "ntf-test-dev-B-$tag";
        $dC = "ntf-test-dev-C-$tag";
        $cat = $catOf('booking.changed');
        $expPrio = $registry->priority('booking.changed') === 'high' ? 'HIGH' : 'NORMAL';
        $expTtl = ($registry->pushTtlMinutes('booking.changed') * 60).'s';
        $noErr = fn ($ex) => $ex === null ? 'none' : get_class($ex).': '.$ex->getMessage();

        // ---- NTF-11 Android token only ----
        $clearDev();
        $regDevice($devUser, 'android', $tA, $dA);
        $n11 = $mkNotif('11');
        $fcmSent = [];
        $fcmPlan = [];
        $ex = $runJob($n11->id);
        $s11 = $fcmSent;
        $check('NTF-11 Android token only: exactly one Firebase request, to that token', $ex === null && count($s11) === 1 && $s11[0]['token'] === $tA, 'requests='.count($s11).', error='.$noErr($ex));
        $check('NTF-11 the message carries the notification uuid, the replace-tag, and the priority and TTL from the registry',
            count($s11) === 1 && $s11[0]['nid'] === $n11->uuid && $s11[0]['tag'] === $n11->uuid && $s11[0]['a_priority'] === $expPrio && $s11[0]['ttl'] === $expTtl,
            'priority='.json_encode($s11[0]['a_priority'] ?? null).' (expected '.$expPrio.'), ttl='.json_encode($s11[0]['ttl'] ?? null).' (expected '.$expTtl.')');

        // ---- NTF-12 iOS token only ----
        $clearDev();
        $regDevice($devUser, 'ios', $tB, $dB);
        $n12 = $mkNotif('12');
        $fcmSent = [];
        $ex = $runJob($n12->id);
        $s12 = $fcmSent;
        $check('NTF-12 iOS token only: exactly one Firebase request, to that token', $ex === null && count($s12) === 1 && $s12[0]['token'] === $tB, 'requests='.count($s12).', error='.$noErr($ex));
        $check('NTF-12 the message carries the APNs collapse id (= uuid), APNs priority and the category as thread id',
            count($s12) === 1 && $s12[0]['collapse'] === $n12->uuid && $s12[0]['apns_priority'] === ($expPrio === 'HIGH' ? '10' : '5') && $s12[0]['thread'] === $cat,
            'collapse='.json_encode($s12[0]['collapse'] ?? null).', apns-priority='.json_encode($s12[0]['apns_priority'] ?? null).', thread='.json_encode($s12[0]['thread'] ?? null));

        // ---- NTF-13 several active devices ----
        $clearDev();
        $regDevice($devUser, 'android', $tA, $dA);
        $regDevice($devUser, 'ios', $tB, $dB);
        $regDevice($devUser, 'android', $tC, $dC);
        $active = $deviceRepo->activeForUser($devUser, ['android', 'ios']);
        $n13 = $mkNotif('13');
        $fcmSent = [];
        $mark = $logMark();
        $ex = $runJob($n13->id);
        $log = $logSince($mark);
        $check('NTF-13 three devices are active for the user', $active->count() === 3, 'active='.$active->count());
        $check('NTF-13 each of the three tokens gets exactly one request', $ex === null && $sentTokens() === collect([$tA, $tB, $tC])->sort()->values()->all(), 'requests='.count($fcmSent).', error='.$noErr($ex));
        if ($log !== '') {
            $check('NTF-13 one fcm.response log line per device', substr_count($log, '"notification_id":'.$n13->id.',') === 3, 'log lines='.substr_count($log, '"notification_id":'.$n13->id.','));
        } else {
            $report('INFO', 'NTF-13 the log file could not be read from here, so the per-device log lines were not checked');
        }

        // ---- NTF-14 duplicate registration ----
        $clearDev();
        $regDevice($devUser, 'android', $tA, $dA);
        $regDevice($devUser, 'android', $tA, $dA); // the same token registered again (the app does this on every start)
        $n14 = $mkNotif('14a');
        $fcmSent = [];
        $runJob($n14->id);
        $check('NTF-14 the same token registered twice: one row, one active device, one request',
            DB::table('device_tokens')->where('token', $tA)->count() === 1 && $deviceRepo->activeForUser($devUser, ['android', 'ios'])->count() === 1 && count($fcmSent) === 1,
            'rows='.DB::table('device_tokens')->where('token', $tA)->count().', requests='.count($fcmSent));

        $clearDev();
        $tA2 = "ntf-test-and-A2-$tag";
        $regDevice($devUser, 'android', $tA, $dA);
        $regDevice($devUser, 'android', $tA2, $dA); // Firebase refreshed the token of the SAME phone
        $n14 = $mkNotif('14b');
        $fcmSent = [];
        $runJob($n14->id);
        $check('NTF-14 a refreshed token for the same phone retires the old one: only the new token is sent to',
            optional($tokRow($tA))->revoked_at !== null && optional($tokRow($tA2))->revoked_at === null && $sentTokens() === [$tA2],
            'old revoked='.json_encode(optional($tokRow($tA))->revoked_at !== null).', requests to='.json_encode($sentTokens()));

        $clearDev();
        $regDevice($devUser, 'android', $tA, '9774d56d682e549c'); // the emulator constant that identifies nothing
        $regDevice($devUser, 'android', $tC, '9774d56d682e549c');
        $n14 = $mkNotif('14c');
        $fcmSent = [];
        $runJob($n14->id);
        $check('NTF-14 the known-bad Android device id is ignored: two tokens stay active and both are sent to', $sentTokens() === collect([$tA, $tC])->sort()->values()->all(), 'requests to='.json_encode($sentTokens()));

        if ($booker2) {
            $clearDev();
            $tM = "ntf-test-and-M-$tag";
            $regDevice($devUser, 'android', $tM, $dA);
            $regDevice((int) $booker2->id, 'android', $tM, $dA); // a shared phone: another user logs in with the same token
            $n14 = $mkNotif('14d');
            $fcmSent = [];
            $runJob($n14->id);
            $check('NTF-14 the same token registered by another user moves to that user: the first user is no longer pushed to',
                (int) optional($tokRow($tM))->user_id === (int) $booker2->id && count($fcmSent) === 0, 'token now belongs to user '.optional($tokRow($tM))->user_id.', requests='.count($fcmSent));
        } else {
            $report('SKIP', 'NTF-14 shared-phone variant (no second user found; set $booker2Id)');
        }

        // ---- NTF-15 invalid tokens are revoked ----
        $clearDev();
        $tG = "ntf-test-and-good-$tag";
        $tX1 = "ntf-test-and-bad-unreg-$tag";
        $tX2 = "ntf-test-ios-bad-arg-$tag";
        $tX3 = "ntf-test-and-bad-sender-$tag";
        $regDevice($devUser, 'android', $tG, "ntf-test-dev-G-$tag");
        $regDevice($devUser, 'android', $tX1, "ntf-test-dev-X1-$tag");
        $regDevice($devUser, 'ios', $tX2, "ntf-test-dev-X2-$tag");
        $regDevice($devUser, 'android', $tX3, "ntf-test-dev-X3-$tag");
        $fcmPlan = [$tX1 => ['unregistered'], $tX2 => ['badtoken'], $tX3 => ['sender']];
        $n15 = $mkNotif('15a');
        $fcmSent = [];
        $ex = $runJob($n15->id);
        $check('NTF-15 three kinds of invalid token (UNREGISTERED, bad registration token, sender mismatch) are revoked; the good token is not',
            $ex === null && optional($tokRow($tX1))->revoked_at !== null && optional($tokRow($tX2))->revoked_at !== null && optional($tokRow($tX3))->revoked_at !== null && optional($tokRow($tG))->revoked_at === null,
            'revoked='.json_encode([optional($tokRow($tX1))->revoked_at !== null, optional($tokRow($tX2))->revoked_at !== null, optional($tokRow($tX3))->revoked_at !== null]).', good revoked='.json_encode(optional($tokRow($tG))->revoked_at !== null).', error='.$noErr($ex));
        $n15b = $mkNotif('15b');
        $fcmSent = [];
        $runJob($n15b->id);
        $check('NTF-15 the next notification is sent to the good device only', $sentTokens() === [$tG], 'requests to='.json_encode($sentTokens()));
        $check('NTF-15 the inbox row of the first notification is kept', DB::table('user_notifications')->where('id', $n15->id)->exists());

        // ---- NTF-16 temporary failure retries ----
        $clearDev();
        $regDevice($devUser, 'android', $tA, $dA);
        $job16 = new SendPushNotificationJob(0);
        $fcmPlan = [$tA => ['unavailable', 'ok']];
        $n16 = $mkNotif('16');
        $fcmSent = [];
        $ex1 = $runJob($n16->id);
        $check('NTF-16 a 503 makes the job throw (so the queue retries it) and the token is NOT revoked', $ex1 instanceof RuntimeException && stripos($ex1->getMessage(), 'temporary') !== false && optional($tokRow($tA))->revoked_at === null,
            'error='.$noErr($ex1));
        $ex2 = $runJob($n16->id); // what the queue does after the backoff
        $check('NTF-16 the retry succeeds: no error, the device was asked twice in total', $ex2 === null && count($fcmSent) === 2 && $fcmSent[1]['kind'] === 'ok', 'requests='.count($fcmSent).', error='.$noErr($ex2));
        $fcmPlan = [$tA => ['ratelimit']];
        $ex3 = $runJob($mkNotif('16b')->id);
        $check('NTF-16 a 429 (rate limit) is also retried', $ex3 instanceof RuntimeException, 'error='.$noErr($ex3));
        $check('NTF-16 the job allows '.$job16->tries.' tries with backoff '.json_encode($job16->backoff()).' seconds', $job16->tries === 3 && $job16->backoff() === [60, 300]);

        // ---- NTF-17 permanent failure ----
        $fcmPlan = [$tA => ['denied']];
        $n17 = $mkNotif('17');
        $fcmSent = [];
        $mark = $logMark();
        $ex = $runJob($n17->id);
        $log = $logSince($mark);
        $check('NTF-17 a permanent failure (400, not a token problem) does not retry and does not revoke the token',
            $ex === null && count($fcmSent) === 1 && optional($tokRow($tA))->revoked_at === null, 'requests='.count($fcmSent).', error='.$noErr($ex));
        $check('NTF-17 the inbox row is kept', DB::table('user_notifications')->where('id', $n17->id)->exists());
        if ($log !== '') {
            $check('NTF-17 the failure is logged (fcm.failed)', str_contains($log, 'fcm.failed'));
        } else {
            $report('INFO', 'NTF-17 the log file could not be read from here, so "fcm.failed" was not checked');
        }

        // ---- NTF-18 partial success, then retry ----
        $clearDev();
        $regDevice($devUser, 'android', $tA, $dA);
        $regDevice($devUser, 'ios', $tB, $dB);
        $fcmPlan = [$tA => ['ok'], $tB => ['unavailable', 'ok']];
        $n18 = $mkNotif('18');
        $fcmSent = [];
        $ex1 = $runJob($n18->id);  // device A is delivered, device B fails temporarily
        $ex2 = $runJob($n18->id);  // the retry
        $toA = collect($fcmSent)->where('token', $tA)->values();
        $check('NTF-18 first run: device A delivered, device B failed so the whole job is retried', $ex1 instanceof RuntimeException && $ex2 === null, 'first='.$noErr($ex1).', retry='.$noErr($ex2));
        $check('NTF-18 the retry skips the device that already succeeded: device A is sent to exactly once, device B twice',
            $toA->count() === 1 && collect($fcmSent)->where('token', $tB)->count() === 2,
            'A='.$toA->count().', B='.collect($fcmSent)->where('token', $tB)->count());
        $check('NTF-18 that second message cannot show twice: both carry the same replace-tag and APNs collapse id (the notification uuid)',
            $toA->count() < 2 || ($toA->pluck('tag')->unique()->count() === 1 && $toA->pluck('collapse')->unique()->count() === 1 && $toA->first()['tag'] === $n18->uuid && $toA->first()['collapse'] === $n18->uuid),
            'tags='.json_encode($toA->pluck('tag')->unique()->values()->all()).', collapse ids='.json_encode($toA->pluck('collapse')->unique()->values()->all()));

        // ---- NTF-19 preference changed after the push was queued ----
        $clearDev();
        $regDevice($devUser, 'android', $tA, $dA);
        $o = NotificationPreference::where('user_id', $devUser)->where('category', $cat)->first();
        $orig = $o ? (bool) $o->push_enabled : null;
        $prefRepo->set($devUser, $cat, true);
        $pushBefore = $pushCount();
        $n19 = $mkNotif('19');
        $queued = $pushCount() - $pushBefore;
        $prefRepo->set($devUser, $cat, false); // the user switches push off AFTER it was queued
        $fcmPlan = [];
        $fcmSent = [];
        $runJob($n19->id);
        if ($orig === null) {
            NotificationPreference::where('user_id', $devUser)->where('category', $cat)->delete();
        } else {
            $prefRepo->set($devUser, $cat, $orig);
        }
        $check('NTF-19 setup: with push ON the push job is queued', $queued === 1, 'push jobs='.$queued);
        $check('NTF-19 push switched OFF after queueing: the job re-checks and sends nothing', count($fcmSent) === 0, 'requests='.count($fcmSent));

        // control: a mandatory type is still delivered when its category is switched OFF after queueing
        $catC = $catOf('booking.cancelled');
        $oc = NotificationPreference::where('user_id', $devUser)->where('category', $catC)->first();
        $origC = $oc ? (bool) $oc->push_enabled : null;
        $nC = $svc->notify($devUser, 'booking.cancelled', 'owner_cancelled', "ntf-test:$tag:19c", $p0, ['event_schedule_id' => $b0->id, 'audience' => 'owner']);
        $prefRepo->set($devUser, $catC, false);
        $fcmSent = [];
        $runJob($nC->id);
        if ($origC === null) {
            NotificationPreference::where('user_id', $devUser)->where('category', $catC)->delete();
        } else {
            $prefRepo->set($devUser, $catC, $origC);
        }
        $check('NTF-19 control: a mandatory type is still sent when its category is switched OFF after queueing', count($fcmSent) === 1, 'requests='.count($fcmSent));

        $clearDev();
    }

    $report('INFO', 'Not exercised: the inbox/device controllers and routes over HTTP, and real parallel requests for NTF-14/NTF-20');

    // ============ real-push demo for user 83 (only with $ntfKeep = true) ============
    $ntfCleanup(); // always remove the data from the checks above
    $prefRestore();

    if ($ntfKeep) {
        $ntfDemoStarted = true;
        $restoreBus(); // real dispatcher, so the push job goes to your database queue

        if (app(Dispatcher::class) instanceof \Illuminate\Support\Testing\Fakes\BusFake) {
            echo PHP_EOL.'FAIL  the real dispatcher could not be restored, so no real pushes can be sent. Start a new tinker session and run again.'.PHP_EOL;
        } else {
            $demoStart = (int) DB::table('user_notifications')->max('id');
            $dEv = $mkEvent($owner, 'D1');
            $dB = $book($dEv, $bookerPhone, $S1);

            if ($dB) {
                $dCtx = ['event_schedule_id' => $dB->id, 'audience' => 'booker'];
                $dP = $paramsFor($dEv);
                $dp1 = $svc->notify($bookerId, 'booking.changed', 'event_edited', "ntf-test:$tag:D1", $dP, $dCtx);
                $dp2 = $svc->notify($bookerId, 'booking.changed', 'event_edited', "ntf-test:$tag:D1", $dP, $dCtx); // same key: must NOT push again
                $dp3 = $svc->notify($bookerId, 'booking.created', 'booker', "ntf-test:$tag:D2", $dP, $dCtx);        // inbox only: no push
                $devices = $activeTokens($bookerId);
                $pushOn = $prefRepo->isPushEnabled($bookerId, $catOf('booking.changed'), true);

                echo PHP_EOL.'--- Real push demo ---'.PHP_EOL;
                echo 'Push setting of user '.$bookerId.' for "'.$catOf('booking.changed').'": '.($pushOn ? 'on' : 'OFF (no push will be sent)').PHP_EOL;
                echo '  notify #1 booking.changed.event_edited  -> row '.optional($dp1)->id.'  (inbox + push)'.PHP_EOL;
                echo '  notify #2 same dedupe key               -> '.($dp2 === null ? 'nothing (duplicate, correct)' : 'row '.$dp2->id.'  <- UNEXPECTED').PHP_EOL;
                echo '  notify #3 booking.created.booker        -> row '.optional($dp3)->id.'  (inbox only, no push)'.PHP_EOL;
                echo PHP_EOL.'EXPECT within about a minute: 1 notification ("'.optional($dp1)->title.'") on each of '.$devices.' device(s) of user '.$bookerId.' = '.$devices.' alerts in total. Nothing for notify #2 and #3.'.PHP_EOL;
                echo 'Check the log (after the wait):  Select-String -Path storage\logs\laravel.log -Pattern "fcm.response" | Select-Object -Last 4'.PHP_EOL;
                echo 'Do NOT run $ntfCleanup() before the push arrived. Then run:  $ntfCleanup();'.PHP_EOL;
            } else {
                $report('INVESTIGATE', 'demo could not create its booking', (string) $lastError);
            }
        }
    }
} catch (Throwable $e) {
    echo PHP_EOL.'STOPPED  '.$e->getMessage().PHP_EOL;
} finally {
    Carbon::setTestNow();
    Auth::forgetUser();
    $prefRestore();
    DB::table('device_tokens')->where('token', 'like', 'ntf-test-%')->delete();
    $fcmReset();
    $restoreBus();
    app()->forgetInstance('mail.manager');
    Facade::clearResolvedInstance('mail.manager');

    if (! $ntfDemoStarted && ($bookingIds || $eventIds)) {
        $ntfCleanup();
    }
}