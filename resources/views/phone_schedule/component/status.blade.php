@if(isset($row->_singleRange))
    @if($row->_singleRange['active'])
        <span class="badge bg-success">Active</span>
    @else
        <span class="badge bg-warning text-white">Upcoming</span>   
    @endif
@else
    <span class="badge bg-light text-muted">{{ __('messages.schedule.no_more_slots_today') }}</span>
@endif