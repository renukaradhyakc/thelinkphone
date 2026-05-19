<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>@yield('title') | {{ getSettingData()['application_name'] }}</title>

    <!-- Favicon -->
    <link rel="icon" href="{{ asset(getSettingData()['favicon']) }}" type="image/png">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Fonts -->
    <link rel="stylesheet" href="//fonts.googleapis.com/css?family=Poppins:300,400,500,600,700"/>

    <!-- General CSS Files -->
    <link href="{{ asset('web/plugins/global/plugins.bundle.css') }}" rel="stylesheet" type="text/css"/>
    <link href="{{ asset('web/css/style.bundle.css') }}" rel="stylesheet" type="text/css"/>
    <!-- CSS Libraries -->
    @yield('page_css')
    @yield('css')
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
<body data-bs-offset="200"
      class="bg-white position-relative header-fixed header-tablet-and-mobile-fixed toolbar-enabled toolbar-fixed toolbar-tablet-and-mobile-fixed aside-enabled aside-fixed">
@stack('sidebar_js')
<div class="main-content">

    @yield('content')

    <footer>
        <div class="container-fluid padding-0">
        </div>
    </footer>
</div>

<!-- Scripts -->
<script src="{{ asset('web/plugins/global/plugins.bundle.js') }}"></script>
<script src="{{ asset('web/js/scripts.bundle.js') }}"></script>
<script src="{{ asset('web/plugins/custom/fslightbox/fslightbox.bundle.js') }}"></script>
<script src="{{ asset('web/plugins/custom/typedjs/typedjs.bundle.js') }}"></script>
<script src="{{ asset('web/js/custom/landing.js') }}"></script>
<script src="{{ asset('web/js/custom/pages/company/pricing.js') }}"></script>

@yield('page_js')
@yield('scripts')
<script>
    $(document).ready(function () {
        $('.alert').delay(5000).slideUp(300);
    });
</script>
</body>
</html>
