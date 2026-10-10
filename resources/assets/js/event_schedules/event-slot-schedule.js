document.addEventListener('DOMContentLoaded',loadPhoneNumberCountryCode2)

function loadPhoneNumberCountryCode2 () {

    if (!$('#phoneNumber').length) {
        return false
    }

    let input = document.querySelector('#phoneNumber'),
        errorMsg = document.querySelector('#error-msg'),
        validMsg = document.querySelector('#valid-msg')

    if ($('#valid-msg').length > 0) {
        setTimeout(function () {
            $('#valid-msg').addClass('d-none')
        }, 10)
    }

    let errorMap = [
        Lang.get('js.invalid_number'),
        Lang.get('js.invalid_country_code'),
        Lang.get('js.too_short'),
        Lang.get('js.too_long'),
        Lang.get('js.invalid_number')]

    // initialise plugin
    let intl2 = window.intlTelInput(input, {
        initialCountry: defaultCountryCodeValue,
        separateDialCode: true,
        geoIpLookup: function (success, failure) {
            $.get('https://ipinfo.io', function () {
            }, 'jsonp').always(function (resp) {
                let countryCode = (resp && resp.country)
                    ? resp.country
                    : ''
                success(countryCode)
            })
        },
        utilsScript: '../../public/assets/js/inttel/js/utils.min.js',
    })

    let reset = function () {
        input.classList.remove('error')
        errorMsg.innerHTML = ''
        errorMsg.classList.add('d-none')
        validMsg.classList.add('d-none')
    }

    input.addEventListener('blur', function () {
        reset()
        if (input.value.trim()) {
            if (intl2.isValidNumber()) {
                validMsg.classList.remove('d-none')
            } else {
                input.classList.add('error')
                let errorCode = intl2.getValidationError()
                errorMsg.innerHTML = errorMap[errorCode]
                errorMsg.classList.remove('d-none')
            }
        }
    })

    // on keyup / change flag: reset
    input.addEventListener('change', reset)
    input.addEventListener('keyup', reset)

    if (typeof phoneNo != 'undefined' && phoneNo !== '') {
        setTimeout(function () {
            $('#phoneNumber').trigger('change')
        }, 500)
    }

    $('#phoneNumber').on('blur keyup change countrychange', function () {
        if (typeof phoneNo != 'undefined' && phoneNo !== '') {
            intl2.setNumber('+' + phoneNo)
            phoneNo = ''
        }
        let getCode = intl2.selectedCountryData['dialCode']
        $('#prefix_code').val(getCode)
    })

    let getCode = intl2.selectedCountryData['dialCode']
    $('#prefix_code').val(getCode)

    let getPhoneNumber = $('#phoneNumber').val()
    let removeSpacePhoneNumber = getPhoneNumber.replace(/\s/g, '')
    $('#phoneNumber').val(removeSpacePhoneNumber)

    $('#phoneNumber').focus()
    $('#phoneNumber').trigger('blur')
}

let paymentType = ''

listenSubmit('#addEventSlotScheduleForm', function (e) {
    e.preventDefault()
    let eventLocationArr = []
    if (locationMeta[1] == 1) {
        if ($('#phoneNumber').val() == '') {
            $('#phoneNumber').focus()
            displayErrorMessage(
                Lang.get('js.phone_required'))
            return false
        }

        eventLocationArr.push(eventLocation)
        eventLocationArr.push(locationMeta[1])
        eventLocationArr.push(
            '+' + $('#prefix_code').val() + $('#phoneNumber').val())
        $('#eventLocationPhoneCall').val(JSON.stringify(eventLocationArr))
    }

    if ($('#bookerShareLocationToggle').is(':checked')) {
        const bookerRadioIsLive = $('#bookerLocationTypeLive').is(':checked');
        const bookerLiveChecked = $('#bookerLiveSharingToggle').is(':checked');
        const bookerAddressRequired = !bookerRadioIsLive || (bookerRadioIsLive && bookerLiveChecked);

        if (bookerAddressRequired) {
            let shortName = $('#shortDescLoc').val();
            let empty = shortName.trim().replace(/ \r\n\t/g, '') === '';

            if (shortName === '' || empty) {
                $('#shortDescLoc').focus();
                displayErrorMessage(Lang.get('js.location'));
                return false;
            }
        }
    }

    paymentType = $('#slotPaymentType').val()
    let btnSubmitEle = $(this).find('#slotPaymentSubmitBtn')
    setBtnLoader(btnSubmitEle)

    $.ajax({
        url: route('scheduled-events.store'),
        type: 'POST',
        data: $(this).serialize(),
        success: function (result) {
            if (result.success) {
                if (result.data.needsGoogleAuth) {
                    window.location.href = result.data.authUrl
                    return
                }
                if (result.data.needsZoomAuth) {
                    window.location.href = result.data.authUrl
                    return
                }
                if (result.data.needsCalendarSelection) {
                    displayErrorMessage(result.message)
                    window.location.href = result.data.settingsUrl
                    return
                }
                let scheduleEventId = result.data.scheduleEventId
                let confirmPageUrl = result.data.redirectUrl
                if (result.data.eventType == Paid) {
                    if (paymentType == 1) {       // stripe payment gateway
                        let sessionId = result.data[0].sessionId
                        stripe.redirectToCheckout({
                            sessionId: sessionId,
                        }).then(function (result) {
                            manageAjaxErrors(result.message)
                        })
                    } else if (paymentType == 2) {  // Paypal payment gateway
                        $.ajax({
                            type: 'GET',
                            url: route('paypal.init'),
                            data: { 'scheduleEventId': scheduleEventId },
                            success: function (result) {
                                if (result.status == 'CREATED') {
                                    let redirectTo = ''

                                    $.each(result.links, function (key, val) {
                                        if (val.rel == 'approve') {
                                            redirectTo = val.href
                                        }
                                    })
                                    location.href = redirectTo
                                } else {
                                    location.href = result.url
                                }
                            }
                        })
                    }
                } else {
                    $('#addEventSlotScheduleForm')[0].reset()
                    displaySuccessMessage(result.message)
                    window.location.href = confirmPageUrl
                }
            }
        },
        error: function (result) {
            displayErrorMessage(result.responseJSON.message);
        },
        complete: function () {
            setBtnLoader(btnSubmitEle);
        },
    });
});

$(document).ready(function () {
    // Initialize Bootstrap tooltips for the booker location section
    $('[data-bs-toggle="tooltip"]').tooltip();
});

// Master toggle: show/hide the whole Location Type section
$(document).on('change', '#bookerShareLocationToggle', function () {
    $("#bookerLocationTypeWrap").toggleClass("d-none", !this.checked);

    if (!this.checked) {
        // reset everything back to default when master toggle is turned off
        $("#bookerLiveSharingToggleWrap").addClass("d-none");
        $("#bookerLiveSharingToggle").prop("checked", false);
        $("#bookerLocationTypeFixed").prop("checked", true);
        $("#bookerLocationTypeLive").prop("checked", false);
    }
});

// Fixed / Live radio: show/hide the live-sharing sub-toggle
$(document).on('change', 'input[name="booker_location_type"]', function () {
    var isLive = $(this).val() === "live";
    $("#bookerLiveSharingToggleWrap").toggleClass("d-none", !isLive);

    if (!isLive) {
        $("#bookerLiveSharingToggle").prop("checked", false);
    }
});

function fetchAndFillBookerLocation() {
    const statusEl = $("#locationFetchStatus");
    statusEl.removeClass("d-none text-danger").addClass("text-muted").text("Fetching your location...");

    if (!navigator.geolocation) {
        statusEl.removeClass("text-muted").addClass("text-danger").text("Geolocation isn't supported by this browser.");
        return;
    }

    navigator.geolocation.getCurrentPosition(
        function (position) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;

            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                .then(res => res.json())
                .then(data => {
                    if (data && data.display_name) {
                        $("#shortDescLoc").val(data.display_name).trigger("change");
                        $("#newBookerLocationLatitude").val(lat);
                        $("#newBookerLocationLongitude").val(lng);
                        $("#newBookerLocationAccuracy").val(position.coords.accuracy);
                        statusEl.addClass("d-none");
                    } else {
                        statusEl.removeClass("text-muted").addClass("text-danger").text("Couldn't resolve an address for that location.");
                    }
                })
                .catch(function () {
                    statusEl.removeClass("text-muted").addClass("text-danger").text("Couldn't resolve an address for that location.");
                });
        },
        function (error) {
            let msg = "Couldn't get your location.";
            if (error.code === error.PERMISSION_DENIED) {
                msg = "Location permission denied. You can still type the address manually.";
            }
            statusEl.removeClass("text-muted").addClass("text-danger").text(msg);
        },
        { enableHighAccuracy: true, timeout: 10000 }
    );
}

$(document).on("click", "#useMyLocationBtn", function () {
    fetchAndFillBookerLocation();
});

$(document).on('change', '#bookerLiveSharingToggle', function () {
    if ($(this).is(':checked')) {
        if (typeof bookerActiveLiveCount !== 'undefined' && bookerActiveLiveCount >= 3) {
            $(this).prop('checked', false);
            displayErrorMessage('You can only share live location for up to 3 bookings at a time. Stop one first.');
            return;
        }
        fetchAndFillBookerLocation();
    } else {
        $("#shortDescLoc").val("");
        $("#locationFetchStatus").addClass("d-none");
        $("#newBookerLocationLatitude").val("");
        $("#newBookerLocationLongitude").val("");
        $("#newBookerLocationAccuracy").val("");
    }
});