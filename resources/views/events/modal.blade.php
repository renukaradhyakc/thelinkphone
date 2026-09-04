<div class="modal fade" id="updateLocation" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">{{__('messages.event.edit_location')}}</h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>
            {{ Form::open(['id' => 'addLocationInfo']) }}
            <div class="modal-body">
                <div class="alert alert-danger fs-4 text-white d-flex align-items-center  d-none" role="alert"
                     id="updateLocationValidationErrorsBox">
                    <i class="fa-solid fa-face-frown me-5"></i>
                </div>
                <div class="row">
                    <div class="col-sm-12">
                        <div class="mb-5">
                            <select name="add_event_location"
                                    class="form-select form-select-solid add-location">
                                @foreach($locationArr as $key => $value)
                                    @if($key == \App\Models\Event::IN_PERSON_MEETING)
                                        <option value="{{ $key }}"
                                                class="update-location" {{ (isset($event->event_location) && $event->event_location == $key) ? 'selected' : '' }}>{{ $value }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="d-none" id="locationData">
                        <div class="col-sm-12">
                            <div class="mb-5">
                                <label class="form-label mb-2">{{ __('messages.event.location_type') ?? 'Location Type' }}:</label>

                                <span data-bs-toggle="tooltip" data-placement="top"
                                    data-bs-original-title="{{ __('messages.tooltip.location_type') ?? 'Fixed Location uses a single address for the whole event. Live Location shares your real-time location with the invitee while the event is active.' }}">
                                    <i class="fa fa-question-circle ms-1 fs-7"></i>
                                </span>

                                <div class="d-flex mt-2">
                                    <div class="form-check me-10">
                                        <input class="form-check-input" type="radio" name="location_type" id="locationTypeFixed" value="fixed" checked>
                                        <label class="form-check-label" for="locationTypeFixed">
                                            {{ __('messages.event.fixed_location') ?? 'Fixed Location' }}
                                        </label>
                                    </div>

                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="location_type" id="locationTypeLive" value="live">
                                        <label class="form-check-label" for="locationTypeLive">
                                            {{ __('messages.event.live_location') ?? 'Live Location' }}
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="mt-4 d-none" id="liveSharingToggleWrap">
                                    <label class="form-label">{{ __('messages.event.live_sharing_status') ?? 'Live Location Status' }}:</label>
                                    <span data-bs-toggle="tooltip" data-placement="top"
                                        data-bs-original-title="{{ __('messages.tooltip.live_sharing_status') ?? 'Turn this on anytime to share your live location manually. If left off, sharing will start automatically when the event begins and stop when it ends. Live Sharing turns off every 24 hours, so for multi-day events you can turn it back on once a day if you want it to continue.' }}">
                                        <i class="fa fa-question-circle ms-1 fs-7"></i>
                                    </span>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="is_live_sharing_active" id="liveSharingToggle" value="1">
                                        <label class="form-check-label" for="liveSharingToggle">
                                            {{ __('messages.event.live_sharing_active') ?? 'Share live location now' }}
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-12">
                            <div class="input-group mb-5">
                                {{ Form::text('short_description_location', null, ['class' => 'form-control', 'id' => 'shortDescLoc', 'placeholder' => __('messages.web.location')]) }}
                                <button type="button" class="btn btn-outline-secondary" id="useMyLocationBtn" title="Use my current location">
                                    <i class="fas fa-location-crosshairs"></i>
                                </button>
                            </div>
                            <div id="locationFetchStatus" class="text-muted fs-6 d-none"></div>
                        </div>
                        @php
                            $styleCss = 'style';
                        @endphp
                        <a href="javascript:void(0)" class="add-information-loc">
                            <i class="fa fa-plus me-2" {{ $styleCss }}="color: #009ef7"
                            ></i>{{ __('messages.event.include_additional_information') }}</a>
                        <div class="d-none long-desc-loc">
                            <div class="col-sm-12">
                                <div class="mb-5">
                                    {{ Form::textarea('long_description_location', null,['class' => 'form-control','id' => 'longDescLoc', 'rows' => 3, 'placeholder' => __('messages.common.description')]) }}
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            <div class="modal-footer pt-0">
                <button type="submit" class="btn btn-primary m-0">{{ __('messages.common.update') }}</button>
                <button type="button" class="btn btn-secondary my-0 ms-5 me-0" data-bs-dismiss="modal">{{ __('messages.common.discard') }}</button>
            </div>
            {{ Form::close() }}
        </div>
    </div>
</div>
