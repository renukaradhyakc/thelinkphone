<div id="loy-loading">
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="loy-receipt">
                <div class="loy-rh" style="background:#e8e8f4;">
                    <div class="loy-sk mb-2" style="height:22px;width:200px;"></div>
                    <div class="loy-sk" style="height:13px;width:280px;"></div>
                </div>
                <div class="loy-info-grid">
                    @for($i=0;$i<4;$i++)<div><div class="loy-sk mb-2" style="height:10px;width:70px;"></div><div class="loy-sk" style="height:16px;width:130px;"></div></div>@endfor
                </div>
                <div class="loy-items-section">
                    @for($i=0;$i<4;$i++)<div class="loy-sk mb-2" style="height:32px;"></div>@endfor
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="loy-side-card"><div class="loy-sk" style="height:110px;border-radius:10px;"></div></div>
            <div class="loy-side-card"><div class="loy-sk" style="height:80px;border-radius:10px;"></div></div>
        </div>
    </div>
</div>