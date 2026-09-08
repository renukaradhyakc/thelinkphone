<?php

namespace App\Listeners;

use App\Models\EventGoogleCalendar;
use App\Models\EventSchedule;
use App\Models\GoogleCalendarIntegration;
use App\Models\UserGoogleEventSchedule;
use App\Repositories\GoogleCalendarRepository;
use Illuminate\Support\Facades\App;
use App\Models\GoogleCalendarList;
use Illuminate\Support\Facades\Log;
use Exception;

class HandleCreatedGoogleEvent
{
    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        $eventScheduleID = $event->eventScheduleID;

        $this->createGoogleEvent($eventScheduleID);
    }

    public function createGoogleEvent($eventScheduleID): bool
    {
        $eventSchedule = EventSchedule::with(['user', 'event'])->find($eventScheduleID);

        if ($eventSchedule->video_provider !== 'google_meet') {
            return true;
        }
        
        $booker = $eventSchedule->otherPartyByPhone;

        $googleCalendarConnected = GoogleCalendarIntegration::whereUserId($booker->id)->exists();

        if ($googleCalendarConnected) {
            /** @var GoogleCalendarRepository $repo */
            $repo = App::make(GoogleCalendarRepository::class);

            $originalCalendarLists = EventGoogleCalendar::whereUserId($booker->id)
                ->pluck('google_calendar_id')
                ->toArray();
            
            $calendarLists = $this->resolveCalendarLists($originalCalendarLists, $booker);

            if (empty($calendarLists)) {
                Log::warning('No Google calendar available for booker, skipping Meet creation', ['booker_id' => $booker->id, 'event_schedule_id' => $eventSchedule->id]);
                return true;
            }

            $usedFallback = empty($originalCalendarLists);

            $meta['name'] = 'Event Schedule Name : '.$eventSchedule->name;
            $meta['description'] = 'Event Name : '.$eventSchedule->event->name;
            $meta['lists'] = $calendarLists;

            $accessToken = $repo->getAccessToken($booker->id);

            try {
                $results = $repo->store($eventSchedule, $accessToken, $meta);
            } catch (Exception $exception) {
                $isNotFoundError = str_contains($exception->getMessage(), '"code": 404') || str_contains($exception->getMessage(), '"reason": "notFound"');

                if (! $isNotFoundError) {
                    Log::error('Google Calendar API call failed (not a stale-calendar issue) — likely an auth/token problem.', [
                        'booker_id' => $booker->id,
                        'event_schedule_id' => $eventSchedule->id,
                        'error' => $exception->getMessage(),
                    ]);
                    return true;
                }

                Log::warning('Google Calendar insert failed, likely a stale/deleted calendar. Cleaning up and retrying with primary calendar.', [
                    'booker_id' => $booker->id,
                    'event_schedule_id' => $eventSchedule->id,
                    'error' => $exception->getMessage(),
                ]);

                EventGoogleCalendar::whereUserId($booker->id)->delete();
                GoogleCalendarList::whereUserId($booker->id)->whereIn('google_calendar_id', $calendarLists)->delete();

                $calendarLists = $this->resolveCalendarLists([], $booker);
                $usedFallback = true;

                if (empty($calendarLists)) {
                    Log::error('No fallback Google calendar available after stale-calendar cleanup', ['booker_id' => $booker->id, 'event_schedule_id' => $eventSchedule->id]);
                    return true;
                }

                $meta['lists'] = $calendarLists;

                try {
                    $results = $repo->store($eventSchedule, $accessToken, $meta);
                } catch (Exception $retryException) {
                    Log::error('Google Calendar insert failed again on fallback calendar, giving up.', [
                        'booker_id' => $booker->id,
                        'event_schedule_id' => $eventSchedule->id,
                        'error' => $retryException->getMessage(),
                    ]);
                    return true;
                }
            }

        
            if ($usedFallback && ! EventGoogleCalendar::whereUserId($booker->id)->exists()) {
                $usedCalendarModel = GoogleCalendarList::whereUserId($booker->id)
                    ->where('google_calendar_id', $calendarLists[0])
                    ->first();

                if ($usedCalendarModel) {
                    EventGoogleCalendar::create([
                        'user_id' => $booker->id,
                        'google_calendar_list_id' => $usedCalendarModel->id,
                        'google_calendar_id' => $usedCalendarModel->google_calendar_id,
                    ]);
                }
            }

            foreach ($results as $result) {
                UserGoogleEventSchedule::create([
                    'user_id' => $booker->id,
                    'event_schedule_id' => $eventSchedule->id,
                    'google_calendar_id' => $result['google_calendar_id'],
                    'google_event_id' => $result['id'],
                    'google_meet_link' => $result['google_meet_link'],
                ]);
            }
        }

        return true;
    }

    private function resolveCalendarLists(array $calendarLists, $booker): array
    {
        if (! empty($calendarLists)) {
            return $calendarLists;
        }

        $candidates = GoogleCalendarList::whereUserId($booker->id)->get();

        if ($candidates->isEmpty()) {
            return [];
        }

        $primaryCalendar = $candidates->first(function ($calendar) {
            $meta = json_decode($calendar->meta, true);
            return ! empty($meta['primary']);
        });

        if (! $primaryCalendar) {
            $primaryCalendar = $candidates->firstWhere('google_calendar_id', $booker->email);
        }

        if (! $primaryCalendar) {
            $primaryCalendar = $candidates->first(); // any available calendar, better than none
        }

        return $primaryCalendar ? [$primaryCalendar->google_calendar_id] : [];
    }
}
