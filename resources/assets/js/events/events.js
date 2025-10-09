// copy slot time code
listenClick(".copy-link", function () {
    let $temp = $("<input>");
    $("body").append($temp);
    $temp.val($(this).attr("data-link")).select();
    document.execCommand("copy");
    $temp.remove();
    $(this).text(Lang.get("js.copied"));
    $(this).prev().css("color", "#8BC34A");
    $(this).prev().removeClass("fa-copy");
    $(this).prev().addClass("fa-check");
    displaySuccessMessage(Lang.get("js.linked_copy_successfully"));
    setTimeout(function () {
        $(".copy-link").text(Lang.get("js.copy_link"));
        $(".copy-link").prev().removeClass("fa-check");
        $(".copy-link").prev().addClass("fa-copy");
        $(".fa-copy").css("color", "#009ef7");
    }, 2000);
});

// event delete record code
listenClick(".event-delete-btn", function () {
    let deleteEventId = $(this).attr("data-id");
    deleteItemLivewire(
        "delete",
        deleteEventId,
        Lang.get("js.event")
    );
});

window.deleteItemLivewire = function (model, id, header) {
    swal({
        title: Lang.get("js.delete") + " !",
        text:
            Lang.get("js.sure_delete") +
            ' "' +
            header +
            '"  ?',
        buttons: {
            confirm: Lang.get("js.yes"),
            cancel: Lang.get("js.no"),
        },
        icon: sweetAlertIcon,
        reverseButtons: true,
    }).then(function (willDelete) {
        if (willDelete) {
            window.livewire.emit(model, id);
        }
    });
};

window.addEventListener("event-error", (event) => {
    swal({
        title: "Error!",
        text: Lang.get("js.this_event_can_not_be_deleted"),
        type: "error",
        confirmButtonColor: "#ADB5BD",
        timer: 2000,
    });
});

window.addEventListener("deleted", function (data) {
    livewireDeleteEventListener(data, "Event");
});

window.livewireDeleteEventListener = function () {
    swal({
        icon: "success",
        confirmButtonColor: "#ADB5BD",
        title: deleteMsg + " !",
        text: Lang.get("js.event") + " " + hasBeenDeleted,
        buttons: {
            confirm: Lang.get("js.ok"),
        },
        timer: 2000,
    });
};

window.livewireDeleteErrorEventListener = function (data) {
    swal({
        title: "Error!",
        text: data,
        type: "error",
        confirmButtonColor: "#ADB5BD",
        timer: 2000,
    });
};

// activation deactivation change event
listenChange(".event-status", function () {
    let eventId = $(this).attr("data-id");
    activeDeActiveStatus(eventId);
});

// activate de-activate Event status
window.activeDeActiveStatus = function (id) {
    $.ajax({
        url: route("change.event.status", id),
        type: "POST",
        success: function (result) {
            if (result.success) {
                displaySuccessMessage(result.message);
                window.livewire.emit("refresh");
            }
        },
        error: function (result) {
            displayErrorMessage(result.responseJSON.message);
            setTimeout(location.reload(true), 700);
        },
    });
};
