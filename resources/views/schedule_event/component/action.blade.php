@php
    $timeZoneName = isset(getLoginUser()->timezone) ?
            \App\Models\User::TIME_ZONE_ARRAY[getLoginUser()->timezone] : \App\Models\User::TIME_ZONE_ARRAY[getTimeZone()];
    date_default_timezone_set($timeZoneName);
    $slotTime = explode(' - ',$row->slot_time);
    $isRecipientView = isset($component) && $component->direction === 'given_to_me';

    $showCancelButton = !$isRecipientView && $row->status == 1 && (
        ($row->schedule_date == \Carbon\Carbon::now()->format('Y-m-d') && strtotime(date(getUserSettingTimeFormat(getLogInUserId()))) < strtotime($slotTime[0]))
        || $row->schedule_date > \Carbon\Carbon::now()->format('Y-m-d')
    );

    $onlyViewShown = !$showCancelButton;
@endphp
<div class="d-flex align-items-center @if($onlyViewShown) justify-content-center @endif">
@if($showCancelButton)
    @if(($row->schedule_date == \Carbon\Carbon::now()->format('Y-m-d')) && ((strtotime(date(getUserSettingTimeFormat(getLogInUserId()))) < strtotime($slotTime[0]))))
        <a href="javascript:void(0)" data-id="{{ $row->id }}"
           class="btn-bg-light btn-sm edit-btn cancel-scheduled-event btn p-1 text-danger"
           data-bs-custom-class="tooltip-dark" data-bs-placement="bottom"
           title="{{ __('messages.schedule_event.cancel_schedule_event') }}">
            <i class="fas fa-calendar-times text-danger fs-6"></i>
        </a>
    @elseif($row->schedule_date > \Carbon\Carbon::now()->format('Y-m-d'))
        <a href="javascript:void(0)" data-id="{{ $row->id }}"
           class="btn-bg-light btn-sm edit-btn cancel-scheduled-event btn p-1 text-danger"
           data-bs-custom-class="tooltip-dark" data-bs-placement="bottom"
           title="{{ __('messages.schedule_event.cancel_schedule_event') }}">
            <i class="fas fa-calendar-times text-danger fs-6"></i>
        </a>
    @endif
@endif
<a href="{{ route('scheduled-events.show', $row->id) }}" title="<?php echo __('messages.common.view') ?>"
   class="btn p-1 text-primary">
    <i class="fas fa-eye fs-6"></i>
</a>
</div>