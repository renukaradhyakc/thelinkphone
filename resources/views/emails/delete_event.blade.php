@component('mail::layout')
    {{-- Header --}}
    @slot('header')
        @php
            $styleCss = 'style';
        @endphp
        @component('mail::header', ['url' => config('app.url')])
            <h1 {{ $styleCss }}="display: inline-block">{{ getSettingData()['application_name'] }}</h1>
        @endcomponent
    @endslot
    {{-- Body --}}
    <div>
        <h2>Hi, <b>{{ $userName }}</b></h2>
        <p>Please note that your appointment that you have scheduled has been cancelled, as the event is deleted by the
            owner.</p>
        <p>Event: <b>{{ $eventName }}</b></p>
        <p>Scheduled Event Date: <b>{{ $slotDate }}</b></p>
        <p>Scheduled Event Time: <b>{{ $timeSlot }}</b></p>
        <p>Thanks</b></p>
    </div>
    {{-- Footer --}}
    @slot('footer')
        @component('mail::footer')
            <h6>© {{ date('Y') }} {{ getSettingData()['application_name'] }}.</h6>
        @endcomponent
    @endslot
@endcomponent
