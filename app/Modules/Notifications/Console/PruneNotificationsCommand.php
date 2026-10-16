<?php

namespace App\Modules\Notifications\Console;

use App\Modules\Notifications\Repositories\DeviceTokenRepository;
use App\Modules\Notifications\Repositories\OutboxRepository;
use App\Modules\Notifications\Repositories\UserNotificationRepository;
use App\Modules\Notifications\Services\NotificationRegistry;
use Illuminate\Console\Command;

class PruneNotificationsCommand extends Command
{
    protected $signature = 'notifications:prune {--outbox-days=30} {--token-days=30}';

    protected $description = 'Delete expired inbox rows, finished outbox rows and old revoked device tokens';

    public function handle(
        NotificationRegistry $registry,
        UserNotificationRepository $inbox,
        OutboxRepository $outbox,
        DeviceTokenRepository $devices
    ): int {
        $expired = $inbox->pruneExpiredBefore(now());

        $aged = 0;
        foreach ($registry->typeKeys() as $type) {
            $days = $registry->inboxDays($type);

            if ($days > 0) {
                $aged += $inbox->pruneOlderThan($type, now()->subDays($days));
            }
        }

        $outboxRows = $outbox->prune(now()->subDays((int) $this->option('outbox-days')));
        $tokens = $devices->deleteRevokedBefore(now()->subDays((int) $this->option('token-days')));

        $this->info("Pruned: {$expired} expired, {$aged} aged inbox rows, {$outboxRows} outbox rows, {$tokens} revoked tokens.");

        return self::SUCCESS;
    }
}