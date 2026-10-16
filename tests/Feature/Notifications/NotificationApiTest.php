<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Modules\Notifications\Jobs\SendPushNotificationJob;
use App\Modules\Notifications\Repositories\PreferenceRepository;
use App\Modules\Notifications\Repositories\UserNotificationRepository;
use App\Modules\Notifications\Services\NotificationRegistry;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Uses DatabaseTransactions: everything is rolled back, nothing stays in the database.
 * Needs at least two existing users (two are used to test isolation).
 */
class NotificationApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();

        $users = User::orderBy('id')->limit(2)->get();

        if ($users->count() < 2) {
            $this->markTestSkipped('Need two users in the database.');
        }

        [$this->user, $this->other] = [$users[0], $users[1]];

        \App\Modules\Notifications\Models\UserNotification::whereIn('user_id', [$this->user->id, $this->other->id])
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    private function make(User $user, string $key): \App\Modules\Notifications\Models\UserNotification
    {
        return app(UserNotificationRepository::class)->createUnique([
            'user_id' => $user->id,
            'type' => 'loyalty.result',
            'reason' => 'success',
            'category' => 'loyalty',
            'title' => 'T',
            'body' => 'B',
            'dedupe_key' => $key,
        ]);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/notifications')
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.')
            ->assertJsonMissingPath('data');
    }

    public function test_device_register_and_remove(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/v1/devices', ['platform' => 'android', 'token' => 'test-token-123'])
            ->assertOk()->assertJsonPath('success', true);

        $this->deleteJson('/api/v1/devices/test-token-123')
            ->assertOk()->assertJsonPath('success', true);

        $this->deleteJson('/api/v1/devices/test-token-123')
            ->assertJsonPath('success', false);
    }

    public function test_device_register_validates_platform(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/v1/devices', ['platform' => 'toaster', 'token' => 'x'])->assertStatus(422);
    }

    public function test_list_unread_count_read_and_read_all(): void
    {
        $a = $this->make($this->user, 't:1');
        $this->make($this->user, 't:2');
        $this->make($this->other, 't:3');

        Sanctum::actingAs($this->user);

        $this->getJson('/api/v1/notifications/unread-count')->assertJsonPath('data.count', 2);

        $this->getJson('/api/v1/notifications?limit=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure(['next_cursor']);

        $this->postJson("/api/v1/notifications/{$a->uuid}/read")
            ->assertOk()->assertJsonPath('data.unread_count', 1);

        $this->postJson('/api/v1/notifications/read-all')
            ->assertOk()->assertJsonPath('data.unread_count', 0);
    }

    public function test_cannot_touch_another_users_notification(): void
    {
        $theirs = $this->make($this->other, 't:other');

        Sanctum::actingAs($this->user);

        $this->postJson("/api/v1/notifications/{$theirs->uuid}/read")->assertStatus(404);
        $this->deleteJson("/api/v1/notifications/{$theirs->uuid}")->assertStatus(404);
    }

    public function test_delete_notification(): void
    {
        $n = $this->make($this->user, 't:del');

        Sanctum::actingAs($this->user);

        $this->deleteJson("/api/v1/notifications/{$n->uuid}")->assertOk();
        $this->getJson('/api/v1/notifications/unread-count')->assertJsonPath('data.count', 0);
    }

    public function test_preferences_read_and_update(): void
    {
        Sanctum::actingAs($this->user);

        $this->getJson('/api/v1/notification-preferences')->assertOk()->assertJsonStructure(['data' => [['category', 'push_enabled']]]);

        $category = array_key_first(app(NotificationRegistry::class)->categories());

        $this->putJson('/api/v1/notification-preferences', [
            'preferences' => [['category' => $category, 'push_enabled' => false]],
        ])->assertOk();

        $this->putJson('/api/v1/notification-preferences', [
            'preferences' => [['category' => 'not-a-category', 'push_enabled' => true]],
        ])->assertStatus(422);
    }

    public function test_same_dedupe_key_notifies_only_once(): void
    {
        Bus::fake();

        $registry = app(NotificationRegistry::class);
        app(PreferenceRepository::class)->set($this->user->id, $registry->category('loyalty.result'), true);

        $service = app(NotificationService::class);

        $first = $service->notify($this->user->id, 'loyalty.result', 'success', 'test:dedupe', ['count' => 5]);
        $second = $service->notify($this->user->id, 'loyalty.result', 'success', 'test:dedupe', ['count' => 5]);

        $this->assertNotNull($first);
        $this->assertNull($second);
        Bus::assertDispatchedTimes(SendPushNotificationJob::class, 1);
    }

    public function test_disabled_category_keeps_inbox_but_sends_no_push(): void
    {
        Bus::fake();

        $registry = app(NotificationRegistry::class);
        app(PreferenceRepository::class)->set($this->user->id, $registry->category('booking.created'), false);

        $row = app(NotificationService::class)->notify(
            $this->user->id, 'booking.created', 'owner', 'test:pref-off:'.uniqid(),
            ['event' => 'E', 'name' => 'N', 'time' => 'T']
        );

        $this->assertNotNull($row);
        Bus::assertNotDispatched(SendPushNotificationJob::class);
    }

    public function test_enabled_category_sends_push(): void
    {
        Bus::fake();

        $registry = app(NotificationRegistry::class);
        app(PreferenceRepository::class)->set($this->user->id, $registry->category('booking.created'), true);

        app(NotificationService::class)->notify(
            $this->user->id, 'booking.created', 'owner', 'test:pref-on:'.uniqid(),
            ['event' => 'E', 'name' => 'N', 'time' => 'T']
        );

        Bus::assertDispatchedTimes(SendPushNotificationJob::class, 1);
    }

    public function test_mandatory_type_ignores_disabled_category(): void
    {
        Bus::fake();

        $registry = app(NotificationRegistry::class);
        app(PreferenceRepository::class)->set($this->user->id, $registry->category('booking.cancelled'), false);

        app(NotificationService::class)->notify(
            $this->user->id, 'booking.cancelled', 'owner_cancelled', 'test:mandatory:'.uniqid(),
            ['event' => 'E', 'name' => 'N', 'time' => 'T']
        );

        Bus::assertDispatchedTimes(SendPushNotificationJob::class, 1);
    }
}