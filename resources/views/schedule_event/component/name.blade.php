@if(isset($component) && $component->direction === 'given_to_me')
    <div class="d-inline-block align-top" style="white-space: nowrap !important;">
        {{ $row->event->user->full_name ?? '—' }}
    </div>
@else
    <div class="d-inline-block align-top" style="white-space: nowrap !important;">
        {{ $row->name }}
    </div>
@endif