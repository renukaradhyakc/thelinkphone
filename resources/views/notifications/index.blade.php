@extends('layouts.app')

@section('title')
    Notifications
@endsection

@section('content')
    <div class="container-fluid pb-7" id="notificationPage"
         data-notification-api="{{ url('/notification-center/api') }}" data-filter="all" data-cursor="">

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-5">
            <h1 class="mb-0 fs-2 text-gray-900">Notifications</h1>

            <div class="d-flex flex-wrap align-items-center gap-2">
                <div class="btn-group" role="group" aria-label="Filter notifications">
                    <button type="button" class="btn btn-primary btn-sm" data-notification-filter="all">All</button>
                    <button type="button" class="btn btn-outline-primary btn-sm" data-notification-filter="unread">Unread</button>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm" id="notificationPageMarkAll">
                    Mark all as read
                </button>
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('notification-center.settings') }}">Settings</a>
            </div>
        </div>

        <div class="card notification-surface mb-7">
            <div id="notificationPageList"></div>

            <div id="notificationPageEmpty" class="text-center text-gray-600 py-8 d-none">
                No notifications yet. Bookings, reminders and updates will show up here.
            </div>
            <div id="notificationPageError" class="text-center text-gray-600 py-8 d-none">
                Could not load notifications.
                <button type="button" class="btn btn-link p-0 align-baseline" id="notificationPageRetry">Try again</button>
            </div>

            <button type="button" class="btn btn-outline-primary btn-sm d-block d-none mx-auto my-4" id="notificationLoadMore">
                Load more
            </button>
        </div>
    </div>
@endsection