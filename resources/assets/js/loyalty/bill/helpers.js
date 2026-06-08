export function fmt(v) {
    return '₹' + parseFloat(v || 0).toLocaleString('en-IN', {
        minimumFractionDigits: 2
    });
}

export function csrf() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
}

export function countUp(el, target) {
    let s = null;

    function step(ts) {
        if (!s) s = ts;

        const p = Math.min((ts - s) / 800, 1);

        const e = 1 - Math.pow(1 - p, 3);
        el.textContent = Math.floor(e * target).toLocaleString('en-IN');
        
        if (p < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
}