<?php

namespace App\Modules\Notifications\Console;

use App\Models\User;
use App\Modules\Notifications\Channels\FcmChannel;
use App\Modules\Notifications\Models\DeviceToken;
use App\Modules\Notifications\Models\UserNotification;
use App\Modules\Notifications\Repositories\DeviceTokenRepository;
use App\Modules\Notifications\Services\NotificationRegistry;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

class TestNotificationCommand extends Command
{
    protected $signature = 'notifications:test {user : User id} {type=loyalty.result} {variant=success} {--dry : Only validate with FCM, send nothing} {--token= : Use this FCM token instead of the user\'s devices}';

    protected $description = 'Send a test notification, or validate the FCM setup with --dry';

    public function handle(
        NotificationService $service,
        NotificationRegistry $registry,
        FcmChannel $fcm,
        DeviceTokenRepository $devices
    ): int {
        $userId = (int) $this->argument('user');
        $type = $this->argument('type');
        $variant = $this->argument('variant');

        if (! User::find($userId)) {
            $this->error("User {$userId} not found.");
            return self::FAILURE;
        }

        if (! $registry->has($type, $variant)) {
            $this->error("Unknown type/variant: {$type} / {$variant}");
            return self::FAILURE;
        }

        if ($this->option('dry')) {
            return $this->dry($registry, $fcm, $devices, $userId, $type, $variant);
        }

        $row = $service->notify(
            $userId,
            $type,
            $variant,
            'test:'.Str::uuid(),
            ['event' => 'Test event', 'time' => now()->format('d M H:i'), 'name' => 'Test', 'count' => 5]
        );

        if ($row === null) {
            $this->warn('Nothing created (type disabled or duplicate).');
            return self::FAILURE;
        }

        $this->info("Inbox row created: {$row->uuid}");
        $this->line('Push is queued. High priority types send right away; others wait for the queue worker.');

        return self::SUCCESS;
    }

    private function dry(
        NotificationRegistry $registry,
        FcmChannel $fcm,
        DeviceTokenRepository $devices,
        int $userId,
        string $type,
        string $variant
    ): int {
        $notification = new UserNotification();
        $notification->forceFill([
            'id' => 0,
            'uuid' => (string) Str::uuid(),
            'type' => $type,
            'reason' => $variant,
            'category' => $registry->category($type),
            'title' => 'Dry run',
            'body' => 'FCM validation only',
        ]);

        if ($this->option('token')) {
            $device = new DeviceToken();
            $device->forceFill(['id' => 0, 'platform' => DeviceToken::ANDROID, 'token' => $this->option('token')]);
            $targets = collect([$device]);
        } else {
            $targets = $devices->activeForUser($userId);
        }

        if ($targets->isEmpty()) {
            $this->warn('No devices for this user. Re-run with --token=anything to test the key.');
            return self::FAILURE;
        }

        foreach ($targets as $device) {
            try {
                $result = $fcm->send($notification, $device, true);
            } catch (Throwable $e) {
                $this->error('FCM setup problem: '.$e->getMessage());
                return self::FAILURE;
            }

            $this->line("{$device->platform}: {$result}");
        }

        $this->info('invalid_token with a fake token = your key and setup work.');

        return self::SUCCESS;
    }
}