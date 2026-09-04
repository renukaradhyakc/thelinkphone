@php($parser = app(\App\Services\TimeParser::class))
@if(isset($row->_singleRange))
    <span class="badge" style="background-color: #e7f5ff; color: #1c7ed6; font-weight: 500; padding: 6px 12px; border-radius: 4px; display: inline-block; min-width: 150px; text-align: center;">
        {{ optional($parser->parseFlexibleTime($row->_singleRange['from'], now('Asia/Kolkata')))->format('h:i A') }}
        &ndash;
        {{ optional($parser->parseFlexibleTime($row->_singleRange['to'], now('Asia/Kolkata')))->format('h:i A') }}
    </span>
@else
    <span class="text-muted">{{ __('messages.schedule.no_more_slots_today') }}</span>
@endif