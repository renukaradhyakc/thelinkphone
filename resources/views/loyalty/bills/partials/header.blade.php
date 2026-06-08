<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div class="d-flex align-items-center gap-3">
        <div class="loy-icon-box">
            <i class="fa-solid fa-receipt"></i>
        </div>

        <div>
            <div class="text-muted fw-bold loy-title-small">Loyalty</div>
            <h2 class="mb-0 fw-bolder">My Bills</h2>
        </div>
    </div>

    <div class="d-flex align-items-center gap-3">
        <span id="loy-bill-count" class="text-muted fw-bold"></span>

        <a href="{{ route('loyalty.dashboard') }}" class="btn btn-primary btn-sm fw-bold">
            <i class="fa-solid fa-star me-1"></i> Dashboard
        </a>
    </div>
</div>