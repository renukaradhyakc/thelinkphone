@extends('layouts.app')

@section('title', 'Loyalty Points')

@section('page_css')
<style>
    /* ── Loyalty CSS Variables (matching CallaLink palette) ── */
    :root {
        --loy-primary:   #6259ca;   /* purple from sidebar */
        --loy-accent:    #f6b600;   /* gold/amber */
        --loy-success:   #22c984;
        --loy-danger:    #e74c3c;
        --loy-dark:      #1a1a2e;
        --loy-card-bg:   #ffffff;
        --loy-border:    #e8e8f7;
        --loy-muted:     #6c757d;
        --loy-text:      #2d2d44;
        --loy-radius:    14px;
        --loy-shadow:    0 4px 24px rgba(98,89,202,0.10);
    }

    /* ── Page wrapper ── */
    .loy-page { padding: 0 8px; }

    /* ── Points Hero Banner ── */
    .loy-hero {
        background: linear-gradient(135deg, var(--loy-primary) 0%, #8b5cf6 60%, #a78bfa 100%);
        border-radius: var(--loy-radius);
        padding: 36px 40px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 28px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 8px 32px rgba(98,89,202,0.28);
    }
    .loy-hero::before {
        content: '';
        position: absolute;
        right: -60px; top: -60px;
        width: 280px; height: 280px;
        border-radius: 50%;
        background: rgba(255,255,255,0.07);
        pointer-events: none;
    }
    .loy-hero::after {
        content: '';
        position: absolute;
        right: 60px; bottom: -80px;
        width: 200px; height: 200px;
        border-radius: 50%;
        background: rgba(255,255,255,0.05);
        pointer-events: none;
    }
    .loy-hero-left h6 {
        color: rgba(255,255,255,0.75);
        font-size: 13px;
        font-weight: 600;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        margin-bottom: 8px;
    }
    .loy-hero-left h1 {
        color: #fff;
        font-size: 52px;
        font-weight: 800;
        margin: 0;
        line-height: 1;
        letter-spacing: -1px;
    }
    .loy-hero-left p {
        color: rgba(255,255,255,0.65);
        margin: 10px 0 0;
        font-size: 14px;
    }
    .loy-hero-right {
        text-align: right;
        z-index: 1;
    }
    .loy-coin-icon {
        width: 90px; height: 90px;
        background: rgba(255,255,255,0.15);
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 40px;
        backdrop-filter: blur(6px);
        border: 2px solid rgba(255,255,255,0.2);
        margin-left: auto;
    }
    .loy-hero-badge {
        display: inline-block;
        background: var(--loy-accent);
        color: #1a1a1a;
        font-size: 11px;
        font-weight: 700;
        padding: 4px 12px;
        border-radius: 20px;
        margin-top: 12px;
        letter-spacing: 0.5px;
    }

    /* ── Stat Cards (matching existing site's card style) ── */
    .loy-stats-row { margin-bottom: 28px; }
    .loy-stat-card {
        background: var(--loy-card-bg);
        border-radius: var(--loy-radius);
        padding: 24px 22px;
        border: 1px solid var(--loy-border);
        box-shadow: var(--loy-shadow);
        display: flex;
        align-items: center;
        gap: 18px;
        transition: transform 0.18s, box-shadow 0.18s;
        height: 100%;
    }
    .loy-stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 32px rgba(98,89,202,0.15);
    }
    .loy-stat-icon {
        width: 52px; height: 52px;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 22px;
        flex-shrink: 0;
    }
    .loy-stat-icon.purple { background: rgba(98,89,202,0.12); color: var(--loy-primary); }
    .loy-stat-icon.amber  { background: rgba(246,182,0,0.12);  color: var(--loy-accent); }
    .loy-stat-icon.green  { background: rgba(34,201,132,0.12); color: var(--loy-success); }
    .loy-stat-icon.red    { background: rgba(231,76,60,0.12);  color: var(--loy-danger); }
    .loy-stat-icon.blue   { background: rgba(13,110,253,0.12); color: #0d6efd; }
    .loy-stat-body h3 {
        font-size: 28px;
        font-weight: 800;
        color: var(--loy-text);
        margin: 0 0 2px;
        line-height: 1;
    }
    .loy-stat-body p {
        font-size: 12px;
        color: var(--loy-muted);
        margin: 0;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* ── Section Titles ── */
    .loy-section-title {
        font-size: 16px;
        font-weight: 700;
        color: var(--loy-text);
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .loy-section-title::after {
        content: '';
        flex: 1;
        height: 1px;
        background: var(--loy-border);
        margin-left: 8px;
    }

    /* ── Recent Bills Table ── */
    .loy-table-card {
        background: var(--loy-card-bg);
        border-radius: var(--loy-radius);
        border: 1px solid var(--loy-border);
        box-shadow: var(--loy-shadow);
        overflow: hidden;
    }
    .loy-table-card .card-header {
        background: transparent;
        border-bottom: 1px solid var(--loy-border);
        padding: 18px 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .loy-table-card .card-header h5 {
        font-size: 15px;
        font-weight: 700;
        color: var(--loy-text);
        margin: 0;
    }
    .loy-table { width: 100%; border-collapse: collapse; }
    .loy-table thead th {
        font-size: 11px;
        font-weight: 700;
        color: var(--loy-muted);
        text-transform: uppercase;
        letter-spacing: 0.8px;
        padding: 12px 20px;
        background: #f8f8fd;
        border-bottom: 1px solid var(--loy-border);
    }
    .loy-table tbody tr {
        border-bottom: 1px solid var(--loy-border);
        transition: background 0.12s;
        cursor: pointer;
    }
    .loy-table tbody tr:last-child { border-bottom: none; }
    .loy-table tbody tr:hover { background: #f8f8fd; }
    .loy-table tbody td {
        padding: 14px 20px;
        font-size: 13.5px;
        color: var(--loy-text);
        vertical-align: middle;
    }

    /* ── Status Badges ── */
    .loy-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.3px;
    }
    .loy-badge.done      { background: rgba(34,201,132,0.12); color: #16a57a; }
    .loy-badge.pending   { background: rgba(246,182,0,0.12);  color: #c48e00; }
    .loy-badge.failed    { background: rgba(231,76,60,0.12);  color: var(--loy-danger); }
    .loy-badge.duplicate { background: rgba(108,117,125,0.12); color: #555; }
    .loy-badge.review    { background: rgba(13,110,253,0.12); color: #0d6efd; }
    .loy-badge.invalid   { background: rgba(231,76,60,0.08);  color: #b03a2e; }

    /* ── Points pill ── */
    .loy-pts {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: rgba(98,89,202,0.10);
        color: var(--loy-primary);
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 700;
    }

    /* ── Empty State ── */
    .loy-empty {
        padding: 48px 24px;
        text-align: center;
        color: var(--loy-muted);
    }
    .loy-empty i { font-size: 40px; opacity: 0.3; display: block; margin-bottom: 12px; }
    .loy-empty p { margin: 0; font-size: 14px; }

    /* ── Loading Skeleton ── */
    .loy-skeleton {
        background: linear-gradient(90deg, #f0f0f8 25%, #e8e8f4 50%, #f0f0f8 75%);
        background-size: 200% 100%;
        animation: loy-shimmer 1.4s infinite;
        border-radius: 6px;
        height: 14px;
    }
    @keyframes loy-shimmer {
        0%   { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }

    /* ── Vendor avatar ── */
    .loy-vendor-avatar {
        width: 34px; height: 34px;
        border-radius: 8px;
        background: linear-gradient(135deg, var(--loy-primary), #8b5cf6);
        color: #fff;
        font-size: 13px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-right: 10px;
        flex-shrink: 0;
    }

    /* ── Quick action btn ── */
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
        transition: background 0.15s, box-shadow 0.15s;
    }
    .loy-btn-primary:hover {
        background: #504db0;
        color: #fff;
        box-shadow: 0 4px 12px rgba(98,89,202,0.3);
    }

    /* ── Responsive ── */
    @media (max-width: 768px) {
        .loy-hero { flex-direction: column; gap: 20px; text-align: center; padding: 28px 24px; }
        .loy-hero-right { text-align: center; }
        .loy-coin-icon { margin: 0 auto; }
        .loy-hero-left h1 { font-size: 40px; }
    }
</style>
@endsection

@section('content')
<div class="container-fluid loy-page">

    {{-- ── HERO: Points Banner ── --}}
    <div class="loy-hero" id="loy-hero">
        <div class="loy-hero-left">
            <h6>🎯 Your Loyalty Rewards</h6>
            <h1 id="loy-total-pts">
                <span class="loy-skeleton d-inline-block" style="width:120px;height:52px;border-radius:8px;"></span>
            </h1>
            <p>Points earned from verified bill uploads</p>
        </div>
        <div class="loy-hero-right">
            <div class="loy-coin-icon">🪙</div>
            <div class="loy-hero-badge">10 pts per ₹100 spent</div>
        </div>
    </div>

    {{-- ── STATS ROW ── --}}
    <div class="row loy-stats-row g-3" id="loy-stats-row">
        {{-- Skeleton placeholders --}}
        @for($i = 0; $i < 5; $i++)
        <div class="col-6 col-md-4 col-xl" id="loy-stat-skeleton-{{ $i }}">
            <div class="loy-stat-card">
                <div class="loy-stat-icon purple">
                    <div class="loy-skeleton" style="width:24px;height:24px;border-radius:50%;"></div>
                </div>
                <div class="loy-stat-body">
                    <h3><div class="loy-skeleton" style="width:60px;height:28px;"></div></h3>
                    <p><div class="loy-skeleton" style="width:80px;height:10px;margin-top:6px;"></div></p>
                </div>
            </div>
        </div>
        @endfor
    </div>

    {{-- ── RECENT BILLS ── --}}
    <div class="loy-section-title">
        <i class="fa-solid fa-receipt" style="color:var(--loy-primary);"></i>
        Recent Bills
    </div>

    <div class="loy-table-card mb-5">
        <div class="card-header">
            <h5>Latest Uploads</h5>
            <a href="{{ route('loyalty.bills.index') }}" class="loy-btn-primary">
                <i class="fa-solid fa-list"></i> View All Bills
            </a>
        </div>
        <div id="loy-recent-bills-wrap">
            <table class="loy-table">
                <thead>
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
                        <td colspan="6">
                            <div class="loy-empty">
                                <i class="fa-solid fa-receipt"></i>
                                <p>Loading bills…</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script>
(function () {
    const API = {
        points:  '/me/points',
        history: '/me/bills?per_page=5',
    };

    // ── Helpers ──
    function statusBadge(s) {
        const map = {
            done:      ['done',      '✓ Approved'],
            pending:   ['pending',   '⏳ Pending'],
            failed:    ['failed',    '✕ Failed'],
            duplicate: ['duplicate', '⊘ Duplicate'],
            review:    ['review',    '👁 Review'],
            invalid:   ['invalid',   '⚠ Invalid'],
        };
        const [cls, label] = map[s] || ['pending', s];
        return `<span class="loy-badge ${cls}">${label}</span>`;
    }

    function fmtAmount(v) {
        return '₹' + parseFloat(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 });
    }

    function vendorAvatar(name) {
        const initial = (name || '?')[0].toUpperCase();
        return `<div class="loy-vendor-avatar">${initial}</div>`;
    }

    function animateNumber(el, target, duration = 900) {
        let start = null;
        const step = (ts) => {
            if (!start) start = ts;
            const progress = Math.min((ts - start) / duration, 1);
            const ease = 1 - Math.pow(1 - progress, 3);
            el.textContent = Math.floor(ease * target).toLocaleString('en-IN');
            if (progress < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
    }

    // ── Fetch & render points ──
    async function loadPoints() {
        try {
            const res  = await fetch(API.points, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await res.json();
            const pts  = json.data?.total_points ?? 0;

            const el = document.getElementById('loy-total-pts');
            el.innerHTML = '<span id="loy-pts-num">0</span> <small style="font-size:20px;font-weight:500;opacity:.7">pts</small>';
            animateNumber(document.getElementById('loy-pts-num'), pts);
        } catch {
            document.getElementById('loy-total-pts').textContent = '—';
        }
    }

    // ── Fetch & render history (stats + table) ──
    async function loadHistory() {
        try {
            const res  = await fetch(API.history, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await res.json();
            const bills = json.data || [];
            const meta  = json.meta || {};

            renderStats(bills, meta);
            renderRecentTable(bills);
        } catch {
            renderStatsError();
        }
    }

    function renderStats(bills, meta) {
        const total     = meta.total ?? bills.length;
        const approved  = bills.filter(b => b.status === 'done').length;
        const pending   = bills.filter(b => b.status === 'pending').length;
        const totalAmt  = bills.reduce((s, b) => s + parseFloat(b.amount || 0), 0);
        const totalPts  = bills.reduce((s, b) => s + (b.points || 0), 0);

        const stats = [
            { icon: 'fa-file-invoice',     cls: 'purple', value: total,                  label: 'Total Bills' },
            { icon: 'fa-circle-check',     cls: 'green',  value: approved,               label: 'Approved' },
            { icon: 'fa-clock',            cls: 'amber',  value: pending,                label: 'Pending Review' },
            { icon: 'fa-indian-rupee-sign',cls: 'blue',   value: '₹'+totalAmt.toFixed(0),label: 'Total Spent', raw: true },
            { icon: 'fa-star',             cls: 'purple', value: totalPts,               label: 'Points (Page)' },
        ];

        const row = document.getElementById('loy-stats-row');
        row.innerHTML = stats.map((s, i) => `
            <div class="col-6 col-md-4 col-xl">
                <div class="loy-stat-card">
                    <div class="loy-stat-icon ${s.cls}">
                        <i class="fa-solid ${s.icon}"></i>
                    </div>
                    <div class="loy-stat-body">
                        <h3 id="loy-sv-${i}">${s.raw ? s.value : 0}</h3>
                        <p>${s.label}</p>
                    </div>
                </div>
            </div>
        `).join('');

        stats.forEach((s, i) => {
            if (!s.raw) animateNumber(document.getElementById(`loy-sv-${i}`), s.value);
        });
    }

    function renderStatsError() {
        document.getElementById('loy-stats-row').innerHTML =
            `<div class="col-12 text-muted text-center py-3">Could not load stats.</div>`;
    }

    function renderRecentTable(bills) {
        const tbody = document.getElementById('loy-recent-tbody');

        if (!bills.length) {
            tbody.innerHTML = `
                <tr><td colspan="6">
                    <div class="loy-empty">
                        <i class="fa-solid fa-receipt"></i>
                        <p>No bills uploaded yet. Upload your first bill to earn points!</p>
                    </div>
                </td></tr>`;
            return;
        }

        tbody.innerHTML = bills.slice(0, 5).map(b => `
            <tr onclick="window.location='/bills/${b.bill_id}'" style="cursor:pointer">
                <td>
                    <div class="d-flex align-items-center">
                        ${vendorAvatar(b.vendor)}
                        <span style="font-weight:600">${b.vendor || '—'}</span>
                    </div>
                </td>
                <td style="color:var(--loy-muted)">${b.bill_date || '—'}</td>
                <td style="font-weight:700">${fmtAmount(b.amount)}</td>
                <td><span class="loy-pts">⭐ ${b.points ?? 0}</span></td>
                <td>${statusBadge(b.status)}</td>
                <td>
                    <a href="/bills/${b.bill_id}"
                       class="btn btn-sm btn-light" style="border-radius:8px;font-size:12px;"
                       onclick="event.stopPropagation()">
                        View
                    </a>
                </td>
            </tr>
        `).join('');
    }

    // ── Init ──
    loadPoints();
    loadHistory();
})();
</script>
@endsection