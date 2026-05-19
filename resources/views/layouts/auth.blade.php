<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>@yield('title') | {{ getSettingData()['application_name'] }}</title>
    <!-- Favicon -->
    <link rel="icon" href="{{ asset(getSettingData()['favicon']) }}" type="image/png">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- General CSS Files -->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/new-theme.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/third-party.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ mix('assets/css/pages.css') }}">
    <!-- CSS Libraries -->
    @livewireStyles
    @stack('css')
    @yield('page_css')
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
@php
    $styleCss = 'style';
@endphp
<body class="header-fixed header-tablet-and-mobile-fixed toolbar-enabled toolbar-fixed toolbar-tablet-and-mobile-fixed aside-enabled aside-fixed">
<div class="d-flex flex-column flex-root">
    <div class="d-flex flex-column flex-column-fluid bgi-position-y-bottom position-x-center bgi-no-repeat bgi-size-contain bgi-attachment-fixed authImage">
        @yield('content')
    </div>
</div>
<footer>
    <div class="container-fluid padding-0">
        <div class="row align-items-center justify-content-center">
            <div class="col-xl-6">
                <div class="copyright text-center text-muted">
                    All rights reserved &copy; {{ date('Y') }}
                    <a href="{{ config('app.url') }}" class="font-weight-bold ml-1"
                       target="_blank">{{ getSettingData()['application_name'] }}</a>
                </div>
            </div>
        </div>
    </div>
</footer>
<!-- Scripts -->
<script src="{{ asset('vendor/livewire/livewire.js') }}"></script>
@include('livewire.livewire-turbo')
<script src="{{ asset('messages.js') }}"></script>
<script>
    let currentLocale = "{{ Config::get('app.locale') }}"
    if (currentLocale == '') {
        currentLocale = 'en'
    }

    Lang.setLocale(currentLocale)

    let defaultCountryCodeValue = "{{ getSettingData()['default_country_code'] }}"
</script>
<script src="{{ asset('assets/js/new-theme.js') }}"></script>
<script src="{{ asset('assets/js/third-party.js') }}"></script>
<script src="{{ asset('assets/js/evo-calendar.js') }}"></script>
<script src="{{ asset('assets/js/front-pages.js') }}"></script>
@routes
@stack('scripts')
@yield('page_js')
<script>
    $(document).ready(function () {
        $('.alert').delay(5000).slideUp(300)
    })
</script>
</body>
</html>

