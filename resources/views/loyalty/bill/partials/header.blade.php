<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">

    <div class="d-flex align-items-center gap-2">
        <div class="text-muted fs-7 fw-bold">
            <a href="{{ route('loyalty.bills.index') }}" class="text-muted">MY BILLS</a>
            <span class="mx-1">/</span>
            <span id="loy-hd-bill">#{{ $billId }}</span>
        </div>

        <h2 class="mb-0 fw-bolder ms-2">Bill Receipt</h2>
    </div>

    <div id="loy-action-btns"></div>

</div>