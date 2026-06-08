import { csrf } from './helpers';

export function fetchBill(billId) {
    return fetch(`/bills/${billId}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf(),
            'Accept': 'application/json'
        }
    }).then(r => r.json());
}