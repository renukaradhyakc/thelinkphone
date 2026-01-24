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
        <p>A new event has been scheduled.</p>
        <p>Event: <b>{{ $eventName }}</b></p>
        <p>Invitee: <b>{{ $name }}</b></p>
        <p>Invitee Email: <b>{{ $email }}</b></p>
        <p>Event Date/Time:</p>
        <p><b>{{ $eventScheduleDateTime }}</b></p>
        <a href="{{ $confirmScheduleEventUrl }}"
        {{ $styleCss }}="
        padding: 7px 15px;text-decoration: none;font-size: 14px;background-color: #50cd89;font-weight: 500;border: none;border-radius: 8px;color: white;text-align: center
        ">Confirm
        Scheduled Event Download Here</a>
    </div>
    {{-- Footer --}}
    @slot('footer')
        @component('mail::footer')
            <h6>© {{ date('Y') }} {{ getSettingData()['application_name'] }}.</h6>
        @endcomponent
    @endslot
@endcomponent
