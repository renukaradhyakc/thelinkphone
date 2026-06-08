window.LOY = window.LOY || {};

window._upFile = null;

window.LOY.loyUploadInit = function() {

    var drop     = document.getElementById('loy-drop-zone');
    var input    = document.getElementById('loy-file-input');
    var clearBtn = document.getElementById('loy-up-clear');
    var upBtn    = document.getElementById('loy-up-btn');

    if (!drop) return;

    drop.addEventListener('dragover', function(e){
        e.preventDefault();
        drop.classList.add('drag');
    });

    drop.addEventListener('dragleave', function(){
        drop.classList.remove('drag');
    });

    drop.addEventListener('drop', function(e){
        e.preventDefault();
        drop.classList.remove('drag');
        if (e.dataTransfer.files[0]) window.LOY.setUpFile(e.dataTransfer.files[0]);
    });

    input.addEventListener('change', function(){
        if (input.files[0]) window.LOY.setUpFile(input.files[0]);
    });

    if (clearBtn) clearBtn.addEventListener('click', function(e){
        e.preventDefault();
        e.stopPropagation();
        window.LOY.loyUploadClear();
    });

    if (upBtn) upBtn.addEventListener('click', function(e){
        e.preventDefault();
        e.stopPropagation();
        window.LOY.doUpload();
    });
};

window.LOY.setUpFile = function(f) {

    var msg = document.getElementById('loy-up-msg');

    if (!['image/jpeg','image/png','application/pdf'].includes(f.type)) {
        msg.innerHTML = '<span class="text-danger">Only JPG, PNG or PDF allowed.</span>';
        return;
    }

    if (f.size > 5 * 1024 * 1024) {
        msg.innerHTML = '<span class="text-danger">Max 5 MB allowed.</span>';
        return;
    }

    window._upFile = f;

    document.getElementById('loy-up-fname').textContent =
        f.name + ' (' + (f.size / 1024).toFixed(1) + ' KB)';

    document.getElementById('loy-up-chosen').classList.add('show');
    document.getElementById('loy-up-submit').classList.add('show');

    msg.innerHTML = '';
};

window.LOY.loyUploadClear = function() {

    window._upFile = null;

    document.getElementById('loy-file-input').value = '';
    document.getElementById('loy-up-chosen').classList.remove('show');
    document.getElementById('loy-up-submit').classList.remove('show');
    document.getElementById('loy-up-msg').innerHTML = '';
    document.getElementById('loy-up-progress').style.display = 'none';
    document.getElementById('loy-up-bar').style.width = '0%';
};

window.LOY.doUpload = function() {

    if (!window._upFile) return;

    var btn  = document.getElementById('loy-up-btn');
    var bar  = document.getElementById('loy-up-bar');
    var prog = document.getElementById('loy-up-progress');
    var msg  = document.getElementById('loy-up-msg');

    btn.disabled = true;
    prog.style.display = 'block';
    bar.style.width = '30%';

    var formData = new FormData();
    formData.append('bill', window._upFile);

    fetch('/loyalty/bills', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': window.LOY.csrf(),
            'Accept': 'application/json'
        },
        body: formData
    })
    .then(r => r.json())
    .then(function(res){

        bar.style.width = '100%';

        if (!res.success) throw new Error(res.message || 'Upload failed.');

        msg.innerHTML = '<span class="text-success">✓ Bill #' + res.data.bill_id + ' uploaded!</span>';

        setTimeout(function(){
            window.LOY.loyUploadClear();
            window.LOY.loyBillsLoad(window._lp_page);
        }, 1500);
    })
    .catch(function(err){
        msg.innerHTML = '<span class="text-danger">'+err.message+'</span>';
        btn.disabled = false;
    })
    .finally(function(){
        setTimeout(function(){
            prog.style.display = 'none';
            bar.style.width = '0%';
        }, 1000);
    });
};