/*
 * CallALink notification center: header bell, notifications page, settings page.
 * Built on the project's own stack: jQuery, Bootstrap 5 dropdown, Turbo, moment.
 * Bundled into public/assets/js/pages.js (add it to the pages.js list in webpack.mix.js).
 *
 * Endpoints: /notification-center/api/*  (the same controllers the mobile API uses).
 * The base URL comes from a data-notification-api attribute in the Blade files.
 */

const NOTIFICATION_POLL_MS = 60000
const notificationState = {count: 0, timer: null, listLoadedAt: 0, listCount: -1}
const NOTIFICATION_LIST_FRESH_MS = 30000
const NOTIFICATION_STORE_LINKS = [
    {host: 'play.google.com', path: /^\/store\/account\/subscriptions\/?$/},
    {host: 'apps.apple.com', path: /^\/account\/(subscriptions|billing)\/?$/},
]

/* ---------------------------------------------------------------- request */

function notificationApi () {
    return String($('[data-notification-api]').first().attr('data-notification-api') || '').replace(/\/$/, '')
}

/**
 * options.json    send the body as JSON (needed for the nested preferences payload)
 * options.timeout milliseconds
 */
function notificationRequest (method, path, data, options) {
    options = options || {}

    const ajax = {
        url: notificationApi() + path,
        method: method,
        dataType: 'json',
        headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
    }

    if (options.timeout) {
        ajax.timeout = options.timeout
    }

    if (data !== undefined) {
        if (options.json) {
            ajax.contentType = 'application/json'
            ajax.data = JSON.stringify(data)
        } else {
            ajax.data = data
        }
    }

    return $.ajax(ajax).fail(function (xhr) {
        if (xhr.status === 401 || xhr.status === 419) {
            stopNotificationPolling() // logged out or session expired
        }
    })
}

/* ------------------------------------------------------------------ badge */

function setNotificationBadge (count) {
    count = Math.max(0, parseInt(count, 10) || 0)
    notificationState.count = count
    $('#notificationBadge').text(count > 99 ? '99+' : count).toggleClass('d-none', count === 0)
}

function refreshNotificationCount (force) {
    if (!$('#notificationBell').length || (document.hidden && !force)) {
        return
    }

    notificationRequest('GET', '/unread-count').done(function (res) {
        const previous = notificationState.count
        setNotificationBadge(res.data.count)

        // a new notification arrived while the dropdown is open
        if (res.data.count > previous && $('#notificationBell').hasClass('show')) {
            loadNotificationDropdown()
        }
    })
}

/* ---------------------------------------------- polling (about every 60 s) */

function startNotificationPolling () {
    if (notificationState.timer) {
        return
    }

    notificationState.timer = setInterval(function () {
        refreshNotificationCount(false) // skipped while the tab is hidden
    }, NOTIFICATION_POLL_MS)
}

function stopNotificationPolling () {
    clearInterval(notificationState.timer)
    notificationState.timer = null
}

$(document).on('visibilitychange', function () {
    if (!document.hidden) {
        refreshNotificationCount(true)
    }
})

/* ---------------------------------------------------------------- one row */

function notificationUrl (link) {
    if (!link) {
        return null
    }

    try {
        const url = new URL(link, window.location.origin)

        if (url.origin === window.location.origin) {
            return url.href
        }

        const isStorePage = url.protocol === 'https:' && NOTIFICATION_STORE_LINKS.some(function (rule) {
            return url.hostname === rule.host && rule.path.test(url.pathname)
        })
 
        return isStorePage ? url.href : null
    } catch (e) {
        return null
    }
}

function notificationTime (iso) {
    if (!iso) {
        return ''
    }

    return window.moment ? moment(iso).fromNow() : new Date(iso).toLocaleString()
}

function buildNotificationItem (n, withDelete) {
    const url = notificationUrl(n.deep_link)

    const $item = $('<div/>', {
        'class': 'notification-item position-relative ' + (n.is_read ? 'is-read' : 'is-unread'),
        'data-id': n.id,
        'data-read': n.is_read ? '1' : '0',
    })

    const $link = $('<a/>', {
        'class': 'd-flex align-items-start gap-3 px-4 py-3 text-decoration-none',
        'href': url || '#',
        'data-turbo': 'false',
        'data-notification-link': '1',
    })

    if (withDelete) {
        $link.css('padding-right', '48px')
    }

    const $dot = $('<span/>', {'class': 'notification-dot flex-shrink-0 mt-2 rounded-circle'}).
        css({width: '8px', height: '8px'})

    const $text = $('<div/>').css('min-width', 0)
    $('<div/>', {'class': 'notification-title'}).text(n.title || '').appendTo($text)

    if (n.body) {
        $('<div/>', {'class': 'notification-body'}).text(n.body).appendTo($text)
    }

    $('<small/>', {'class': 'notification-time'}).text(notificationTime(n.created_at)).appendTo($text)

    $link.append($dot, $text)
    $item.append($link)

    if (withDelete) {
        $('<button/>', {
            'type': 'button',
            'class': 'btn btn-sm px-2 position-absolute top-0 end-0 mt-2 me-2 notification-delete',
            'aria-label': 'Delete notification',
        }).append($('<i/>', {'class': 'fa-solid fa-xmark text-gray-600'})).appendTo($item)
    }

    return $item
}

function markNotificationRead ($item) {
    $item.attr('data-read', '1').removeClass('is-unread').addClass('is-read')
    setNotificationBadge(notificationState.count - 1)

    return notificationRequest('POST', '/' + encodeURIComponent($item.attr('data-id')) + '/read', undefined,
        {timeout: 2000}).done(function (res) {
        if (res.data && typeof res.data.unread_count === 'number') {
            setNotificationBadge(res.data.unread_count)
        }
    })
}

// click a notification: mark it read, then open its page
$(document).on('click', '[data-notification-link]', function (e) {
    const $link = $(this)
    const $item = $link.closest('.notification-item')
    const href = $link.attr('href')
    const unread = $item.attr('data-read') === '0'
    const opensPage = href && href !== '#'

    // open in a new tab: let the browser do it, just mark it read
    if (e.ctrlKey || e.metaKey || e.shiftKey || e.which === 2) {
        if (unread) {
            markNotificationRead($item)
        }
        return
    }

    if (!opensPage) {
        e.preventDefault()
        if (unread) {
            markNotificationRead($item)
        }
        return
    }

    if (!unread) {
        return // already read: normal navigation
    }

    e.preventDefault()
    markNotificationRead($item).always(function () {
        window.location.href = href
    })
})

// "Mark all as read" in the dropdown and on the page
$(document).on('click', '#notificationMarkAll, #notificationPageMarkAll', function () {
    const $button = $(this).prop('disabled', true)

    notificationRequest('POST', '/read-all').done(function () {
        $('.notification-item[data-read="0"]').attr('data-read', '1').removeClass('is-unread').addClass('is-read')
        setNotificationBadge(0)

        if ($('#notificationPage').attr('data-filter') === 'unread') {
            loadNotificationPage(true)
        }
    }).always(function () {
        $button.prop('disabled', false)
    })
})

/* --------------------------------------------------------------- dropdown */

function loadNotificationDropdown () {
    const $list = $('#notificationList')

    $('#notificationEmpty, #notificationError').addClass('d-none')

    notificationRequest('GET', '/list', {limit: 10}).done(function (res) {
        $list.empty()
        ;(res.data || []).forEach(function (n) {
            $list.append(buildNotificationItem(n, false))
        })
        $('#notificationEmpty').toggleClass('d-none', $list.children().length > 0)
        notificationState.listLoadedAt = Date.now()
        notificationState.listCount = notificationState.count
    }).fail(function () {
        $list.empty()
        $('#notificationError').removeClass('d-none')
    })
}

$(document).on('click', '#notificationBell', function () {
    const fresh = Date.now() - notificationState.listLoadedAt < NOTIFICATION_LIST_FRESH_MS
        && notificationState.listCount === notificationState.count

    if (!fresh) {
        loadNotificationDropdown()
    }
})

/* ------------------------------------------------------ notifications page */

function loadNotificationPage (reset) {
    const $page = $('#notificationPage')

    if (!$page.length || $page.data('loading')) {
        return
    }

    const $list = $('#notificationPageList')
    const $more = $('#notificationLoadMore')
    const params = {limit: 20}

    $page.data('loading', true)
    $('#notificationPageError').addClass('d-none')

    if (reset) {
        $page.attr('data-cursor', '')
        $list.empty()
        $more.addClass('d-none')
    }

    if ($page.attr('data-cursor')) {
        params.cursor = $page.attr('data-cursor')
    }

    if ($page.attr('data-filter') === 'unread') {
        params.unread = 1
    }

    notificationRequest('GET', '/list', params).done(function (res) {
        ;(res.data || []).forEach(function (n) {
            $list.append(buildNotificationItem(n, true))
        })
        $page.attr('data-cursor', res.next_cursor || '')
        $more.toggleClass('d-none', !res.next_cursor)
        $('#notificationPageEmpty').toggleClass('d-none', $list.children().length > 0)
    }).fail(function () {
        $('#notificationPageError').removeClass('d-none')
    }).always(function () {
        $page.data('loading', false)
    })
}

$(document).on('click', '[data-notification-filter]', function () {
    const $tab = $(this)

    $('[data-notification-filter]').removeClass('btn-primary').addClass('btn-outline-primary')
    $tab.removeClass('btn-outline-primary').addClass('btn-primary')
    $('#notificationPage').attr('data-filter', $tab.attr('data-notification-filter'))
    loadNotificationPage(true)
})

$(document).on('click', '#notificationLoadMore', function () {
    loadNotificationPage(false)
})

$(document).on('click', '#notificationPageRetry', function () {
    loadNotificationPage($('#notificationPageList').children().length === 0)
})

$(document).on('click', '.notification-delete', function () {
    const $item = $(this).closest('.notification-item')

    notificationRequest('DELETE', '/' + encodeURIComponent($item.attr('data-id'))).done(function () {
        $item.remove()
        $('#notificationPageEmpty').toggleClass('d-none', $('#notificationPageList').children().length > 0)
        refreshNotificationCount(true)
    })
})

/* ------------------------------------------------------------ preferences */

function notificationPrefStatus (text, isError) {
    $('#notificationPrefStatus').text(text).toggleClass('text-danger', !!isError).toggleClass('text-gray-600', !isError)
}

function loadNotificationPreferences () {
    const $root = $('#notificationSettings')

    if (!$root.length) {
        return
    }

    notificationPrefStatus('Loading your settings...', false)

    // missing rows already come back with the server default, so new categories need no change here
    notificationRequest('GET', '/preferences').done(function (res) {
        ;(res.data || []).forEach(function (p) {
            const $toggle = $root.find('.notification-pref-toggle[data-category="' + p.category + '"]')

            if ($toggle.attr('data-locked') !== '1') {
                $toggle.prop('checked', !!p.push_enabled)
            }
        })

        $root.find('.notification-pref-toggle[data-locked="0"]').prop('disabled', false)
        notificationPrefStatus('', false)
    }).fail(function () {
        notificationPrefStatus('Could not load your settings. Refresh the page to try again.', true)
    })
}

// save on every switch; put the switch back if the save fails
$(document).on('change', '.notification-pref-toggle', function () {
    const $toggle = $(this)
    const wanted = $toggle.is(':checked')

    $toggle.prop('disabled', true)
    notificationPrefStatus('Saving...', false)

    notificationRequest('PUT', '/preferences', {
        preferences: [{category: $toggle.attr('data-category'), push_enabled: wanted}],
    }, {json: true}).done(function () {
        notificationPrefStatus('Saved.', false)
    }).fail(function () {
        $toggle.prop('checked', !wanted)
        notificationPrefStatus('Could not save that change. Try again.', true)
    }).always(function () {
        $toggle.prop('disabled', false)
    })
})

/* ------------------------------------------------------------------ start */

// runs on the first load and after every Turbo visit (the header is re-rendered each time)
$(document).on('turbo:load', function () {
    if ($('#notificationBell').length) {
        setNotificationBadge($('#notificationBadge').attr('data-count'))
        startNotificationPolling()
    }

    loadNotificationPage(true)
    loadNotificationPreferences()
})