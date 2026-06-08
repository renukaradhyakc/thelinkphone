<div class="d-flex align-items-center gap-2 mb-4 flex-wrap">

    <span class="loy-filter-label">Filter:</span>

    <button class="loy-filter-btn active" data-status="">All Status</button>
    <button class="loy-filter-btn" data-status="done">Approved</button>
    <button class="loy-filter-btn" data-status="pending">Pending</button>
    <button class="loy-filter-btn" data-status="review">Review</button>
    <button class="loy-filter-btn" data-status="failed">Failed</button>

    <span class="loy-filter-label ms-2">Sort:</span>

    <select id="loy-sort" class="form-select form-select-sm loy-sort">
        <option value="latest">Latest First</option>
        <option value="amount_desc">Highest Amount</option>
        <option value="amount_asc">Lowest Amount</option>
    </select>

    <button id="loy-refresh" class="btn btn-sm btn-light ms-auto fw-bold">
        <i class="fa-solid fa-rotate-right me-1"></i> Refresh
    </button>
</div>