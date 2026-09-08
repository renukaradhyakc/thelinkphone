@php
    $eventLocation = optional($row->event)->event_location;
    
    $isRecipientView = isset($component) && $component->direction === 'given_to_me';

    $isInPersonMeeting = $eventLocation === \App\Models\Event::IN_PERSON_MEETING;
    $location = $isInPersonMeeting ? optional($row->event)->location : null;

    $isLive = $location && (int) $location->location_type === \App\Models\Location::LIVE;
    $hasCoords = $location && $location->latitude !== null && $location->longitude !== null;
    $address = $location->address ?? null;

    $isActivelySharing = false;
    if ($isLive && $location->is_live_sharing_active) {
        $expiresAt = $location->liveExpiresAt($row->event);
        $isActivelySharing = ! ($expiresAt && now()->greaterThanOrEqualTo($expiresAt));
    }

    $iconColorClass = $isLive ? ($isActivelySharing ? 'text-success' : 'text-danger') : '';

    $mapsUrl = null;
    if ($isRecipientView) {
        if ($hasCoords) {
            $mapsUrl = "https://www.google.com/maps/search/?api=1&query={$location->latitude},{$location->longitude}";
        } elseif ($address) {
            $mapsUrl = "https://www.google.com/maps/search/?api=1&query=" . urlencode($address);
        }
    }   
    $showLocationIcon = $mapsUrl || ($isLive && $isRecipientView);

    $isPhoneCall = $eventLocation === \App\Models\Event::PHONE_CALL;
    $party = $isRecipientView ? ($row->event->user ?? null) : ($row->otherPartyByPhone ?? null);

    $callalinkUrl = $party && !empty($party->domain_url) ? rtrim(config('app.url'), '/') . '/call/' . ltrim($party->domain_url, '/') : null;

    $isVideoCall = $eventLocation === \App\Models\Event::VIDEO_CALL;
    $isGoogleMeet = $isVideoCall && $row->video_provider === 'google_meet';
    $isZoom = $isVideoCall && $row->video_provider === 'zoom';

    $meetingLink = null;
    if ($isVideoCall && $row->status != \App\Models\EventSchedule::CANCELLED) {
        $booker = $row->otherPartyByPhone;

        if ($isGoogleMeet) {
            $meetingLink = $row->userGoogleEventSchedule->google_meet_link ?? null;
        } elseif ($isZoom) {
            $meetingLink = $row->userZoomEventSchedule->zoom_join_url ?? null;
        }
    }

    $showMeetLink = ! empty($meetingLink);
@endphp
<div class="text-center">
    @if($isInPersonMeeting)
        @if($showLocationIcon)
            @if($mapsUrl)
                <a href="{{ $mapsUrl }}"
                    target="_blank" rel="noopener"
                    class="live-location-icon text-decoration-none"
                    data-event-id="{{ optional($row->event)->id }}"
                    data-location-type="{{ optional($location)->location_type }}"
                    data-address="{{ $address }}"
                    title="{{ $isActivelySharing ? 'Loading…' : ($address ?? '') }}">
                    <i class="fas fa-map-marker-alt fs-3 {{ $iconColorClass }}"></i>
                </a>
            @else
                <a href="javascript:void(0)"
                    class="live-location-icon"
                    data-event-id="{{ optional($row->event)->id }}"
                    data-location-type="{{ optional($location)->location_type }}"
                    data-address=""
                    title="Location sharing hasn't started yet">
                    <i class="fas fa-map-marker-alt fs-3 {{ $iconColorClass }}"></i>
                </a>
            @endif
        @else
            —
        @endif
    @elseif($isPhoneCall)
        @if($callalinkUrl)
            <a href="{{ $callalinkUrl }}" target="_blank" rel="noopener noreferrer" title="Phone Call">
                <i class="fa-solid fa-phone fs-3"></i>
            </a>
         @else
            —
        @endif
    @elseif($isGoogleMeet)
        @if($showMeetLink)
            <a href="{{ $meetingLink }}" target="_blank" title="Google Meet">
                <img src="{{ asset('assets/images/logo_google_meet.svg') }}" alt="Google Meet" width="20" height="20">
            </a>
        @else
            —
        @endif
    @elseif($isZoom)
        @if($showMeetLink)
            <a href="{{ $meetingLink }}" target="_blank" title="Zoom">
                <img src="{{ asset('assets/images/logo_zoom_meet.svg') }}" alt="Zoom" width="20" height="20">
            </a>
        @else
            —
        @endif
    @else
        —
    @endif
</div>