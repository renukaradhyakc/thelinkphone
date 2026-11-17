window.callalinkLogout = function (event) {
    event.preventDefault();

    if (window.__callalinkLoggingOut) {
        return;
    }
    window.__callalinkLoggingOut = true;
    setTimeout(function () { window.__callalinkLoggingOut = false; }, 15000);

    var logoutForm = document.getElementById('logout-form');

    if (!logoutForm) {
        console.error('CallALink logout form not found.');
        window.__callalinkLoggingOut = false;
        return;
    }

    // Android native logout.
    if (
        window.CallaLinkApp &&
        typeof window.CallaLinkApp.onLogout === 'function'
    ) {
        try { localStorage.clear(); } catch (e) {}
        window.CallaLinkApp.onLogout();
        return;
    }

    // iOS native logout.
    if (
        window.webkit &&
        window.webkit.messageHandlers &&
        window.webkit.messageHandlers.callalinkLogout
    ) {
        try { localStorage.clear(); } catch (e) {}
        
        window.webkit.messageHandlers.callalinkLogout.postMessage({
            action: 'logout'
        });
        
        return;

    }

    // Preserve the existing website logout behavior.
    localStorage.clear();
    logoutForm.submit();
}