document.addEventListener('turbo:load', loadEnquiryData)

function loadEnquiryData () {
    if (!$('#enquiryStatusFilter').length) {
        return false
    }

    $('#enquiryStatusFilter').select2({
        width: '100%',
        placeholder: Lang.get('js.select_status'),
    })
}

// status filter code
listenChange('.enquiry-status-filter', function () {
    window.livewire.emit('changeFilter', $(this).val())
})

// delete enquiry record code
listenClick('.enquiry-delete-btn', function () {
    let deleteEnquiryId = $(this).attr('data-id')
    deleteItem(route('enquiries.destroy', deleteEnquiryId),
        Lang.get('js.enquiry'))
})

// reset filter modal code
listenClick('#resetEnquiryStatusFilter', function () {
    window.livewire.emit('refresh')
    $('#enquiryStatusFilter').val(2).trigger('change')
    $('#enquiryStatusFilterBtn').dropdown('toggle')
})
