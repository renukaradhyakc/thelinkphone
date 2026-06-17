@extends('layouts.app')
@section('title', 'Loyalty — Your Rewards')

@section('page_css')
<meta name="turbo-cache-control" content="no-cache">
<link rel="stylesheet" href="{{ asset('css/loyalty/dashboard.css') }}">
@endsection

@section('content')
<div class="container-fluid">

    {{-- page header --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <div class="text-muted fw-bold fs-7 text-uppercase mb-1">Loyalty Program</div>
            <h2 class="mb-0 fw-bolder">Your Rewards</h2>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('loyalty.bills.index') }}" class="btn btn-light btn-sm fw-bold">
                <i class="fa-solid fa-clock-rotate-left me-1"></i> History
            </a>

            <button class="btn btn-primary btn-sm fw-bold"
                    data-bs-toggle="modal"
                    data-bs-target="#uploadModal">
                <i class="fa-solid fa-upload me-1"></i> Upload Bill
            </button>
        </div>
    </div>

    {{-- hero --}}
    @include('loyalty.dashboard.partials.hero')

    {{-- stats --}}
    @include('loyalty.dashboard.partials.stats')

    {{-- recent bills --}}
    @include('loyalty.dashboard.partials.recent-bills')

</div>

{{-- upload modal --}}
@include('loyalty.dashboard.partials.upload-modal')

@endsection

@section('scripts')
<script src="{{ asset('js/loyalty/dashboard/helpers.js') }}"></script>
<script src="{{ asset('js/loyalty/dashboard/dashboard.js') }}"></script>
<script src="{{ asset('js/loyalty/dashboard/upload.js') }}"></script>

<!-- <script src="{{ asset('assets/js/loyalty/dashboard.js') }}" defer></script> -->
@endsection