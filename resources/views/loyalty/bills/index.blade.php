@extends('layouts.app')

@section('title', 'My Bills')

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
        font-size: 22px;
        font-weight: 800;
        color: var(--loy-text);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* ── Filter Bar ── */
    .loy-filter-bar {
        background: var(--loy-card-bg);
        border: 1px solid var(--loy-border);
        border-radius: var(--loy-radius);
        padding: 14px 20px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        box-shadow: var(--loy-shadow);
    }
    .loy-filter-bar select,
    .loy-filter-bar input {
        border: 1px solid var(--loy-border);
        border-radius: 8px;
        padding: 7px 14px;
        font-size: 13px;
        color: var(--loy-text);
        background: #fff;
        outline: none;
        transition: border-color 0.15s;
    }
    .loy-filter-bar select:focus,
    .loy-filter-bar input:focus {
        border-color: var(--loy-primary);
    }
    .loy-filter-label {
        font-size: 12px;
        font-weight: 600;
        color: var(--loy-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* ── Bills Grid ── */
    .loy-bills-grid { }

    /* ── Bill Row Card ── */
    .loy-bill-row {
        background: var(--loy-card-bg);
        border: 1px solid var(--loy-border);
        border-radius: var(--loy-radius);
        padding: 18px 22px;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 16px;
        cursor: pointer;
        transition: transform 0.15s, box-shadow 0.15s, border-color 0.15s;
        box-shadow: var(--loy-shadow);
        text-decoration: none;
    }
    .loy-bill-row:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 28px rgba(98,89,202,0.14);
        border-color: var(--loy-primary);
        text-decoration: none;
    }

    /* ── Vendor Avatar ── */
    .loy-vav {
        width: 46px; height: 46px;
        border-radius: 12px;
        background: linear-gradient(135deg, var(--loy-primary) 0%, #8b5cf6 100%);
        color: #fff;
        font-size: 18px;
        font-weight: 800;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .loy-vav.amber  { background: linear-gradient(135deg, #f6b600, #f97316); }
    .loy-vav.green  { background: linear-gradient(135deg, #22c984, #16a57a); }
    .loy-vav.blue   { background: linear-gradient(135deg, #0d6efd, #0ea5e9); }
    .loy-vav.red    { background: linear-gradient(135deg, #e74c3c, #ef4444); }

    /* ── Bill Info ── */
    .loy-bill-main { flex: 1; min-width: 0; }
    .loy-bill-name {
        font-size: 15px;
        font-weight: 700;
        color: var(--loy-text);
        margin-bottom: 4px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .loy-bill-meta {
        font-size: 12px;
        color: var(--loy-muted);
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }
    .loy-bill-meta span { display: flex; align-items: center; gap: 4px; }

    /* ── Amount Column ── */
    .loy-bill-amount {
        text-align: right;
        flex-shrink: 0;
    }
    .loy-bill-amount .amount {
        font-size: 18px;
        font-weight: 800;
        color: var(--loy-text);
    }
    .loy-bill-amount .pts {
        font-size: 11px;
        color: var(--loy-primary);
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 3px;
        margin-top: 2px;
    }

    /* ── Right side ── */
    .loy-bill-right {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 8px;
        flex-shrink: 0;
    }

    /* ── Status Badges ── */
    .loy-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
    }
    .loy-badge.done      { background: rgba(34,201,132,0.12); color: #16a57a; }
    .loy-badge.pending   { background: rgba(246,182,0,0.12);  color: #c48e00; }
    .loy-badge.failed    { background: rgba(231,76,60,0.12);  color: var(--loy-danger); }
    .loy-badge.duplicate { background: rgba(108,117,125,0.12); color: #555; }
    .loy-badge.review    { background: rgba(13,110,253,0.12); color: #0d6efd; }
    .loy-badge.invalid   { background: rgba(231,76,60,0.08);  color: #b03a2e; }

    /* ── Confidence Bar ── */
    .loy-conf {
        width: 60px;
        height: 4px;
        background: #eee;
        border-radius: 4px;
        overflow: hidden;
    }
    .loy-conf-fill {
        height: 100%;
        border-radius: 4px;
        background: var(--loy-success);
    }

    /* ── Pagination ── */
    .loy-pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 6px;
        margin-top: 24px;
        flex-wrap: wrap;
    }
    .loy-pag-btn {
        border: 1px solid var(--loy-border);
        background: #fff;
        color: var(--loy-text);
        padding: 6px 14px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.14s;
    }
    .loy-pag-btn:hover { border-color: var(--loy-primary); color: var(--loy-primary); }
    .loy-pag-btn.active { background: var(--loy-primary); border-color: var(--loy-primary); color: #fff; }
    .loy-pag-btn:disabled { opacity: 0.4; cursor: default; }

    /* ── Empty ── */
    .loy-empty {
        text-align: center;
        padding: 64px 24px;
        color: var(--loy-muted);
    }
    .loy-empty-icon {
        font-size: 48px;
        opacity: 0.25;
        margin-bottom: 16px;
    }

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
    .loy-btn-primary:hover { background: #504db0; color: #fff; }

    @media (max-width: 576px) {
        .loy-bill-row { flex-wrap: wrap; }
        .loy-bill-right { flex-direction: row; align-items: center; }
    }
</style>
@endsection

@section('content')
<div class="container-fluid loy-page">

    {{-- ── Page Header ── --}}
    <div class="loy-page-header">
        <h2>
            <a href="{{ route('loyalty.dashboard') }}" style="color:var(--loy-muted);font-size:18px;margin-right:4px;">
                <i class="fa-solid fa-chevron-left"></i>
            </a>
            <i class="fa-solid fa-receipt" style="color:var(--loy-primary)"></i>
            My Bills
        </h2>
        <div class="d-flex align-items-center gap-2">
            <span id="loy-bill-count" class="text-muted" style="font-size:13px;"></span>
            <a href="{{ route('loyalty.dashboard') }}" class="loy-btn-primary">
                <i class="fa-solid fa-star"></i> Dashboard
            </a>
        </div>
    </div>

    {{-- ── Filter Bar ── --}}
    <div class="loy-filter-bar">
        <span class="loy-filter-label">Filter:</span>
        <select id="loy-status-filter" onchange="loadBills(1)">
            <option value="">All Status</option>
            <option value="done">Approved</option>
            <option value="pending">Pending</option>
            <option value="review">In Review</option>
            <option value="failed">Failed</option>
            <option value="duplicate">Duplicate</option>
        </select>
        <span class="loy-filter-label">Sort:</span>
        <select id="loy-sort-filter" onchange="loadBills(1)">
            <option value="latest">Latest First</option>
            <option value="amount_desc">Highest Amount</option>
            <option value="amount_asc">Lowest Amount</option>
        </select>
        <button class="loy-btn-primary ms-auto" onclick="loadBills(currentPage)" style="padding:7px 14px;">
            <i class="fa-solid fa-rotate-right"></i> Refresh
        </button>
    </div>

    {{-- ── Bills List ── --}}
    <div class="loy-bills-grid" id="loy-bills-list">
        {{-- JS renders here --}}
        @for($i = 0; $i < 5; $i++)
        <div class="loy-bill-row" style="pointer-events:none">
            <div class="loy-skeleton" style="width:46px;height:46px;border-radius:12px;flex-shrink:0;"></div>
            <div class="loy-bill-main">
                <div class="loy-skeleton" style="height:15px;width:160px;margin-bottom:8px;"></div>
                <div class="loy-skeleton" style="height:11px;width:220px;"></div>
            </div>
            <div style="text-align:right">
                <div class="loy-skeleton" style="height:20px;width:80px;margin-bottom:6px;"></div>
                <div class="loy-skeleton" style="height:12px;width:60px;margin-left:auto;"></div>
            </div>
        </div>
        @endfor
    </div>

    {{-- ── Pagination ── --}}
    <div class="loy-pagination" id="loy-pagination"></div>

</div>
@endsection

@section('scripts')
<script>
let currentPage = 1;
let totalPages  = 1;
const COLORS    = ['', 'amber', 'green', 'blue', 'red', ''];

function vendorColor(name) {
    if (!name) return '';
    const sum = [...name].reduce((s, c) => s + c.charCodeAt(0), 0);
    return COLORS[sum % COLORS.length];
}

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

function confBar(c) {
    if (c == null) return '';
    const pct = Math.round(c * 100);
    const color = pct >= 80 ? '#22c984' : pct >= 50 ? '#f6b600' : '#e74c3c';
    return `
        <div style="display:flex;align-items:center;gap:5px;">
            <div class="loy-conf">
                <div class="loy-conf-fill" style="width:${pct}%;background:${color}"></div>
            </div>
            <span style="font-size:10px;color:#999">${pct}%</span>
        </div>`;
}

async function loadBills(page) {
    currentPage = page;
    const status = document.getElementById('loy-status-filter').value || '';
    const sort   = document.getElementById('loy-sort-filter').value || 'latest';

    let url = `/me/bills?page=${page}&per_page=10`;
    if (status) url += `&status=${status}`;

    const list = document.getElementById('loy-bills-list');
    list.style.opacity = '0.5';

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    try {
        const res  = await fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            }
        });
        const json = await res.json();
        let bills  = json.data || [];
        const meta = json.meta || {};

        totalPages = meta.last_page || 1;

        // Client-side sort (API doesn't support it natively)
        if (sort === 'amount_desc') bills.sort((a, b) => b.amount - a.amount);
        if (sort === 'amount_asc')  bills.sort((a, b) => a.amount - b.amount);

        renderBills(bills, meta);
        renderPagination(meta);
    } catch (e) {
        list.innerHTML = `<div class="loy-empty">
            <div class="loy-empty-icon">⚠️</div>
            <p>Failed to load bills. Please try again.</p>
        </div>`;
    } finally {
        list.style.opacity = '1';
    }
}

function renderBills(bills, meta) {
    const list = document.getElementById('loy-bills-list');

    const countEl = document.getElementById('loy-bill-count');
    if (countEl) countEl.textContent = `${meta.total ?? bills.length} bills total`;

    if (!bills.length) {
        list.innerHTML = `
            <div class="loy-empty">
                <div class="loy-empty-icon"><i class="fa-solid fa-receipt"></i></div>
                <p style="font-size:16px;font-weight:600;margin-bottom:6px;">No bills found</p>
                <p style="font-size:13px;">Try adjusting the filters or upload a new bill.</p>
            </div>`;
        return;
    }

    list.innerHTML = bills.map(b => {
        const vColor = vendorColor(b.vendor);
        const initial = (b.vendor || '?')[0].toUpperCase();
        return `
        <a href="/bills/${b.bill_id}" class="loy-bill-row">
            <div class="loy-vav ${vColor}">${initial}</div>

            <div class="loy-bill-main">
                <div class="loy-bill-name">${b.vendor || 'Unknown Vendor'}</div>
                <div class="loy-bill-meta">
                    <span><i class="fa-regular fa-calendar"></i> ${b.bill_date || '—'}</span>
                    <span><i class="fa-solid fa-tag"></i> #${b.bill_id}</span>
                    ${b.provider ? `<span><i class="fa-solid fa-robot"></i> ${b.provider}</span>` : ''}
                    ${b.confidence != null ? `<span>${confBar(b.confidence)}</span>` : ''}
                </div>
            </div>

            <div class="loy-bill-amount">
                <div class="amount">${fmtAmount(b.amount)}</div>
                <div class="pts">⭐ ${b.points ?? 0} pts</div>
            </div>

            <div class="loy-bill-right">
                ${statusBadge(b.status)}
                <span style="font-size:11px;color:#bbb">${b.created_at ? new Date(b.created_at).toLocaleDateString('en-IN') : ''}</span>
            </div>
        </a>`;
    }).join('');
}

function renderPagination(meta) {
    const pag = document.getElementById('loy-pagination');
    const cur = meta.current_page || 1;
    const last = meta.last_page || 1;

    if (last <= 1) { pag.innerHTML = ''; return; }

    let html = `
        <button class="loy-pag-btn" onclick="loadBills(${cur - 1})" ${cur <= 1 ? 'disabled' : ''}>
            <i class="fa-solid fa-chevron-left"></i>
        </button>`;

    for (let p = 1; p <= last; p++) {
        if (p === 1 || p === last || (p >= cur - 2 && p <= cur + 2)) {
            html += `<button class="loy-pag-btn ${p === cur ? 'active' : ''}" onclick="loadBills(${p})">${p}</button>`;
        } else if (p === cur - 3 || p === cur + 3) {
            html += `<span style="padding:0 4px;color:#bbb">…</span>`;
        }
    }

    html += `
        <button class="loy-pag-btn" onclick="loadBills(${cur + 1})" ${cur >= last ? 'disabled' : ''}>
            <i class="fa-solid fa-chevron-right"></i>
        </button>`;

    pag.innerHTML = html;
}

function loyBillsInit() {
    if (document.getElementById('loy-bills-list')) {
        loadBills(1);
    }
}

document.addEventListener('DOMContentLoaded', loyBillsInit);

document.addEventListener('turbo:load', loyBillsInit);
document.addEventListener('turbolinks:load', loyBillsInit);

</script>
@endsection
