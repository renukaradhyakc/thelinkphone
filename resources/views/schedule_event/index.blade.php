@extends('layouts.app')
@section('title')
    {{__('messages.schedule_events')}}
@endsection
@section('content')
    <div class="container-fluid">
        @include('flash::message')
        @include('layouts.errors')

        <ul class="nav nav-pills mb-5">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#given-by-me">
                    {{ __('messages.schedule_event.given_by_me') }}
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#given-to-me">
                    {{ __('messages.schedule_event.given_to_me') }}
                </a>
            </li>
        </ul>

        <div class="tab-content" id="scheduleEventTabsContent">
            <div class="tab-pane fade show active" id="given-by-me" role="tabpanel">
                <h3 class="mb-3 mt-4">{{ __('messages.event.event_schedules') ?? 'Event Schedules' }}</h4>
                <livewire:event-schedule-table
                    :eventStatus="$eventStatus"
                    direction="given_by_me"
                    wire:key="event-schedule-table-given-by-me" />

                <h3 class="mb-3 mt-4">{{ __('messages.event.phone_schedules') ?? 'Phone Schedules' }}</h4>
                <livewire:phone-schedule-table
                    direction="given_by_me"
                    wire:key="phone-schedule-table-given-by-me" />
            </div>
            <div class="tab-pane fade" id="given-to-me" role="tabpanel">
                <h3 class="mb-3 mt-4">{{ __('messages.event.event_schedules') ?? 'Event Schedules' }}</h4>
                <livewire:event-schedule-table
                    :eventStatus="$eventStatus"
                    direction="given_to_me"
                    wire:key="event-schedule-table-given-to-me" />

                <h3 class="mb-3 mt-4">{{ __('messages.event.phone_schedules') ?? 'Phone Schedules' }}</h4>
                <livewire:phone-schedule-table
                    direction="given_to_me"
                    wire:key="phone-schedule-table-given-to-me" />
            </div>
        </div>

        @include('schedule_event.cancel_modal')
    </div>
@endsection