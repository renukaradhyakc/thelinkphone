// for next month calendar 
listenClick('#nextMonth', function () {
    let nextMonth = $(this).attr('data-next-month')
    window.livewire.emit('changeMonth', nextMonth)
})

// for previous month calendar 
listenClick('#prevMonth', function () {
    let prevMonth = $(this).attr('data-prev-month')
    window.livewire.emit('changeMonth', prevMonth)
})

// get task related date
listenClick('.get-slots', function () {
    let slotDate = $(this).attr('date-slot-date')
    window.livewire.emit('getSlotTime', moment(slotDate).format('YYYY-MM-DD'))
})
