/******/ (() => { // webpackBootstrap
var __webpack_exports__ = {};
/*!**********************************************************!*\
  !*** ./resources/assets/js/schedule_events/check-otp.js ***!
  \**********************************************************/
var passed = false;
listenSubmit('#checkOTPForm', function (e) {
  if (!passed) {
    e.preventDefault();
  } else {
    return true;
  }

  passed = true;
  $('#checkOTPForm')[0].submit();
  $('#checkOTP').prop('disabled', true);
});
/******/ })()
;