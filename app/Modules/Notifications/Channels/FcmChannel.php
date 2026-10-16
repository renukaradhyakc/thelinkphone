<?php

namespace App\Modules\Notifications\Channels;

use App\Modules\Notifications\Models\DeviceToken;
use App\Modules\Notifications\Models\UserNotification;
use App\Modules\Notifications\Services\NotificationRegistry;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * FCM HTTP v1 sender. No extra package: the OAuth token is minted with openssl and cached.
 */
class FcmChannel
{
    public const SENT = 'sent';
    public const INVALID_TOKEN = 'invalid_token';
    public const RETRY = 'retry';
    public const FAILED = 'failed';

    private const TOKEN_CACHE_KEY = 'fcm_access_token';

    private ?array $credentials = null;

    public function __construct(private NotificationRegistry $registry)
    {
    }

    public function send(UserNotification $notification, DeviceToken $device, bool $validateOnly = false): string
    {
        $url = 'https://fcm.googleapis.com/v1/projects/'.$this->credentials()['project_id'].'/messages:send';

        $body = [
            'validate_only' => $validateOnly,
            'message' => $this->message($notification, $device),
        ];

        $response = Http::withToken($this->accessToken())->timeout(10)->post($url, $body);

        if ($response->status() === 401) {
            Cache::forget(self::TOKEN_CACHE_KEY);
            $response = Http::withToken($this->accessToken())->timeout(10)->post($url, $body);
        }

        return $this->classify($response, $notification, $device);
    }

    private function message(UserNotification $n, DeviceToken $device): array
    {
        $high = $this->registry->priority($n->type) === 'high';

        $data = [
            'notification_id' => (string) $n->uuid,
            'type' => (string) $n->type,
            'reason' => (string) $n->reason,
            'category' => (string) $n->category,
            'title' => (string) $n->title,
            'body' => (string) $n->body,
            'deep_link' => (string) ($n->deep_link ?? ''),
            'data' => json_encode($n->data ?? new \stdClass()),
            'created_at' => optional($n->created_at)->toIso8601String() ?? '',
            'expires_at' => optional($n->expires_at)->toIso8601String() ?? '',
        ];

        return [
            'token' => $device->token,
            'notification' => ['title' => $n->title, 'body' => $n->body],
            'data' => $data, // FCM requires string values only
            'android' => [
                'priority' => $high ? 'HIGH' : 'NORMAL',
                'ttl' => ($this->registry->pushTtlMinutes($n->type) * 60).'s',
                'notification' => [
                    'channel_id' => $n->category, // the app must create channels with these ids
                    'tag' => (string) $n->uuid,   // same notification replaces itself, never doubles
                    'click_action' => 'com.thelinkphone.app.OPEN_NOTIFICATION',
                ],
            ],
            'apns' => [
                'headers' => [
                    'apns-priority' => $high ? '10' : '5',
                    'apns-collapse-id' => (string) $n->uuid,
                ],
                'payload' => ['aps' => ['sound' => 'default', 'thread-id' => $n->category]],
            ],
        ];
    }

    private function classify(Response $response, UserNotification $n, DeviceToken $device): string
    {
        Log::info('fcm.response', [
            'notification_id' => $n->id,
            'device_id' => $device->id,
            'status' => $response->status(),
            'fcm_error' => $response->json('error.status'),
        ]);

        if ($response->successful()) {
            return self::SENT;
        }

        $status = $response->status();
        $errorStatus = (string) $response->json('error.status');
        $message = strtolower((string) $response->json('error.message'));
        $codes = collect($response->json('error.details') ?? [])->pluck('errorCode')->all();

        if ($status === 404 || in_array('UNREGISTERED', $codes, true) || $errorStatus === 'NOT_FOUND') {
            return self::INVALID_TOKEN;
        }

        if (in_array('SENDER_ID_MISMATCH', $codes, true)) {
            return self::INVALID_TOKEN;
        }

        if ($errorStatus === 'INVALID_ARGUMENT' && str_contains($message, 'registration token')) {
            return self::INVALID_TOKEN;
        }

        if ($status === 429 || $status >= 500) {
            return self::RETRY;
        }

        Log::warning('fcm.failed', ['status' => $status, 'body' => $response->body()]);

        return self::FAILED;
    }

    private function accessToken(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);

        if ($cached) {
            return $cached;
        }

        $c = $this->credentials();
        $now = time();

        $header = $this->b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = $this->b64(json_encode([
            'iss' => $c['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => $c['token_uri'],
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $signature = '';
        if (! openssl_sign("$header.$claims", $signature, $c['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('FCM: could not sign the service-account JWT (check the openssl extension).');
        }

        $response = Http::asForm()->timeout(10)->post($c['token_uri'], [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => "$header.$claims.".$this->b64($signature),
        ]);

        if (! $response->successful() || ! $response->json('access_token')) {
            throw new RuntimeException('FCM: token request failed with status '.$response->status());
        }

        $token = $response->json('access_token');
        $ttl = max(60, (int) $response->json('expires_in', 3600) - 300);
        Cache::put(self::TOKEN_CACHE_KEY, $token, $ttl);

        return $token;
    }

    private function credentials(): array
    {
        if ($this->credentials !== null) {
            return $this->credentials;
        }

        $path = (string) config('app.fcm_credentials_path');

        if ($path === '') {
            throw new RuntimeException('FCM_CREDENTIALS_PATH is not set.');
        }

        $absolute = str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path);
        $file = $absolute ? $path : storage_path($path);

        if (! is_file($file)) {
            throw new RuntimeException("FCM credentials file not found: {$file}");
        }

        $json = json_decode(file_get_contents($file), true);

        foreach (['project_id', 'client_email', 'private_key', 'token_uri'] as $key) {
            if (empty($json[$key])) {
                throw new RuntimeException("FCM credentials file is missing '{$key}'.");
            }
        }

        return $this->credentials = $json;
    }

    private function b64(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}