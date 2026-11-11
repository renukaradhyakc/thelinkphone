<?php

/*
 * PS-24: notification retention. Inserts three inbox rows directly (no push is sent),
 * runs the real `notifications:prune` command, and checks which rows survive.
 *
 *   php artisan tinker
 *   >>> include 'phone-schedule-expiry-test.php';
 *
 * Optional, before the include:  $userId = 83;
 *
 * Note: notifications:prune is the normal maintenance job, so it also removes any other
 * expired inbox rows, finished outbox rows older than 30 days and revoked device tokens
 * older than 30 days. That is what it does in production too.
 */

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$userId = (int) ($userId ?? 83);
$tag = 'ps24-test:'.uniqid();
$days = (int) (config('notifications.types')['phone_schedule.changed']['inbox_days'] ?? 90);

$check = function ($name, $ok, $detail = '') {
    echo ($ok ? 'PASS  ' : 'FAIL  ').$name.($detail !== '' ? '  ('.$detail.')' : '').PHP_EOL;
};

$make = function (string $label, $createdAt, $expiresAt) use ($userId, $tag) {
    return DB::table('user_notifications')->insertGetId([
        'uuid' => (string) Str::uuid(),
        'user_id' => $userId,
        'type' => 'phone_schedule.changed',
        'reason' => 'modified',
        'category' => 'phone_schedules',
        'title' => 'PS-24 test',
        'body' => 'PS-24 '.$label,
        'data' => json_encode(['audience' => 'contact']),
        'deep_link' => null,
        'dedupe_key' => $tag.':'.$label,
        'read_at' => null,
        'expires_at' => $expiresAt,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
};

$expiredId = $make('expired', now()->subDays($days + 1), now()->subDay());
$agedId = $make('aged', now()->subDays($days + 1), now()->addDays(10));
$freshId = $make('fresh', now(), now()->addDays($days));

$exists = fn ($id) => DB::table('user_notifications')->where('id', $id)->exists();

try {
    $check('setup: three test rows exist', $exists($expiredId) && $exists($agedId) && $exists($freshId));

    Artisan::call('notifications:prune');
    echo trim(Artisan::output()).PHP_EOL;

    $check('PS-24 a row past its expires_at is deleted', ! $exists($expiredId));
    $check("PS-24 a row older than $days days is deleted even if expires_at is still ahead", ! $exists($agedId));
    $check('PS-24 a fresh row is kept', $exists($freshId));
} finally {
    DB::table('user_notifications')->where('dedupe_key', 'like', $tag.':%')->delete();
    echo PHP_EOL.'Removed the test rows.'.PHP_EOL;
}
