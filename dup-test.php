<?php

/*
 * Quick checks for RevenueCatEventHandler (DUP-01 .. DUP-08).
 * LOCAL DATABASE ONLY. It temporarily changes user 83's user_subscriptions row
 * and restores it at the end (even if a test crashes).
 *
 * Run from the Laravel project root:
 *     php artisan tinker
 *     >>> include 'dup-test.php';
 */

use App\Models\RevenueCatEvent;
use App\Services\RevenueCatEventHandler;
use Illuminate\Support\Facades\DB;

$handler = app(RevenueCatEventHandler::class);
$userId = 83;
$email = (string) DB::table('users')->where('id', $userId)->value('email');
$entitle = (string) config('services.revenuecat.entitlement');
$backup = DB::table('user_subscriptions')->where('user_id', $userId)->first();

// every generated event is newer than the last one, and newer than what is already stored
$clock = max((int) (microtime(true) * 1000), (int) ($backup->last_event_ms ?? 0)) + 10000;

$event = function (array $override = []) use ($email, $entitle, &$clock) {
    $clock += 1000;

    return array_merge([
        'id' => 'dup-'.uniqid(),
        'type' => 'RENEWAL',
        'app_user_id' => $email,
        'event_timestamp_ms' => $clock,
        'expiration_at_ms' => $clock + 3600000,
        'period_type' => 'NORMAL',
        'store' => 'APP_STORE',
        'product_id' => 'com.callalink.app.subscription.monthly',
        'entitlement_ids' => $entitle !== '' ? [$entitle] : [],
    ], $override);
};

$sub = fn () => DB::table('user_subscriptions')->where('user_id', $userId)->first();
$events = fn ($id) => RevenueCatEvent::where('event_id', $id)->count();
$notes = fn ($id) => DB::table('user_notifications')->where('dedupe_key', 'like', '%'.$id)->count();
$check = function ($name, $ok, $detail = '') {
    echo ($ok ? 'PASS  ' : 'FAIL  ').$name.($detail !== '' ? '  ('.$detail.')' : '').PHP_EOL;
};

echo "User $userId ($email), starting state: ".($backup ? $backup->store.' / '.$backup->status : 'no row').PHP_EOL.PHP_EOL;

try {
    // ---- DUP-01 / DUP-02: the same event id twice (the second hits the unique key, error 1062)
    $a = $event();
    $r1 = $handler->handle($a);
    $afterFirst = $sub();
    $r2 = $handler->handle($a);
    $afterSecond = $sub();

    $check('DUP-01/02 same event id is rejected the second time', $r1 === 'processed' && $r2 === 'duplicate', "first=$r1 second=$r2");
    $check('DUP-01 the duplicate had no side effects',
        $events($a['id']) === 1 && $notes($a['id']) <= 1 && $afterFirst->updated_at === $afterSecond->updated_at,
        'event rows='.$events($a['id']).' inbox rows='.$notes($a['id']));

    // ---- DUP-03: different event ids are both processed
    $b = $event();
    $c = $event();
    $rb = $handler->handle($b);
    $rc = $handler->handle($c);
    $check('DUP-03 two different event ids are both processed',
        $rb === 'processed' && $rc === 'processed' && $events($b['id']) === 1 && $events($c['id']) === 1,
        "b=$rb c=$rc inbox b=".$notes($b['id']).' c='.$notes($c['id']));

    // ---- DUP-05: the same expiration twice
    $e = $event(['type' => 'EXPIRATION']);
    $re1 = $handler->handle($e);
    $re2 = $handler->handle($e);
    $check('DUP-05 repeated expiration is rejected', $re1 === 'processed' && $re2 === 'duplicate' && $notes($e['id']) <= 1,
        "first=$re1 second=$re2 inbox=".$notes($e['id']));
    $check('DUP-05 status is now expired', $sub()->status === 'expired', 'status='.$sub()->status);

    // ---- DUP-04: ordering (the subscription is expired right now)
    $last = (int) $sub()->last_event_ms;

    $equal = $handler->handle($event(['event_timestamp_ms' => $last]));
    $check('DUP-04a equal timestamp must not reactivate an expired subscription', $equal === 'stale', "got $equal");

    $older = $handler->handle($event(['event_timestamp_ms' => $last - 5000]));
    $check('DUP-04b older event is ignored', $older === 'stale', "got $older");

    $newer = $handler->handle($event());
    $check('DUP-04c newer event reactivates the subscription', $newer === 'processed' && $sub()->status === 'active',
        "got $newer, status=".$sub()->status);

    // ---- DUP-04 known gap: one row for two stores
    $last = (int) $sub()->last_event_ms;
    $play = ['store' => 'PLAY_STORE', 'product_id' => 'callalink_premium:monthly'];

    $gap1 = $handler->handle($event($play + ['event_timestamp_ms' => $last - 500]));
    echo ($gap1 === 'stale' ? 'KNOWN GAP  ' : 'OK         ')
        .'DUP-04d an older event from the OTHER store is dropped as stale (got '.$gap1.')'.PHP_EOL;

    $gap2 = $handler->handle($event($play));
    echo ($sub()->store === 'PLAY_STORE' ? 'KNOWN GAP  ' : 'OK         ')
        .'DUP-04e the single row flips to whichever store sent the last event (store='.$sub()->store.', '.$gap2.')'.PHP_EOL;

    // ---- DUP-06: a failure must roll the event row back so a retry is processed
    $bad = $event(['product_id' => str_repeat('x', 300)]); // longer than the product_id column
    $threw = false;
    try {
        $handler->handle($bad);
    } catch (\Throwable $ex) {
        $threw = true;
    }

    if (! $threw) {
        echo 'SKIP  DUP-06 the database is not in strict mode, so the long value was cut instead of failing'.PHP_EOL;
    } else {
        $check('DUP-06a a failed event leaves no event row behind', $events($bad['id']) === 0, 'event rows='.$events($bad['id']));
        $retry = $handler->handle(array_merge($bad, ['product_id' => 'com.callalink.app.subscription.monthly']));
        $check('DUP-06b the retry of the same event id succeeds', $retry === 'processed', "got $retry");
    }

    // ---- DUP-08: which CallALink user an event belongs to
    $upper = $handler->handle($event(['app_user_id' => strtoupper($email)]));
    $check('DUP-08a email match ignores letter case', $upper === 'processed', "got $upper");

    $alias = $handler->handle($event(['app_user_id' => '$RCAnonymousID:'.uniqid(), 'aliases' => [$email]]));
    $check('DUP-08b user is found through an alias', $alias === 'processed', "got $alias");

    $unknown = $handler->handle($event(['app_user_id' => 'nobody-'.uniqid().'@example.com']));
    $check('DUP-08c unknown user is ignored', $unknown === 'ignored', "got $unknown");

    $devices = DB::table('device_tokens')->where('user_id', $userId)->whereNull('revoked_at')->pluck('platform');
    echo 'INFO  active devices for user '.$userId.': '.($devices->implode(', ') ?: 'none').PHP_EOL;
} finally {
    RevenueCatEvent::where('event_id', 'like', 'dup-%')->delete();
    DB::table('user_notifications')->where('dedupe_key', 'like', '%dup-%')->delete();

    if ($backup === null) {
        DB::table('user_subscriptions')->where('user_id', $userId)->delete();
    } else {
        $row = (array) $backup;
        unset($row['id']);
        DB::table('user_subscriptions')->where('user_id', $userId)->update($row);
    }

    echo PHP_EOL."Cleaned up the test events and restored user $userId's subscription row.".PHP_EOL;
}