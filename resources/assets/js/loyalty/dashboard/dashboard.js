import { get, countUp, fmt, fmtDate, badge, avColor } from './helpers';
import './upload'

export function loyDashInit() {

    if (!document.getElementById('loy-pts-val')) return;

    document.getElementById('loy-pts-val').textContent = '—';

    document.getElementById('loy-recent-body').innerHTML =
        '<div class="loy-empty-row"><i class="fa-solid fa-receipt" style="opacity:.2;font-size:24px;display:block;margin-bottom:8px;"></i><p>Loading…</p></div>';

    get('/me/points')
    .then(function(j){

        var pts = j.data
            ? j.data.total_points
            : 0;

        var el = document.getElementById('loy-pts-val');

        el.textContent = '0';

        countUp(el, pts);

        var lsP = document.getElementById('ls-points');

        if (lsP){
            lsP.textContent='0';
            countUp(lsP, pts);
        }

    }).catch(function(){

        document.getElementById('loy-pts-val').textContent='—';

    });

    get('/me/bills?per_page=5')
    .then(function(j){

        var bills = j.data || [],
            meta  = j.meta || {};

        var total    = meta.total || 0;

        var approved = bills.filter(function(b){
            return b.status === 'done';
        }).length;

        var pending = bills.filter(function(b){
            return b.status === 'pending';
        }).length;

        var spent = bills.reduce(function(s,b){
            return s + parseFloat(b.amount || 0);
        },0);

        document.getElementById('loy-spent-val').textContent = fmt(spent);

        document.getElementById('loy-bills-processed').textContent =
            total + ' bills processed';

        var elT  = document.getElementById('ls-total'),
            elA  = document.getElementById('ls-approved'),
            elPe = document.getElementById('ls-pending'),
            elAr = document.getElementById('ls-appr-rate');

        if(elT){
            elT.textContent='0';
            countUp(elT,total);
        }

        if(elA){
            elA.textContent='0';
            countUp(elA,approved);
        }

        if(elPe){
            elPe.textContent='0';
            countUp(elPe,pending);
        }

        if(elAr){
            elAr.textContent = total > 0
                ? Math.round(approved/total*100)+'% rate'
                : '—';
        }

        var body = document.getElementById('loy-recent-body');

        if (!bills.length) {

            body.innerHTML =
                '<div class="loy-empty-row"><i class="fa-solid fa-receipt" style="opacity:.2;font-size:24px;display:block;margin-bottom:8px;"></i><p>No bills yet. Upload your first bill!</p></div>';

            return;
        }

        body.innerHTML = bills.slice(0,5).map(function(b){

            var av = avColor(b.vendor);

            var init = (b.vendor || '?')[0].toUpperCase();

            return '<a href="/bills/'+b.bill_id+'" class="loy-tbl-row">'+
                '<div class="d-flex align-items-center gap-2">'+
                    '<div class="loy-avatar '+av+'">'+init+'</div>'+
                    '<div><div class="loy-vendor-name">'+(b.vendor||'Unknown')+'</div>'+
                    '<div class="loy-vendor-meta"><span>#'+b.bill_id+'</span><span>'+(b.provider||'')+'</span></div></div>'+
                '</div>'+
                '<div class="text-muted fs-7">'+fmtDate(b.bill_date)+'</div>'+
                '<div class="fw-bold">'+fmt(b.amount)+'</div>'+
                '<div class="loy-pts-pill">⭐ '+(b.points||0)+'</div>'+
                '<div>'+badge(b.status)+'</div>'+
            '</a>';

        }).join('');

    }).catch(function(){

        document.getElementById('loy-recent-body').innerHTML =
            '<div class="loy-empty-row"><i class="fa-solid fa-triangle-exclamation" style="font-size:24px;display:block;margin-bottom:8px;opacity:.3;"></i><p>Could not load bills.</p></div>';

    });
}

window.loyDashInit = loyDashInit;

if (document.readyState==='loading') {
    document.addEventListener('DOMContentLoaded', loyDashInit);
} else {
    loyDashInit();
}

document.addEventListener('turbo:load', loyDashInit);
document.addEventListener('turbolinks:load', loyDashInit);