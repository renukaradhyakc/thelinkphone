@if($party && trim($party->email ?? '') !== '')
    <div class="d-inline-block align-top">
        <a href="mailto:{{ $party->email }}" target="_blank" rel="noopener noreferrer">
            {{ $party->email }}
        </a>
    </div>
@else
    <div class="ps-3">—</div>
@endif