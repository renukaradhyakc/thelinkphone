@extends('layouts.app')

@section('title')
    Notification settings
@endsection

@section('content')
    <div class="container-fluid" id="notificationSettings"
         data-notification-api="{{ url('/notification-center/api') }}">

        <div>
            <a href="{{ route('notification-center.index') }}" class="text-decoration-none">Back to notifications</a>
        </div>

        <h3 class="mb-4 mt-3">
            Push notifications
            <span class="fs-6 ms-1" data-bs-toggle="tooltip"
                  title="Choose which updates can send a push notification to your phone. Everything still appears in your notifications list.">
                <i class="fa fa-question-circle"></i>
            </span>
        </h3>

        <div class="card">
            @foreach ($categories as $key => $category)
                <div class="d-flex align-items-center justify-content-between gap-4 px-4 py-4 {{ $loop->last ? '' : 'border-bottom' }}">
                    <div class="min-w-0">
                        <label class="fw-semibold text-gray-900 mb-0" for="notification-pref-{{ $key }}">
                            {{ $category['label'] }}
                        </label>
                        @if ($category['hint'] || $category['locked'])
                            <div class="text-gray-600">
                                {{ $category['hint'] }}
                                @if ($category['locked'])
                                    Always on, so you don't miss them.
                                @endif
                            </div>
                        @endif
                    </div>

                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input notification-pref-toggle" type="checkbox" role="switch"
                               id="notification-pref-{{ $key }}"
                               data-category="{{ $key }}"
                               data-locked="{{ $category['locked'] ? '1' : '0' }}"
                               {{ $category['locked'] ? 'checked' : '' }} disabled>
                    </div>
                </div>
            @endforeach
        </div>

        <p class="mt-3 mb-0 text-gray-600" id="notificationPrefStatus" role="status" aria-live="polite"></p>
    </div>
@endsection