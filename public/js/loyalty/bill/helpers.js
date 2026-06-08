function fmt(v) {
    return '₹' + parseFloat(v || 0).toLocaleString('en-IN', {
        minimumFractionDigits: 2
    });
}

function csrf() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
}

function countUp(el, target) {
    var s = null;

    function step(ts) {
        if (!s) s = ts;

        var p = Math.min((ts - s) / 800, 1);

        var e = 1 - Math.pow(1 - p, 3);
        el.textContent = Math.floor(e * target).toLocaleString('en-IN');
        
        if (p < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
}