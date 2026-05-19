(function () {

    const API = {
        points: '/me/points',
        history: '/me/bills?per_page=5',
    };

    function statusBadge(s) {
        const map = {
            done: ['success', '✓ Approved'],
            pending: ['warning', '⏳ Pending'],
            failed: ['danger', '✕ Failed'],
            duplicate: ['secondary', '⊘ Duplicate'],
            review: ['info', '👁 Review'],
            invalid: ['dark', '⚠ Invalid'],
        };

        const [cls, label] = map[s] || ['secondary', s];

        return `<span class="badge bg-${cls}">${label}</span>`;
    }

    function fmtAmount(v) {
        return '₹' + parseFloat(v || 0).toLocaleString('en-IN', {
            minimumFractionDigits: 2
        });
    }

    function vendorAvatar(name) {
        const initial = (name || '?')[0].toUpperCase();
        return `
            <div class="d-flex align-items-center gap-2">
                <div class="bg-primary text-white rounded d-flex align-items-center justify-content-center"
                     style="width:32px;height:32px;font-weight:600;">
                    ${initial}
                </div>
                <span class="fw-semibold">${name || '—'}</span>
            </div>
        `;
    }

    function animateNumber(el, target, duration = 900) {
        let start = null;

        function step(ts) {
            if (!start) start = ts;

            const progress = Math.min((ts - start) / duration, 1);
            const value = Math.floor(progress * target);

            el.textContent = value.toLocaleString('en-IN');

            if (progress < 1) requestAnimationFrame(step);
        }

        requestAnimationFrame(step);
    }

    async function loadPoints() {
        try {
            const res = await fetch(API.points);
            const json = await res.json();

            const pts = json.data?.total_points ?? 0;

            const el = document.getElementById('loy-total-pts');
            el.innerHTML = `<span id="pts-num">0</span> pts`;

            animateNumber(document.getElementById('pts-num'), pts);

        } catch (e) {
            document.getElementById('loy-total-pts').textContent = '—';
        }
    }

    async function loadHistory() {
        try {
            const res = await fetch(API.history);
            const json = await res.json();

            const bills = json.data || [];

            renderStats(bills);
            renderTable(bills);

        } catch (e) {
            document.getElementById('loy-recent-tbody').innerHTML =
                `<tr><td colspan="6" class="text-center text-danger">Failed to load</td></tr>`;
        }
    }

    function renderStats(bills) {

        const total = bills.length;
        const approved = bills.filter(b => b.status === 'done').length;
        const pending = bills.filter(b => b.status === 'pending').length;
        const spent = bills.reduce((s, b) => s + parseFloat(b.amount || 0), 0);
        const points = bills.reduce((s, b) => s + (b.points || 0), 0);

        const stats = [
            ['Total Bills', total],
            ['Approved', approved],
            ['Pending', pending],
            ['Spent', '₹' + spent.toFixed(0)],
            ['Points', points],
        ];

        const row = document.getElementById('loy-stats-row');

        row.innerHTML = stats.map(([label, value]) => `
            <div class="col-6 col-md-4 col-xl-3">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="text-muted small">${label}</div>
                        <div class="fs-4 fw-bold">${value}</div>
                    </div>
                </div>
            </div>
        `).join('');
    }

    function renderTable(bills) {
        const tbody = document.getElementById('loy-recent-tbody');

        if (!bills.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-4 text-muted">
                        No bills yet
                    </td>
                </tr>`;
            return;
        }

        tbody.innerHTML = bills.slice(0, 5).map(b => `
            <tr onclick="window.location='/bills/${b.bill_id}'" style="cursor:pointer">
                <td>${vendorAvatar(b.vendor)}</td>
                <td>${b.bill_date || '—'}</td>
                <td class="fw-semibold">${fmtAmount(b.amount)}</td>
                <td>⭐ ${b.points || 0}</td>
                <td>${statusBadge(b.status)}</td>
                <td>
                    <a href="/bills/${b.bill_id}"
                       class="btn btn-sm btn-light"
                       onclick="event.stopPropagation()">
                        View
                    </a>
                </td>
            </tr>
        `).join('');
    }

    loadPoints();
    loadHistory();

})();