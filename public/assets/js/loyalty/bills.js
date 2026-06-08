/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./resources/assets/js/loyalty/bills/api.js":
/*!**************************************************!*\
  !*** ./resources/assets/js/loyalty/bills/api.js ***!
  \**************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "loyBillsFetch": () => (/* binding */ loyBillsFetch)
/* harmony export */ });
/* harmony import */ var _helpers__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./helpers */ "./resources/assets/js/loyalty/bills/helpers.js");

function loyBillsFetch(page, status, sort) {
  var url = "/me/bills?page=".concat(page, "&per_page=10");

  if (status) {
    url += "&status=".concat(encodeURIComponent(status));
  }

  var body = document.getElementById('loy-list-body');
  if (body) body.style.opacity = '.5';
  return fetch(url, {
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': (0,_helpers__WEBPACK_IMPORTED_MODULE_0__.csrf)(),
      'Accept': 'application/json'
    }
  }).then(function (r) {
    return r.json();
  }).then(function (j) {
    var bills = j.data || [];
    var meta = j.meta || {};

    if (sort === 'amount_desc') {
      bills.sort(function (a, b) {
        return parseFloat(b.amount) - parseFloat(a.amount);
      });
    }

    if (sort === 'amount_asc') {
      bills.sort(function (a, b) {
        return parseFloat(a.amount) - parseFloat(b.amount);
      });
    }

    return {
      bills: bills,
      meta: meta
    };
  })["finally"](function () {
    if (body) body.style.opacity = '1';
  });
}
;

/***/ }),

/***/ "./resources/assets/js/loyalty/bills/helpers.js":
/*!******************************************************!*\
  !*** ./resources/assets/js/loyalty/bills/helpers.js ***!
  \******************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "avColor": () => (/* binding */ avColor),
/* harmony export */   "badge": () => (/* binding */ badge),
/* harmony export */   "fmt": () => (/* binding */ fmt),
/* harmony export */   "fmtDate": () => (/* binding */ fmtDate),
/* harmony export */   "csrf": () => (/* binding */ csrf),
/* harmony export */   "confBar": () => (/* binding */ confBar)
/* harmony export */ });
var AV_COLORS = ['loy-av-purple', 'loy-av-amber', 'loy-av-green', 'loy-av-blue', 'loy-av-red'];
function avColor(n) {
  if (!n) return AV_COLORS[0];
  var s = n.split('').reduce(function (a, c) {
    return a + c.charCodeAt(0);
  }, 0);
  return AV_COLORS[s % AV_COLORS.length];
}
function badge(s) {
  var M = {
    done: 'badge-done ✓ Approved',
    pending: 'badge-pending ⏳ Pending',
    failed: 'badge-failed ✕ Failed',
    duplicate: 'badge-duplicate ⊘ Dup',
    review: 'badge-review 👁 Review',
    invalid: 'badge-invalid ⚠ Invalid'
  };
  var v = (M[s] || "badge-duplicate ".concat(s)).split(' ');
  var cls = v.shift();
  return "<span class=\"".concat(cls, "\">").concat(v.join(' '), "</span>");
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
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
  }).replace(/\//g, '-');
}
function csrf() {
  var m = document.querySelector('meta[name="csrf-token"]');
  return m ? m.getAttribute('content') : '';
}
function confBar(c) {
  if (c == null) return '';
  var pct = Math.round(c * 100);
  var col = pct >= 80 ? '#22c984' : pct >= 50 ? '#f6b600' : '#e74c3c';
  return "\n        <div class=\"loy-conf-track\">\n            <div class=\"loy-conf-fill\" style=\"width:".concat(pct, "%;background:").concat(col, "\"></div>\n        </div>\n        <span style=\"font-size:10px;color:#aaa;margin-left:3px\">").concat(pct, "%</span>\n    ");
}

/***/ }),

/***/ "./resources/assets/js/loyalty/bills/list.js":
/*!***************************************************!*\
  !*** ./resources/assets/js/loyalty/bills/list.js ***!
  \***************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "renderList": () => (/* binding */ renderList),
/* harmony export */   "renderPag": () => (/* binding */ renderPag),
/* harmony export */   "loyBillsLoad": () => (/* binding */ loyBillsLoad)
/* harmony export */ });
/* harmony import */ var _helpers__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./helpers */ "./resources/assets/js/loyalty/bills/helpers.js");
/* harmony import */ var _api__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./api */ "./resources/assets/js/loyalty/bills/api.js");


function renderList(bills, meta) {
  var cnt = document.getElementById('loy-bill-count');
  var body = document.getElementById('loy-list-body');
  if (cnt) cnt.textContent = "(".concat(meta.total || bills.length, " total)");

  if (!bills.length) {
    body.innerHTML = '<div class="loy-empty">' + '<i class="fa-solid fa-receipt" style="font-size:24px;display:block;margin-bottom:8px;opacity:.25;"></i>' + '<div style="font-weight:700">No bills found</div>' + '<p style="font-size:13px;margin-top:4px;">Try a different filter</p>' + '</div>';
    return;
  }

  body.innerHTML = bills.map(function (b) {
    var av = (0,_helpers__WEBPACK_IMPORTED_MODULE_0__.avColor)(b.vendor);
    var init = (b.vendor || '?')[0].toUpperCase();
    var dl = '/bills/' + b.bill_id + '/file';
    var view = '/bills/' + b.bill_id;
    return '<div class="loy-bill-item">' + '<a href="' + view + '" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:inherit;">' + '<div class="loy-avatar ' + av + '">' + init + '</div>' + '<div>' + '<div class="loy-vname">' + (b.vendor || 'Unknown') + '</div>' + '<div class="loy-vmeta">' + '<span>#' + b.bill_id + '</span>' + (b.provider ? '<span>' + b.provider + '</span>' : '') + (b.confidence != null ? '<span>' + (0,_helpers__WEBPACK_IMPORTED_MODULE_0__.confBar)(b.confidence) + '</span>' : '') + '</div>' + '</div>' + '</a>' + '<a href="' + view + '" class="col-date text-muted" style="font-size:13px;text-decoration:none;color:inherit;">' + (0,_helpers__WEBPACK_IMPORTED_MODULE_0__.fmtDate)(b.bill_date) + '</a>' + '<a href="' + view + '" class="col-amount" style="font-weight:700;text-decoration:none;color:inherit;">' + (0,_helpers__WEBPACK_IMPORTED_MODULE_0__.fmt)(b.amount) + '</a>' + '<a href="' + view + '" class="col-pts loy-pts-pill" style="text-decoration:none;">⭐ ' + (b.points || 0) + '</a>' + '<a href="' + view + '" class="col-status" style="text-decoration:none;">' + (0,_helpers__WEBPACK_IMPORTED_MODULE_0__.badge)(b.status) + '</a>' + '<div class="col-actions loy-row-actions">' + '<a href="' + view + '" title="View"><i class="fa-solid fa-eye"></i></a>' + '<a href="' + dl + '" class="dl" title="Download" target="_blank"><i class="fa-solid fa-download"></i></a>' + '</div>' + '</div>';
  }).join('');
}
;
function renderPag(meta) {
  var pag = document.getElementById('loy-pagination');
  if (!pag) return;
  var cur = meta.current_page || 1;
  var last = meta.last_page || 1;

  if (last <= 1) {
    pag.innerHTML = '';
    return;
  }

  var h = '<button class="loy-pag-btn" onclick="window.LOY_LOADER(' + (cur - 1) + ')" ' + (cur <= 1 ? 'disabled' : '') + '>‹</button>';

  for (var p = 1; p <= last; p++) {
    if (p === 1 || p === last || p >= cur - 2 && p <= cur + 2) {
      h += '<button class="loy-pag-btn' + (p === cur ? ' active' : '') + '" onclick="window.LOY_LOADER(' + p + ')">' + p + '</button>';
    } else if (p === cur - 3 || p === cur + 3) {
      h += '<span style="padding:0 4px;color:#bbb">…</span>';
    }
  }

  h += '<button class="loy-pag-btn" onclick="window.LOY_LOADER(' + (cur + 1) + ')" ' + (cur >= last ? 'disabled' : '') + '>›</button>';
  pag.innerHTML = h;
}
;
function loyBillsLoad() {
  var page = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : 1;
  window._lp_page = page || 1;
  return (0,_api__WEBPACK_IMPORTED_MODULE_1__.loyBillsFetch)(page, window._lp_status || '', window._lp_sort || '').then(function (res) {
    renderList(res.bills, res.meta);
    renderPag(res.meta);
  });
}
;

/***/ }),

/***/ "./resources/assets/js/loyalty/bills/upload.js":
/*!*****************************************************!*\
  !*** ./resources/assets/js/loyalty/bills/upload.js ***!
  \*****************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "loyUploadInit": () => (/* binding */ loyUploadInit)
/* harmony export */ });
function loyUploadInit() {
  var drop = document.getElementById('loy-drop-zone');
  var input = document.getElementById('loy-file-input');
  var clearBtn = document.getElementById('loy-up-clear');
  var upBtn = document.getElementById('loy-up-btn');
  if (!drop) return;
  drop.addEventListener('dragover', function (e) {
    e.preventDefault();
    drop.classList.add('drag');
  });
  drop.addEventListener('dragleave', function () {
    drop.classList.remove('drag');
  });
  drop.addEventListener('drop', function (e) {
    e.preventDefault();
    drop.classList.remove('drag');
    if (e.dataTransfer.files[0]) setUpFile(e.dataTransfer.files[0]);
  });
  input.addEventListener('change', function () {
    if (input.files[0]) setUpFile(input.files[0]);
  });
  if (clearBtn) clearBtn.addEventListener('click', function (e) {
    e.preventDefault();
    e.stopPropagation();
    loyUploadClear();
  });
  if (upBtn) upBtn.addEventListener('click', function (e) {
    e.preventDefault();
    e.stopPropagation();
    doUpload();
  });
}
;
var _upFile = null;

function setUpFile(f) {
  var msg = document.getElementById('loy-up-msg');

  if (!['image/jpeg', 'image/png', 'application/pdf'].includes(f.type)) {
    msg.innerHTML = '<span class="text-danger">Only JPG, PNG or PDF allowed.</span>';
    return;
  }

  if (f.size > 5 * 1024 * 1024) {
    msg.innerHTML = '<span class="text-danger">Max 5 MB allowed.</span>';
    return;
  }

  _upFile = f;
  document.getElementById('loy-up-fname').textContent = "".concat(f.name, " (").concat((f.size / 1024).toFixed(1), " KB)");
  document.getElementById('loy-up-chosen').classList.add('show');
  document.getElementById('loy-up-submit').classList.add('show');
  msg.innerHTML = '';
}

;

function loyUploadClear() {
  _upFile = null;
  document.getElementById('loy-file-input').value = '';
  document.getElementById('loy-up-chosen').classList.remove('show');
  document.getElementById('loy-up-submit').classList.remove('show');
  document.getElementById('loy-up-msg').innerHTML = '';
  document.getElementById('loy-up-progress').style.display = 'none';
  document.getElementById('loy-up-bar').style.width = '0%';
}

;

function doUpload() {
  var _document$querySelect;

  if (!_upFile) return;
  var btn = document.getElementById('loy-up-btn');
  var bar = document.getElementById('loy-up-bar');
  var prog = document.getElementById('loy-up-progress');
  var msg = document.getElementById('loy-up-msg');
  btn.disabled = true;
  prog.style.display = 'block';
  bar.style.width = '30%';
  var formData = new FormData();
  formData.append('bill', _upFile);
  fetch('/loyalty/bills', {
    method: 'POST',
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': (_document$querySelect = document.querySelector('meta[name="csrf-token"]')) === null || _document$querySelect === void 0 ? void 0 : _document$querySelect.getAttribute('content'),
      'Accept': 'application/json'
    },
    body: formData
  }).then(function (r) {
    return r.json();
  }).then(function (res) {
    bar.style.width = '100%';
    if (!res.success) throw new Error(res.message || 'Upload failed.');
    msg.innerHTML = "<span class=\"text-success\">\u2713 Bill #".concat(res.data.bill_id, " uploaded!</span>");
    setTimeout(function () {
      var _window$LOY_LOADER, _window;

      loyUploadClear();
      (_window$LOY_LOADER = (_window = window).LOY_LOADER) === null || _window$LOY_LOADER === void 0 ? void 0 : _window$LOY_LOADER.call(_window, window._lp_page || 1);
    }, 1500);
  })["catch"](function (err) {
    msg.innerHTML = "<span class=\"text-danger\">".concat(err.message, "</span>");
    btn.disabled = false;
  })["finally"](function () {
    setTimeout(function () {
      prog.style.display = 'none';
      bar.style.width = '0%';
    }, 1000);
  });
}

;

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
/*!**************************************************!*\
  !*** ./resources/assets/js/loyalty/bills/app.js ***!
  \**************************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _list__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./list */ "./resources/assets/js/loyalty/bills/list.js");
/* harmony import */ var _upload__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./upload */ "./resources/assets/js/loyalty/bills/upload.js");


window.LOY_LOADER = _list__WEBPACK_IMPORTED_MODULE_0__.loyBillsLoad;

function initAll() {
  (0,_upload__WEBPACK_IMPORTED_MODULE_1__.loyUploadInit)();

  if (document.getElementById('loy-list-body')) {
    (0,_list__WEBPACK_IMPORTED_MODULE_0__.loyBillsLoad)(1);
  }
}

document.addEventListener('DOMContentLoaded', initAll);
document.addEventListener('turbo:load', initAll);
document.addEventListener('turbolinks:load', initAll);
})();

/******/ })()
;