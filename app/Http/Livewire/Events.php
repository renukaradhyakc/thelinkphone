<?php

namespace App\Http\Livewire;

use App\Mail\DeleteEventMail;
use App\Models\Event;
use App\Models\EventSchedule;
use App\Models\User;
use App\Modules\Notifications\Repositories\OutboxRepository;
use App\Modules\Notifications\Services\BookingNotifier;
use App\Modules\Notifications\Services\RecipientResolver;
use Carbon\Carbon;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Str;

class Events extends SearchableComponent
{
    /**
     * @var string[]
     */
    protected $listeners = [
        'refresh' => '$refresh',
        'delete',
    ];

    public function model(): string
    {
        return Event::class;
    }

    /**
     * @return Application|Factory|View
     */
    public function render()
    {
        $events = $this->searchEvents();

        return view('livewire.events', compact('events'));
    }

    public function searchEvents(): LengthAwarePaginator
    {
        $query = $this->getQuery()->where('user_id', '=', getLogInUserId());

        $query->where(function () {
            $this->filterEventResults();
        });

        return $query->paginate();
    }

    /**
     * @return string[]
     */
    public function searchableFields(): array
    {
        return ['name'];
    }

    public function delete($id)
    {
        $loginUserEventIds = Event::whereUserId(getLogInUserId())->pluck('id')->toArray();

        if (! in_array($id, $loginUserEventIds)) {
            $this->dispatchBrowserEvent('event-error');
        } else {
            $event = Event::findOrFail($id);
            $timeZoneName = isset(getLoginUser()->timezone) ?
                User::TIME_ZONE_ARRAY[getLoginUser()->timezone] : User::TIME_ZONE_ARRAY[getTimeZone()];
            date_default_timezone_set($timeZoneName);
            $eventScheduleEmails = EventSchedule::whereUserId($event->user_id)
                ->whereEventId($id)
                ->where('status', EventSchedule::BOOKED)
                ->where('schedule_date', '>', Carbon::now()->format('Y-m-d'))
                ->get();

            $notifier = app(BookingNotifier::class);
            $recipients = app(RecipientResolver::class);
            $outbox = app(OutboxRepository::class);

            DB::transaction(function () use ($event, $notifier, $recipients, $outbox) {
                $upcomingBookings = EventSchedule::where('event_id', $event->id)
                    ->where('status', EventSchedule::BOOKED)
                    ->whereDate('schedule_date', '>=', now()->subDay()->toDateString())
                    ->get()
                    ->sortBy(fn ($b) => $notifier->startUtc($b)?->timestamp ?? PHP_INT_MAX);

                $told = [];

                foreach ($upcomingBookings as $upcomingBooking) {
                    $start = $notifier->startUtc($upcomingBooking);
                    $end = $start ? ($notifier->endUtc($upcomingBooking) ?? $start->copy()->addMinutes(30)) : null;

                    if ($start === null || ! $end->gt(now())) {
                        continue;
                    }

                    $phone = trim((string) $upcomingBooking->phone_call);
                    $person = $phone === '' ? null : $recipients->uniqueUserByPhone($phone);

                    if ($person !== null && isset($told[$person->id])) {
                        $outbox->cancelPendingForBooking($upcomingBooking->id);

                        continue;
                    }

                    if ($person !== null) {
                        $told[$person->id] = true;
                    }

                    $notifier->cancelled($upcomingBooking, 'event_deleted');
                }

                $event->delete();
            });

            $emailNotification = getUserSettings();
            if (isset($emailNotification['email_notification']) && ($emailNotification['email_notification'] == 1)) {
                foreach ($eventScheduleEmails as $eventScheduleEmail) {
                    try {
                        Mail::to($eventScheduleEmail->email)->send(new DeleteEventMail('emails.delete_event',
                            'You have created schedule this event is deleted', [$event, $eventScheduleEmail]));
                    } catch (\Throwable $e) {
                        Log::error('events.delete_mail_failed', ['event_id' => $event->id, 'error' => $e->getMessage()]);
                    }
                }
            }

            $this->dispatchBrowserEvent('deleted');
        }
    }

    protected function filterEventResults(): Builder
    {
        $searchableFields = $this->searchableFields();
        $search = $this->search;

        $this->getQuery()->when(! empty($search), function () use ($search, $searchableFields) {
            $this->getQuery()->where(function (Builder $q) use ($search, $searchableFields) {
                $searchString = '%'.$search.'%';
                foreach ($searchableFields as $field) {
                    if (Str::contains($field, '.')) {
                        $field = explode('.', $field);
                        $q->orWhereHas($field[0], function (Builder $query) use ($field, $searchString) {
                            $query->whereRaw("lower($field[1]) like ?", $searchString);
                        });
                    } else {
                        $q->orWhereRaw("lower($field) like ?", $searchString);
                    }
                }
            });
        });

        return $this->getQuery();
    }
}
