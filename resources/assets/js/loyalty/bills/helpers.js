const AV_COLORS = [
    'loy-av-purple',
    'loy-av-amber',
    'loy-av-green',
    'loy-av-blue',
    'loy-av-red'
];

export function avColor(n) {
    if (!n) return AV_COLORS[0];

    const s = n.split('').reduce((a, c) => a + c.charCodeAt(0), 0);
    return AV_COLORS[s % AV_COLORS.length];
}

export function badge(s) {
    const M = {
        done: 'badge-done ✓ Approved',
        pending: 'badge-pending ⏳ Pending',
        failed: 'badge-failed ✕ Failed',
        duplicate: 'badge-duplicate ⊘ Dup',
        review: 'badge-review 👁 Review',
        invalid: 'badge-invalid ⚠ Invalid'
    };

    const v = (M[s] || `badge-duplicate ${s}`).split(' ');
    const cls = v.shift();

    return `<span class="${cls}">${v.join(' ')}</span>`;
}

export function fmt(v) {
    return '₹' + parseFloat(v || 0).toLocaleString('en-IN', {
        minimumFractionDigits: 2
    });
}

export function fmtDate(v) {
    if (!v) return '—';

    const d = new Date(v);
    return isNaN(d) ?
        v :
        d.toLocaleDateString('en-IN', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        }).replace(/\//g, '-');
}

export function csrf() {
    const m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
}

export function confBar(c) {
    if (c == null) return '';

    const pct = Math.round(c * 100);
    const col = pct >= 80 ? '#22c984' : pct >= 50 ? '#f6b600' : '#e74c3c';

    return `
        <div class="loy-conf-track">
            <div class="loy-conf-fill" style="width:${pct}%;background:${col}"></div>
        </div>
        <span style="font-size:10px;color:#aaa;margin-left:3px">${pct}%</span>
    `;
}