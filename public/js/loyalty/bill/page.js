var BILL_ID = window.BILL_ID;

var STATUS_MAP = {
    done:      {icon:'✅', label:'Approved & Verified', sub:'Points awarded',   cls:'done'},
    pending:   {icon:'⏳', label:'Pending Processing',   sub:'Being reviewed',   cls:'pending'},
    failed:    {icon:'❌', label:'Processing Failed',    sub:'Contact support',  cls:'failed'},
    duplicate: {icon:'⊘',  label:'Duplicate Detected',  sub:'Already submitted',cls:'duplicate'},
    review:    {icon:'👁', label:'Under Manual Review',  sub:'Admin reviewing',  cls:'review'},
    invalid:   {icon:'⚠️', label:'Invalid Document',    sub:'Not a valid bill', cls:'invalid'},
};

function loyShowInit() {
    if (!document.getElementById('loy-show-page')) return;

    document.getElementById('loy-loading').style.display = 'block';
    document.getElementById('loy-content').style.display = 'none';
    document.getElementById('loy-action-btns').innerHTML = '';

    fetchBill(BILL_ID)
        .then(function(j){
            render(j.data, j.data.items || []);
        })
        .catch(function(){
            document.getElementById('loy-loading').innerHTML =
                '<div style="text-align:center;padding:64px;color:#6c757d;">' +
                '<div style="font-size:40px;margin-bottom:16px;">😕</div>' +
                '<p style="font-size:16px;font-weight:600;">Could not load bill.</p>' +
                '<a href="' + window.location.origin + '/loyalty/bills" class="btn btn-primary mt-2">← Back to Bills</a>' +
                '</div>';
        });
}

function render(b, items) {

    var dlUrl = '/bills/' + BILL_ID + '/file';

    document.getElementById('loy-action-btns').innerHTML =
        '<a href="' + dlUrl + '" class="btn btn-success btn-sm fw-bold" target="_blank">' +
        '<i class="fa-solid fa-download me-1"></i>Download Bill</a>';

    document.getElementById('loy-store-name').textContent = b.vendor || 'Unknown Vendor';

    document.getElementById('loy-rh-meta').innerHTML =
        (b.bill_date ? '<span><i class="fa-regular fa-calendar me-1"></i>' + b.bill_date + '</span>' : '') +
        '<span><i class="fa-solid fa-hashtag me-1"></i>Bill #' + b.bill_id + '</span>' +
        (b.provider ? '<span><i class="fa-solid fa-robot me-1"></i>' + b.provider + '</span>' : '');

    if (b.invoice_number) {
        document.getElementById('loy-inv-badge').style.display = 'block';
        document.getElementById('loy-inv-num').textContent = '#' + b.invoice_number;
    }

    var fields = [
        ['Bill Date', b.bill_date || '—'],
        ['Vendor', b.vendor || '—'],
        ['Invoice No.', b.invoice_number || '—'],
        ['OCR Provider', b.provider || '—']
    ];

    document.getElementById('loy-info-grid').innerHTML =
        fields.map(function(f){
            return '<div><div class="loy-info-label">' + f[0] + '</div><div class="loy-info-val">' + f[1] + '</div></div>';
        }).join('');

    var sec = document.getElementById('loy-items-section');

    if (items.length) {

        var rows = items.map(function(item){

            var dotColor = item.is_eligible ? '#22c984' : '#ddd';

            return '<tr>' +
                '<td>' +
                    '<span class="loy-item-dot" style="background:' + dotColor + '"></span>' +
                    '<span style="font-weight:600">' + (item.description || '—') + '</span>' +
                    (item.category ? '<div class="loy-item-cat">' + item.category + '</div>' : '') +
                '</td>' +
                '<td style="text-align:center">' + (item.quantity || 1) + '</td>' +
                '<td style="text-align:right">' + (item.unit_price ? fmt(item.unit_price) : '—') + '</td>' +
                '<td style="text-align:right;font-weight:700">' + fmt(item.line_total) + '</td>' +
            '</tr>';
        }).join('');

        sec.innerHTML =
            '<div class="loy-items-label">Line Items (' + items.length + ')</div>' +
            '<table class="loy-items-table">' +
            '<thead><tr><th>Item</th><th style="text-align:center">Qty</th><th style="text-align:right">Unit Price</th><th style="text-align:right">Total</th></tr></thead>' +
            '<tbody>' + rows + '</tbody></table>';

    } else {
        sec.innerHTML =
            '<div class="text-muted text-center py-4 fs-7">' +
            '<i class="fa-solid fa-list" style="opacity:.25;font-size:24px;display:block;margin-bottom:8px;"></i>' +
            'No line items available.</div>';
    }

    var sub = items.reduce(function(s,i){
        return s + parseFloat(i.line_total || 0);
    }, 0);

    var totHtml = '';

    if (items.length) {
        totHtml += '<div class="loy-total-row"><span>Subtotal (' + items.length + ' items)</span><span>' + fmt(sub) + '</span></div>';
    }

    var tax = parseFloat(b.amount || 0) - sub;

    if (items.length && tax > 0.01) {
        totHtml += '<div class="loy-total-row"><span>HST Tax</span><span>' + fmt(tax) + '</span></div>';
    }

    totHtml += '<div class="loy-total-grand"><span>Total</span><span>' + fmt(b.amount) + '</span></div>';

    document.getElementById('loy-totals').innerHTML = totHtml;

    var ptsEl = document.getElementById('loy-pts-num');
    var pts = parseInt(b.points) || 0;

    ptsEl.textContent = '0';
    countUp(ptsEl, pts);

    var si = STATUS_MAP[b.status] || {
        icon:'❓', label:b.status, sub:'', cls:'pending'
    };

    document.getElementById('loy-status-block').innerHTML =
        '<div class="loy-status-block ' + si.cls + '">' +
        '<span style="font-size:24px">' + si.icon + '</span>' +
        '<div><div class="fw-bold" style="font-size:14px">' + si.label + '</div>' +
        '<div class="text-muted" style="font-size:12px">' + si.sub + '</div></div></div>';

    if (b.confidence != null) {

        var pct = Math.round(b.confidence * 100);
        var col = pct >= 80 ? '#22c984' : pct >= 50 ? '#f6b600' : '#e74c3c';

        document.getElementById('loy-conf-card').style.display = 'block';
        document.getElementById('loy-conf-pct').textContent = pct + '%';
        document.getElementById('loy-conf-pct').style.color = col;
        document.getElementById('loy-conf-label').textContent =
            pct >= 80 ? 'High confidence read' :
            pct >= 50 ? 'Moderate confidence' :
            'Low — manual check advised';

        var bar = document.getElementById('loy-conf-bar');
        bar.style.background = col;

        setTimeout(function(){
            bar.style.width = pct + '%';
        }, 200);
    }

    document.getElementById('loy-dl-link').href = dlUrl;

    document.getElementById('loy-loading').style.display = 'none';
    document.getElementById('loy-content').style.display = 'block';
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loyShowInit);
} else {
    loyShowInit();
}

document.addEventListener('turbo:load', loyShowInit);
document.addEventListener('turbolinks:load', loyShowInit);