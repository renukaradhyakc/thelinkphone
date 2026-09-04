<div class="col-12">
    <div class="tab-content" id="myTabContent">
        <div class="tab-pane fade show active" id="patientOverview" role="tabpanel">
            @php
                $isOwner = getLogInUserId() === $eventSchedule->user_id;
                $owner = optional($eventSchedule->event)->user;
                $booker = $eventSchedule->otherPartyByPhone ?? null;

                $displayName = $isOwner
                    ? ($eventSchedule->name ?? '—')
                    : ($owner->full_name ?? '—');

                $displayEmail = $isOwner
                    ? ($eventSchedule->email ?? null)
                    : ($owner->email ?? null);

                $linkParty = $isOwner ? $booker : $owner;
                $callalinkUrl = $linkParty && !empty($linkParty->domain_url)
                    ? rtrim(config('app.url'), '/') . '/call/' . ltrim($linkParty->domain_url, '/')
                    : null;

                $location = optional($eventSchedule->event)->location;
                $isLiveType = $location && (int) $location->location_type === \App\Models\Location::LIVE;
                $isFixedType = $location && (int) $location->location_type === \App\Models\Location::FIXED;
                $locationAddress = (!$isOwner && $location) ? $location->address : null;

                $liveStatusLabel = null;
                $liveStartedLabel = null;

                if (!$isOwner && $isLiveType) {
                    if ($location->latitude === null || $location->longitude === null) {
                        $liveStartedLabel = 'Not Started';
                        $liveStatusLabel = 'Not Started';
                    } elseif (! $location->is_live_sharing_active) {
                        $liveStartedLabel = $location->live_started_at ? $location->live_started_at->diffForHumans() : 'Not Started';
                        $liveStatusLabel = 'Location sharing has stopped';
                    } else {
                        $liveStartedLabel = $location->live_started_at ? $location->live_started_at->diffForHumans() : 'Not Started';

                        $expiresAt = $location->liveExpiresAt($eventSchedule->event);
                        $isExpired = $expiresAt && now()->greaterThanOrEqualTo($expiresAt);

                        $liveStatusLabel = $isExpired
                            ? 'Expired ' . ($expiresAt ? $expiresAt->diffForHumans() : optional($location->updated_at)->diffForHumans())
                            : 'Updated ' . optional($location->updated_at)->diffForHumans();
                    }               
                }

                $descriptionText = $isOwner
                    ? ($eventSchedule->description ?? 'N/A')
                    : (optional($eventSchedule->event)->description ?? 'N/A');
            @endphp

            <div class="row mb-7">
                <label class="col-lg-4 fs-4 text-gray-600">{{ __('messages.common.name')  }}</label>
                <div class="col-lg-8">
                    <span class="fs-4 text-gray-800">{{ $displayName }}</span>
                </div>
            </div>
            <div class="row mb-7">
                <label class="col-lg-4 fs-4 text-gray-600">{{ __('messages.user.email') }}</label>
                <div class="col-lg-8 fv-row">
                    @if(!empty(trim($displayEmail ?? '')))
                        <span class="fs-4 text-gray-800">
                            <a href="mailto:{{ $displayEmail }}" class="text-decoration-none">{{ $displayEmail }}</a>
                        </span>
                     @else
                        <span class="fs-4 text-gray-800">—</span>
                     @endif
                </div>
            </div>
            <div class="row mb-7">
                <label class="col-lg-4 fs-4 text-gray-600">{{ __('messages.common.link') }}</label>
                <div class="col-lg-8 fv-row">
                    @if($callalinkUrl)
                        <span class="fs-4 text-gray-800">
                            <a href="{{ $callalinkUrl }}" target="_blank" rel="noopener noreferrer" class="text-decoration-none">{{ $callalinkUrl }}</a>
                        </span>
                    @else
                        <span class="fs-4 text-gray-800">—</span>
                    @endif
                </div>
            </div>
            <div class="row mb-7">
                <label class="col-lg-4 fs-4 text-gray-600">{{ __('messages.event.event_name') }}</label>
                <div class="col-lg-8">
                    <span class="fs-4 text-gray-800">{{ $eventSchedule->event->name }}</span>
                </div>
            </div>
            <div class="row mb-7">
                <label class="col-lg-4 fs-4 text-gray-600">{{ __('messages.event.event_type') }}</label>
                <div class="col-lg-8">
                    <span class="badge bg-light-{{getBadgeEventTypeColors($eventSchedule->event->event_type)}} ">{{ \App\Models\Event::EVENT_TYPE[$eventSchedule->event->event_type] }}</span>
                </div>
            </div>
            <div class="row mb-7">
                <label class="col-lg-4 fs-4 text-gray-600">{{ __('messages.event.meeting_type') }}</label>
                <div class="col-lg-8">
                    <span class="fs-4 text-gray-800">{{ \App\Models\Event::LOCATION_ARRAY[$eventSchedule->event->event_location] ?? '—' }}</span>
                </div>
            </div>
            <div class="row mb-7">
                <label class="col-lg-4 fs-4 text-gray-600">{{ __('messages.schedule_event.scheduled_date') }}</label>
                    <div class="col-lg-8 fv-row">
                        <span class="fs-4 text-gray-800">{{ $eventSchedule->schedule_date }}</span>
                    </div>
            </div>
            <div class="row mb-7">
                <label class="col-lg-4 fs-4 text-gray-600">{{ __('messages.schedule_event.scheduled_time') }}</label>
                <div class="col-lg-8">
                    <span class="fs-4 text-gray-800">{{ $eventSchedule->slot_time }}</span>
                </div>
            </div>
            @if($eventSchedule->event->event_location != \App\Models\Event::PHONE_CALL)
                <div class="row mb-7">
                    <label class="col-lg-4 fs-4 text-gray-600">
                        {{ $eventSchedule->event->event_location == \App\Models\Event::GOOGLE_MEET ? 'Google Meet Link' : __('messages.event.location') }}
                    </label>
                    <div class="col-lg-8">
                        @if($eventSchedule->event->event_location == \App\Models\Event::GOOGLE_MEET)
                            @php
                                $meetLink = \App\Models\UserGoogleEventSchedule::where('event_schedule_id', $eventSchedule->id)->value('google_meet_link');
                            @endphp
                            @if($meetLink)
                                <span class="fs-4 text-gray-800">
                                    <a href="{{ $meetLink }}" target="_blank" rel="noopener noreferrer" class="text-decoration-none">{{ $meetLink }}</a>
                                </span>
                            @else
                                <span class="fs-4 text-gray-800">—</span>
                            @endif
                        @else
                            <span class="fs-4 text-gray-800">{{ $locationAddress ?? '—' }}</span>
                        @endif
                    </div>
                </div>
            @endif
            @if($isLiveType && !$isOwner)
                <div class="row mb-7">
                    <label class="col-lg-4 fs-4 text-gray-600">Live Started At</label>
                    <div class="col-lg-8">
                        <span class="fs-4 text-gray-800">{{ $liveStartedLabel ?? '-' }}</span>
                    </div>
                </div>
                <div class="row mb-7">
                    <label class="col-lg-4 fs-4 text-gray-600">Live Status</label>
                    <div class="col-lg-8">
                        <span class="fs-4 text-gray-800">{{ $liveStatusLabel ?? '—' }}</span>
                    </div>
                </div>
            @endif
            <div class="row mb-7">
                <label class="col-lg-4 fs-4 text-gray-600">{{ __('messages.event.description') }}</label>
                <div class="col-lg-8">
                    <span class="fs-4 text-gray-800">{!! $descriptionText !!}</span>
                </div>
            </div>
            <div class="row mb-7">
                <label class="col-lg-4 fs-4 text-gray-600">{{ __('messages.schedule_event.status') }}</label>
                <div class="col-lg-8">
                    <span class="badge bg-light-{{getBadgeColors($eventSchedule->status)}} ">{{ \App\Models\EventSchedule::STATUS[$eventSchedule->status] }}</span>
                </div>
            </div>
            @if($eventSchedule->payment_type && $eventSchedule->event->event_type == \App\Models\Event::PAID)
                <div class="row mb-7">
                    <label class="col-lg-4 fs-4 text-gray-600">{{ __('messages.schedule_event.payment_type') }}</label>
                    <div class="col-lg-8">
                        @if($eventSchedule->payment_type == \App\Models\EventSchedule::STRIPE)
                            <span class="badge bg-light-success">{{ \App\Models\EventSchedule::PAYMENT_METHOD[$eventSchedule->payment_type] }}</span>
                        @elseif ($eventSchedule->payment_type == \App\Models\EventSchedule::PAYPAL)
                            <span class="badge bg-light-primary">{{ \App\Models\EventSchedule::PAYMENT_METHOD[$eventSchedule->payment_type] }}</span>
                        @endif
                    </div>
                </div>
            @endif
            <div class="row mb-7">
                <label class="col-lg-4 fs-4 text-gray-600">{{ __('messages.schedule_event.cancel_reason') }}</label>
                <div class="col-lg-8">
                    <span class="fs-4 text-gray-800">{{ $eventSchedule->cancel_reason ?? 'N/A' }}</span>
                </div>
            </div>
            <div class="row mb-7">
                <label class="col-lg-4 fs-4 text-gray-600">{{ __('messages.common.created_at') }}</label>
                <div class="col-lg-8">
                    <span class="fs-4 text-gray-800 " data-bs-toggle="tooltip" data-bs-placement="right" title="{{\Carbon\Carbon::parse($eventSchedule->created_at)->translatedFormat('jS M Y')}}">{{$eventSchedule->created_at->diffForHumans()}}</span>
                </div>
            </div>
            <div class="row mb-7">
                <label class="col-lg-4 fs-4 text-gray-600">{{ __('messages.common.last_updated') }}</label>
                <div class="col-lg-8">
                    <span class="fs-4 text-gray-800" data-bs-toggle="tooltip" data-bs-placement="right" title="{{\Carbon\Carbon::parse($eventSchedule->updated_at)->translatedFormat('jS M Y')}}">{{$eventSchedule->updated_at->diffForHumans()}}</span>
                </div>
            </div>
            @if($eventSchedule->status == \App\Models\EventSchedule::HOLD)
                <div class="row mb-7">
                    <label class="col-lg-4 fs-4 text-gray-600"></label>
                    <div class="col-lg-8">
                        <a href="{{ route('remove.hold.status.user', $eventSchedule->id) }}" class="btn btn-primary">{{ __('messages.remove_hold_status') }}</a>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
