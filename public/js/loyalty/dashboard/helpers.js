var AV_COLORS = [
    'loy-av-purple',
    'loy-av-amber',
    'loy-av-green',
    'loy-av-blue',
    'loy-av-red'
];

function avColor(n) {
    if (!n) return 'loy-av-purple';

    var s = n.split('').reduce(function(a,c){
        return a + c.charCodeAt(0);
    },0);

    return AV_COLORS[s % AV_COLORS.length];
}

function badge(s) {
    var M = {
        done:'badge-done ✓ Done',
        pending:'badge-pending ⏳ Pending',
        failed:'badge-failed ✕ Failed',
        duplicate:'badge-duplicate ⊘ Dup',
        review:'badge-review 👁 Review',
        invalid:'badge-invalid ⚠ Invalid'
    };

    var v = (M[s] || 'badge-duplicate ' + s).split(' ');
    var cls = v.shift();

    return '<span class="'+cls+'">'+v.join(' ')+'</span>';
}

function fmt(v){
    return '₹' + parseFloat(v || 0).toLocaleString('en-IN',{
        minimumFractionDigits:2
    });
}

function fmtDate(v){
    if (!v) return '—';

    var d = new Date(v);

    return isNaN(d)
        ? v
        : d.toLocaleDateString('en-IN',{
            day:'2-digit',
            month:'short',
            year:'numeric'
        });
}

function csrf(){
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
}

function get(url){
    return fetch(url,{
        headers:{
            'X-Requested-With':'XMLHttpRequest',
            'X-CSRF-TOKEN':csrf(),
            'Accept':'application/json'
        }
    }).then(function(r){
        return r.json();
    });
}

function countUp(el,target){
    var start=null;

    function step(ts){
        if(!start) start=ts;

        var p=Math.min((ts-start)/800,1),
            e=1-Math.pow(1-p,3);

        el.textContent=Math.floor(e*target)
            .toLocaleString('en-IN');

        if(p<1) requestAnimationFrame(step);
    }

    requestAnimationFrame(step);
}