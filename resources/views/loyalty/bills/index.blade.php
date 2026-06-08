@extends('layouts.app')

@section('title', 'My Bills')

@section('page_css')
<meta name="turbo-cache-control" content="no-cache">
<link rel="stylesheet" href="{{ asset('assets/css/loyalty/bills.css') }}">
@endsection

@section('content')
<div class="container-fluid">

    @include('loyalty.bills.partials.header')

    @include('loyalty.bills.partials.filters')

    @include('loyalty.bills.partials.table')

    @include('loyalty.bills.partials.pagination')

    @include('loyalty.bills.partials.upload')

</div>
@endsection

@section('scripts')
<script src="{{ asset('js/loyalty/bills/helpers.js') }}" defer></script>
<script src="{{ asset('js/loyalty/bills/api.js') }}" defer></script>
<script src="{{ asset('js/loyalty/bills/list.js') }}" defer></script>
<script src="{{ asset('js/loyalty/bills/upload.js') }}" defer></script>
<script src="{{ asset('js/loyalty/bills/init.js') }}" defer></script>

<!-- <script src="{{ asset('assets/js/loyalty/bills.js') }}" defer></script> -->
@endsection