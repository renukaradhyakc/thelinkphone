/******/ (() => { // webpackBootstrap
var __webpack_exports__ = {};
// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!**********************************************!*\
  !*** ./resources/assets/js/custom/helper.js ***!
  \**********************************************/
window.listen = function (event, selector, callback) {
  $(document).on(event, selector, callback);
};

window.listenClick = function (selector, callback) {
  $(document).on('click', selector, callback);
};

window.listenSubmit = function (selector, callback) {
  $(document).on('submit', selector, callback);
};

window.listenChange = function (selector, callback) {
  $(document).on('change', selector, callback);
};

window.listenKeyup = function (selector, callback) {
  $(document).on('keyup', selector, callback);
};

window.listenHiddenBsModal = function (selector, callback) {
  $(document).on('hidden.bs.modal', selector, callback);
};
})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!**********************************************!*\
  !*** ./resources/assets/js/custom/custom.js ***!
  \**********************************************/
document.addEventListener('turbo:load', loadCustomData);
var source = null;
document.addEventListener('turbo:load', initAllComponents);

function initAllComponents() {
  refreshCsrfToken();
  alertInitialize();
  modalInputFocus();
  inputFocus();
  tooltip();
}

function alertInitialize() {
  $('.alert').delay(5000).slideUp(300);
}

function refreshCsrfToken() {
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });
}

function tooltip() {
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });
}

var inputFocus = function inputFocus() {
  $('input:text:not([readonly="readonly"]):not([name="search"])').first().focus();
};

var modalInputFocus = function modalInputFocus() {
  $(function () {
    $('.modal').on('shown.bs.modal', function () {
      if ($(this).find('input:text')[0]) {
        $(this).find('input:text')[0].focus();
      }
    });
  });
};

window.hideDropdownManually = function (dropdownBtnEle) {
  dropdownBtnEle.removeClass('show');
};

function loadCustomData() {
  // script to active parent menu if sub menu has currently active
  var hasActiveMenu = $(document).find('.nav-item.dropdown ul li').hasClass('active');

  if (hasActiveMenu) {
    $(document).find('.nav-item.dropdown ul li.active').parent('ul').css('display', 'block');
    $(document).find('.nav-item.dropdown ul li.active').parent('ul').parent('li').addClass('active');
  }

  var timezone_offset_minutes = new Date().getTimezoneOffset();
  timezone_offset_minutes = timezone_offset_minutes === 0 ? 0 : -timezone_offset_minutes;
  document.cookie = 'timezone_offset_minutes=' + timezone_offset_minutes;
}

listen('select2:open', function () {
  var allFound = document.querySelectorAll('.select2-container--open .select2-search__field');
  allFound[allFound.length - 1].focus();
});
listen('focus', '.select2.select2-container', function (e) {
  var isOriginalEvent = e.originalEvent; // don't re-open on closing focus event

  var isSingleSelect = $(this).find('.select2-selection--single').length > 0; // multi-select will pass focus to input

  if (isOriginalEvent && isSingleSelect) {
    $(this).siblings('select:enabled').select2('open');
  }
});
toastr.options = {
  "closeButton": true,
  "debug": false,
  "newestOnTop": false,
  "progressBar": true,
  "positionClass": "toast-top-right",
  "preventDuplicates": false,
  "onclick": null,
  "showDuration": "300",
  "hideDuration": "1000",
  "timeOut": "5000",
  "extendedTimeOut": "1000",
  "showEasing": "swing",
  "hideEasing": "linear",
  "showMethod": "fadeIn",
  "hideMethod": "fadeOut"
};

window.resetModalForm = function (formId, validationBox) {
  $(formId)[0].reset();
  $('select.select2Selector').each(function (index, element) {
    var drpSelector = '#' + $(this).attr('id');
    $(drpSelector).val('');
    $(drpSelector).trigger('change');
  });
  $(validationBox).hide();
};

window.printErrorMessage = function (selector, errorResult) {
  $(selector).show().html('');
  $(selector).text(errorResult.responseJSON.message);
};

window.manageAjaxErrors = function (data) {
  var errorDivId = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 'editValidationErrorsBox';

  if (data.status == 404) {
    toastr.error(data.responseJSON.message);
  } else {
    printErrorMessage('#' + errorDivId, data);
  }
};

window.displaySuccessMessage = function (message) {
  toastr.success(message, Lang.get('js.successful'));
};

window.displayErrorMessage = function (message) {
  toastr.error(message, Lang.get('js.something_went_wrong'));
};

window.deleteItem = function (url, header) {
  var callFunction = arguments.length > 3 && arguments[3] !== undefined ? arguments[3] : null;
  swal({
    title: Lang.get('js.delete'),
    text: Lang.get('js.sure_delete') + ' "' + header + '"  ?',
    buttons: {
      confirm: Lang.get('js.yes'),
      cancel: Lang.get('js.no')
    },
    icon: sweetAlertIcon,
    reverseButtons: true
  }).then(function (willDelete) {
    if (willDelete) {
      deleteItemAjax(url, header, callFunction);
    }
  });
};

function deleteItemAjax(url, header) {
  var callFunction = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : null;
  $.ajax({
    url: url,
    type: 'DELETE',
    dataType: 'json',
    success: function success(obj) {
      if (obj.success) {
        window.livewire.emit('refresh');
        window.livewire.emit('resetPageTable');
      }

      swal({
        icon: 'success',
        confirmButtonColor: '#ADB5BD',
        title: deleteMsg + ' !',
        text: header + ' ' + hasBeenDeleted,
        buttons: {
          confirm: Lang.get("js.ok")
        },
        timer: 2000
      });

      if (callFunction) {
        eval(callFunction);
      }
    },
    error: function error(data) {
      swal({
        title: 'Error',
        icon: 'error',
        text: data.responseJSON.message,
        type: 'error',
        timer: 4000
      });
    }
  });
}

window.format = function (dateTime) {
  var format = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 'DD-MMM-YYYY';
  return moment(dateTime).format(format);
};

window.processingBtn = function (selecter, btnId) {
  var state = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : null;
  var loadingButton = $(selecter).find(btnId);

  if (state === 'loading') {
    loadingButton.button('loading');
  } else {
    loadingButton.button('reset');
  }
};

window.setBtnLoader = function (btnLoader) {
  if (btnLoader.attr('data-old-text')) {
    btnLoader.html(btnLoader.attr('data-old-text')).prop('disabled', false);
    btnLoader.removeAttr('data-old-text');
    return;
  }

  btnLoader.attr('data-old-text', btnLoader.text());
  btnLoader.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>').prop('disabled', true);
};

window.prepareTemplateRender = function (templateSelector, data) {
  var template = jsrender.templates(templateSelector);
  return template.render(data);
};

window.isValidFile = function (inputSelector, validationMessageSelector) {
  var ext = $(inputSelector).val().split('.').pop().toLowerCase();

  if ($.inArray(ext, ['gif', 'png', 'jpg', 'jpeg']) == -1) {
    $(inputSelector).val('');
    $(validationMessageSelector).removeClass('d-none');
    $(validationMessageSelector).html('The image must be a file of type: jpeg, jpg, png.').show();
    $(validationMessageSelector).delay(5000).slideUp(300);
    return false;
  }

  $(validationMessageSelector).hide();
  return true;
};

window.displayPhoto = function (input, selector) {
  var displayPreview = true;

  if (input.files && input.files[0]) {
    var reader = new FileReader();

    reader.onload = function (e) {
      var image = new Image();
      image.src = e.target.result;

      image.onload = function () {
        $(selector).attr('src', e.target.result);
        displayPreview = true;
      };
    };

    if (displayPreview) {
      reader.readAsDataURL(input.files[0]);
      $(selector).show();
    }
  }
};

window.removeCommas = function (str) {
  return str.replace(/,/g, '');
};

window.DatetimepickerDefaults = function (opts) {
  return $.extend({}, {
    sideBySide: true,
    ignoreReadonly: true,
    icons: {
      close: 'fa fa-times',
      time: 'fa fa-clock-o',
      date: 'fa fa-calendar',
      up: 'fa fa-arrow-up',
      down: 'fa fa-arrow-down',
      previous: 'fa fa-chevron-left',
      next: 'fa fa-chevron-right',
      today: 'fa fa-clock-o',
      clear: 'fa fa-trash-o'
    }
  }, opts);
};

window.isEmpty = function (value) {
  return value === undefined || value === null || value === '';
};

window.urlValidation = function (value, regex) {
  var urlCheck = value == '' ? true : value.match(regex) ? true : false;

  if (!urlCheck) {
    return false;
  }

  return true;
};

if ($(window).width() > 992) {
  $('.no-hover').on('click', function () {
    $(this).toggleClass('open');
  });
}

window.preparedTemplate = function () {
  source = $('#actionTemplate').html();
  window.preparedTemplate = Handlebars.compile(source);
};

window.ajaxCallInProgress = function () {
  ajaxCallIsRunning = true;
};

window.ajaxCallCompleted = function () {
  ajaxCallIsRunning = false;
};

window.avoidSpace = function (event) {
  var k = event ? event.which : window.event.keyCode;

  if (k == 32) {
    return false;
  }
};

$('input[type=radio][name=gender]').on('change', function () {
  var file = $('#profilePicture').val();

  if (isEmpty(file)) {
    if (this.value == 1) {
      $('.image-input-wrapper').attr('style', 'background-image:url(' + manAvatar + ')');
    } else if (this.value == 2) {
      $('.image-input-wrapper').attr('style', 'background-image:url(' + womanAvatar + ')');
    }
  }
});

window.setBtnLoader = function (btnLoader) {
  if (btnLoader.attr('data-old-text')) {
    btnLoader.html(btnLoader.attr('data-old-text')).prop('disabled', false);
    btnLoader.removeAttr('data-old-text');
    return;
  }

  btnLoader.attr('data-old-text', btnLoader.text());
  btnLoader.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>').prop('disabled', true);
};

window.addCommas = function (nStr) {
  nStr += '';
  var x = nStr.split('.');
  var x1 = x[0];
  var x2 = x.length > 1 ? '.' + x[1] : '';
  var rgx = /(\d+)(\d{3})/;

  while (rgx.test(x1)) {
    x1 = x1.replace(rgx, '$1' + ',' + '$2');
  }

  return x1 + x2;
};

window.getFormattedPrice = function (price) {
  if (price != '' || price > 0) {
    if (typeof price !== 'number') {
      price = price.replace(/,/g, '');
    }

    return addCommas(price);
  }
};

listenClick('.change-type', function (e) {
  var inputField = $(this).siblings();
  var oldType = inputField.attr('type');
  var type = !isEmpty(oldType) ? oldType : 'password';

  if (type == 'password') {
    $(this).children().addClass('fa-eye');
    $(this).children().removeClass('fa-eye-slash');
    inputField.attr('type', 'text');
  } else {
    $(this).children().removeClass('fa-eye');
    $(this).children().addClass('fa-eye-slash');
    inputField.attr('type', 'password');
  }
});
$('.dropdown-menu a').on('click', function () {
  $(this).closest('.dropdown-menu').prev().dropdown('toggle');
}); // cancel schedule event modal code

listenClick('.cancel-scheduled-event', function () {
  var scheduledEventId = $(this).attr('data-id');
  $('#scheduleEventId').val(scheduledEventId);
  $('#cancelScheduleEventModal').modal('show').appendTo('body');
}); // cancel schedule event code

listenSubmit('#cancelScheduleEventForm', function (e) {
  e.preventDefault();

  if (isEmpty($('#cancelReason').val())) {
    displayErrorMessage('Cancel reason field is required.');
    return false;
  }

  var scheduledEventId = $('#scheduleEventId').val();
  $.ajax({
    url: route('cancel.scheduled.event', scheduledEventId),
    type: 'POST',
    data: $(this).serialize(),
    success: function success(result) {
      if (result.success) {
        window.livewire.emit('refresh');
        $('#cancelScheduleEventModal').modal('hide');
      }
    },
    error: function error(result) {
      displayErrorMessage(result.responseJSON.message);
    }
  });
}); // cancel modal reset data code

listenHiddenBsModal('#cancelScheduleEventModal', function () {
  resetModalForm('#cancelScheduleEventForm', '#cancelValidationErrorsBox');
});
listenClick('.copy-google-meet-link', function () {
  var $temp = $('<input>');
  $('body').append($temp);
  $temp.val($(this).attr('data-link')).select();
  document.execCommand('copy');
  $temp.remove();
  $(this).children().css('color', '#8BC34A');
  $(this).children().removeClass('fa-copy');
  $(this).children().addClass('fa-check');
  displaySuccessMessage(Lang.get('js.linked_copy_successfully'));
  setTimeout(function () {
    $('.copy-google-meet-link').children().removeClass('fa-check');
    $('.copy-google-meet-link').children().addClass('fa-copy');
    $('.copy-google-meet-link').children().css('color', '#009ef7');
  }, 2000);
});
})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!*****************************************************************!*\
  !*** ./resources/assets/js/custom/phone-number-country-code.js ***!
  \*****************************************************************/
document.addEventListener('turbo:load', loadPhoneNumberCountryCodeData);

function loadPhoneNumberCountryCodeData() {
  loadPhoneNumberCountryCode();
}

function loadPhoneNumberCountryCode() {
  if (!$('#phoneNumber').length) {
    return false;
  }

  var input = document.querySelector('#phoneNumber'),
      errorMsg = document.querySelector('#error-msg'),
      validMsg = document.querySelector('#valid-msg');

  if ($('#valid-msg').length > 0) {
    setTimeout(function () {
      $('#valid-msg').addClass('d-none');
    }, 10);
  }

  var errorMap = [Lang.get('js.invalid_number'), Lang.get('js.invalid_country_code'), Lang.get('js.too_short'), Lang.get('js.too_long'), Lang.get('js.invalid_number')]; // initialise plugin

  var intl = window.intlTelInput(input, {
    initialCountry: defaultCountryCodeValue,
    separateDialCode: true,
    preferredCountries: false,
    geoIpLookup: function geoIpLookup(success, failure) {
      $.get('https://ipinfo.io', function () {}, 'jsonp').always(function (resp) {
        var countryCode = resp && resp.country ? resp.country : '';
        success(countryCode);
      });
    },
    utilsScript: '../../public/assets/js/inttel/js/utils.min.js'
  });

  var reset = function reset() {
    input.classList.remove('error');
    errorMsg.innerHTML = '';
    errorMsg.classList.add('d-none');
    validMsg.classList.add('d-none');
  };

  input.addEventListener('blur', function () {
    reset();

    if (input.value.trim()) {
      if (intl.isValidNumber()) {
        validMsg.classList.remove('d-none');
      } else {
        input.classList.add('error');
        var errorCode = intl.getValidationError();
        errorMsg.innerHTML = errorMap[errorCode];
        errorMsg.classList.remove('d-none');
      }
    }
  }); // on keyup / change flag: reset

  input.addEventListener('change', reset);
  input.addEventListener('keyup', reset);

  if (typeof phoneNo != 'undefined' && phoneNo !== '') {
    setTimeout(function () {
      $('#phoneNumber').trigger('change');
    }, 500);
  }

  $('#phoneNumber').on('blur keyup change countrychange', function () {
    if (typeof phoneNo != 'undefined' && phoneNo !== '') {
      intl.setNumber('+' + phoneNo);
      phoneNo = '';
    }

    var getCode = intl.selectedCountryData['dialCode'];
    $('#prefix_code').val(getCode);
  });
  var getCode = intl.selectedCountryData['dialCode'];
  $('#prefix_code').val(getCode);
  var getPhoneNumber = $('#phoneNumber').val();
  var removeSpacePhoneNumber = getPhoneNumber.replace(/\s/g, '');
  $('#phoneNumber').val(removeSpacePhoneNumber);
  $('#phoneNumber').focus();
  $('#phoneNumber').trigger('blur');
}

listenClick('.iti__country', function () {
  var flagClass = $('.iti__selected-flag>.iti__flag').attr('class');
  flagClass = flagClass.split(/\s+/)[1];
  var dialCodeVal = $('.iti__selected-dial-code').text();
  window.localStorage.setItem('flagClassLocal', flagClass);
  window.localStorage.setItem('dialCodeValLocal', dialCodeVal);
});
})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!************************************************************!*\
  !*** ./resources/assets/js/slot_calendar/slot-calendar.js ***!
  \************************************************************/
document.addEventListener('DOMContentLoaded', loadSlotCalendarData);

function loadSlotCalendarData() {
  if (!$('#calendar').length) {
    return;
  }

  var slotCalendar = $('#calendar').evoCalendar({
    theme: 'Royal Navy',
    format: 'yyyy-mm-dd',
    calendarEvents: eventSchedules
  });
  $('#calendar').evoCalendar('selectDate', moment().tz(currentUTCDate).format('LL'));
  $('#calendar').evoCalendar('selectMonth', months);
  $('#calendar').evoCalendar('selectYear', year);
  var selectedDate = moment().tz(currentUTCDate).format('Y-M-D');
  var selectedYear = year;
  var month = parseInt(parseInt(months) + 1);
  var selectedMonth = moment(month, 'M').format('MMMM');
  $(slotCalendar).on('selectMonth', function (event, activeEvent) {
    window.location.href = slotCalendarUrl + '?month=' + activeEvent + '&year=' + selectedYear;
  });
  $(slotCalendar).on('selectYear', function (event, activeEvent) {
    window.location.href = slotCalendarUrl + '?month=' + selectedMonth + '&year=' + activeEvent;
  });
  $(slotCalendar).on('selectDate', function (event, activeEvent) {
    selectedDate = activeEvent;
  });
  $('#calendar').on('selectEvent', function (event, activeEvent) {
    var activeDate = $('#calendar').evoCalendar('getActiveDate');
    var activeEvents = $('#calendar').evoCalendar('getActiveEvents');
    var id = activeEvent.id;
    var selectedTime = '';
    var originalTime = '';
    $.each(activeEvents, function (i, v) {
      if (id === v.id) {
        selectedTime = v.name;
        originalTime = v.originalTime;
      }
    });
    window.location.href = slotCalendarUrl + '/' + activeDate + '?time=' + selectedTime + '&originalTime=' + originalTime;
  });
}
})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!********************************************************************!*\
  !*** ./resources/assets/js/event_schedules/event-slot-schedule.js ***!
  \********************************************************************/
document.addEventListener('DOMContentLoaded', loadPhoneNumberCountryCode2);

function loadPhoneNumberCountryCode2() {
  if (!$('#phoneNumber').length) {
    return false;
  }

  var input = document.querySelector('#phoneNumber'),
      errorMsg = document.querySelector('#error-msg'),
      validMsg = document.querySelector('#valid-msg');

  if ($('#valid-msg').length > 0) {
    setTimeout(function () {
      $('#valid-msg').addClass('d-none');
    }, 10);
  }

  var errorMap = [Lang.get('js.invalid_number'), Lang.get('js.invalid_country_code'), Lang.get('js.too_short'), Lang.get('js.too_long'), Lang.get('js.invalid_number')]; // initialise plugin

  var intl2 = window.intlTelInput(input, {
    initialCountry: defaultCountryCodeValue,
    separateDialCode: true,
    geoIpLookup: function geoIpLookup(success, failure) {
      $.get('https://ipinfo.io', function () {}, 'jsonp').always(function (resp) {
        var countryCode = resp && resp.country ? resp.country : '';
        success(countryCode);
      });
    },
    utilsScript: '../../public/assets/js/inttel/js/utils.min.js'
  });

  var reset = function reset() {
    input.classList.remove('error');
    errorMsg.innerHTML = '';
    errorMsg.classList.add('d-none');
    validMsg.classList.add('d-none');
  };

  input.addEventListener('blur', function () {
    reset();

    if (input.value.trim()) {
      if (intl2.isValidNumber()) {
        validMsg.classList.remove('d-none');
      } else {
        input.classList.add('error');
        var errorCode = intl2.getValidationError();
        errorMsg.innerHTML = errorMap[errorCode];
        errorMsg.classList.remove('d-none');
      }
    }
  }); // on keyup / change flag: reset

  input.addEventListener('change', reset);
  input.addEventListener('keyup', reset);

  if (typeof phoneNo != 'undefined' && phoneNo !== '') {
    setTimeout(function () {
      $('#phoneNumber').trigger('change');
    }, 500);
  }

  $('#phoneNumber').on('blur keyup change countrychange', function () {
    if (typeof phoneNo != 'undefined' && phoneNo !== '') {
      intl2.setNumber('+' + phoneNo);
      phoneNo = '';
    }

    var getCode = intl2.selectedCountryData['dialCode'];
    $('#prefix_code').val(getCode);
  });
  var getCode = intl2.selectedCountryData['dialCode'];
  $('#prefix_code').val(getCode);
  var getPhoneNumber = $('#phoneNumber').val();
  var removeSpacePhoneNumber = getPhoneNumber.replace(/\s/g, '');
  $('#phoneNumber').val(removeSpacePhoneNumber);
  $('#phoneNumber').focus();
  $('#phoneNumber').trigger('blur');
}

var paymentType = '';
listenSubmit('#addEventSlotScheduleForm', function (e) {
  e.preventDefault();
  var eventLocationArr = [];

  if (locationMeta[1] == 1) {
    if ($('#phoneNumber').val() == '') {
      $('#phoneNumber').focus();
      displayErrorMessage(Lang.get('js.phone_required'));
      return false;
    }

    eventLocationArr.push(eventLocation);
    eventLocationArr.push(locationMeta[1]);
    eventLocationArr.push('+' + $('#prefix_code').val() + $('#phoneNumber').val());
    $('#eventLocationPhoneCall').val(JSON.stringify(eventLocationArr));
  }

  paymentType = $('#slotPaymentType').val();
  var btnSubmitEle = $(this).find('#slotPaymentSubmitBtn');
  setBtnLoader(btnSubmitEle);
  $.ajax({
    url: route('scheduled-events.store'),
    type: 'POST',
    data: $(this).serialize(),
    success: function success(result) {
      if (result.success) {
        var scheduleEventId = result.data.scheduleEventId;
        var confirmPageUrl = result.data.redirectUrl;

        if (result.data.eventType == Paid) {
          if (paymentType == 1) {
            // stripe payment gateway
            var sessionId = result.data[0].sessionId;
            stripe.redirectToCheckout({
              sessionId: sessionId
            }).then(function (result) {
              manageAjaxErrors(result.message);
            });
          } else if (paymentType == 2) {
            // Paypal payment gateway
            $.ajax({
              type: 'GET',
              url: route('paypal.init'),
              data: {
                'scheduleEventId': scheduleEventId
              },
              success: function success(result) {
                if (result.status == 'CREATED') {
                  var redirectTo = '';
                  $.each(result.links, function (key, val) {
                    if (val.rel == 'approve') {
                      redirectTo = val.href;
                    }
                  });
                  location.href = redirectTo;
                } else {
                  location.href = result.url;
                }
              }
            });
          }
        } else {
          $('#addEventSlotScheduleForm')[0].reset();
          displaySuccessMessage(result.message);
          window.location.href = confirmPageUrl;
        }
      }
    },
    error: function error(result) {
      displayErrorMessage(result.responseJSON.message);
    },
    complete: function complete() {
      setBtnLoader(btnSubmitEle);
    }
  });
});
})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!**********************************************************************************!*\
  !*** ./resources/assets/js/custom-livewire-calendar/custom-livewire-calendar.js ***!
  \**********************************************************************************/
// for next month calendar 
listenClick('#nextMonth', function () {
  var nextMonth = $(this).attr('data-next-month');
  window.livewire.emit('changeMonth', nextMonth);
}); // for previous month calendar 

listenClick('#prevMonth', function () {
  var prevMonth = $(this).attr('data-prev-month');
  window.livewire.emit('changeMonth', prevMonth);
}); // get task related date

listenClick('.get-slots', function () {
  var slotDate = $(this).attr('date-slot-date');
  window.livewire.emit('getSlotTime', moment(slotDate).format('YYYY-MM-DD'));
});
})();

/******/ })()
;