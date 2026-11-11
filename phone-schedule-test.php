<?php

/*
 * Phone schedule notification checks.
 *   PS-03, PS-07, PS-08, PS-09, PS-10, PS-13, PS-14, PS-15, PS-16, PS-17, PS-23, PS-24
 *   plus a few extra edge cases (not a user, self assignment, no assignments, privacy).
 *
 * LOCAL DATABASE ONLY. It creates throwaway reusable schedules for the owner, assigns
 * them to the contact's number, edits their hours, and checks the inbox and outbox rows.
 * Everything it creates is deleted at the end (even if a test crashes).
 *
 * Run from the Laravel project root, in a fresh tinker session:
 *     php artisan tinker
 *     >>> include 'phone-schedule-test.php';
 *
 * Optional settings, set before the include:
 *     $ownerId = 83;      // the user who assigns the schedules (default 83)
 *     $contactId = 101;   // the user who receives the notifications (default 101)
 *     $psKeep = true;     // leave the rows in place so the tick worker can send real pushes,
 *                         // then run  $psCleanup();  when you are done
 *
 * "assigned" and "modified" notifications are pushed to the contact's real devices.
 * Stop the notifications:tick loop first if you do not want pushes sent.
 *
 * The outbox rows are processed here with the same steps as notifications:dispatch-due
 * (claim, sendHoursChange, markSent/markCancelled), but only for the rows this script made,
 * so nothing else in the outbox is touched.
 */

use App\Models\PhoneSchedule;
use App\Models\Schedule;
use App\Models\User;
use App\Models\UserSchedule;
use App\Modules\Notifications\Models\NotificationOutbox;
use App\Modules\Notifications\Repositories\OutboxRepository;
use App\Modules\Notifications\Services\PhoneScheduleNotifier;
use App\Modules\Notifications\Services\RecipientResolver;
use App\Repositories\PhoneScheduleRepository;
use App\Services\PhoneNumberNormalizer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$ownerId = (int) ($ownerId ?? 83);
$contactId = (int) ($contactId ?? 101);
$psKeep = $psKeep ?? false;

$check = function ($name, $ok, $detail = '') {
    echo ($ok ? 'PASS  ' : 'FAIL  ').$name.($detail !== '' ? '  ('.$detail.')' : '').PHP_EOL;
};

// ---- Preconditions: stop with a clear message instead of touching anything -----------------
$owner = User::find($ownerId);
$contact = User::find($contactId);
$norm = app(PhoneNumberNormalizer::class);
$resolver = app(RecipientResolver::class);

if (! $owner || ! $contact || $ownerId === $contactId) {
    echo 'SKIP  owner and contact must be two different existing users'.PHP_EOL;

    return;
}

$contactPhone = $norm->normalize((string) $contact->phone_number);
$ownerPhone = $norm->normalize((string) $owner->phone_number);
$nonUserPhone = '9000000001';

if (strlen($contactPhone) !== 10 || optional($resolver->uniqueUserByPhone($contactPhone))->id !== $contactId) {
    echo "SKIP  user $contactId needs a 10-digit phone number that belongs only to that user".PHP_EOL;

    return;
}

$testPhones = array_values(array_filter([
    $contactPhone,
    $nonUserPhone,
    strlen($ownerPhone) === 10 ? $ownerPhone : null,
]));

if (PhoneSchedule::where('user_id', $ownerId)->whereIn('phone_number_normalized', $testPhones)->exists()) {
    echo "SKIP  user $ownerId already has an assignment for one of the test numbers. Remove it first so nothing real is changed".PHP_EOL;

    return;
}

Auth::setUser($owner);

if ((int) getLogInUserId() !== $ownerId) {
    echo 'SKIP  getLogInUserId() does not follow Auth::setUser(), so the repository cannot act as the owner'.PHP_EOL;
    Auth::forgetUser();

    return;
}

// ---- Helpers -------------------------------------------------------------------------------
$repo = app(PhoneScheduleRepository::class);
$notifier = app(PhoneScheduleNotifier::class);
$outboxRepo = app(OutboxRepository::class);
$quiet = max(1, (int) config('notifications.rules.event_change_quiet_minutes', 3));

$startId = (int) DB::table('user_notifications')->max('id');
$outboxStartId = (int) NotificationOutbox::max('id');
$scheduleIds = [];

$rows = fn () => DB::table('user_notifications')
    ->where('user_id', $contactId)->where('type', 'phone_schedule.changed')
    ->where('id', '>', $startId)->orderBy('id')->get();
$hoursRows = fn () => $rows()->filter(fn ($r) => str_starts_with($r->dedupe_key, 'phone_schedule.hours:'))->values();
$reasons = fn () => $rows()->pluck('reason')->implode(',') ?: 'none';
$allNew = fn () => DB::table('user_notifications')
    ->where('id', '>', $startId)->where('type', 'phone_schedule.changed')->count();

$mapping = fn (?string $phone = null) => PhoneSchedule::where('user_id', $ownerId)
    ->where('phone_number_normalized', $phone ?? $contactPhone)->first();
$hoursKey = fn () => 'phone_schedule.hours:'.$mapping()->id.':'.$contactId;
$outboxRow = fn (string $key) => NotificationOutbox::where('dedupe_key', $key)->first();

$assignExisting = fn (int $scheduleId) => $repo->assignExistingSchedule($contactPhone, $scheduleId);
$assignCustom = fn (array $slots) => $repo->assignCustomSchedule($contactPhone, $slots);
$remove = fn () => $repo->removeSchedule($contactPhone);

$days = array_slice(array_keys(UserSchedule::WEEKDAY), 0, 2);
$mkSlots = fn (string $from, string $to) => array_map(
    fn ($d) => ['day_of_week' => $d, 'from_time' => $from, 'to_time' => $to],
    $days
);

$mkSchedule = function (string $name, array $slots) use ($ownerId, &$scheduleIds) {
    $schedule = Schedule::create([
        'user_id' => $ownerId, 'schedule_name' => $name, 'status' => 0, 'is_default' => 0, 'is_custom' => 1,
    ]);
    $scheduleIds[] = $schedule->id;

    foreach ($slots as $slot) {
        UserSchedule::create([
            'schedule_id' => $schedule->id, 'user_id' => $ownerId,
            'from_time' => $slot['from_time'], 'to_time' => $slot['to_time'], 'day_of_week' => $slot['day_of_week'],
            'check_tab' => 0, 'check_default' => 0, 'event_id' => null,
        ]);
    }

    return $schedule;
};

// Same steps as ScheduleController::addScheduleTimeSlot (web "save hours" on a reusable schedule).
$editHours = function (int $scheduleId, array $slots) use ($notifier, $ownerId) {
    $before = $notifier->hoursSignature($scheduleId);
    UserSchedule::where('schedule_id', $scheduleId)->delete();

    foreach ($slots as $slot) {
        UserSchedule::create([
            'schedule_id' => $scheduleId, 'user_id' => $ownerId,
            'from_time' => $slot['from_time'], 'to_time' => $slot['to_time'], 'day_of_week' => $slot['day_of_week'],
            'check_tab' => 0, 'check_default' => 0, 'event_id' => null,
        ]);
    }

    $notifier->scheduleHoursSaved($ownerId, $scheduleId, $before);
};

// Same steps as notifications:dispatch-due, for one outbox row.
$dispatch = function (string $key) use ($notifier, $outboxRepo, $outboxRow) {
    $row = $outboxRow($key);

    if ($row === null) {
        return 'no-row';
    }

    $row->update(['status' => NotificationOutbox::CLAIMED, 'claimed_at' => now(), 'attempts' => $row->attempts + 1]);
    $result = $notifier->sendHoursChange($row->fresh());
    $result === 'sent' ? $outboxRepo->markSent($row) : $outboxRepo->markCancelled($row);

    return $result;
};

$psCleanup = function () use (&$scheduleIds, $testPhones, $ownerId, $contactId, $startId, $outboxStartId) {
    NotificationOutbox::where('id', '>', $outboxStartId)
        ->where('type', 'phone_schedule.changed')->where('user_id', $contactId)->delete();
    DB::table('user_notifications')->where('id', '>', $startId)->where('type', 'phone_schedule.changed')
        ->whereIn('user_id', [$contactId, $ownerId])->delete();

    $ids = PhoneSchedule::where('user_id', $ownerId)->whereIn('phone_number_normalized', $testPhones)->pluck('id');
    UserSchedule::whereIn('phone_schedule_id', $ids)->delete();
    PhoneSchedule::whereIn('id', $ids)->delete();

    UserSchedule::whereIn('schedule_id', $scheduleIds)->delete();
    Schedule::whereIn('id', $scheduleIds)->delete();
};

echo "Owner $ownerId assigns to contact $contactId (number ending ".substr($contactPhone, -4).'), quiet window '.$quiet.' min'.PHP_EOL.PHP_EOL;

try {
    $tag = substr(uniqid(), -6);
    $A = $mkSchedule("PS-TEST A $tag", $mkSlots('09:00 AM', '05:00 PM'));
    $B = $mkSchedule("PS-TEST B $tag", $mkSlots('10:00 AM', '06:00 PM'));
    $C = $mkSchedule("PS-TEST C $tag", $mkSlots('08:00 AM', '04:00 PM'));
    $D = $mkSchedule("PS-TEST D $tag", $mkSlots('07:00 AM', '03:00 PM')); // never assigned to anyone
    $custom = $mkSlots('11:00 AM', '03:00 PM');

    // ============ Assignment changes (sent immediately) ============
    $assignExisting($A->id);
    $check('PS-01 baseline: the first assignment sends one "assigned"',
        $rows()->count() === 1 && $rows()->first()->reason === 'assigned', 'reasons='.$reasons());

    // PS-03: same assignment again
    $assignExisting($A->id);
    $check('PS-03 assigning the same saved schedule again sends nothing new', $rows()->count() === 1, 'reasons='.$reasons());

    // PS-07: saved -> another saved
    $assignExisting($B->id);
    $m = $mapping();
    $check('PS-07 saved schedule replaced by another saved schedule',
        $m && (int) $m->schedule_id === $B->id && PhoneSchedule::where('user_id', $ownerId)->where('phone_number_normalized', $contactPhone)->count() === 1,
        'schedule_id='.($m->schedule_id ?? 'none'));
    $check('PS-07 recipient gets one "modified" naming the new schedule',
        $rows()->count() === 2 && $rows()->last()->reason === 'modified' && str_contains($rows()->last()->body, $B->schedule_name),
        'reasons='.$reasons());

    // PS-08: saved -> custom
    $assignCustom($custom);
    $m = $mapping();
    $check('PS-08 saved schedule replaced by custom hours',
        $m && $m->schedule_id === null && UserSchedule::where('phone_schedule_id', $m->id)->count() === count($custom),
        'custom rows='.($m ? UserSchedule::where('phone_schedule_id', $m->id)->count() : 'n/a'));
    $check('PS-08 recipient gets "modified" saying custom hours',
        $rows()->count() === 3 && $rows()->last()->reason === 'modified' && str_contains($rows()->last()->body, 'custom hours'),
        'reasons='.$reasons());

    // PS-03 for custom hours, including the same hours in a different order
    $assignCustom($custom);
    $assignCustom(array_reverse($custom));
    $check('PS-03 same custom hours again (even re-ordered) sends nothing new', $rows()->count() === 3, 'reasons='.$reasons());
    $check('PS-03 same custom hours do not pile up duplicate rows',
        UserSchedule::where('phone_schedule_id', $mapping()->id)->count() === count($custom));

    // PS-09: custom -> saved
    $assignExisting($A->id);
    $m = $mapping();
    $check('PS-09 custom hours replaced by a saved schedule, and the custom rows are gone',
        $m && (int) $m->schedule_id === $A->id && UserSchedule::where('phone_schedule_id', $m->id)->count() === 0
            && PhoneSchedule::where('user_id', $ownerId)->where('phone_number_normalized', $contactPhone)->count() === 1);
    $check('PS-09 recipient gets "modified"',
        $rows()->count() === 4 && $rows()->last()->reason === 'modified' && str_contains($rows()->last()->body, $A->schedule_name),
        'reasons='.$reasons());

    // PS-06 baseline and PS-10: delete, then delete something that is not there
    $removed = $remove();
    $check('PS-06 baseline: removing the assignment returns true and sends "revoked"',
        $removed === true && $mapping() === null && $rows()->count() === 5 && $rows()->last()->reason === 'revoked',
        'reasons='.$reasons());

    $removedAgain = $remove();
    $removedOther = $repo->removeSchedule('9111111111');
    $check('PS-10 removing an assignment that does not exist changes nothing',
        $removedAgain === false && $removedOther === false && $rows()->count() === 5, 'reasons='.$reasons());

    // PS-23: assign again right after the revoke, same minute as the first assignment
    $assignExisting($A->id);
    $assigned = $rows()->where('reason', 'assigned')->count();
    echo ($assigned >= 2 ? 'OK         ' : 'KNOWN GAP  ')
        .'PS-23 re-assigning right after a revoke is announced (assigned rows='.$assigned
        .'; within the same clock minute the dedupe key matches the first "assigned" and the second is dropped)'.PHP_EOL;

    // ============ Reusable schedule hours (delayed, via the outbox) ============
    $key = $hoursKey();
    $sig0 = $notifier->hoursSignature($A->id);

    // PS-14: unchanged hours
    $editHours($A->id, $mkSlots('09:00 AM', '05:00 PM'));
    $check('PS-14 saving unchanged hours queues nothing', $outboxRow($key) === null && $hoursRows()->count() === 0);

    // PS-13: three quick edits become one notice
    $editHours($A->id, $mkSlots('09:30 AM', '05:00 PM'));
    $first = $outboxRow($key);
    $check('PS-13 first edit queues one pending notice about '.$quiet.' minutes ahead',
        $first && $first->status === NotificationOutbox::PENDING
            && abs($first->notify_at->diffInSeconds(now()->addMinutes($quiet), false)) < 60,
        'status='.($first->status ?? 'none'));
    $t1 = $first->notify_at->copy();

    $editHours($A->id, $mkSlots('10:00 AM', '05:00 PM'));
    $editHours($A->id, $mkSlots('10:30 AM', '05:00 PM'));
    $row = $outboxRow($key);
    $check('PS-13 three rapid edits still leave one pending outbox row',
        NotificationOutbox::where('dedupe_key', $key)->count() === 1 && $row->status === NotificationOutbox::PENDING);
    $check('PS-13 the "before" fingerprint is the hours from before the first edit', ($row->context['before'] ?? null) === $sig0);
    $check('PS-13 each edit pushed the send time later (or kept it)', $row->notify_at->greaterThanOrEqualTo($t1));
    $check('PS-13 nothing is sent while the row is pending', $hoursRows()->count() === 0);

    $result = $dispatch($key);
    $check('PS-13 when due, exactly one "modified" is sent and the row is marked sent',
        $result === 'sent' && $hoursRows()->count() === 1 && $outboxRow($key)->status === NotificationOutbox::SENT,
        "result=$result, hours rows=".$hoursRows()->count());
    $check('PS-13 the notice names the schedule',
        $hoursRows()->count() === 1 && str_contains($hoursRows()->first()->body, $A->schedule_name));

    // PS-13 extra: change then change back -> nothing to tell
    sleep(1); // so the new send time is not identical to the one just used
    $editHours($A->id, $mkSlots('11:00 AM', '05:00 PM'));
    $reused = $outboxRow($key);
    $check('PS-13 an edit after a sent notice revives the same row as pending',
        $reused->status === NotificationOutbox::PENDING && NotificationOutbox::where('dedupe_key', $key)->count() === 1);
    $editHours($A->id, $mkSlots('10:30 AM', '05:00 PM')); // back to what it was
    $before = $hoursRows()->count();
    $result = $dispatch($key);
    $check('PS-13 changing hours and changing them back sends nothing',
        $result === 'cancelled' && $hoursRows()->count() === $before, "result=$result");

    // PS-15: assignment removed before the delayed notice is due
    $editHours($A->id, $mkSlots('12:00 PM', '05:00 PM'));
    $check('PS-15 setup: a notice is pending', optional($outboxRow($key))->status === NotificationOutbox::PENDING);
    $remove();
    $before = $hoursRows()->count();
    $result = $dispatch($key);
    $check('PS-15 assignment removed before dispatch: the pending notice is cancelled',
        $result === 'cancelled' && $hoursRows()->count() === $before && $outboxRow($key)->status === NotificationOutbox::CANCELLED,
        "result=$result");

    // PS-16: assignment replaced before the delayed notice is due
    $assignExisting($A->id);
    $key = $hoursKey();
    $editHours($A->id, $mkSlots('01:00 PM', '05:00 PM'));
    $check('PS-16 setup: a notice is pending', optional($outboxRow($key))->status === NotificationOutbox::PENDING);
    $assignExisting($C->id);
    $before = $hoursRows()->count();
    $result = $dispatch($key);
    $check('PS-16 assignment replaced before dispatch: the old hours notice is cancelled',
        $result === 'cancelled' && $hoursRows()->count() === $before && $outboxRow($key)->status === NotificationOutbox::CANCELLED,
        "result=$result");
    $check('PS-16 the replacement itself was announced as "modified"',
        $rows()->last()->reason === 'modified' && str_contains($rows()->last()->body, $C->schedule_name),
        'last='.$rows()->last()->reason);

    // PS-17: retries
    $key = $hoursKey();
    $editHours($C->id, $mkSlots('08:30 AM', '04:00 PM'));
    $result = $dispatch($key);
    $afterSend = $hoursRows()->count();
    $retry = $notifier->sendHoursChange($outboxRow($key)->fresh()); // a retry after a crash before markSent
    $check('PS-17 sending the same row twice does not create a second inbox notification',
        $result === 'sent' && $retry === 'sent' && $hoursRows()->count() === $afterSend, "retry=$retry");

    $retryRow = $outboxRow($key);
    NotificationOutbox::where('id', $retryRow->id)->update(['status' => NotificationOutbox::CLAIMED, 'claimed_at' => now()->subMinutes(6), 'attempts' => 1]);
    $outboxRepo->releaseStaleClaims();
    $check('PS-17 a claim stuck for 6 minutes (1 attempt) goes back to pending', $outboxRow($key)->status === NotificationOutbox::PENDING);

    NotificationOutbox::where('id', $retryRow->id)->update(['status' => NotificationOutbox::CLAIMED, 'claimed_at' => now()->subMinutes(6), 'attempts' => 3]);
    $outboxRepo->releaseStaleClaims();
    $check('PS-17 after 3 attempts it is marked failed instead of retried forever', $outboxRow($key)->status === NotificationOutbox::FAILED);
    $outboxRepo->markCancelled($outboxRow($key));

    // ============ Extra edge cases ============
    $before = $allNew();
    $repo->assignExistingSchedule($nonUserPhone, $A->id);
    $check('EDGE assigning to a number that is not a CallALink user saves it and notifies nobody',
        $mapping($nonUserPhone) !== null && $allNew() === $before);
    $repo->removeSchedule($nonUserPhone);

    if (strlen($ownerPhone) === 10) {
        $before = $allNew();
        $repo->assignExistingSchedule($ownerPhone, $A->id);
        $check('EDGE assigning to your own number notifies nobody', $allNew() === $before);
        $repo->removeSchedule($ownerPhone);
    } else {
        echo 'SKIP  EDGE self assignment: the owner has no 10-digit phone number'.PHP_EOL;
    }

    $outboxBefore = NotificationOutbox::where('id', '>', $outboxStartId)->count();
    $editHours($D->id, $mkSlots('06:00 AM', '02:00 PM'));
    $check('EDGE editing hours of a schedule nobody uses queues nothing',
        NotificationOutbox::where('id', '>', $outboxStartId)->count() === $outboxBefore);

    // PS-24: retention
    $inboxDays = (int) (config('notifications.types')['phone_schedule.changed']['inbox_days'] ?? 90);
    $sample = $rows()->first();
    $days = $sample && $sample->expires_at
        ? Carbon::parse($sample->created_at)->diffInDays(Carbon::parse($sample->expires_at))
        : null;
    $check("PS-24 inbox rows are given an expiry about $inboxDays days ahead",
        $days !== null && abs($days - $inboxDays) <= 1, 'days='.($days ?? 'none'));

    // Privacy rule from config/notifications.php: no phone number in text, data or link
    $leaks = $rows()->filter(function ($r) use ($contactPhone, $ownerPhone) {
        $blob = $r->title.' '.$r->body.' '.$r->data.' '.$r->deep_link;

        return str_contains($blob, $contactPhone) || (strlen($ownerPhone) === 10 && str_contains($blob, $ownerPhone));
    })->count();
    $check('EDGE no phone number appears in any notification text, data or link', $leaks === 0, "rows with a number=$leaks");
    $badLinks = $rows()->filter(fn ($r) => ! str_contains((string) $r->deep_link, 'scheduled-events'))->count();
    $check('PS-25 every notification links to the scheduled events page', $badLinks === 0, "bad links=$badLinks");
} finally {
    if ($psKeep) {
        echo PHP_EOL.'KEPT the test rows. Wait for the next notifications:tick run, check the contact\'s phones, then run:  $psCleanup();'.PHP_EOL;
    } else {
        $psCleanup();
        echo PHP_EOL.'Deleted the throwaway schedules, assignments, outbox rows and notifications.'.PHP_EOL;
    }

    Auth::forgetUser();
}
