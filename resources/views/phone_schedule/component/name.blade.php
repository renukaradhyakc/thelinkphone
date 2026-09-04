@if($party && trim($party->full_name ?? '') !== '')
    <div style="display: flex; align-items: center; min-height: 31px; white-space: nowrap !important;">
        {{ $party->full_name }}
    </div>
@else
    <div style="display: flex; align-items: center; min-height: 31px;">—</div>
@endif