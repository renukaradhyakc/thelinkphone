import { avColor, fmt, fmtDate, badge, confBar } from './helpers';
import { loyBillsFetch } from './api';

export function renderList(bills, meta) {

    const cnt  = document.getElementById('loy-bill-count');
    const body = document.getElementById('loy-list-body');

    if (cnt) cnt.textContent = `(${meta.total || bills.length} total)`;

    if (!bills.length) {
        body.innerHTML =
            '<div class="loy-empty">' +
            '<i class="fa-solid fa-receipt" style="font-size:24px;display:block;margin-bottom:8px;opacity:.25;"></i>' +
            '<div style="font-weight:700">No bills found</div>' +
            '<p style="font-size:13px;margin-top:4px;">Try a different filter</p>' +
            '</div>';
        return;
    }

    body.innerHTML = bills.map(function(b){

        var av   = avColor(b.vendor);
        var init = (b.vendor || '?')[0].toUpperCase();

        var dl   = '/bills/' + b.bill_id + '/file';
        var view = '/bills/' + b.bill_id;

        return (
            '<div class="loy-bill-item">' +

                '<a href="'+view+'" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:inherit;">' +
                    '<div class="loy-avatar '+av+'">'+init+'</div>' +
                    '<div>' +
                        '<div class="loy-vname">'+(b.vendor||'Unknown')+'</div>' +
                        '<div class="loy-vmeta">' +
                            '<span>#'+b.bill_id+'</span>' +
                            (b.provider?'<span>'+b.provider+'</span>':'') +
                            (b.confidence!=null?'<span>'+confBar(b.confidence)+'</span>':'') +
                        '</div>' +
                    '</div>' +
                '</a>' +

                '<a href="'+view+'" class="col-date text-muted" style="font-size:13px;text-decoration:none;color:inherit;">'+fmtDate(b.bill_date)+'</a>' +

                '<a href="'+view+'" class="col-amount" style="font-weight:700;text-decoration:none;color:inherit;">'+fmt(b.amount)+'</a>' +

                '<a href="'+view+'" class="col-pts loy-pts-pill" style="text-decoration:none;">⭐ '+(b.points||0)+'</a>' +

                '<a href="'+view+'" class="col-status" style="text-decoration:none;">'+badge(b.status)+'</a>' +

                '<div class="col-actions loy-row-actions">' +
                    '<a href="'+view+'" title="View"><i class="fa-solid fa-eye"></i></a>' +
                    '<a href="'+dl+'" class="dl" title="Download" target="_blank"><i class="fa-solid fa-download"></i></a>' +
                '</div>' +

            '</div>'
        );

    }).join('');
};

export function renderPag(meta) {

    const pag = document.getElementById('loy-pagination');
    if (!pag) return;

    const cur  = meta.current_page || 1;
    const last = meta.last_page || 1;

    if (last <= 1) {
        pag.innerHTML = '';
        return;
    }

    let h = '<button class="loy-pag-btn" onclick="window.LOY_LOADER('+(cur-1)+')" '+(cur<=1?'disabled':'')+'>‹</button>';

    for (let p=1;p<=last;p++){
        if (p===1 || p===last || (p>=cur-2 && p<=cur+2)) {
            h += '<button class="loy-pag-btn'+(p===cur?' active':'')+'" onclick="window.LOY_LOADER('+p+')">'+p+'</button>';
        } else if (p===cur-3 || p===cur+3) {
            h += '<span style="padding:0 4px;color:#bbb">…</span>';
        }
    }

    h += '<button class="loy-pag-btn" onclick="window.LOY_LOADER('+(cur+1)+')" '+(cur>=last?'disabled':'')+'>›</button>';

    pag.innerHTML = h;
};

export function loyBillsLoad(page = 1) {

    window._lp_page = page || 1;

    return loyBillsFetch(
        page,
        window._lp_status || '',
        window._lp_sort   || ''
    ).then(function(res){
        renderList(res.bills, res.meta);
        renderPag(res.meta);
    });
};