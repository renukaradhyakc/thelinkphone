import { fetchBill } from './api';
import { render } from './page';

let initialized = false;

export function initBillPage() {

    if (initialized) return;
    if (!document.getElementById('loy-show-page')) return;

    initialized = true;

    const billId = document.body.dataset.billId || window.BILL_ID;

    if (!billId) return;

    document.getElementById('loy-loading').style.display = 'block';
    document.getElementById('loy-content').style.display = 'none';
    document.getElementById('loy-action-btns').innerHTML = '';

    fetchBill(billId)
        .then(function(j){
            render(j.data, j.data.items || [], billId);
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

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initBillPage);
} else {
    initBillPage();
}

document.addEventListener('turbo:load', initBillPage);
document.addEventListener('turbolinks:load', initBillPage);