<?php

/*
 * Quick checks for the loyalty notification flow
 * (LOY-03, LOY-09, LOY-10, LOY-11, LOY-13, LOY-19).
 * LOCAL DATABASE ONLY. It creates throwaway bills for user 83, changes their
 * status so BillStatusObserver fires, counts the inbox rows, and deletes
 * everything it created at the end (even if a test crashes).
 *
 * Run from the Laravel project root:
 *     php artisan tinker
 *     >>> include 'loyalty-test.php';
 *
 * Pushes for user 83's devices may be queued. Stop the notifications:tick loop
 * first if you do not want them sent.
 *
 * Not covered here (need AdminBillController and the observer's rules):
 * LOY-06, LOY-17, LOY-18.
 */

use App\Modules\Loyalty\Models\Bill;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$userId = 83;
$created = [];

// a throwaway pending bill; inserted with the query builder so no events fire yet
$newBill = function () use ($userId, &$created) {
    $id = DB::table('bills')->insertGetId([
        'user_id'    => $userId,
        'file_url'   => 'loytest/'.uniqid().'.jpg',
        'status'     => 'pending',
        'hash'       => hash('sha256', uniqid('loytest', true)),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $created[] = $id;

    return Bill::findOrFail($id);
};

$rows = fn ($id) => DB::table('user_notifications')
    ->where('type', 'loyalty.result')
    ->where('dedupe_key', 'like', 'loyalty.result:'.$id.':%')
    ->orderBy('id')
    ->get(['reason', 'title', 'body']);

$reasons = fn ($id) => $rows($id)->pluck('reason')->implode(',') ?: 'none';

$check = function ($name, $ok, $detail = '') {
    echo ($ok ? 'PASS  ' : 'FAIL  ').$name.($detail !== '' ? '  ('.$detail.')' : '').PHP_EOL;
};

echo "User $userId, creating throwaway bills".PHP_EOL.PHP_EOL;

try {
    // ---- LOY-03: a real duplicate gets one duplicate notification
    $b = $newBill();
    $b->update(['status' => 'duplicate']);
    $r = $rows($b->id);
    $first = $r->first();
    $check('LOY-03 duplicate creates exactly one duplicate notification',
        $r->count() === 1 && $first->reason === 'duplicate', 'reasons='.$reasons($b->id));
    $check('LOY-03 duplicate wording is right and claims no points',
        $first !== null && $first->title === 'Bill already scanned' && stripos($first->body, 'earned') === false,
        $first ? 'title='.$first->title : 'no row');

    // ---- LOY-09: the same status fired again must not create a second row
    $b->update(['status' => 'processing']);
    $b->update(['status' => 'duplicate']);
    $check('LOY-09 same bill and reason fired twice leaves one row',
        $rows($b->id)->count() === 1, 'reasons='.$reasons($b->id));

    // ---- LOY-11: the same for a repeated failure
    $f = $newBill();
    $f->update(['status' => 'failed']);
    $f->update(['status' => 'processing']);
    $f->update(['status' => 'failed']);
    $check('LOY-11 repeated failure leaves one failed notification',
        $rows($f->id)->count() === 1 && $rows($f->id)->first()->reason === 'failed', 'reasons='.$reasons($f->id));

    // ---- LOY-10: review then done must both be created
    $rv = $newBill();
    $rv->update(['status' => 'review']);
    $afterReview = $reasons($rv->id);
    $rv->update(['status' => 'done']);
    $got = $rows($rv->id)->pluck('reason')->all();
    $check('LOY-10 review then done creates both notifications',
        in_array('review', $got, true) && in_array('success', $got, true),
        'after review='.$afterReview.', after done='.implode(',', $got)
        .(in_array('success', $got, true) ? '' : ' (done may need a points row to send a success notification)'));
    $review = $rows($rv->id)->firstWhere('reason', 'review');
    $check('LOY-05 review text does not claim points were awarded',
        $review !== null && stripos($review->body, 'earned') === false, $review ? 'ok' : 'no review row');

    echo 'INFO  LOY-13 (same status event repeated) is covered by LOY-09 and LOY-11 above'.PHP_EOL;

    // ---- LOY-19: no bill has more than one points row (read-only check on real data)
    if (Schema::hasTable('loyalty_points') && Schema::hasColumn('loyalty_points', 'bill_id')) {
        $dupes = DB::table('loyalty_points')->select('bill_id')->groupBy('bill_id')
            ->havingRaw('COUNT(*) > 1')->pluck('bill_id');
        $check('LOY-19 no bill has more than one points row', $dupes->isEmpty(),
            $dupes->isEmpty() ? '' : 'bill ids: '.$dupes->implode(','));
    } else {
        echo 'SKIP  LOY-19 table loyalty_points or column bill_id not found'.PHP_EOL;
    }

    echo 'SKIP  LOY-06, LOY-17, LOY-18 need the admin approve/reject flow (see AdminBillController)'.PHP_EOL;
} finally {
    foreach ($created as $id) {
        DB::table('user_notifications')->where('dedupe_key', 'like', 'loyalty.result:'.$id.':%')->delete();
    }
    DB::table('bills')->whereIn('id', $created)->delete();

    echo PHP_EOL.'Deleted '.count($created).' throwaway bills and their notifications.'.PHP_EOL;
}
