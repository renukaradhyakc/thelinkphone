(function (window, $) {
    const START_URL_TEMPLATE = '/scheduled-events/__EVENT_SCHEDULE_ID__/location/live/start';
    const STOP_URL_TEMPLATE = '/scheduled-events/__EVENT_SCHEDULE_ID__/location/live/stop';
    const UPDATE_URL = '/scheduled-events/location/live/update';
    const SESSION_CHECK_URL = '/scheduled-events/location/live-session';

    const DISTANCE_THRESHOLD_METERS = 150;
    const HEARTBEAT_INTERVAL_MS = 1 * 60 * 1000;
    const STORAGE_ACTIVE_KEY = 'callalink_booker_live_location_active';
    const STORAGE_EVENT_SCHEDULE_KEY = 'callalink_booker_live_location_event_schedule_id';

    let watchId = null;
    let updateTimer = null;
    let latestPosition = null;
    let activeEventScheduleIds = new Set();
    let lastSentPosition = null;

    function buildUrl(template, eventScheduleId) {
        return template.replace('__EVENT_SCHEDULE_ID__', eventScheduleId);
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
        if (!latestPosition || activeEventScheduleIds.size === 0) {
            console.log('[CallaLink] booker sendUpdate skipped — no position or no active booking');
            return;
        }

        const coords = latestPosition.coords;
        console.log('[CallaLink] booker sending update:', coords.latitude, coords.longitude, coords.accuracy);

        postJson(UPDATE_URL, {
            latitude: coords.latitude,
            longitude: coords.longitude,
            accuracy: coords.accuracy,
        }).done(function (res) {
            console.log('[CallaLink] booker update ACK:', res);
        }).fail(function (xhr) {
            console.error('[CallaLink] booker update FAILED:', xhr.status, xhr.responseText);
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
            console.log('[CallaLink] booker beginWatching skipped — already watching or no geolocation');
            return;
        }

        console.log('[CallaLink] booker beginWatching: starting watch + timer');

        watchId = navigator.geolocation.watchPosition(
            function (position) {
                maybeSend(position);
                console.log('[CallaLink] booker position updated:', position.coords.latitude, position.coords.longitude, position.coords.accuracy);
            },
            function (err) {
                console.error('[CallaLink] booker watchPosition ERROR:', err.code, err.message);
            },
            { enableHighAccuracy: true, maximumAge: 0 }
        );

        updateTimer = setInterval(function () {
            console.log('[CallaLink] booker heartbeat interval fired');
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
        activeEventScheduleIds = new Set();
        localStorage.removeItem(STORAGE_ACTIVE_KEY);
        localStorage.removeItem(STORAGE_EVENT_SCHEDULE_KEY);
    }

    function startSharing(eventScheduleId) {
        return postJson(buildUrl(START_URL_TEMPLATE, eventScheduleId), {}).done(function () {
            activeEventScheduleIds.add(eventScheduleId);
            localStorage.setItem(STORAGE_ACTIVE_KEY, '1');
            localStorage.setItem(STORAGE_EVENT_SCHEDULE_KEY, JSON.stringify(Array.from(activeEventScheduleIds)));
            fetchAndSendImmediate();
            beginWatching();
        });
    }

    function stopSharing(eventScheduleId) {
        return postJson(buildUrl(STOP_URL_TEMPLATE, eventScheduleId), {}).done(function () {
            activeEventScheduleIds.delete(eventScheduleId);
            if (activeEventScheduleIds.size === 0) {
                deactivateTracking();
            } else {
                localStorage.setItem(STORAGE_EVENT_SCHEDULE_KEY, JSON.stringify(Array.from(activeEventScheduleIds)));
            }
        });
    }

    function restoreSessionIfActive() {
        $.get(SESSION_CHECK_URL).done(function (response) {
            const data = response.data || response;
            console.log('[CallaLink] booker session check:', JSON.stringify(data));

            if (data.active) {
                let previousIds = [];
                try {
                    const stored = JSON.parse(localStorage.getItem(STORAGE_EVENT_SCHEDULE_KEY) || '[]');
                    previousIds = Array.isArray(stored) ? stored : [];
                } catch (e) {
                    previousIds = [];
                }
                const newIds = data.event_schedule_ids || [];
                const isNewActivation = JSON.stringify(previousIds.slice().sort()) !== JSON.stringify(newIds.slice().sort());
                console.log('[CallaLink] booker session active, isNewActivation:', isNewActivation);

                activeEventScheduleIds = new Set(newIds);
                localStorage.setItem(STORAGE_ACTIVE_KEY, '1');
                localStorage.setItem(STORAGE_EVENT_SCHEDULE_KEY, JSON.stringify(newIds));

                if (isNewActivation) {
                    fetchAndSendImmediate();
                }

                beginWatching();
            } else {
                console.log('[CallaLink] booker session inactive, deactivating');
                deactivateTracking();
            }
        }).fail(function (xhr) {
            console.error('[CallaLink] booker session check FAILED:', xhr.status, xhr.responseText);
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

    window.CallaLinkBookerLiveLocation = {
        startSharing: startSharing,
        stopSharing: stopSharing,
    };
})(window, jQuery);