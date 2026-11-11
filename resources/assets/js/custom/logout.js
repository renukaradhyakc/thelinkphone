window.callalinkLogout = function (event) {
    event.preventDefault();

    var logoutForm = document.getElementById('logout-form');

    if (!logoutForm) {
        console.error('CallALink logout form not found.');
        return;
    }

    // Android native logout.
    if (
        window.CallaLinkApp &&
        typeof window.CallaLinkApp.onLogout === 'function'
    ) {
        window.CallaLinkApp.onLogout();
    }

    // iOS native logout.
    if (
        window.webkit &&
        window.webkit.messageHandlers &&
        window.webkit.messageHandlers.callalinkLogout
    ) {
        window.webkit.messageHandlers.callalinkLogout.postMessage({
            action: 'logout'
        });
    }

    // Preserve the existing website logout behavior.
    localStorage.clear();
    logoutForm.submit();
}