function fetchBill(billId) {
    return fetch('/bills/' + billId, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf(),
            'Accept': 'application/json'
        }
    }).then(function(r){
        return r.json();
    });
}