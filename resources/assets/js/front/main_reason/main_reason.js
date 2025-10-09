let imageSize = ''

listenChange('#imageMainReason', function () {
    return imageSize = (this.files[0].size)
})

listenSubmit('#addMainReasonForm', function () {
    if (imageSize > 2000000) {
        displayErrorMessage('Image size should be less than 2 MB')
        return false
    }

    if ($('#main_title').val().trim() == '') {
        displayErrorMessage('The main title field is required.')
        return false
    }

    if ($('#title_1').val().trim()== '') {
        displayErrorMessage('The title 1 field is required.')
        return false
    }


    if ($('#description_1').val().trim()== '') {
        displayErrorMessage('The description 1 field is required.')
        return false
    }
    else if($('#description_1').val().length>=122)
    {
        displayErrorMessage('The description 1 must not be greater than 122 characters.')
        return false
    }
    
    
    if ($('#title_2').val().trim()== '') {
        displayErrorMessage('The title 2 field is required.')
        return false
    }


    if ($('#description_2').val().trim()== '') {
        displayErrorMessage('The description 2 field is required.')
        return false
    }
    else if($('#description_2').val().length>=122)
    {
        displayErrorMessage('The description 2 must not be greater than 122 characters.')
        return false
    }

    if ($('#title_3').val().trim()== '') {
        displayErrorMessage('The title 3 field is required.')
        return false
    }


    if ($('#description_3').val().trim() == '') {
        displayErrorMessage('The description 3 field is required.')
        return false
    } else if ($('#description_3').val().length >= 122) {
        displayErrorMessage(
            'The description 3 must not be greater than 122 characters.')
        return false
    }

    $('#mainReasonSaveBtn').attr('disabled',true)
});
