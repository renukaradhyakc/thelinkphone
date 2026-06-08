import { csrf } from './helpers';

(function() {
    // Use event delegation on document so listeners survive Turbo re-renders.
    // The input overlay handles file picking natively — no fileInput.click() needed.
    var selectedFile = null;

    function el(id) {
        return document.getElementById(id);
    }

    function setFile(f) {
        var allowed = ['image/jpeg', 'image/png', 'application/pdf'];
        var msg = el('loy-upload-msg');
        if (!allowed.includes(f.type)) {
            msg.innerHTML = '<span class="text-danger">Only JPG, PNG or PDF allowed.</span>';
            return;
        }
        if (f.size > 5 * 1024 * 1024) {
            msg.innerHTML = '<span class="text-danger">File too large. Max 5 MB.</span>';
            return;
        }
        selectedFile = f;
        el('loy-file-name').innerHTML =
            '<i class="fa-solid fa-file-circle-check" style="color:#16a57a;margin-right:5px;"></i>' +
            '<strong>' + f.name + '</strong> (' + (f.size / 1024).toFixed(1) + ' KB)';
        el('loy-upload-btn').disabled = false;
        if (msg) msg.innerHTML = '';
    }

    // Wire input change — re-query every time modal opens so reference is always fresh
    document.addEventListener('shown.bs.modal', function(e) {
        if (!e.target || e.target.id !== 'uploadModal') return;
        // reset
        selectedFile = null;
        el('loy-file-name').textContent = '';
        el('loy-upload-msg').textContent = '';
        el('loy-upload-btn').disabled = true;
        var prog = el('loy-progress');
        if (prog) prog.style.display = 'none';
        var bar = el('loy-progress-bar');
        if (bar) bar.style.width = '0%';
        // fresh input listener
        var inp = el('loy-file-input');
        if (inp) {
            inp.value = '';
            // clone to remove any old listeners
            var fresh = inp.cloneNode(true);
            inp.parentNode.replaceChild(fresh, inp);
            fresh.addEventListener('change', function() {
                if (fresh.files && fresh.files[0]) setFile(fresh.files[0]);
            });
            // drag events on drop zone
            var dz = el('loy-drop-zone');
            if (dz) {
                dz.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    dz.classList.add('drag');
                });
                dz.addEventListener('dragleave', function() {
                    dz.classList.remove('drag');
                });
                dz.addEventListener('drop', function(e) {
                    e.preventDefault();
                    dz.classList.remove('drag');
                    if (e.dataTransfer.files[0]) setFile(e.dataTransfer.files[0]);
                });
            }
        }
    });

    // Upload button
    document.addEventListener('click', function(e) {
        if (!e.target || e.target.id !== 'loy-upload-btn') return;
        if (!selectedFile) return;

        var btn = el('loy-upload-btn');
        var prog = el('loy-progress');
        var bar = el('loy-progress-bar');
        var msg = el('loy-upload-msg');

        btn.disabled = true;
        prog.style.display = 'block';
        bar.style.width = '20%';
        msg.innerHTML = '<span class="text-muted">Authenticating…</span>';

        // Direct web route upload — CSRF protected, no token needed
        bar.style.width = '40%';
        msg.innerHTML = '<span class="text-muted">Uploading…</span>';
        var fd = new FormData();
        fd.append('bill', selectedFile);
        Promise.resolve(fetch('/loyalty/bills', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf(),
                    'Accept': 'application/json'
                },
                body: fd
            }))
            .then(function(r) {
                return r.json().then(function(j) {
                    return {
                        ok: r.ok,
                        j: j
                    };
                });
            })
            .then(function(res) {
                bar.style.width = '100%';
                if (res.ok) {
                    msg.innerHTML = '<span class="text-success fw-bold">✓ Bill #' + res.j.bill_id + ' uploaded! Processing will begin shortly.</span>';
                    selectedFile = null;
                    el('loy-file-name').textContent = '';
                    btn.disabled = true;
                    setTimeout(function() {
                        var modal = bootstrap.Modal.getInstance(el('uploadModal'));
                        if (modal) modal.hide();
                        window.loyDashInit();
                    }, 2000);
                } else {
                    var errMsg = res.j.error || 'Upload failed.';
                    if (errMsg === 'Duplicate bill') errMsg = 'This bill was already uploaded.';
                    throw new Error(errMsg);
                }
            })
            .catch(function(err) {
                msg.innerHTML = '<span class="text-danger">' + (err.message || 'Upload failed. Try again.') + '</span>';
                btn.disabled = false;
            })
            .finally(function() {
                setTimeout(function() {
                    prog.style.display = 'none';
                    bar.style.width = '0%';
                }, 1200);
            });
    });
}());