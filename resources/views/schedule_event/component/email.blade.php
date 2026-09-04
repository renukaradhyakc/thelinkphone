@php
    $email = isset($component) && $component->direction === 'given_to_me'
        ? ($row->event->user->email ?? null)
        : ($row->email ?? null);
@endphp
@if(!empty(trim($email ?? '')))
    <div class="d-inline-block align-top">
        <a href="mailto:{{ $email }}" target="_blank" rel="noopener noreferrer">
            {{ $email }}
        </a>
    </div>
@else
    <div class="ps-3">—</div>
@endif