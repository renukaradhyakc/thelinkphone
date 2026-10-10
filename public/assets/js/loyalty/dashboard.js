/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./resources/assets/js/loyalty/dashboard/helpers.js":
/*!**********************************************************!*\
  !*** ./resources/assets/js/loyalty/dashboard/helpers.js ***!
  \**********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "avColor": () => (/* binding */ avColor),
/* harmony export */   "badge": () => (/* binding */ badge),
/* harmony export */   "fmt": () => (/* binding */ fmt),
/* harmony export */   "fmtDate": () => (/* binding */ fmtDate),
/* harmony export */   "csrf": () => (/* binding */ csrf),
/* harmony export */   "get": () => (/* binding */ get),
/* harmony export */   "countUp": () => (/* binding */ countUp)
/* harmony export */ });
var AV_COLORS = ['loy-av-purple', 'loy-av-amber', 'loy-av-green', 'loy-av-blue', 'loy-av-red'];
function avColor(n) {
  if (!n) return 'loy-av-purple';
  var s = n.split('').reduce(function (a, c) {
    return a + c.charCodeAt(0);
  }, 0);
  return AV_COLORS[s % AV_COLORS.length];
}
function badge(s) {
  var M = {
    done: 'badge-done ✓ Done',
    pending: 'badge-pending ⏳ Pending',
    failed: 'badge-failed ✕ Failed',
    duplicate: 'badge-duplicate ⊘ Dup',
    review: 'badge-review 👁 Review',
    invalid: 'badge-invalid ⚠ Invalid'
  };
  var v = (M[s] || 'badge-duplicate ' + s).split(' ');
  var cls = v.shift();
  return '<span class="' + cls + '">' + v.join(' ') + '</span>';
}
function fmt(v) {
  return '₹' + parseFloat(v || 0).toLocaleString('en-IN', {
    minimumFractionDigits: 2
  });
}
function fmtDate(v) {
  if (!v) return '—';
  var d = new Date(v);
  return isNaN(d) ? v : d.toLocaleDateString('en-IN', {
    day: '2-digit',
    month: 'short',
    year: 'numeric'
  });
}
function csrf() {
  var m = document.querySelector('meta[name="csrf-token"]');
  return m ? m.getAttribute('content') : '';
}
function get(url) {
  return fetch(url, {
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': csrf(),
      'Accept': 'application/json'
    }
  }).then(function (r) {
    return r.json();
  });
}
function countUp(el, target) {
  var start = null;

  function step(ts) {
    if (!start) start = ts;
    var p = Math.min((ts - start) / 800, 1),
        e = 1 - Math.pow(1 - p, 3);
    el.textContent = Math.floor(e * target).toLocaleString('en-IN');
    if (p < 1) requestAnimationFrame(step);
  }

  requestAnimationFrame(step);
}

/***/ }),

/***/ "./resources/assets/js/loyalty/dashboard/upload.js":
/*!*********************************************************!*\
  !*** ./resources/assets/js/loyalty/dashboard/upload.js ***!
  \*********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _helpers__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./helpers */ "./resources/assets/js/loyalty/dashboard/helpers.js");


(function () {
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
    el('loy-file-name').innerHTML = '<i class="fa-solid fa-file-circle-check" style="color:#16a57a;margin-right:5px;"></i>' + '<strong>' + f.name + '</strong> (' + (f.size / 1024).toFixed(1) + ' KB)';
    el('loy-upload-btn').disabled = false;
    if (msg) msg.innerHTML = '';
  } // Wire input change — re-query every time modal opens so reference is always fresh


  document.addEventListener('shown.bs.modal', function (e) {
    if (!e.target || e.target.id !== 'uploadModal') return; // reset

    selectedFile = null;
    el('loy-file-name').textContent = '';
    el('loy-upload-msg').textContent = '';
    el('loy-upload-btn').disabled = true;
    var prog = el('loy-progress');
    if (prog) prog.style.display = 'none';
    var bar = el('loy-progress-bar');
    if (bar) bar.style.width = '0%'; // fresh input listener

    var inp = el('loy-file-input');

    if (inp) {
      inp.value = ''; // clone to remove any old listeners

      var fresh = inp.cloneNode(true);
      inp.parentNode.replaceChild(fresh, inp);
      fresh.addEventListener('change', function () {
        if (fresh.files && fresh.files[0]) setFile(fresh.files[0]);
      }); // drag events on drop zone

      var dz = el('loy-drop-zone');

      if (dz) {
        dz.addEventListener('dragover', function (e) {
          e.preventDefault();
          dz.classList.add('drag');
        });
        dz.addEventListener('dragleave', function () {
          dz.classList.remove('drag');
        });
        dz.addEventListener('drop', function (e) {
          e.preventDefault();
          dz.classList.remove('drag');
          if (e.dataTransfer.files[0]) setFile(e.dataTransfer.files[0]);
        });
      }
    }
  }); // Upload button

  document.addEventListener('click', function (e) {
    if (!e.target || e.target.id !== 'loy-upload-btn') return;
    if (!selectedFile) return;
    var btn = el('loy-upload-btn');
    var prog = el('loy-progress');
    var bar = el('loy-progress-bar');
    var msg = el('loy-upload-msg');
    btn.disabled = true;
    prog.style.display = 'block';
    bar.style.width = '20%';
    msg.innerHTML = '<span class="text-muted">Authenticating…</span>'; // Direct web route upload — CSRF protected, no token needed

    bar.style.width = '40%';
    msg.innerHTML = '<span class="text-muted">Uploading…</span>';
    var fd = new FormData();
    fd.append('bill', selectedFile);
    Promise.resolve(fetch('/loyalty/bills', {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': (0,_helpers__WEBPACK_IMPORTED_MODULE_0__.csrf)(),
        'Accept': 'application/json'
      },
      body: fd
    })).then(function (r) {
      return r.json().then(function (j) {
        return {
          ok: r.ok,
          j: j
        };
      });
    }).then(function (res) {
      bar.style.width = '100%';

      if (res.ok) {
        msg.innerHTML = '<span class="text-success fw-bold">✓ Bill #' + res.j.bill_id + ' uploaded! Processing will begin shortly.</span>';
        selectedFile = null;
        el('loy-file-name').textContent = '';
        btn.disabled = true;
        setTimeout(function () {
          var modal = bootstrap.Modal.getInstance(el('uploadModal'));
          if (modal) modal.hide();
          window.loyDashInit();
        }, 2000);
      } else {
        var errMsg = res.j.error || 'Upload failed.';
        if (errMsg === 'Duplicate bill') errMsg = 'This bill was already uploaded.';
        throw new Error(errMsg);
      }
    })["catch"](function (err) {
      msg.innerHTML = '<span class="text-danger">' + (err.message || 'Upload failed. Try again.') + '</span>';
      btn.disabled = false;
    })["finally"](function () {
      setTimeout(function () {
        prog.style.display = 'none';
        bar.style.width = '0%';
      }, 1200);
    });
  });
})();

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry need to be wrapped in an IIFE because it need to be isolated against other modules in the chunk.
(() => {
/*!************************************************************!*\
  !*** ./resources/assets/js/loyalty/dashboard/dashboard.js ***!
  \************************************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "loyDashInit": () => (/* binding */ loyDashInit)
/* harmony export */ });
/* harmony import */ var _helpers__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./helpers */ "./resources/assets/js/loyalty/dashboard/helpers.js");
/* harmony import */ var _upload__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./upload */ "./resources/assets/js/loyalty/dashboard/upload.js");


function loyDashInit() {
  if (!document.getElementById('loy-pts-val')) return;
  document.getElementById('loy-pts-val').textContent = '—';
  document.getElementById('loy-recent-body').innerHTML = '<div class="loy-empty-row"><i class="fa-solid fa-receipt" style="opacity:.2;font-size:24px;display:block;margin-bottom:8px;"></i><p>Loading…</p></div>';
  (0,_helpers__WEBPACK_IMPORTED_MODULE_0__.get)('/me/points').then(function (j) {
    var pts = j.data ? j.data.total_points : 0;
    var el = document.getElementById('loy-pts-val');
    el.textContent = '0';
    (0,_helpers__WEBPACK_IMPORTED_MODULE_0__.countUp)(el, pts);
    var lsP = document.getElementById('ls-points');

    if (lsP) {
      lsP.textContent = '0';
      (0,_helpers__WEBPACK_IMPORTED_MODULE_0__.countUp)(lsP, pts);
    }
  })["catch"](function () {
    document.getElementById('loy-pts-val').textContent = '—';
  });
  (0,_helpers__WEBPACK_IMPORTED_MODULE_0__.get)('/me/bills?per_page=5').then(function (j) {
    var bills = j.data || [],
        meta = j.meta || {};
    var total = meta.total || 0;
    var approved = bills.filter(function (b) {
      return b.status === 'done';
    }).length;
    var pending = bills.filter(function (b) {
      return b.status === 'pending';
    }).length;
    var spent = bills.reduce(function (s, b) {
      return s + parseFloat(b.amount || 0);
    }, 0);
    document.getElementById('loy-spent-val').textContent = (0,_helpers__WEBPACK_IMPORTED_MODULE_0__.fmt)(spent);
    document.getElementById('loy-bills-processed').textContent = total + ' bills processed';
    var elT = document.getElementById('ls-total'),
        elA = document.getElementById('ls-approved'),
        elPe = document.getElementById('ls-pending'),
        elAr = document.getElementById('ls-appr-rate');

    if (elT) {
      elT.textContent = '0';
      (0,_helpers__WEBPACK_IMPORTED_MODULE_0__.countUp)(elT, total);
    }

    if (elA) {
      elA.textContent = '0';
      (0,_helpers__WEBPACK_IMPORTED_MODULE_0__.countUp)(elA, approved);
    }

    if (elPe) {
      elPe.textContent = '0';
      (0,_helpers__WEBPACK_IMPORTED_MODULE_0__.countUp)(elPe, pending);
    }

    if (elAr) {
      elAr.textContent = total > 0 ? Math.round(approved / total * 100) + '% rate' : '—';
    }

    var body = document.getElementById('loy-recent-body');

    if (!bills.length) {
      body.innerHTML = '<div class="loy-empty-row"><i class="fa-solid fa-receipt" style="opacity:.2;font-size:24px;display:block;margin-bottom:8px;"></i><p>No bills yet. Upload your first bill!</p></div>';
      return;
    }

    body.innerHTML = bills.slice(0, 5).map(function (b) {
      var av = (0,_helpers__WEBPACK_IMPORTED_MODULE_0__.avColor)(b.vendor);
      var init = (b.vendor || '?')[0].toUpperCase();
      return '<a href="/bills/' + b.bill_id + '" class="loy-tbl-row">' + '<div class="d-flex align-items-center gap-2">' + '<div class="loy-avatar ' + av + '">' + init + '</div>' + '<div><div class="loy-vendor-name">' + (b.vendor || 'Unknown') + '</div>' + '<div class="loy-vendor-meta"><span>#' + b.bill_id + '</span><span>' + (b.provider || '') + '</span></div></div>' + '</div>' + '<div class="text-muted fs-7">' + (0,_helpers__WEBPACK_IMPORTED_MODULE_0__.fmtDate)(b.bill_date) + '</div>' + '<div class="fw-bold">' + (0,_helpers__WEBPACK_IMPORTED_MODULE_0__.fmt)(b.amount) + '</div>' + '<div class="loy-pts-pill">⭐ ' + (b.points || 0) + '</div>' + '<div>' + (0,_helpers__WEBPACK_IMPORTED_MODULE_0__.badge)(b.status) + '</div>' + '</a>';
    }).join('');
  })["catch"](function () {
    document.getElementById('loy-recent-body').innerHTML = '<div class="loy-empty-row"><i class="fa-solid fa-triangle-exclamation" style="font-size:24px;display:block;margin-bottom:8px;opacity:.3;"></i><p>Could not load bills.</p></div>';
  });
}
window.loyDashInit = loyDashInit;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', loyDashInit);
} else {
  loyDashInit();
}

document.addEventListener('turbo:load', loyDashInit);
document.addEventListener('turbolinks:load', loyDashInit);
})();

/******/ })()
;