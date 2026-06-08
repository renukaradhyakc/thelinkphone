<div class="loy-section-hd mb-3">
    <div>
        <h3>Recent Bills</h3>
        <p>Latest 5 uploads</p>
    </div>

    <a href="{{ route('loyalty.bills.index') }}"
       class="text-primary fw-bold fs-7">
        View all
    </a>
</div>

<div class="loy-tbl-wrap mb-5">

    <div class="loy-tbl-head">
        <span>Vendor</span>
        <span>Date</span>
        <span>Amount</span>
        <span>Pts</span>
        <span>Status</span>
    </div>

    <div id="loy-recent-body">
        <div class="loy-empty-row">
            <i class="fa-solid fa-receipt"
               style="opacity:.2;font-size:24px;display:block;margin-bottom:8px;">
            </i>

            <p>Loading…</p>
        </div>
    </div>

</div>