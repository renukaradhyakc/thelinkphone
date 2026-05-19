@extends('layouts.app')

@section('title', 'Loyalty Points')

@section('content')
<div class="container-fluid">

    {{-- HERO --}}
    <div class="card bg-primary text-white shadow-sm mb-4">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <h6 class="text-uppercase opacity-75 mb-2">Your Loyalty Rewards</h6>

                <h1 id="loy-total-pts" class="fw-bold mb-0">
                    <span class="spinner-border spinner-border-sm"></span>
                </h1>

                <small class="opacity-75">Points earned from verified bill uploads</small>
            </div>

            <div class="text-end">
                <div class="bg-white bg-opacity-10 rounded-circle p-3 d-inline-flex">
                    <span style="font-size: 32px;">🪙</span>
                </div>
                <div class="mt-2">
                    <span class="badge bg-warning text-dark">10 pts per ₹100 spent</span>
                </div>
            </div>
        </div>
    </div>

    {{-- STATS --}}
    <div class="row g-3" id="loy-stats-row">

        @for($i=0; $i<5; $i++)
        <div class="col-6 col-md-4 col-xl-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="placeholder-glow">
                        <span class="placeholder col-4"></span>
                        <span class="placeholder col-6"></span>
                    </div>
                </div>
            </div>
        </div>
        @endfor

    </div>

    {{-- RECENT BILLS --}}
    <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
        <h5 class="mb-0">Recent Bills</h5>

        <a href="{{ route('loyalty.bills.index') }}" class="btn btn-primary btn-sm">
            View All
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Vendor</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Points</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>

                    <tbody id="loy-recent-tbody">
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                Loading bills...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

</div>
@endsection

@section('scripts')
<script src="{{ asset('resources/assets/js/loyalty/dashboard.js') }}"></script>
@endsection