@extends('layouts.app')
@section('title')
    {{__('messages.setting.general') }}
@endsection
@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-column">
            <div class="tab-content" id="myTabContent">
                <div class="tab-pane fade show active" id="aoverview" role="tabpanel">
                    @include('user_settings.setting_menu')
                    <div class="card mb-5 mb-xl-10">
                        <div class="collapse show">
                            <div class="card-body p-sm-12 p-6">
                                {{ Form::open(['route'=>'user.setting.create.update','id' => 'generalUserSettingForm']) }}
                                <div class="row d-flex align-items-center">
                                    {{ Form::hidden('sectionName','user_'.$sectionName) }}
                                    <div class="col-md-6 col-sm-12 mb-5">
                                        <label class="form-label">
                                            {{ __('messages.user_dashboard.send_emails_to_all_attendees_when_events_are_deleted') }}
                                        </label>
                                        <span data-bs-toggle="tooltip"
                                              title="{{ __('messages.user_dashboard.emails_are_only_sent_to_the_users_whose_appointments_is_upcoming') }}"> 
                <i class="fa fa-question-circle"></i>
            </span>
                                    </div>
                                    <div class="form-check form-switch col-md-6 col-sm-12 mb-5 ps-14 ps-sm-11">
                                        <div class="fv-row d-flex align-items-center">
                                            {{ Form::checkbox('email_notification', 1, $setting['email_notification'] ?? 0,['class' => 'form-check-input  me-5']) }}
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-sm-12">
                                        <label class="form-label">{{ __('messages.setting.time_format') }}</label>
                                    </div>
                                    <div class="col-md-6 col-sm-12 ms-0 ps-sm-0">
                                        <div class="radio-button-group">
                                            <div class="btn-group btn-group-toggle m-0" data-toggle="buttons">
                                                <input type="radio" name="time_format" id="time_format-0"
                                                       value="0" {{ !empty($setting['time_format']) ? ($setting['time_format'] == 0 ? 'checked' : '') : 'checked' }}>
                                                <label for="time_format-0" class="me-2" role="button">12 Hour</label>
                                                <input type="radio" name="time_format" id="time_format-1"
                                                       value="1" {{ !empty($setting['time_format']) ? ($setting['time_format'] == 0 ? '' : 'checked') : '' }}>
                                                <label for="time_format-1" role="button">24 Hour</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-6 mt-5">
                                        <label class="form-label required">
                                            {{ __('messages.user_dashboard.calendar_view') }}
                                        </label>
                                    </div>
                                    <div class="row mt-5">
                                        <div class="row mt-5">
                                            <div class="col-md-6 mb-3">
                                                <div class="img-radio img-thumbnail {{ !empty($setting['calendar_view']) && $setting['calendar_view'] == 1 ? 'img-border' : '' }} {{ empty($setting['calendar_view']) ? 'img-border' : '' }}"
                                                     data-calendar-val="1">
                                                    <img src="{{ asset('web/images/default_calendar.png') }}"
                                                         class="calendar-view"
                                                         alt="calendar">
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <div class="img-radio img-thumbnail {{ !empty($setting['calendar_view']) && $setting['calendar_view'] == 2 ? 'img-border' : '' }}"
                                                     data-calendar-val="2">
                                                    <img src="{{ asset('web/images/custom_calendar.png') }}"
                                                         class="calendar-view"
                                                         alt="Template">
                                                </div>
                                            </div>
                                            {{ Form::hidden('calendar_view',null,['id' => 'calendarView']) }}
                                        </div>
                                        <div class="d-flex mt-5">
                                            {{ Form::submit(__('messages.common.save'),['class' => 'btn btn-primary','id'=>'generalSettingSaveBtn']) }}
                                        </div>
                                    </div>
                                    {{ Form::close() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
