let imageSize = ''

listenChange('.service_image', function () {
    return imageSize = (this.files[0].size)
})

listenSubmit('#addServiceForm', function () {
    if (imageSize > 2000000) {
        displayErrorMessage('Image size should be less than 2 MB')
        return false
    }

    if ($('.main-title').val().trim() == '') {
        displayErrorMessage('The main title field is required.')
        return false
    }

    if ($('.service-title-1').val().trim() == '') {
        displayErrorMessage('The service title 1 field is required.')
        return false
    }

    if ($('.service-description-1').val().trim() == '') {
        displayErrorMessage('The service description 1 field is required.')
        return false
    } else if ($('.service-description-1').val().length >= 90) {
        displayErrorMessage(
            'The description 1 must not be greater than 90 characters.')
        return false
    }

    if ($('.service-title-2').val().trim() == '') {
        displayErrorMessage('The service title 2 field is required.')
        return false
    }

    if ($('.service-description-2').val().trim() == '') {
        displayErrorMessage('The service description 2 field is required.')
        return false
    } else if ($('.service-description-2').val().length >= 90) {
        displayErrorMessage(
            'The service description 2 must not be greater than 122 characters.')
        return false
    }

    if ($('.service-title-3').val().trim() == '') {
        displayErrorMessage('The service title 3 field is required.')
        return false
    }

    if ($('.service-description-3').val().trim() == '') {
        displayErrorMessage('The service description 3 field is required.')
        return false
    } else if ($('.service-description-3').val().length >= 90) {
        displayErrorMessage(
            'The service description 3 must not be greater than 90 characters.')
        return false
    }

    $('#servicesSaveBtn').attr('disabled',true);
});
