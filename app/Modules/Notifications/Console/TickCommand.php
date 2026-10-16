<?php

namespace App\Modules\Notifications\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class TickCommand extends Command
{
    protected $signature = 'notifications:tick {--work-seconds=55 : How long the queue worker runs}';

    protected $description = 'The single cron entry point: due reminders, nightly cleanup, then the queue worker';

    public function handle(): int
    {
        $lock = fopen(storage_path('framework/notifications-tick.lock'), 'c');

        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            return self::SUCCESS; // the previous minute is still running
        }

        try {
            $this->runStep('notifications:dispatch-due');

            if (now()->format('H:i') === '03:30') {
                $this->runStep('notifications:prune');
            }

            $this->call('queue:work', [
                '--queue' => 'notifications,default',
                '--sleep' => 3,
                '--tries' => 3,
                '--timeout' => 60,
                '--max-time' => max(1, (int) $this->option('work-seconds')),
            ]);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return self::SUCCESS;
    }

    private function runStep(string $command): void
    {
        try {
            $this->callSilently($command);
        } catch (Throwable $e) {
            Log::error('notifications.tick_step_failed', ['command' => $command, 'error' => $e->getMessage()]);
        }
    }
}