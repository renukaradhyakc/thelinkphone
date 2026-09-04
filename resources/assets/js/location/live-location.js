(function (window, $) {
    const START_URL_TEMPLATE = '/events/__EVENT_ID__/location/live/start';
    const STOP_URL_TEMPLATE = '/events/__EVENT_ID__/location/live/stop';
    const UPDATE_URL_TEMPLATE = '/events/__EVENT_ID__/location/live/update';
    const SESSION_CHECK_URL = '/location/live-session';

    const DISTANCE_THRESHOLD_METERS = 150;  
    const HEARTBEAT_INTERVAL_MS = 1 * 60 * 1000; 
    const STORAGE_ACTIVE_KEY = 'callalink_live_location_active';
    const STORAGE_EVENT_KEY = 'callalink_live_location_event_id';

    let watchId = null;
    let updateTimer = null;
    let latestPosition = null;
    let activeEventId = null;
    let lastSentPosition = null;

    function buildUrl(template, eventId) {
        return template.replace('__EVENT_ID__', eventId);
    }

    function csrfToken() {
        return $('meta[name="csrf-token"]').attr('content');
    }

    function postJson(url, data) {
        return $.ajax({
            url: url,
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            data: data,
        });
    }

    function sendUpdate() {
        if (!latestPosition || !activeEventId) {
            console.log('[CallaLink] sendUpdate skipped — no position or no active event'); 
            return;
        }

        const coords = latestPosition.coords;
        console.log('[CallaLink] sending update:', coords.latitude, coords.longitude, coords.accuracy);

        postJson(buildUrl(UPDATE_URL_TEMPLATE, activeEventId), {
            latitude: coords.latitude,
            longitude: coords.longitude,
            accuracy: coords.accuracy,
        }).done(function (res) {
            console.log('[CallaLink] update ACK:', res);
        }).fail(function (xhr) {
            console.error('[CallaLink] update FAILED:', xhr.status, xhr.responseText);
            if (xhr.status === 409 || xhr.status === 404) {
                deactivateTracking();
            }
        });
    }

    function fetchAndSendImmediate() {
        if (!navigator.geolocation) {
            return;
        }

        navigator.geolocation.getCurrentPosition(
            function (position) {
                latestPosition = position;
                sendUpdate(); 
            },
            function () {
            },
            { enableHighAccuracy: true, maximumAge: 0 }
        );
    }

    function beginWatching() {
        if (!navigator.geolocation || watchId !== null) {
            console.log('[CallaLink] beginWatching skipped — already watching or no geolocation'); 
            return; 
        }

        console.log('[CallaLink] beginWatching: starting watch + timer');

        watchId = navigator.geolocation.watchPosition(
            function (position) {
                 maybeSend(position);
                console.log('[CallaLink] position updated:', position.coords.latitude, position.coords.longitude, position.coords.accuracy); 
            },
            function (err) {
                console.error('[CallaLink] watchPosition ERROR:', err.code, err.message); 
            },
            { enableHighAccuracy: true, maximumAge: 0 }
        );

        updateTimer = setInterval(function () {
            console.log('[CallaLink] heartbeat interval fired');
            sendUpdate();
        }, HEARTBEAT_INTERVAL_MS);
    }

    function stopWatching() {
        if (watchId !== null) {
            navigator.geolocation.clearWatch(watchId);
            watchId = null;
        }
        if (updateTimer !== null) {
            clearInterval(updateTimer);
            updateTimer = null;
        }
        latestPosition = null;
        lastSentPosition = null;
    }

    function deactivateTracking() {
        stopWatching();
        activeEventId = null;
        localStorage.removeItem(STORAGE_ACTIVE_KEY);
        localStorage.removeItem(STORAGE_EVENT_KEY);
    }

    function startSharing(eventId) {
        return postJson(buildUrl(START_URL_TEMPLATE, eventId), {}).done(function () {
            activeEventId = eventId;
            localStorage.setItem(STORAGE_ACTIVE_KEY, '1');
            localStorage.setItem(STORAGE_EVENT_KEY, String(eventId));
            fetchAndSendImmediate();
            beginWatching();
        });
    }

    function stopSharing(eventId) {
        return postJson(buildUrl(STOP_URL_TEMPLATE, eventId), {}).done(function () {
            deactivateTracking();
        });
    }

    function restoreSessionIfActive() {
        $.get(SESSION_CHECK_URL).done(function (response) {
            const data = response.data || response;
            console.log('[CallaLink] session check:', data);

            if (data.active) {
                const previousEventId = localStorage.getItem(STORAGE_EVENT_KEY);
                const isNewActivation = String(previousEventId) !== String(data.event_id);
                console.log('[CallaLink] session active, isNewActivation:', isNewActivation); 

                activeEventId = data.event_id;
                localStorage.setItem(STORAGE_ACTIVE_KEY, '1');
                localStorage.setItem(STORAGE_EVENT_KEY, String(data.event_id));

                if (isNewActivation) {
                    fetchAndSendImmediate(); 
                }

                beginWatching(); 
            } else {
                console.log('[CallaLink] session inactive, deactivating');
                deactivateTracking();
            }
        }).fail(function (xhr) {
            console.error('[CallaLink] session check FAILED:', xhr.status, xhr.responseText); 
        });
    }

    function distanceInMeters(lat1, lon1, lat2, lon2) {
        const R = 6371000;
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat / 2) ** 2 + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLon / 2) ** 2;
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    function maybeSend(position) {
        latestPosition = position;

        if (!lastSentPosition) {
            sendUpdate();
            lastSentPosition = position;
            return;
        }

        const moved = distanceInMeters(
            lastSentPosition.coords.latitude, lastSentPosition.coords.longitude,
            position.coords.latitude, position.coords.longitude
        );

        if (moved >= DISTANCE_THRESHOLD_METERS) {
            sendUpdate();
            lastSentPosition = position;
        }
    }

    document.addEventListener('turbo:load', restoreSessionIfActive);

    window.CallaLinkLiveLocation = {
        startSharing: startSharing,
        stopSharing: stopSharing,
    };
})(window, jQuery);