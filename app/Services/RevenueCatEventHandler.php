<?php

namespace App\Services;

use App\Models\User;
use App\Models\RevenueCatEvent;
use App\Models\UserSubscription;
use App\Modules\Notifications\Services\SubscriptionNotifier;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Applies one RevenueCat webhook event: keeps user_subscriptions up to date and
 * sends the matching notification. Safe to call twice with the same event.
 *
 * Returns: processed | duplicate | ignored | stale
 */
class RevenueCatEventHandler
{
    public function __construct(private SubscriptionNotifier $notifier)
    {
    }

    public function handle(array $event): string
    {
        $eventId = trim((string) ($event['id'] ?? ''));
        $type = strtoupper(trim((string) ($event['type'] ?? '')));

        if ($eventId === '' || $type === '') {
            return 'ignored';
        }

        $user = $this->resolveUser($event);

        // One transaction: if anything below throws, the event row is rolled back too,
        // so RevenueCat's retry is processed again instead of being skipped as a duplicate.
        return DB::transaction(function () use ($event, $eventId, $type, $user) {
            try {
                $record = RevenueCatEvent::create([
                    'event_id' => $eventId,
                    'type' => $type,
                    'app_user_id' => Str::limit((string) ($event['app_user_id'] ?? ''), 191, ''),
                    'user_id' => $user?->id,
                    'payload' => $event,
                ]);
            } catch (QueryException $exception) {
                if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                    return 'duplicate';
                }

                throw $exception;
            }

            $result = ($user === null || $type === 'TEST' || ! $this->entitlementMatches($event))
                ? 'ignored'
                : $this->apply($user, $type, $event, $eventId);

            $record->update(['processed_at' => now()]);

            return $result;
        });
    }

    private function apply(User $user, string $type, array $event, string $eventId): string
    {
        $sub = UserSubscription::where('user_id', $user->id)->lockForUpdate()->first()
            ?? new UserSubscription(['user_id' => $user->id]);

        $timestampMs = (int) ($event['event_timestamp_ms'] ?? 0);

        // An older event delivered late must not overwrite newer state. With an equal
        // timestamp the order is unknown, so an event that would reactivate a subscription
        // never overrides one that is already marked expired.
        if ($sub->exists && $timestampMs > 0) {
            $last = (int) $sub->last_event_ms;
            $reactivates = in_array($type, [
                'INITIAL_PURCHASE', 'RENEWAL', 'UNCANCELLATION', 'PRODUCT_CHANGE',
                'SUBSCRIPTION_EXTENDED',
            ], true);

            if ($timestampMs < $last
                || ($timestampMs === $last && $sub->status === UserSubscription::EXPIRED && $reactivates)) {
                return 'stale';
            }
        }

        $wasTrial = $sub->exists && $sub->isTrial();
        $period = strtoupper((string) ($event['period_type'] ?? $sub->period_type ?? ''));
        $isTrial = in_array($period, ['TRIAL', 'INTRO'], true);

        $expires = isset($event['expiration_at_ms'])
            ? Carbon::createFromTimestampMs((int) $event['expiration_at_ms'], 'UTC')
            : $sub->expires_at;

        $attributes = [
            'store' => $event['store'] ?? $sub->store,
            'product_id' => $event['new_product_id'] ?? $event['product_id'] ?? $sub->product_id,
            'period_type' => $period !== '' ? $period : $sub->period_type,
            'expires_at' => $expires,
            'last_event_ms' => max($timestampMs, (int) $sub->last_event_ms),
        ];

        switch ($type) {
            case 'INITIAL_PURCHASE':
            case 'RENEWAL':
            case 'UNCANCELLATION':
            case 'PRODUCT_CHANGE':
                $attributes += ['status' => UserSubscription::ACTIVE, 'will_renew' => true];
                break;

            case 'SUBSCRIPTION_EXTENDED':
                $attributes += ['status' => UserSubscription::ACTIVE];
                break;

            case 'CANCELLATION': // still active until expires_at, but will not renew
                $attributes += ['will_renew' => false];
                break;

            case 'BILLING_ISSUE':
                $attributes += ['status' => UserSubscription::BILLING_ISSUE];
                break;

            case 'EXPIRATION':
                $attributes += ['status' => UserSubscription::EXPIRED, 'will_renew' => false];
                break;

            default:
                return 'ignored'; // other event types are not needed here
        }

        $sub->forceFill($attributes)->save();

        switch ($type) {
            case 'INITIAL_PURCHASE':
                if ($isTrial) {
                    $this->notifier->trialStarted($user->id, $expires);
                } else {
                    // a paid subscription bought straight away, without a trial first
                    $this->notifier->fromWebhook($user->id, 'subscription_started', $eventId, $expires);
                }
                break;

            case 'RENEWAL':
                // first paid period right after a trial vs. a normal renewal
                $variant = ($wasTrial && ! $isTrial) ? 'subscription_started' : 'renewed';
                $this->notifier->fromWebhook($user->id, $variant, $eventId, $expires);
                break;

            case 'CANCELLATION':
                $this->notifier->fromWebhook($user->id, 'subscription_cancelled', $eventId, $expires);
                break;

            case 'BILLING_ISSUE':
                $this->notifier->fromWebhook($user->id, 'billing_issue', $eventId, $expires);
                break;

            case 'EXPIRATION':
                if ($isTrial && $expires !== null) {
                    $this->notifier->trialEnded($user->id, $expires);
                } else {
                    $this->notifier->fromWebhook($user->id, 'subscription_ended', $eventId, $expires);
                }
                break;
        }

        return 'processed';
    }

    /** The apps use the user's email as the RevenueCat app user id. */
    private function resolveUser(array $event): ?User
    {
        $candidates = array_merge(
            [$event['app_user_id'] ?? null, $event['original_app_user_id'] ?? null],
            (array) ($event['aliases'] ?? [])
        );

        foreach ($candidates as $candidate) {
            $email = strtolower(trim((string) $candidate));

            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue; // anonymous RevenueCat ids are not emails
            }

            $user = User::where('email', $email)->first();

            if ($user !== null) {
                return $user;
            }
        }

        return null;
    }

    private function entitlementMatches(array $event): bool
    {
        $wanted = (string) config('services.revenuecat.entitlement');
        
        if ($wanted === '') {
            return true;
        }

        $ids = (array) ($event['entitlement_ids'] ?? []);

        if (isset($event['entitlement_id'])) {
            $ids[] = $event['entitlement_id'];
        }

        if ($ids === []) {
            if (config('services.revenuecat.strict_entitlement')) {
                return false;
            }

            Log::warning('notifications.revenuecat.no_entitlement_ids', [
                'event_id' => $event['id'] ?? null,
                'type' => $event['type'] ?? null,
            ]);

            return true;
        }

        return in_array($wanted, $ids, true);
    }
}