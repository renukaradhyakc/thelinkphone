import { csrf } from './helpers';

export function loyBillsFetch(page, status, sort) {

    let url = `/me/bills?page=${page}&per_page=10`;

    if (status) {
        url += `&status=${encodeURIComponent(status)}`;
    }

    const body = document.getElementById('loy-list-body');
    if (body) body.style.opacity = '.5';

    return fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf(),
                'Accept': 'application/json'
            }
        })
        .then(r => r.json())
        .then(j => {

            let bills = j.data || [];
            const meta = j.meta || {};

            if (sort === 'amount_desc') {
                bills.sort((a, b) => parseFloat(b.amount) - parseFloat(a.amount));
            }

            if (sort === 'amount_asc') {
                bills.sort((a, b) => parseFloat(a.amount) - parseFloat(b.amount));
            }

            return {
                bills: bills,
                meta: meta
            };
        })
        .finally(function() {
            if (body) body.style.opacity = '1';
        });
};