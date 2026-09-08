<?php

namespace App\Repositories;

use App\Models\Event;
use App\Models\ZoomIntegration;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;


class ZoomRepository
{
    private const TOKEN_URL = 'https://zoom.us/oauth/token';
    private const API_BASE = 'https://api.zoom.us/v2';

    /**
     * Create a Zoom meeting under the connected user's account.
     *
     * @param  \App\Models\EventSchedule  $eventSchedule
     * @param  string  $accessToken
     * @param  array  $meta  ['name' => ..., 'description' => ...]
     * @return array  ['zoom_meeting_id' => ..., 'zoom_join_url' => ...]
     */
    public function store($eventSchedule, string $accessToken, array $meta): array
    {
        $date = $eventSchedule['schedule_date'];
        $timezone = $eventSchedule->user->timezone;
        $timeZone = isset(\App\Models\User::TIME_ZONE_ARRAY[$timezone]) ? \App\Models\User::TIME_ZONE_ARRAY[$timezone] : null;
        $time = explode(' - ', $eventSchedule['slot_time']);
        $startTime = date('H:i', strtotime($time[0]));
        $endTime = date('H:i', strtotime($time[1]));

        $startDateTime = Carbon::parse($date.' '.$startTime, $timeZone);
        $endDateTime = Carbon::parse($date.' '.$endTime, $timeZone);
        $durationMinutes = max(1, $startDateTime->diffInMinutes($endDateTime));

        $response = Http::withToken($accessToken)
            ->post(self::API_BASE.'/users/me/meetings', [
                'topic' => $meta['name'],
                'agenda' => $meta['description'] ?? '',
                'type' => 2, // scheduled meeting
                'start_time' => $startDateTime->clone()->setTimezone('UTC')->toIso8601ZuluString(),
                'duration' => $durationMinutes,
                'timezone' => 'UTC',
                'settings' => [
                    // Anyone with the link can join without the host needing to start
                    // the meeting first — matches the "no login required" behavior
                    // this pipeline is built around. Flip to false + waiting_room true
                    // if you want Google Meet-style "can't join until admitted".
                    'join_before_host' => true,
                    'waiting_room' => false,
                ],
            ]);

        if ($response->failed()) {
            Log::error('[Zoom] meeting creation failed', [
                'event_schedule_id' => $eventSchedule->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new UnprocessableEntityHttpException('Zoom meeting could not be created: '.$response->body());
        }

        $data = $response->json();

        return [
            'zoom_meeting_id' => (string) $data['id'],
            'zoom_join_url' => $data['join_url'],
        ];
    }

    public function getAccessToken(int $userId): string
    {
        $integration = ZoomIntegration::whereUserId($userId)->first();

        if (! $integration) {
            throw new UnprocessableEntityHttpException('Zoom is not connected for this user.');
        }

        $meta = json_decode($integration->meta, true) ?: [];
        $obtainedAt = $meta['obtained_at'] ?? null;
        $expiresIn = $meta['expires_in'] ?? null;

        $isExpired = true;
        if ($obtainedAt !== null && $expiresIn !== null) {
            $isExpired = Carbon::createFromTimestamp($obtainedAt)->addSeconds($expiresIn)->subSeconds(60)->isPast();
        }

        if (! $isExpired) {
            return $integration->access_token;
        }

        if (empty($integration->refresh_token)) {
            throw new UnprocessableEntityHttpException('Please reconnect your Zoom account.');
        }

        $response = Http::asForm()
            ->withBasicAuth(config('services.zoom.client_id'), config('services.zoom.client_secret'))
            ->post(self::TOKEN_URL, [
                'grant_type' => 'refresh_token',
                'refresh_token' => $integration->refresh_token,
            ]);

        if ($response->failed()) {
            Log::error('[Zoom] token refresh failed', [
                'user_id' => $userId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new UnprocessableEntityHttpException('Please reconnect your Zoom account.');
        }

        $tokenData = $response->json();

        $newMeta = array_merge($tokenData, ['obtained_at' => now()->timestamp]);

        $integration->update([
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'],
            'meta' => json_encode($newMeta),
            'last_used_at' => Carbon::now(),
        ]);

        return $tokenData['access_token'];
    }

    public function destroy($userZoomEventSchedules): void
    {
        foreach ($userZoomEventSchedules as $userZoomEventSchedule) {
            try {
                $accessToken = $this->getAccessToken($userZoomEventSchedule->user_id);
            } catch (\Exception $exception) {
                Log::warning('[Zoom] could not get access token during destroy, skipping', [
                    'user_zoom_event_schedule_id' => $userZoomEventSchedule->id,
                    'error' => $exception->getMessage(),
                ]);
                continue;
            }

            $response = Http::withToken($accessToken)
                ->delete(self::API_BASE.'/meetings/'.$userZoomEventSchedule->zoom_meeting_id);

            if ($response->failed()) {
                if ($response->status() !== 404) {
                    Log::error('[Zoom] meeting delete failed', [
                        'user_zoom_event_schedule_id' => $userZoomEventSchedule->id,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    throw new UnprocessableEntityHttpException('Zoom meeting could not be deleted: '.$response->body());
                }
            }
        }
    }
}