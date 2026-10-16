<?php

namespace App\Modules\Notifications\Services;

class TextBuilder
{
    /**
     * @return array{title: string, body: string}
     */
    public function build(string $type, string $variant, array $params = [], ?string $locale = null): array
    {
        $key = "notifications.{$type}.{$variant}";
        $text = trans($key, $params, $locale ?: config('app.locale'));

        if (! is_array($text) || ! isset($text['title'], $text['body'])) {
            // missing text: never send the raw key to a user
            return ['title' => config('app.name'), 'body' => ''];
        }

        return ['title' => $text['title'], 'body' => $text['body']];
    }
}