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
                            <div class="input-group mb-5">
                            <span class="input-group-text" id="basic-addon1">
                               <i class="fas fa-map-marker-alt fs-3"></i>
                            </span>
                                {{ Form::text('short_description_location', null,['class' => 'form-control','id' => 'shortDescLoc','placeholder' => __('messages.web.location')]) }}
                            </div>
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
