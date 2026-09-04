@php
    $party = isset($component) && $component->direction === 'given_to_me'
        ? ($row->event->user ?? null)
        : ($row->otherPartyByPhone ?? null);

    $callalinkUrl = $party && !empty($party->domain_url)
        ? rtrim(config('app.url'), '/') . '/call/' . ltrim($party->domain_url, '/')
        : null;
@endphp
@if($callalinkUrl)
    <div class="d-inline-block align-top">
        <a href="{{ $callalinkUrl }}" target="_blank" rel="noopener noreferrer">
            {{ $callalinkUrl }}
        </a>
    </div>
@else
    <div class="ps-3">—</div>
@endif