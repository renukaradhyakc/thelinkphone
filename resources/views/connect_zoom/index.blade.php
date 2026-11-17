@extends('layouts.app')
@section('title')
    {{ __('messages.setting.connect_zoom') }}
@endsection
@section('content')
    <div class="container-fluid">
        @include('flash::message')
        <div class="d-flex flex-column">
            <div class="card mb-5 mb-xl-10">
                @if(!$data['zoomIntegrationExists'])
                    <div class="card-header border-0 justify-content-center cursor-pointer">
                        <div class="card-title m-0">
                            <a href="{{ route('zoomAuth') }}" data-turbo="false"
                               class="btn btn-primary m-0">{{ __('messages.setting.connect_your_zoom_account') }}</a>
                        </div>
                    </div>
                @else
                    <div class="card-header border-0 justify-content-center text-center">
                        <div class="card-title flex-column m-0">
                            <span class="fs-5 fw-bold d-block mb-4">{{ __('messages.setting.zoom_account_connected') }}</span>
                                <a href="{{ route('disconnectZoom.destroy') }}" data-turbo="false"
                                    class="btn btn-danger m-0">{{ __('messages.setting.disconnect_your_zoom_account') }}</a>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection