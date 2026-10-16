<?php

namespace App\Modules\Notifications\Services;

use InvalidArgumentException;

/**
 * Read-only access to config/notifications.php (types, variants, categories, rules).
 */
class NotificationRegistry
{
    private function types(): array
    {
        return config('notifications.types', []);
    }

    public function typeKeys(): array
    {
        return array_keys($this->types());
    }

    public function has(string $type, ?string $variant = null): bool
    {
        $def = $this->types()[$type] ?? null;

        if (!is_array($def)) {
            return false;
        }

        return $variant === null || isset($def['variants'][$variant]);
    }

    public function isEnabled(string $type): bool
    {
        return $this->type($type)['enabled'] ?? true;
    }

    public function type(string $type): array
    {
        $def = $this->types()[$type] ?? null;

        if (!is_array($def)) {
            throw new InvalidArgumentException("Unknown notification type: {$type}");
        }

        return $def;
    }

    public function variant(string $type, string $variant): array
    {
        $def = $this->type($type);

        if (!isset($def['variants'][$variant])) {
            throw new InvalidArgumentException("Unknown variant '{$variant}' for type {$type}");
        }

        return $def['variants'][$variant];
    }

    /** @return string[] inbox|push|email */
    public function channels(string $type, string $variant): array
    {
        return $this->variant($type, $variant)['channels'] ?? [];
    }

    public function category(string $type): string
    {
        return $this->type($type)['category'];
    }

    public function priority(string $type): string
    {
        return $this->type($type)['priority'] ?? 'normal';
    }

    public function inboxDays(string $type): int
    {
        return (int) ($this->type($type)['inbox_days'] ?? 90);
    }

    public function pushTtlMinutes(string $type): int
    {
        return (int) ($this->type($type)['push_ttl_min'] ?? 1440);
    }

    /** Variant-level flag wins over type-level. Mandatory ignores the user's category toggle. */
    public function isMandatory(string $type, string $variant): bool
    {
        $v = $this->variant($type, $variant);

        return (bool) ($v['mandatory'] ?? $this->type($type)['mandatory'] ?? false);
    }

    public function categories(): array
    {
        return config('notifications.categories', []);
    }

    public function categoryDefaultEnabled(string $category): bool
    {
        return (bool) config("notifications.categories.$category.default_enabled", true);
    }

    public function androidImportance(string $category): string
    {
        return config("notifications.categories.$category.android_importance", 'default');
    }

    public function rule(string $key, $default = null)
    {
        return config("notifications.rules.$key", $default);
    }
}