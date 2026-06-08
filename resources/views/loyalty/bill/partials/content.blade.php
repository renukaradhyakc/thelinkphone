<div id="loy-content" style="display:none">
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="loy-receipt">
                <div class="loy-rh">
                    <div class="loy-inv-badge" id="loy-inv-badge" style="display:none">
                        <small>Invoice</small>
                        <span id="loy-inv-num"></span>
                    </div>
                    <div class="loy-rh-store" id="loy-store-name"></div>
                    <div class="loy-rh-meta" id="loy-rh-meta"></div>
                </div>
                <div class="loy-info-grid" id="loy-info-grid"></div>
                <div class="loy-items-section" id="loy-items-section"></div>
                <div class="loy-totals" id="loy-totals"></div>
            </div>
        </div>
        <div class="col-lg-4">

            {{-- points --}}
            <div class="loy-side-card">
                <div class="loy-side-label">Points Earned</div>
                <div class="loy-pts-big" id="loy-pts-num">0</div>
                <div class="text-muted fs-7 mt-1">loyalty points</div>
                <div class="text-muted mt-2" style="font-size:11px;">10 pts per ₹100 spent</div>
            </div>

            {{-- status --}}
            <div class="loy-side-card">
                <div class="loy-side-label">Bill Status</div>
                <div id="loy-status-block"></div>
            </div>

            {{-- confidence --}}
            <div class="loy-side-card" id="loy-conf-card" style="display:none">
                <div class="loy-side-label">OCR Confidence</div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted fs-7">Accuracy</span>
                    <span id="loy-conf-pct" class="fw-bolder fs-5"></span>
                </div>
                <div class="loy-conf-bar-track"><div id="loy-conf-bar" class="loy-conf-bar-fill" style="width:0%"></div></div>
                <div id="loy-conf-label" class="text-muted mt-2" style="font-size:11px;"></div>
            </div>

            {{-- download --}}
            <div class="loy-side-card">
                <div class="loy-side-label">Original Bill</div>
                <a id="loy-dl-link" href="#" class="btn btn-success w-100 fw-bold" target="_blank">
                    <i class="fa-solid fa-download me-1"></i> Download Original
                </a>
                <div class="text-muted text-center mt-2" style="font-size:11px;">Downloads the uploaded file</div>
            </div>

        </div>
    </div>
</div>