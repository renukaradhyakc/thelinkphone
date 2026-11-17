/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./resources/assets/js/loyalty/bill/api.js":
/*!*************************************************!*\
  !*** ./resources/assets/js/loyalty/bill/api.js ***!
  \*************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "fetchBill": () => (/* binding */ fetchBill)
/* harmony export */ });
/* harmony import */ var _helpers__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./helpers */ "./resources/assets/js/loyalty/bill/helpers.js");

function fetchBill(billId) {
  return fetch("/bills/".concat(billId), {
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': (0,_helpers__WEBPACK_IMPORTED_MODULE_0__.csrf)(),
      'Accept': 'application/json'
    }
  }).then(function (r) {
    return r.json();
  });
}

/***/ }),

/***/ "./resources/assets/js/loyalty/bill/helpers.js":
/*!*****************************************************!*\
  !*** ./resources/assets/js/loyalty/bill/helpers.js ***!
  \*****************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "fmt": () => (/* binding */ fmt),
/* harmony export */   "csrf": () => (/* binding */ csrf),
/* harmony export */   "countUp": () => (/* binding */ countUp)
/* harmony export */ });
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

/***/ }),

/***/ "./resources/assets/js/loyalty/bill/page.js":
/*!**************************************************!*\
  !*** ./resources/assets/js/loyalty/bill/page.js ***!
  \**************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "render": () => (/* binding */ render)
/* harmony export */ });
/* harmony import */ var _helpers__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./helpers */ "./resources/assets/js/loyalty/bill/helpers.js");

var STATUS_MAP = {
  done: {
    icon: '✅',
    label: 'Approved & Verified',
    sub: 'Points awarded',
    cls: 'done'
  },
  pending: {
    icon: '⏳',
    label: 'Pending Processing',
    sub: 'Being reviewed',
    cls: 'pending'
  },
  failed: {
    icon: '❌',
    label: 'Processing Failed',
    sub: 'Contact support',
    cls: 'failed'
  },
  duplicate: {
    icon: '⊘',
    label: 'Duplicate Detected',
    sub: 'Already submitted',
    cls: 'duplicate'
  },
  review: {
    icon: '👁',
    label: 'Under Manual Review',
    sub: 'Admin reviewing',
    cls: 'review'
  },
  invalid: {
    icon: '⚠️',
    label: 'Invalid Document',
    sub: 'Not a valid bill',
    cls: 'invalid'
  }
};
function render(b, items, billId) {
  var dlUrl = '/bills/' + billId + '/file';
  document.getElementById('loy-action-btns').innerHTML = '<a href="' + dlUrl + '" class="btn btn-success btn-sm fw-bold" target="_blank">' + '<i class="fa-solid fa-download me-1"></i>Download Bill</a>';
  document.getElementById('loy-store-name').textContent = b.vendor || 'Unknown Vendor';
  document.getElementById('loy-rh-meta').innerHTML = (b.bill_date ? '<span><i class="fa-regular fa-calendar me-1"></i>' + b.bill_date + '</span>' : '') + '<span><i class="fa-solid fa-hashtag me-1"></i>Bill #' + b.bill_id + '</span>' + (b.provider ? '<span><i class="fa-solid fa-robot me-1"></i>' + b.provider + '</span>' : '');

  if (b.invoice_number) {
    document.getElementById('loy-inv-badge').style.display = 'block';
    document.getElementById('loy-inv-num').textContent = '#' + b.invoice_number;
  }

  var fields = [['Bill Date', b.bill_date || '—'], ['Vendor', b.vendor || '—'], ['Invoice No.', b.invoice_number || '—'], ['OCR Provider', b.provider || '—']];
  document.getElementById('loy-info-grid').innerHTML = fields.map(function (f) {
    return '<div><div class="loy-info-label">' + f[0] + '</div><div class="loy-info-val">' + f[1] + '</div></div>';
  }).join('');
  var sec = document.getElementById('loy-items-section');

  if (items.length) {
    var rows = items.map(function (item) {
      var dotColor = item.is_eligible ? '#22c984' : '#ddd';
      return '<tr>' + '<td>' + '<span class="loy-item-dot" style="background:' + dotColor + '"></span>' + '<span style="font-weight:600">' + (item.description || '—') + '</span>' + (item.category ? '<div class="loy-item-cat">' + item.category + '</div>' : '') + '</td>' + '<td style="text-align:center">' + (item.quantity || 1) + '</td>' + '<td style="text-align:right">' + (item.unit_price ? (0,_helpers__WEBPACK_IMPORTED_MODULE_0__.fmt)(item.unit_price) : '—') + '</td>' + '<td style="text-align:right;font-weight:700">' + (0,_helpers__WEBPACK_IMPORTED_MODULE_0__.fmt)(item.line_total) + '</td>' + '</tr>';
    }).join('');
    sec.innerHTML = '<div class="loy-items-label">Line Items (' + items.length + ')</div>' + '<table class="loy-items-table">' + '<thead><tr><th>Item</th><th style="text-align:center">Qty</th><th style="text-align:right">Unit Price</th><th style="text-align:right">Total</th></tr></thead>' + '<tbody>' + rows + '</tbody></table>';
  } else {
    sec.innerHTML = '<div class="text-muted text-center py-4 fs-7">' + '<i class="fa-solid fa-list" style="opacity:.25;font-size:24px;display:block;margin-bottom:8px;"></i>' + 'No line items available.</div>';
  }

  var sub = items.reduce(function (s, i) {
    return s + parseFloat(i.line_total || 0);
  }, 0);
  var totHtml = '';

  if (items.length) {
    totHtml += '<div class="loy-total-row"><span>Subtotal (' + items.length + ' items)</span><span>' + (0,_helpers__WEBPACK_IMPORTED_MODULE_0__.fmt)(sub) + '</span></div>';
  }

  var tax = parseFloat(b.amount || 0) - sub;

  if (items.length && tax > 0.01) {
    totHtml += '<div class="loy-total-row"><span>HST Tax</span><span>' + (0,_helpers__WEBPACK_IMPORTED_MODULE_0__.fmt)(tax) + '</span></div>';
  }

  totHtml += '<div class="loy-total-grand"><span>Total</span><span>' + (0,_helpers__WEBPACK_IMPORTED_MODULE_0__.fmt)(b.amount) + '</span></div>';
  document.getElementById('loy-totals').innerHTML = totHtml;
  var ptsEl = document.getElementById('loy-pts-num');
  var pts = parseInt(b.points) || 0;
  ptsEl.textContent = '0';
  (0,_helpers__WEBPACK_IMPORTED_MODULE_0__.countUp)(ptsEl, pts);
  var si = STATUS_MAP[b.status] || {
    icon: '❓',
    label: b.status,
    sub: '',
    cls: 'pending'
  };
  document.getElementById('loy-status-block').innerHTML = '<div class="loy-status-block ' + si.cls + '">' + '<span style="font-size:24px">' + si.icon + '</span>' + '<div><div class="fw-bold" style="font-size:14px">' + si.label + '</div>' + '<div class="text-muted" style="font-size:12px">' + si.sub + '</div></div></div>';

  if (b.confidence != null) {
    var pct = Math.round(b.confidence * 100);
    var col = pct >= 80 ? '#22c984' : pct >= 50 ? '#f6b600' : '#e74c3c';
    document.getElementById('loy-conf-card').style.display = 'block';
    document.getElementById('loy-conf-pct').textContent = pct + '%';
    document.getElementById('loy-conf-pct').style.color = col;
    document.getElementById('loy-conf-label').textContent = pct >= 80 ? 'High confidence read' : pct >= 50 ? 'Moderate confidence' : 'Low — manual check advised';
    var bar = document.getElementById('loy-conf-bar');
    bar.style.background = col;
    setTimeout(function () {
      bar.style.width = pct + '%';
    }, 200);
  }

  document.getElementById('loy-dl-link').href = dlUrl;
  document.getElementById('loy-loading').style.display = 'none';
  document.getElementById('loy-content').style.display = 'block';
}

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
/*!*************************************************!*\
  !*** ./resources/assets/js/loyalty/bill/app.js ***!
  \*************************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "initBillPage": () => (/* binding */ initBillPage)
/* harmony export */ });
/* harmony import */ var _api__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./api */ "./resources/assets/js/loyalty/bill/api.js");
/* harmony import */ var _page__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./page */ "./resources/assets/js/loyalty/bill/page.js");


var initialized = false;
function initBillPage() {
  if (initialized) return;
  if (!document.getElementById('loy-show-page')) return;
  initialized = true;
  var billId = document.body.dataset.billId || window.BILL_ID;
  if (!billId) return;
  document.getElementById('loy-loading').style.display = 'block';
  document.getElementById('loy-content').style.display = 'none';
  document.getElementById('loy-action-btns').innerHTML = '';
  (0,_api__WEBPACK_IMPORTED_MODULE_0__.fetchBill)(billId).then(function (j) {
    (0,_page__WEBPACK_IMPORTED_MODULE_1__.render)(j.data, j.data.items || [], billId);
  })["catch"](function () {
    document.getElementById('loy-loading').innerHTML = '<div style="text-align:center;padding:64px;color:#6c757d;">' + '<div style="font-size:40px;margin-bottom:16px;">😕</div>' + '<p style="font-size:16px;font-weight:600;">Could not load bill.</p>' + '<a href="' + window.location.origin + '/loyalty/bills" class="btn btn-primary mt-2">← Back to Bills</a>' + '</div>';
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initBillPage);
} else {
  initBillPage();
}

document.addEventListener('turbo:load', initBillPage);
document.addEventListener('turbolinks:load', initBillPage);
})();

/******/ })()
;