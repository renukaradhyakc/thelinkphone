<?php

namespace App\Modules\Notifications\Console;

use App\Models\UserTrial;
use App\Models\RevenueCatEvent;
use App\Models\UserSubscription;
use App\Modules\Notifications\Services\NotificationRegistry;
use App\Modules\Notifications\Services\SubscriptionNotifier;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Time-based subscription notifications. Idempotent (dedupe keys), so it is safe
 * to run every hour; TickCommand does that.
 *
 *  1. App Store trials (known from RevenueCat webhooks)  -> trial ending
 *  2. Subscriptions that will not renew                  -> subscription ending
 *  3. Own server-side trials (user_trials, Android)      -> trial ending / trial ended
 *     only for users RevenueCat has never reported, so nobody who subscribed is told
 *     their trial is ending.
 * Trial ended for App Store trials comes from the EXPIRATION webhook, not from here.
 */
class SubscriptionRemindersCommand extends Command
{
    protected $signature = 'notifications:subscription-reminders {--ended-window-days=2}';

    protected $description = 'Trial ending, trial ended and subscription ending notifications';

    public function handle(SubscriptionNotifier $notifier, NotificationRegistry $registry): int
    {
        $trialDays = $this->days($registry->rule('trial_ending_days_before', [3, 1]));
        $subscriptionDays = $this->days($registry->rule('subscription_expiring_days_before', [3]));
        $window = max(1, (int) $this->option('ended-window-days'));
        $now = now();
        $sent = ['trial_ending' => 0, 'trial_ended' => 0, 'subscription_expiring' => 0];

        $notRevenueCatUsers = function ($query) {
            $query->select('user_id')->from('user_subscriptions');
        };

        // 1. App Store trials
        if ($trialDays->isNotEmpty()) {
            UserSubscription::whereIn('period_type', ['TRIAL', 'INTRO'])
                ->where('status', UserSubscription::ACTIVE)
                ->where('expires_at', '>', $this->utc($now))
                ->where('expires_at', '<=', $this->utc($now->copy()->addDays($trialDays->max())))
                ->chunkById(200, function ($rows) use ($notifier, $trialDays, $now, &$sent) {
                    foreach ($rows as $row) {
                        $days = $this->windowFor($row->expires_at, $trialDays, $now);
                        $variant = $row->will_renew ? 'trial_ending_auto' : 'trial_ending_cancelled';

                        if ($days !== null && $notifier->trialEnding($row->user_id, $variant, $row->expires_at, $days)) {
                            $sent['trial_ending']++;
                        }
                    }
                });
        }

        // 2. Subscriptions that were cancelled and are about to run out
        if ($subscriptionDays->isNotEmpty()) {
            UserSubscription::where('period_type', 'NORMAL')
                ->where('status', UserSubscription::ACTIVE)
                ->where('will_renew', false)
                ->where('expires_at', '>', $this->utc($now))
                ->where('expires_at', '<=', $this->utc($now->copy()->addDays($subscriptionDays->max())))
                ->chunkById(200, function ($rows) use ($notifier, $subscriptionDays, $now, &$sent) {
                    foreach ($rows as $row) {
                        $days = $this->windowFor($row->expires_at, $subscriptionDays, $now);

                        if ($days !== null && $notifier->subscriptionExpiring($row->user_id, $row->expires_at, $days)) {
                            $sent['subscription_expiring']++;
                        }
                    }
                });
        }

        // 3a. Own trials that are about to end
        if ($trialDays->isNotEmpty()) {
            UserTrial::whereNotNull('started_at')
                ->where('consumed', false)
                ->where('expires_at', '>', $now)
                ->where('expires_at', '<=', $now->copy()->addDays($trialDays->max()))
                ->whereNotIn('user_id', $notRevenueCatUsers)
                ->chunkById(200, function ($trials) use ($notifier, $trialDays, $now, &$sent) {
                    foreach ($trials as $trial) {
                        $days = $this->windowFor($trial->expires_at, $trialDays, $now);

                        if ($days !== null && $notifier->trialEnding($trial->user_id, 'trial_ending', $trial->expires_at, $days)) {
                            $sent['trial_ending']++;
                        }
                    }
                });
        }

        // 3b. Own trials that ended recently (older ones are not announced after a deploy)
        UserTrial::whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->where('expires_at', '>', $now->copy()->subDays($window))
            ->whereNotIn('user_id', $notRevenueCatUsers)
            ->chunkById(200, function ($trials) use ($notifier, &$sent) {
                foreach ($trials as $trial) {
                    if ($notifier->trialEnded($trial->user_id, $trial->expires_at)) {
                        $sent['trial_ended']++;
                    }
                }
            });

        // keep the raw webhook log small
        RevenueCatEvent::where('created_at', '<', $now->copy()->subDays(60))->delete();

        $this->info(
            "trial ending {$sent['trial_ending']}, trial ended {$sent['trial_ended']}, subscription ending {$sent['subscription_expiring']}"
        );

        return self::SUCCESS;
    }

    /** Smallest "days before" window the end date already falls into, e.g. 1 when under a day is left. */
    private function windowFor(CarbonInterface $endsAt, Collection $days, CarbonInterface $now): ?int
    {
        return $days->first(fn (int $d) => $endsAt->lte($now->copy()->addDays($d)));
    }

    private function days($value): Collection
    {
        return collect($value)->map(fn ($d) => (int) $d)->filter(fn (int $d) => $d > 0)->sort()->values();
    }

    private function utc(CarbonInterface $time): string
    {
        return $time->copy()->utc()->format('Y-m-d H:i:s');
    }
}