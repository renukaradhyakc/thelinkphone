<?php

namespace App\Modules\Notifications\Repositories;

use App\Modules\Notifications\Models\NotificationPreference;

/**
 * Per-category push on/off. A missing row means "use the registry default".
 */
class PreferenceRepository
{
    /**
     * @return array<string, bool>  category => push_enabled, only for categories the user changed
     */
    public function forUser(int $userId): array
    {
        return NotificationPreference::where('user_id', $userId)
            ->pluck('push_enabled', 'category')
            ->map(fn ($enabled) => (bool) $enabled)
            ->all();
    }

    public function isPushEnabled(int $userId, string $category, bool $default): bool
    {
        $row = NotificationPreference::where('user_id', $userId)
            ->where('category', $category)
            ->first();

        return $row ? (bool) $row->push_enabled : $default;
    }

    public function set(int $userId, string $category, bool $pushEnabled): NotificationPreference
    {
        return NotificationPreference::updateOrCreate(
            ['user_id' => $userId, 'category' => $category],
            ['push_enabled' => $pushEnabled]
        );
    }
}