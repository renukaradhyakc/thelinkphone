document.addEventListener('turbo:load', loadSubscriptionPlanData)

function loadSubscriptionPlanData () {
    if (!$('#planTypeFilter').length) {
        return
    }

    $('#planTypeFilter').select2({
        placeholder: 'Select Status',
    })
}

// delete subscription record code
listenClick('.subscription-plan-delete-btn', function () {
    let deleteSubscriptionId = $(this).attr('data-id')
    let deleteSubscriptionUrl = route('subscription-plans.index') + '/' +
        deleteSubscriptionId
    deleteItem(deleteSubscriptionUrl,
        Lang.get('js.subscription_plan'))
})

listenChange('.is_default', function (event) {
    let subscriptionPlanId = $(event.currentTarget).data('id')
    updateStatusToDefault(subscriptionPlanId)
})

window.updateStatusToDefault = function (id) {
    $.ajax({
        url: route('subscription-plans.index') + '/' + id +
            '/make-plan-as-default',
        method: 'post',
        cache: false,
        success: function (result) {
            if (result.success) {
                displaySuccessMessage(result.message)
                livewire.emit('refresh')
            }
        },
    });
};

// reset filter modal code
listenClick('#resetFilter', function () {
    $('#planTypeFilter').val(0).trigger('change')
    $('#subscriptionPlanFilterBtn').dropdown('toggle')
})

// call filter data code
listenChange('#planTypeFilter', function () {
    window.livewire.emit('changeFilter', $(this).val())
})
