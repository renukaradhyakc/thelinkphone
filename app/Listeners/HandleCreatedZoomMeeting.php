<?php

namespace App\Listeners;

use App\Models\EventSchedule;
use App\Models\UserZoomEventSchedule;
use App\Models\ZoomIntegration;
use App\Repositories\ZoomRepository;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Exception;

class HandleCreatedZoomMeeting
{
    public function handle(object $event): void
    {
        $eventScheduleID = $event->eventScheduleID;
        $this->createZoomMeeting($eventScheduleID);
    }

    public function createZoomMeeting($eventScheduleID): bool
    {
        $eventSchedule = EventSchedule::with(['user', 'event'])->find($eventScheduleID);
        $booker = $eventSchedule->otherPartyByPhone;

        $zoomConnected = ZoomIntegration::whereUserId($booker->id)->exists();

        if (! $zoomConnected) {
            Log::warning('[Zoom] Booker has no Zoom integration, skipping meeting creation', [
                'booker_id' => $booker->id,
                'event_schedule_id' => $eventSchedule->id,
            ]);
            return true;
        }

        /** @var ZoomRepository $repo */
        $repo = App::make(ZoomRepository::class);

        try {
            $accessToken = $repo->getAccessToken($booker->id);
        } catch (Exception $exception) {
            Log::error('[Zoom] Could not get access token, skipping meeting creation', [
                'booker_id' => $booker->id,
                'event_schedule_id' => $eventSchedule->id,
                'error' => $exception->getMessage(),
            ]);
            return true;
        }

        $meta = [
            'name' => 'Event Schedule Name : '.$eventSchedule->name,
            'description' => 'Event Name : '.$eventSchedule->event->name,
        ];

        try {
            $result = $repo->store($eventSchedule, $accessToken, $meta);
        } catch (Exception $exception) {
            Log::error('[Zoom] Meeting creation failed', [
                'booker_id' => $booker->id,
                'event_schedule_id' => $eventSchedule->id,
                'error' => $exception->getMessage(),
            ]);
            return true;
        }

        UserZoomEventSchedule::updateOrCreate(
            ['event_schedule_id' => $eventSchedule->id],
            [
                'user_id' => $booker->id,
                'zoom_meeting_id' => $result['zoom_meeting_id'],
                'zoom_join_url' => $result['zoom_join_url'],
            ]
        );

        return true;
    }
}