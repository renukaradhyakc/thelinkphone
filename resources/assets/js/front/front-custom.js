document.addEventListener('turbo:load', loadFrontCustomData)

function loadFrontCustomData () {
    loadTestimonialCarousel()
    toastr.options = {
        'closeButton': true,
        'debug': false,
        'newestOnTop': false,
        'progressBar': true,
        'positionClass': 'toast-top-right',
        'preventDuplicates': false,
        'onclick': null,
        'showDuration': '300',
        'hideDuration': '1000',
        'timeOut': '5000',
        'extendedTimeOut': '1000',
        'showEasing': 'swing',
        'hideEasing': 'linear',
        'showMethod': 'fadeIn',
        'hideMethod': 'fadeOut',
    }

    window.displaySuccessMessage = function (message) {
        toastr.success(message)
    }

    window.displayErrorMessage = function (message) {
        toastr.error(message)
    }
}
listenClick('.languageSelection', function () {
    let languageName = $(this).data('prefix-value')

    $.ajax({
        type: 'POST',
        url: route('front.change.language'),
        data: { '_token': csrfToken, languageName: languageName },
        success: function () {
            location.reload()
        },
    })
})

listenClick('#month-tab', function () {
    $('#month').removeClass('d-none')
    $('#year').addClass('d-none')
    $('#unlimited').addClass('d-none')
})

listenClick('#year-tab', function () {
    $('#year').removeClass('d-none')
    $('#month').addClass('d-none')
    $('#unlimited').addClass('d-none')
})

listenClick('#unlimited-tab', function () {
    $('#unlimited').removeClass('d-none')
    $('#month').addClass('d-none')
    $('#year').addClass('d-none')
})

listenClick('.collapse-btn', function () {
    $('.collapse-btn').removeClass('show')
    if ($('.faq-collapse').hasClass('show')) {
        $(this).addClass('show')
    }
})

function loadTestimonialCarousel(){
    $('.testimonial-carousel').slick({
        dots: false,
        centerPadding: '0',
        slidesToShow: 1,
        slidesToScroll: 1,
    })
}
