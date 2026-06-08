@extends('layouts.app')

@section('title', 'Bill Receipt')

@section('page_css')
<meta name="turbo-cache-control" content="no-cache">

<link rel="stylesheet" href="{{ asset('assets/css/loyalty/bill.css') }}">
@endsection

@section('content')
<div class="container-fluid" id="loy-show-page">

    {{-- header --}}
    @include('loyalty.bill.partials.header')

    {{-- skeleton --}}
    @include('loyalty.bill.partials.skeleton')

    {{-- content --}}
    @include('loyalty.bill.partials.content')

</div>
@endsection

@section('scripts')
<script>
    window.BILL_ID = {{ $billId }};
</script>

<script src="{{ asset('js/loyalty/bill/helpers.js') }}" defer></script>
<script src="{{ asset('js/loyalty/bill/api.js') }}" defer></script>
<script src="{{ asset('js/loyalty/bill/page.js') }}" defer></script>

<!-- <script src="{{ asset('assets/js/loyalty/bill.js') }}" defer></script> -->
@endsection