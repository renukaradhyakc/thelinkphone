<?php

namespace App\Http\Controllers;

use App\Models\EventGoogleCalendar;
use App\Models\GoogleCalendarIntegration;
use App\Models\GoogleCalendarList;
use App\Repositories\GoogleCalendarRepository;
use Carbon\Carbon;
use Exception;
use Google_Client;
use Google_Service_Calendar;
use Google_Service_Calendar_EventDateTime;
use GuzzleHttp\Client;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laracasts\Flash\Flash;
use App\Models\EventSchedule;

/**
 * Class GoogleCalendarController
 */
class GoogleCalendarController extends AppBaseController
{
    public $client;

    public function __construct()
    {
        $name = config('app.google_oauth_path');

        if (empty($name)) {
            return;
        }

        $this->client = new Google_Client();
        // Set the application name, this is included in the User-Agent HTTP header.
        $this->client->setApplicationName(config('app.name'));
        // Set the authentication credentials we downloaded from Google.
        $this->client->setAuthConfig(resource_path(config('app.google_oauth_path')));
        // Setting offline here means we can pull data from the venue's calendar when they are not actively using the site.
        $this->client->setAccessType('offline');
        // This will include any other scopes (Google APIs) previously granted by the venue
        $this->client->setIncludeGrantedScopes(true);
        // Set this to force to consent form to display.
        $this->client->setApprovalPrompt('force');
        // Add the Google Calendar scope to the request.
        $this->client->addScope(Google_Service_Calendar::CALENDAR);
    }

    /**
     * @return Application|RedirectResponse|Redirector
     */
    public function oauth(): RedirectResponse
    {
        $name = config('app.google_oauth_path');

        if (empty($name) && (file_exists(storage_path()))) {
            Flash::error('Please set your google calendar credentials json file');

            return redirect()->back();
        }

        $authUrl = $this->client->createAuthUrl();
        $filteredUrl = filter_var($authUrl, FILTER_SANITIZE_URL);

        return redirect($filteredUrl);
    }

    /**
     * @return Application|RedirectResponse|Redirector
     */
    public function redirect(Request $request): RedirectResponse
    {
        try {
            \Log::info('redirect() hit', ['session_id' => session()->getId(), 'has_pending' => session()->has('pending_booking')]);
            if ($request->get('error')) {
                $returnUrl = session()->pull('pending_booking_return_url', route('events.index'));
                session()->forget('pending_booking');
                Flash::error('Booking requires Google Calendar access to continue.');
                return redirect($returnUrl);
            }
            $accessToken = $this->client->fetchAccessTokenWithAuthCode($request->get('code'));
            $exists = GoogleCalendarIntegration::whereUserId(getLogInUserId())->exists();

            if ($exists) {
                GoogleCalendarIntegration::whereUserId(getLogInUserId())->delete();
                GoogleCalendarList::whereUserId(getLogInUserId())->delete();
            }

            $googleCalendarIntegration = GoogleCalendarIntegration::create([
                'user_id' => getLogInUserId(),
                'access_token' => $accessToken['access_token'],
                'last_used_at' => Carbon::now(),
                'meta' => json_encode($accessToken),
            ]);

            $this->client->setAccessToken($accessToken);
            $calendarLists = $this->fetchCalendarListAndSyncToDB();

            $this->ensureDefaultCalendarSelected();

            if (session()->has('pending_booking')) {
                $pendingBooking = session()->pull('pending_booking');
                $returnUrl = session()->pull('pending_booking_return_url', route('events.index'));

                if (assignPlanFeatures($pendingBooking['user_id'])->schedule_events <= getActiveScheduleEventsCount($pendingBooking['user_id'])) {
                    Flash::error(__('messages.success_message.schedule_events_upgrade'));
                    return redirect($returnUrl);
                }

                $bookedSlots = EventSchedule::whereEventId($pendingBooking['event_id'])
                    ->whereDate('schedule_date', '=', $pendingBooking['schedule_date'])
                    ->where('slot_time', '=', $pendingBooking['slot_time'])
                    ->where('status', '!=', EventSchedule::CANCELLED)
                    ->pluck('slot_time')->toArray();

                if ($bookedSlots) {
                    Flash::error('Sorry, this slot was just booked by someone else. Please pick another.');
                    return redirect($returnUrl);
                }

                $scheduleEventRepo = App::make(\App\Repositories\ScheduleEventRepository::class);
                $eventSchedule = $scheduleEventRepo->store($pendingBooking);

                Flash::success(__('messages.success_message.google_calender_connected'));

                return redirect(url(getSlotConfirmPageUrl($eventSchedule)));
            }
        } catch (Exception $exception) {
            Log::error($exception->getMessage());
        }

        Flash::success(__('messages.success_message.google_calender_connected'));

        return redirect(route('google.calendar.index'));
    }

    public function createNotification()
    {
        $gcHelper = new Google_Service_Calendar($this->client);
        $clinet = new Client();

        $calendarID = 'primary';
        $notificationURL = route('google.webhook');
        $response = Http::post("https://www.googleapis.com/calendar/v3/calendars/$calendarID/events/watch", [
            'id' => $calendarID,
            'type' => 'web_hook',
            'address' => $notificationURL,
        ]);
    }

    /**
     * @return array
     */
    public function fetchCalendarListAndSyncToDB()
    {
        $gcHelper = new Google_Service_Calendar($this->client);
        // Use the Google Client calendar service. This gives us methods for interacting
        // with the Google Calendar API
        $calendarList = $gcHelper->calendarList->listCalendarList();

        $googleCalendarList = [];
        foreach ($calendarList->getItems() as $calendarListEntry) {
            if ($calendarListEntry->accessRole == 'owner') {
                $googleCalendarList[] = GoogleCalendarList::create([
                    'user_id' => getLogInUserId(),
                    'calendar_name' => $calendarListEntry['summary'],
                    'google_calendar_id' => $calendarListEntry['id'],
                    'meta' => json_encode($calendarListEntry),
                ]);
            }
        }

        return $googleCalendarList;
    }

    /**
     * @return JsonResponse|RedirectResponse
     */
    public function show($eventId)
    {
        $accessToken = $this->getAccessToken();
        if ($accessToken) {
            $this->client->setAccessToken($accessToken);

            $service = new Google_Service_Calendar($this->client);
            $event = $service->events->get('primary', $eventId);

            if (! $event) {
                return response()->json(['status' => 'error', 'message' => 'Something went wrong']);
            }

            return response()->json(['status' => 'success', 'data' => $event]);
        } else {
            return redirect()->route('oauthCallback');
        }
    }

    /**
     * @return JsonResponse|RedirectResponse
     */
    public function update(Request $request, $eventId)
    {
        $accessToken = $this->getAccessToken();
        if ($accessToken) {
            $this->client->setAccessToken($accessToken);
            $service = new Google_Service_Calendar($this->client);

            $startDateTime = Carbon::parse($request->start_date)->toRfc3339String();

            $eventDuration = 30; //minutes

            if ($request->has('end_date')) {
                $endDateTime = Carbon::parse($request->end_date)->toRfc3339String();
            } else {
                $endDateTime = Carbon::parse($request->start_date)->addMinutes($eventDuration)->toRfc3339String();
            }

            // retrieve the event from the API.
            $event = $service->events->get('primary', $eventId);

            $event->setSummary($request->title);

            $event->setDescription($request->description);

            //start time
            $start = new Google_Service_Calendar_EventDateTime();
            $start->setDateTime($startDateTime);
            $event->setStart($start);

            //end time
            $end = new Google_Service_Calendar_EventDateTime();
            $end->setDateTime($endDateTime);
            $event->setEnd($end);

            $updatedEvent = $service->events->update('primary', $event->getId(), $event);

            if (! $updatedEvent) {
                return response()->json(['status' => 'error', 'message' => 'Something went wrong']);
            }

            return response()->json(['status' => 'success', 'data' => $updatedEvent]);
        } else {
            return redirect()->route('oauthCallback');
        }
    }

    /**
     * @return RedirectResponse|void
     */
    public function destroy($eventId): RedirectResponse
    {
        $accessToken = $this->getAccessToken();
        if ($accessToken) {
            $this->client->setAccessToken($accessToken);
            $service = new Google_Service_Calendar($this->client);

            $service->events->delete('primary', $eventId);
        } else {
            return redirect()->route('oauthCallback');
        }
    }

    /**
     * @return Application|Factory|View
     */
    public function googleCalendar(): \Illuminate\View\View
    {
        $data['googleCalendarIntegrationExists'] = GoogleCalendarIntegration::whereUserId(getLogInUserId())->exists();
        $data['googleCalendarLists'] = GoogleCalendarList::with('eventGoogleCalendar')->whereUserId(getLogInUserId())
            ->get();

        $data['checkTimeZone'] = getLogInUser();

        return view('connect_google_calendar.index', compact('data'));
    }

    public function eventGoogleCalendarStore(Request $request): JsonResponse
    {
        $input = $request->all();
        $submittedIds = $input['google_calendar'] ?? [];

        $existing = EventGoogleCalendar::whereUserId(getLogInUserId())
            ->pluck('google_calendar_list_id')
            ->toArray();

        $toRemove = array_diff($existing, $submittedIds);
        $toAdd = array_diff($submittedIds, $existing);

        if (! empty($toRemove)) {
            EventGoogleCalendar::whereUserId(getLogInUserId())
                ->whereIn('google_calendar_list_id', $toRemove)
                ->delete();
        }

        foreach ($toAdd as $googleCalendarId) {
            $googleCalendarList = GoogleCalendarList::find($googleCalendarId);
            if (! $googleCalendarList) {
                continue;
            }
            EventGoogleCalendar::create([
                'user_id' => getLogInUserId(),
                'google_calendar_list_id' => $googleCalendarId,
                'google_calendar_id' => $googleCalendarList->google_calendar_id,
            ]);
        }

        return $this->sendSuccess(__('messages.success_message.calendar_added_successfully'));
    }

    /**
     * @return Application|RedirectResponse|Redirector
     */
    public function disconnectGoogleCalendar(): RedirectResponse
    {
        EventGoogleCalendar::whereUserId(getLogInUserId())->delete();
        GoogleCalendarIntegration::whereUserId(getLogInUserId())->delete();
        GoogleCalendarList::whereUserId(getLogInUserId())->delete();

        Flash::success(__('messages.success_message.google_calender_disconnected'));

        return redirect(route('google.calendar.index'));
    }

    public function syncGoogleCalendarList(): JsonResponse
    {
        /** @var GoogleCalendarRepository $repo */
        $repo = App::make(GoogleCalendarRepository::class);

        $repo->syncCalendarList(getLogInUser());

        $this->ensureDefaultCalendarSelected();

        return $this->sendSuccess(__('messages.success_message.google_calender_updated'));
    }

    private function ensureDefaultCalendarSelected(): void
    {
        if (EventGoogleCalendar::whereUserId(getLogInUserId())->exists()) {
            return;
        }

        $candidates = GoogleCalendarList::whereUserId(getLogInUserId())->get();

        if ($candidates->isEmpty()) {
            return;
        }

        $primaryCalendar = $candidates->first(function ($calendar) {
            $meta = json_decode($calendar->meta, true);
            return ! empty($meta['primary']);
        });

        if (! $primaryCalendar) {
            $primaryCalendar = $candidates->firstWhere('google_calendar_id', getLogInUser()->email);
        }

        if (! $primaryCalendar) {
            $primaryCalendar = $candidates->first();
        }

        if ($primaryCalendar) {
            EventGoogleCalendar::create([
                'user_id' => getLogInUserId(),
                'google_calendar_list_id' => $primaryCalendar->id,
                'google_calendar_id' => $primaryCalendar->google_calendar_id,
            ]);
        }
    }
}
