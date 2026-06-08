window.LOY = window.LOY || {};

window.LOY.loyBillsFetch = function(page, status, sort) {

    var url = '/me/bills?page=' + page + '&per_page=10';

    if (status !== '') {
        url += '&status=' + encodeURIComponent(status);
    }

    var body = document.getElementById('loy-list-body');
    if (body) body.style.opacity = '.5';

    return fetch(url, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': window.LOY.csrf(),
            'Accept': 'application/json'
        }
    })
    .then(function(r){ return r.json(); })
    .then(function(j){

        var bills = j.data || [];
        var meta  = j.meta || {};

        if (sort === 'amount_desc') {
            bills.sort(function(a,b){ return parseFloat(b.amount) - parseFloat(a.amount); });
        }

        if (sort === 'amount_asc') {
            bills.sort(function(a,b){ return parseFloat(a.amount) - parseFloat(b.amount); });
        }

        return { bills: bills, meta: meta };
    })
    .finally(function(){
        if (body) body.style.opacity = '1';
    });
};