@component('mail::layout')
    {{-- Header --}}
    @slot('header')
        @component('mail::header', ['url' => config('app.url')])
            <h1 style="display: inline-block">{{ getSettingData()['application_name'] }}</h1>
        @endcomponent
    @endslot
    {{-- Body --}}
    <div>
        <h2>Dear, <b>{{ $name }}</b></h2>
        <p>We hope you're doing well.</p>
        <p>We wanted to remind you that your next appointment within 5 minutes with <b>{{ $eventName }}</b> is scheduled for <b>{{ $eventScheduleDate }}</b> and <b>{{ $eventScheduleTime }}</b>.</p>
        <p>We look forward to seeing you then.</p>
    </div>
    {{-- Footer --}}
    @slot('footer')
        @component('mail::footer')
            <h6>© {{ date('Y') }} {{ getSettingData()['application_name'] }}.</h6>
        @endcomponent
    @endslot
@endcomponent
