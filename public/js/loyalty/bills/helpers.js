window.LOY = window.LOY || {};

window.LOY.AV = ['loy-av-purple','loy-av-amber','loy-av-green','loy-av-blue','loy-av-red'];

window.LOY.avC = function(n){
    if(!n) return window.LOY.AV[0];
    var s = n.split('').reduce(function(a,c){ return a + c.charCodeAt(0); }, 0);
    return window.LOY.AV[s % window.LOY.AV.length];
};

window.LOY.badge = function(s){
    var M = {
        done:'badge-done ✓ Approved',
        pending:'badge-pending ⏳ Pending',
        failed:'badge-failed ✕ Failed',
        duplicate:'badge-duplicate ⊘ Dup',
        review:'badge-review 👁 Review',
        invalid:'badge-invalid ⚠ Invalid'
    };

    var v = (M[s] || 'badge-duplicate ' + s).split(' ');
    var c = v.shift();

    return '<span class="'+c+'">'+v.join(' ')+'</span>';
};

window.LOY.fmt = function(v){
    return '₹' + parseFloat(v || 0).toLocaleString('en-IN', {
        minimumFractionDigits: 2
    });
};

window.LOY.fmtDate = function(v){
    if(!v) return '—';
    var d = new Date(v);
    return isNaN(d) ? v : d.toLocaleDateString('en-IN',{
        year:'numeric', month:'2-digit', day:'2-digit'
    }).replace(/\//g,'-');
};

window.LOY.csrf = function(){
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
};

window.LOY.confBar = function(c){
    if(c == null) return '';

    var pct = Math.round(c * 100);
    var col = pct >= 80 ? '#22c984' : pct >= 50 ? '#f6b600' : '#e74c3c';

    return '<div class="loy-conf-track">' +
           '<div class="loy-conf-fill" style="width:'+pct+'%;background:'+col+'"></div>' +
           '</div>' +
           '<span style="font-size:10px;color:#aaa;margin-left:3px">'+pct+'%</span>';
};