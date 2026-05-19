<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    @if(Route::currentRouteName() == 'events.edit')
        <meta name="turbo-visit-control" content="reload"/>
    @endif
    <title>@yield('title') | {{ getSettingData()['application_name'] }}</title>
    <!-- Favicon -->
    <link rel="icon" href="{{ asset(getSettingData()['favicon']) }}" type="image/png">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- General CSS Files -->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/new-theme.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/third-party.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ mix('assets/css/pages.css') }}">
    @yield('page_css')
    @livewireStyles
    @yield('css')

    {{-- scripts --}}
    <script src="{{ asset('vendor/livewire/livewire.js') }}"></script>
    <script data-turbolinks-eval="false" data-turbo-eval="false">
        let currentRouteName = "{{ Route::currentRouteName() }}";
    </script>
    @include('livewire.livewire-turbo')
    <script src="https://cdn.jsdelivr.net/gh/livewire/turbolinks@v0.1.x/dist/livewire-turbolinks.js"
            data-turbolinks-eval="false" data-turbo-eval="false"></script>
    <script src="{{ asset('assets/js/new-theme.js') }}"></script>
    <script src="{{ asset('assets/js/third-party.js') }}"></script>
    <script src="{{ mix('assets/js/pages.js') }}"></script>
    <script src="{{ asset('messages.js') }}"></script>
    @routes
    @yield('page_js')
    @yield('scripts')
    <script data-turbo-eval="false">
        let womanAvatar = '{{ url(asset('web/media/avatars/female.png')) }}'
        let manAvatar = '{{ url(asset('web/media/avatars/male.png')) }}'
        let sweetAlertIcon = "{{ asset('images/remove.png') }}"
        let deleteMsg = "{{ __('messages.placeholder.deleted') }}"
        let hasBeenDeleted = "{{ __('messages.has_been_deleted') }}"
        let eventUrl = "{{ Request::is('events*') }}"
        if (eventUrl !== '') {
            let phoneNo = ''
        }

        let currentLocale = "{{ Config::get('app.locale') }}"
        if (currentLocale == '') {
            currentLocale = 'en'
        }
        Lang.setLocale(currentLocale)
        let defaultImage = '{{asset('web/media/avatars/male.png')}}'
        let defaultCountryCodeValue = "{{ getSettingData()['default_country_code'] }}"
    </script>
    <script data-turbolinks-eval="false" data-turbo-eval="false">
        document.addEventListener('turbo:load', function () {
            currentRouteName = "{{ Route::currentRouteName() }}";
        })
    </script>
</head>
<!-- Meta Pixel Code -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];
t=b.createElement(e);t.async=!0;
t.src=v;
s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)
}(window, document,'script','https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '934631735570751');
fbq('track', 'PageView');
</script>
<noscript>
  <img height="1" width="1" style="display:none"
       alt="Meta Pixel tracking image for CallaLink website analytics and conversion measurement"
       src="https://www.facebook.com/tr?id=934631735570751&ev=PageView&noscript=1"/>
</noscript>
<!-- End Meta Pixel Code -->
<body class="custom-overflow-x-hidden">
<div class="d-flex flex-column flex-root">
    <div class="d-flex flex-row flex-column-fluid">
        @include('layouts.sidebar')
        <div class="wrapper d-flex flex-column flex-row-fluid custom-overflow-x-hidden">
            <div class='container-fluid d-flex align-items-stretch justify-content-between px-0'>
                @include('layouts.header')
            </div>
            <div class="content d-flex flex-column flex-column-fluid pt-7">
                @yield('header_toolbar')
                <div class="flex-column-fluid">
                    @if(getLogInUser()->hasRole('user') && isSubscriptionExpired()['status'])
                        <div class="alert alert-warning mx-7">
                            <div class="d-flex align-items-center">
                                <i class="fa-solid fa-face-meh me-5 text-white"></i>
                                <span class="text-white">{{ isSubscriptionExpired()['message'] }}</span>
                            </div>
                        </div>
                    @endif
                    @yield('content')
                </div>
            </div>
            <div class='container-fluid'>
                @include('layouts.footer')
            </div>
        </div>
    </div>
</div>
@include('profile.changePassword')
@include('profile.changeLanguage')
</body>
</html>
