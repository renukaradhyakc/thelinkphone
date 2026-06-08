window.LOY = window.LOY || {};

window.LOY.loyBillsInit = function() {

    if (!document.getElementById('loy-list-body')) return;

    window._lp_page = 1;
    window._lp_status = '';
    window._lp_sort = 'latest';

    window.LOY.loyBillsLoad(1);
};

function initAll() {
    window.LOY.loyBillsInit();
    window.LOY.loyUploadInit();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
} else {
    initAll();
}

document.addEventListener('turbo:load', initAll);
document.addEventListener('turbolinks:load', initAll);