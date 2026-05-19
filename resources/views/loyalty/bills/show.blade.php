@extends('layouts.app')

@section('title', 'Bill Receipt')

@section('page_css')
<style>
    :root {
        --loy-primary: #6259ca;
        --loy-accent:  #f6b600;
        --loy-success: #22c984;
        --loy-danger:  #e74c3c;
        --loy-border:  #e8e8f7;
        --loy-muted:   #6c757d;
        --loy-text:    #2d2d44;
        --loy-radius:  14px;
        --loy-shadow:  0 4px 24px rgba(98,89,202,0.10);
        --loy-card-bg: #ffffff;
    }

    .loy-page { padding: 0 8px; }

    /* ── Page Header ── */
    .loy-page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 24px;
        flex-wrap: wrap;
        gap: 12px;
    }
    .loy-page-header h2 {
        font-size: 20px;
        font-weight: 800;
        color: var(--loy-text);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .loy-btn-outline {
        border: 1.5px solid var(--loy-border);
        background: #fff;
        color: var(--loy-text);
        padding: 7px 16px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: border-color 0.14s, color 0.14s;
    }
    .loy-btn-outline:hover { border-color: var(--loy-primary); color: var(--loy-primary); text-decoration: none; }
    .loy-btn-primary {
        background: var(--loy-primary);
        color: #fff;
        border: none;
        padding: 8px 18px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: background 0.15s;
    }
    .loy-btn-primary:hover { background: #504db0; color: #fff; text-decoration: none; }
    .loy-btn-dl {
        background: var(--loy-success);
        color: #fff;
        border: none;
        padding: 8px 18px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: background 0.15s;
    }
    .loy-btn-dl:hover { background: #16a57a; color: #fff; text-decoration: none; }

    /* ── Layout: 2 columns on desktop ── */
    .loy-receipt-layout {
        display: grid;
        grid-template-columns: 1fr 340px;
        gap: 24px;
        align-items: start;
    }
    @media (max-width: 992px) {
        .loy-receipt-layout { grid-template-columns: 1fr; }
    }

    /* ── Receipt Card (main) ── */
    .loy-receipt-card {
        background: var(--loy-card-bg);
        border: 1px solid var(--loy-border);
        border-radius: var(--loy-radius);
        box-shadow: var(--loy-shadow);
        overflow: hidden;
    }

    /* ── Receipt Header (mimics thermal receipt top) ── */
    .loy-receipt-header {
        background: linear-gradient(135deg, var(--loy-primary) 0%, #8b5cf6 100%);
        padding: 28px 32px;
        color: #fff;
        position: relative;
        overflow: hidden;
    }
    .loy-receipt-header::after {
        content: '';
        position: absolute;
        bottom: -1px; left: 0; right: 0;
        height: 20px;
        background: var(--loy-card-bg);
        clip-path: polygon(0 100%, 2% 0, 4% 100%, 6% 0, 8% 100%, 10% 0, 12% 100%, 14% 0, 16% 100%, 18% 0, 20% 100%, 22% 0, 24% 100%, 26% 0, 28% 100%, 30% 0, 32% 100%, 34% 0, 36% 100%, 38% 0, 40% 100%, 42% 0, 44% 100%, 46% 0, 48% 100%, 50% 0, 52% 100%, 54% 0, 56% 100%, 58% 0, 60% 100%, 62% 0, 64% 100%, 66% 0, 68% 100%, 70% 0, 72% 100%, 74% 0, 76% 100%, 78% 0, 80% 100%, 82% 0, 84% 100%, 86% 0, 88% 100%, 90% 0, 92% 100%, 94% 0, 96% 100%, 98% 0, 100% 100%);
    }
    .loy-receipt-store {
        font-size: 22px;
        font-weight: 800;
        margin-bottom: 4px;
        letter-spacing: -0.5px;
    }
    .loy-receipt-meta-top {
        font-size: 13px;
        opacity: 0.75;
        display: flex;
        gap: 16px;
        flex-wrap: wrap;
        margin-top: 8px;
    }
    .loy-receipt-invoice {
        position: absolute;
        top: 20px; right: 24px;
        background: rgba(255,255,255,0.18);
        backdrop-filter: blur(4px);
        border: 1px solid rgba(255,255,255,0.25);
        border-radius: 8px;
        padding: 6px 14px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.5px;
    }

    /* ── Receipt Body ── */
    .loy-receipt-body { padding: 28px 32px 8px; }

    /* ── Info Grid ── */
    .loy-info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-bottom: 24px;
    }
    @media (max-width: 576px) { .loy-info-grid { grid-template-columns: 1fr; } }
    .loy-info-item label {
        display: block;
        font-size: 10px;
        font-weight: 700;
        color: var(--loy-muted);
        text-transform: uppercase;
        letter-spacing: 0.8px;
        margin-bottom: 4px;
    }
    .loy-info-item span {
        font-size: 14px;
        font-weight: 600;
        color: var(--loy-text);
    }

    /* ── Divider ── */
    .loy-dashed { border: none; border-top: 2px dashed var(--loy-border); margin: 16px 0; }

    /* ── Line Items Table ── */
    .loy-items-title {
        font-size: 11px;
        font-weight: 700;
        color: var(--loy-muted);
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 12px;
    }
    .loy-items-table { width: 100%; border-collapse: collapse; }
    .loy-items-table thead th {
        font-size: 11px;
        font-weight: 700;
        color: var(--loy-muted);
        text-transform: uppercase;
        letter-spacing: 0.6px;
        padding: 8px 10px;
        border-bottom: 1px solid var(--loy-border);
        text-align: left;
    }
    .loy-items-table thead th:last-child,
    .loy-items-table tbody td:last-child { text-align: right; }
    .loy-items-table tbody tr { border-bottom: 1px solid #f4f4f8; }
    .loy-items-table tbody tr:last-child { border-bottom: none; }
    .loy-items-table tbody td {
        padding: 10px 10px;
        font-size: 13px;
        color: var(--loy-text);
        vertical-align: top;
    }
    .loy-item-desc { font-weight: 600; }
    .loy-item-sub  { font-size: 11px; color: var(--loy-muted); margin-top: 2px; }
    .loy-item-elig {
        display: inline-block;
        width: 8px; height: 8px;
        border-radius: 50%;
        margin-right: 4px;
        vertical-align: middle;
    }
    .loy-item-elig.yes { background: var(--loy-success); }
    .loy-item-elig.no  { background: #ddd; }

    /* ── Total Footer ── */
    .loy-receipt-footer {
        background: #f8f8fd;
        border-top: 1px solid var(--loy-border);
        padding: 20px 32px;
    }
    .loy-total-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 6px;
    }
    .loy-total-row span:first-child { font-size: 13px; color: var(--loy-muted); font-weight: 500; }
    .loy-total-row span:last-child  { font-size: 13px; font-weight: 700; color: var(--loy-text); }
    .loy-total-row.grand span:first-child { font-size: 16px; font-weight: 800; color: var(--loy-text); }
    .loy-total-row.grand span:last-child  { font-size: 22px; font-weight: 900; color: var(--loy-primary); }

    /* ── Side Panel ── */
    .loy-side-panel { display: flex; flex-direction: column; gap: 16px; }
    .loy-side-card {
        background: var(--loy-card-bg);
        border: 1px solid var(--loy-border);
        border-radius: var(--loy-radius);
        box-shadow: var(--loy-shadow);
        padding: 20px 22px;
    }
    .loy-side-card h6 {
        font-size: 12px;
        font-weight: 700;
        color: var(--loy-muted);
        text-transform: uppercase;
        letter-spacing: 0.8px;
        margin-bottom: 16px;
    }

    /* ── Points Box ── */
    .loy-pts-box {
        background: linear-gradient(135deg, var(--loy-primary), #8b5cf6);
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        color: #fff;
        margin-bottom: 12px;
    }
    .loy-pts-box .big { font-size: 40px; font-weight: 900; line-height: 1; }
    .loy-pts-box .label { font-size: 12px; opacity: 0.75; margin-top: 4px; }

    /* ── Status Badge (large) ── */
    .loy-status-block {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 16px;
        border-radius: 10px;
        margin-bottom: 4px;
    }
    .loy-status-block.done      { background: rgba(34,201,132,0.08); }
    .loy-status-block.pending   { background: rgba(246,182,0,0.08); }
    .loy-status-block.failed    { background: rgba(231,76,60,0.08); }
    .loy-status-block.duplicate { background: rgba(108,117,125,0.08); }
    .loy-status-block.review    { background: rgba(13,110,253,0.08); }
    .loy-status-block.invalid   { background: rgba(231,76,60,0.06); }
    .loy-status-icon { font-size: 24px; }
    .loy-status-text strong { display: block; font-size: 14px; font-weight: 700; color: var(--loy-text); }
    .loy-status-text small { font-size: 11px; color: var(--loy-muted); }

    /* ── Confidence ── */
    .loy-conf-big {
        height: 8px;
        background: #eee;
        border-radius: 8px;
        overflow: hidden;
        margin-top: 8px;
    }
    .loy-conf-fill { height: 100%; border-radius: 8px; transition: width 0.8s ease; }

    /* ── Skeleton ── */
    .loy-skeleton {
        background: linear-gradient(90deg, #f0f0f8 25%, #e8e8f4 50%, #f0f0f8 75%);
        background-size: 200% 100%;
        animation: loy-shimmer 1.4s infinite;
        border-radius: 6px;
    }
    @keyframes loy-shimmer {
        0%   { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }
</style>
@endsection

@section('content')
<div class="container-fluid loy-page" id="loy-bill-page">

    {{-- ── Page Header ── --}}
    <div class="loy-page-header">
        <h2>
            <a href="{{ route('loyalty.bills.index') }}" class="loy-btn-outline" style="padding:7px 12px;">
                <i class="fa-solid fa-chevron-left"></i>
            </a>
            <i class="fa-solid fa-receipt" style="color:var(--loy-primary)"></i>
            Bill Receipt
        </h2>
        <div class="d-flex gap-2" id="loy-action-btns">
            {{-- JS renders download button --}}
        </div>
    </div>

    {{-- ── Loading State ── --}}
    <div id="loy-loading" class="loy-receipt-layout">
        <div class="loy-receipt-card">
            <div class="loy-receipt-header" style="background:#e8e8f4;">
                <div class="loy-skeleton" style="height:24px;width:200px;margin-bottom:8px;"></div>
                <div class="loy-skeleton" style="height:13px;width:280px;"></div>
            </div>
            <div class="loy-receipt-body">
                <div class="loy-info-grid">
                    @for($i=0;$i<4;$i++)
                    <div><div class="loy-skeleton" style="height:11px;width:80px;margin-bottom:6px;"></div>
                    <div class="loy-skeleton" style="height:16px;width:130px;"></div></div>
                    @endfor
                </div>
                <hr class="loy-dashed">
                @for($i=0;$i<4;$i++)
                <div class="loy-skeleton" style="height:36px;margin-bottom:8px;"></div>
                @endfor
            </div>
        </div>
        <div class="loy-side-panel">
            <div class="loy-side-card">
                <div class="loy-skeleton" style="height:110px;border-radius:12px;"></div>
            </div>
        </div>
    </div>

    {{-- ── Actual Content ── --}}
    <div id="loy-content" class="loy-receipt-layout" style="display:none;"></div>

</div>
@endsection

@section('scripts')
<script>
(function () {
    const billId = {{ $billId }};

    function fmtAmount(v) {
        return '₹' + parseFloat(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 });
    }

    function statusInfo(s) {
        const map = {
            done:      { icon: '✅', label: 'Approved & Verified',   sub: 'Points awarded' },
            pending:   { icon: '⏳', label: 'Pending Processing',     sub: 'Being reviewed' },
            failed:    { icon: '❌', label: 'Processing Failed',      sub: 'Contact support' },
            duplicate: { icon: '⊘',  label: 'Duplicate Detected',    sub: 'Already submitted' },
            review:    { icon: '👁', label: 'Under Manual Review',    sub: 'Admin is reviewing' },
            invalid:   { icon: '⚠️', label: 'Invalid Document',      sub: 'Not a valid receipt' },
        };
        return map[s] || { icon: '❓', label: s, sub: '' };
    }

    function confColor(c) {
        if (c == null) return '#ddd';
        const pct = c * 100;
        return pct >= 80 ? '#22c984' : pct >= 50 ? '#f6b600' : '#e74c3c';
    }

    async function load() {
        try {
            // Single fetch — show() returns bill + items + points together
            const billRes  = await fetch(`/bills/${billId}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            });

            if (!billRes.ok) throw new Error('Bill not found');
            const billJson  = await billRes.json();
            const b      = billJson.data;

            const itemsRes = await fetch(`/bills/${billId}/items?per_page=100`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            });

            if (!itemsRes.ok) throw new Error('Failed to load items');

            const itemsJson = await itemsRes.json();
            const items = itemsJson.data.items || [];

            b.points = b.points || 0;

            render(b, items);

        } catch (e) {
            console.error(e);
            
            document.getElementById('loy-loading').innerHTML = `
                <div style="grid-column:1/-1;text-align:center;padding:64px;color:var(--loy-muted);">
                    <div style="font-size:40px;margin-bottom:16px;">😕</div>
                    <p style="font-size:16px;font-weight:600;">Could not load bill.</p>
                    <a href="/loyalty/bills" class="loy-btn-primary mt-3">← Back to Bills</a>
                </div>`;
        }
    }

    function render(b, items) {
        const si       = statusInfo(b.status);
        const confPct  = b.confidence != null ? Math.round(b.confidence * 100) : null;
        const confCol  = confColor(b.confidence);

        // ── Action Buttons ──
        document.getElementById('loy-action-btns').innerHTML = `
            <a href="/bills/${billId}/file"
               class="loy-btn-dl" target="_blank">
                <i class="fa-solid fa-download"></i> Download Bill
            </a>`;

        // ── Main Receipt HTML ──
        const mainHtml = `
        <div class="loy-receipt-card">
            <div class="loy-receipt-header">
                ${b.vendor ? `<div class="loy-receipt-invoice">Invoice ${b.invoice_number || '#—'}</div>` : ''}
                <div class="loy-receipt-store">${b.vendor || 'Unknown Vendor'}</div>
                <div class="loy-receipt-meta-top">
                    <span><i class="fa-regular fa-calendar"></i> ${b.bill_date || '—'}</span>
                    <span><i class="fa-solid fa-hashtag"></i> Bill #${b.bill_id}</span>
                    ${b.provider ? `<span><i class="fa-solid fa-robot"></i> ${b.provider}</span>` : ''}
                </div>
            </div>

            <div class="loy-receipt-body">
                <div class="loy-info-grid">
                    <div class="loy-info-item">
                        <label>Bill Date</label>
                        <span>${b.bill_date || '—'}</span>
                    </div>
                    <div class="loy-info-item">
                        <label>Vendor</label>
                        <span>${b.vendor || '—'}</span>
                    </div>
                    <div class="loy-info-item">
                        <label>Invoice Number</label>
                        <span>${b.invoice_number || '—'}</span>
                    </div>
                    <div class="loy-info-item">
                        <label>OCR Provider</label>
                        <span style="text-transform:capitalize">${b.provider || '—'}</span>
                    </div>
                </div>

                <hr class="loy-dashed">

                ${items.length ? `
                <div class="loy-items-title">Line Items (${items.length})</div>
                <table class="loy-items-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th style="text-align:center">Qty</th>
                            <th style="text-align:right">Unit Price</th>
                            <th style="text-align:right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${items.map(item => `
                        <tr>
                            <td>
                                <span class="loy-item-elig ${item.is_eligible ? 'yes' : 'no'}"></span>
                                <span class="loy-item-desc">${item.description || '—'}</span>
                                ${item.hsn_code ? `<div class="loy-item-sub">HSN: ${item.hsn_code}</div>` : ''}
                                ${item.category ? `<div class="loy-item-sub">${item.category}</div>` : ''}
                            </td>
                            <td style="text-align:center">${item.quantity ?? 1}</td>
                            <td style="text-align:right">${item.unit_price ? fmtAmount(item.unit_price) : '—'}</td>
                            <td style="text-align:right;font-weight:700">${fmtAmount(item.line_total)}</td>
                        </tr>`).join('')}
                    </tbody>
                </table>
                <hr class="loy-dashed">
                ` : `
                <div style="text-align:center;padding:20px 0;color:var(--loy-muted);font-size:13px;">
                    <i class="fa-solid fa-list" style="opacity:0.3;font-size:24px;display:block;margin-bottom:8px;"></i>
                    No line items available for this bill.
                </div>
                <hr class="loy-dashed">
                `}
            </div>

            <div class="loy-receipt-footer">
                ${items.length ? `
                <div class="loy-total-row">
                    <span>Subtotal (${items.length} items)</span>
                    <span>${fmtAmount(items.reduce((s,i) => s + parseFloat(i.line_total||0), 0))}</span>
                </div>` : ''}
                <div class="loy-total-row grand">
                    <span>Total Amount</span>
                    <span>${fmtAmount(b.amount)}</span>
                </div>
            </div>
        </div>`;

        // ── Side Panel HTML ──
        const sideHtml = `
        <div class="loy-side-panel">

            {{-- Points card --}}
            <div class="loy-side-card">
                <h6>Points Earned</h6>
                <div class="loy-pts-box">
                    <div class="big" id="loy-pts-anim">0</div>
                    <div class="label">loyalty points</div>
                </div>
                <p style="font-size:11px;color:var(--loy-muted);margin:0;text-align:center;">
                    10 pts per ₹100 spent
                </p>
            </div>

            {{-- Status card --}}
            <div class="loy-side-card">
                <h6>Bill Status</h6>
                <div class="loy-status-block ${b.status}">
                    <div class="loy-status-icon">${si.icon}</div>
                    <div class="loy-status-text">
                        <strong>${si.label}</strong>
                        <small>${si.sub}</small>
                    </div>
                </div>
            </div>

            {{-- Confidence card --}}
            ${confPct != null ? `
            <div class="loy-side-card">
                <h6>OCR Confidence</h6>
                <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                    <span style="font-size:13px;color:var(--loy-muted)">Accuracy</span>
                    <span style="font-size:14px;font-weight:800;color:${confCol}">${confPct}%</span>
                </div>
                <div class="loy-conf-big">
                    <div class="loy-conf-fill" id="loy-conf-fill" style="width:0%;background:${confCol}"></div>
                </div>
                <p style="font-size:11px;color:var(--loy-muted);margin:8px 0 0;">
                    ${confPct >= 80 ? 'High confidence read' : confPct >= 50 ? 'Moderate confidence' : 'Low confidence — manual check advised'}
                </p>
            </div>` : ''}

            {{-- Download card --}}
            <div class="loy-side-card">
                <h6>Original Bill</h6>
                <a href="/bills/${billId}/file" class="loy-btn-dl" style="width:100%;justify-content:center;" target="_blank">
                    <i class="fa-solid fa-download"></i> Download Original
                </a>
                <p style="font-size:11px;color:var(--loy-muted);margin:10px 0 0;text-align:center;">
                    Downloads the original uploaded file
                </p>
            </div>

        </div>`;

        // ── Inject ──
        const content = document.getElementById('loy-content');
        content.innerHTML = mainHtml + sideHtml;

        document.getElementById('loy-loading').style.display = 'none';
        content.style.display = 'grid';

        // ── Animate points (comes directly from b.points) ──
        const ptsEl = document.getElementById('loy-pts-anim');
        const pts   = parseInt(b.points) || 0;
        let start   = null;
        function animPts(ts) {
            if (!start) start = ts;
            const p = Math.min((ts - start) / 800, 1);
            const e = 1 - Math.pow(1 - p, 3);
            ptsEl.textContent = Math.floor(e * pts);
            if (p < 1) requestAnimationFrame(animPts);
        }
        requestAnimationFrame(animPts);

        // ── Animate confidence bar ──
        if (confPct != null) {
            setTimeout(() => {
                const fill = document.getElementById('loy-conf-fill');
                if (fill) fill.style.width = confPct + '%';
            }, 200);
        }
    }

    load();
})();
</script>
@endsection