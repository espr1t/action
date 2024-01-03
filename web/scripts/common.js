/*
 * Sleeps for ms milliseconds.
 */
function sleep(ms) {
    // Use: await sleep(1337)
    return new Promise(resolve => setTimeout(resolve, ms));
}

/*
 * Get last url token (can be problem id, submission id, news id, etc.)
 */
function getLastUrlToken() {
    var urlTokens = window.location.href.split('/');
    return urlTokens[urlTokens.length - 1].split('#')[0];
}

/*
 * Converts HTML string into a DOM element.
 */
function htmlToElement(html) {
    var template = document.createElement('template');
    template.innerHTML = html.trim();
    return template.content.firstChild;
}

/*
 * AJAX calls
 */
function ajaxCall(url, data, callback) {
    var first = true;
    var args = '';
    for (var field in data) {
        if (data.hasOwnProperty(field)) {
            args += (first ? '' : '&') + field + '=' + encodeURIComponent(data[field]);
            first = false;
        }
    }

    var xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
        if (xhttp.readyState == 4) {
            callback(xhttp.status == 200 ? xhttp.responseText : '{"status" : "ERROR"}');
        }
    }
    xhttp.open('POST', url, true);
    xhttp.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
    xhttp.send(args);
}

/*
 * Redirect
 */
function redirect(url, notificationType=null, notificationText=null) {
    if (notificationType && notificationText) {
        var form = htmlToElement(`
            <form action="${url}" method="POST" style="display: none;">
                <input type="text" name="notificationType" value="${notificationType}" />
                <input type="text" name="notificationText" value="${notificationText}" />
            </form>
        `);
        document.body.appendChild(form);
        form.submit();
    } else {
        window.location = url;
    }
}

/*
 * Fade-out
 */
function fadeOutAndRemove(elementId) {
    var element = document.getElementById(elementId);
    if (element) {
        if (element.className.includes('fade-in')) {
            element.className = element.className.replace('fade-in', 'fade-out');
        } else {
            element.className += ' fade-out';
        }
        setTimeout(function() {
            element.parentNode.removeChild(element);
        }, 300);
    }
}

/*
 * Key bindings
 */
function identifyEscPress(event, handler) {
    var keyCode = event.keyCode || event.which || 0;
    if (keyCode == 27) {
        popEscHandler();
        event.preventDefault();
        event.stopPropagation();
        handler();
    }
}

var keyDownEventStack = [];
function addEscHandler(handler) {
    keyDownEventStack.push(document.onkeydown);
    document.onkeydown = function(event) {
        identifyEscPress(event, handler);
    }
}

function popEscHandler() {
    if (!keyDownEventStack.empty) {
        document.onkeydown = keyDownEventStack.pop();
    }
}

/*
 * Butter bars (pop-up messages)
 */
function showNotification(type, text, timeout=3 /* seconds */) {
    var notificationEl = document.createElement('div');

    var id = "i" + Date.now();
    var className, icon;
    if (type == 'INFO') {
        className = 'notification notification-info';
        icon = '<i class="fa fa-check fa-fw"></i>';
    } else {
        className = 'notification notification-error';
        icon = '<i class="fa fa-exclamation-triangle fa-fw"></i>';
    }

    notificationEl.id = id;
    notificationEl.className = className + ' fade-in';
    notificationEl.innerHTML = `
        <div class="notification-content">
            <div class="notification-icon">${icon}</div>
            <div class="notification-text">${text}</div>
            <div class="notification-close" onclick="hideNotification('${id}');">
                <i class="fa fa-times fa-fw" style="line-height: 2rem;"></i>
            </div>
        </div>
    `;

    // Make it possible to hide the notification with hitting escape
    addEscHandler(function() {hideNotification(id);});

    var wrapperEl = document.getElementById('wrapper');
    var headerEl = document.getElementById('head');
    wrapperEl.insertBefore(notificationEl, headerEl.nextSibling);

    // Center the notification horizontally
    // Please note that this has to be done after it is appended to the DOM, since otherwise its width will be 0 (it is not visible)
    notificationEl.style.marginLeft = (wrapperEl.offsetWidth / 2 - notificationEl.offsetWidth / 2) + 'px';

    // Hide the notification after several seconds
    setTimeout(function() {
        hideNotification(id);
    }, timeout * 1000);
}

function hideNotification(id) {
    fadeOutAndRemove(id);
}

/*
 * Overlay (for pop-up boxes)
 */
function showOverlay(overlayId) {
    var overlay = document.createElement('div');
    overlay.id = overlayId;
    document.body.appendChild(overlay);
    overlay.className = 'overlay fade-in-overlay';
    document.body.style.overflow = 'hidden';
}

function hideOverlay(overlayId) {
    fadeOutAndRemove(overlayId);
    document.body.style.overflow = 'auto';
}

/*
 * Form actions (show/hide/submit)
 */
function reposition(elementId) {
    // Position the box in the center of the screen (well, kind of)
    var el = document.getElementById(elementId);
    var screenHeight = window.innerHeight || document.documentElement.clientHeight;
    var offset = Math.min(el.clientHeight / 2 + 20, screenHeight / 2 - 20);
    el.style.marginTop = -offset + 'px';
}

function showActionForm(content, redirect, classes='') {
    // Create an overlay shadowing the rest of the page
    showOverlay('actionFormOverlay');

    // Create the form box and add it to the DOM using a fade-in animation
    var form = document.createElement('div');
    form.id = 'actionForm';
    form.innerHTML = `
        <div class="action-form-close" onclick="hideActionForm('${redirect}');"><i class="fa fa-times fa-fw"></i></div>
        <div id="action-form-content">${content}</div>
    `;
    document.body.appendChild(form);
    form.className = 'action-form fade-in' + (classes != '' ? ' ' + classes : '');

    // Set the vertical position of the box
    reposition('actionForm');

    // Bind escape button for closing it
    addEscHandler(function() {hideActionForm(redirect);});
}

function hideActionForm(redirectUrl=null) {
    fadeOutAndRemove('actionForm');
    hideOverlay('actionFormOverlay');
    // Redirect to another page if requested
    if (redirectUrl) {
        redirect(redirectUrl);
    }
}

function parseActionResponse(response, hideOnSuccess=true) {
    try {
        response = JSON.parse(response);
    } catch(ex) {
        alert(response);
        response = null;
    }
    var type = 'ERROR';
    if (response && response.status) {
        type = response.status == 'NONE' ? 'NONE' :
               response.status == 'OK' ? 'INFO' : 'ERROR';
    }
    var message = (response && response.message) ? response.message :
                  'Could not get action response or the response was invalid.';
    if (type == 'INFO' && hideOnSuccess) {
        hideActionForm();
    }
    if (type == 'ERROR') {
        console.error(message);
        showNotification('ERROR', message);
    }
    return response;
}

/*
 * Submit form handling
 */
function showSubmitForm(content) {
    showActionForm(content, '');

    // Run language detection after every update
    var sourceEl = document.getElementById('source');
    sourceEl.onchange = sourceEl.onpaste = sourceEl.onkeydown = function() {
        setTimeout(function() {
            var langEl = document.getElementById('language');
            var language = detectLanguage(sourceEl.value);
            if (language == 'Python')
                language = 'Python3'
            langEl.innerText = language;
        }, 50);
    }
}

function submitSubmitForm(problemId, full=true) {
    var source = document.getElementById('source').value;
    var language = detectLanguage(source);
    var data = {
        'problemId': problemId,
        'source' : source,
        'language' : language,
        'full': full ? 1 : 0
    };

    var callback = function(response) {
        response = parseActionResponse(response);
        if ('id' in response) {
            redirect(window.location.href + '/submits/' + response.id);
        }
    }
    ajaxCall('/actions/sendSubmission', data, callback);
}

/*
 * Report form handling
 */
function showReportForm(content) {
    showActionForm(content, '');
}

function submitReportForm() {
    var pageLink = window.location.href;
    var problem = document.getElementById('reportText').value;

    var data = {
        'link': pageLink,
        'problem': problem
    };

    var callback = function(response) {
        response = parseActionResponse(response);
        if (response) {
            showNotification(
                response.status == "OK" ? "INFO" : "ERROR",
                response.message
            );
        }
    }
    ajaxCall('/actions/reportProblem', data, callback);
}

/*
 * Update grader status
 */
function updateGraderStatus() {
    var callback = function(response) {
        try {
            response = JSON.parse(response);
        } catch(ex) {
            alert(response);
            response = null;
        }

        if (response != null) {
            var statusIconEl = document.getElementById('graderStatusIcon');
            var statusTooltipEl = document.getElementById('graderStatusTooltip');
            if ('status' in response) {
                if (response['status'] === 'OK') {
                    statusIconEl.className = 'fa fa-check-circle green';
                    statusTooltipEl.setAttribute('data-tooltip', 'Грейдърът е достъпен за ' + response['message'] + 's.');
                } else {
                    statusIconEl.className = 'fa fa-exclamation-circle red';
                    statusTooltipEl.setAttribute('data-tooltip', 'Грейдърът се прави на недостъпен.');
                }
            }
        }
    }
    ajaxCall('/actions/checkGrader', {}, callback);
}

/*
 * Asynchronously regrade submission
 */
function regradeSubmission(id) {
    var callback = function(response) {
        showNotification('INFO', 'Събмит ' + id + ' беше пратен за ретестване.');
    }
    ajaxCall('/admin/regrade/submit/' + id, {}, callback);
}

/*
 * Order ranking by various parameters
 */
function orderRanking(orderBy) {
    var table = document.getElementById('rankingTable');
    var tableBody = table.tBodies[1];
    var rows = Array.prototype.slice.call(tableBody.rows, 0);
    rows = rows.sort(function(a, b) {
        var userNameA = a.cells[1].textContent, townA = a.cells[3].textContent, tasksA = parseInt(a.cells[4].textContent), achievementsA = parseInt(a.cells[5].textContent);
        var userNameB = b.cells[1].textContent, townB = b.cells[3].textContent, tasksB = parseInt(b.cells[4].textContent), achievementsB = parseInt(b.cells[5].textContent);
        switch (orderBy) {
            // You expected this to be a standard operator < ? Well - no. It is designed after the C-style qsort() function,
            // which requires an integer result: less than 0 for "less", 0 for "equal", and greater-than 0 for "greater".
            case 'town':
                return townA !== townB ? (townA < townB ? -1 : +1)
                        : (tasksA !== tasksB ? tasksA - tasksB
                            : (achievementsA !== achievementsB ? achievementsA - achievementsB
                                : (userNameA < userNameB ? -1 : +1)));
            case 'achievements':
                return achievementsA !== achievementsB ? achievementsA - achievementsB
                        : (tasksA !== tasksB ? tasksA - tasksB
                            : (townA !== townB ? (townA < townB ? -1 : +1)
                                : (userNameA < userNameB ? -1 : +1)));
            default:
                return tasksA !== tasksB ? tasksA - tasksB
                        : (achievementsA !== achievementsB ? achievementsA - achievementsB
                            : (townA !== townB ? (townA < townB ? -1 : +1)
                                : (userNameA < userNameB ? -1 : +1)));
        }
    });
    rows.reverse();
    for (var i = 0; i < rows.length; i++) {
        rows[i].cells[0].textContent = i + 1;
        tableBody.appendChild(rows[i]);
    }
}

/*
 * Circular progress bar
 */
function circularProgress(parentId, done, total) {
    var percent = done / total;
    var remaining = 1 - percent - 0.025;

    // Unfinished segment
    var unfinished = document.createElement('div');
    unfinished.style.height = unfinished.style.width = '4.8rem';
    unfinished.style.position = 'absolute';
    unfinished.style.left = '50%';
    unfinished.style.marginLeft = '-2.4rem';
    unfinished.style.top = '50%';
    unfinished.style.marginTop = '-2.4rem';
    unfinished.style.borderRadius = '50%';
    unfinished.style.backgroundColor = '#999999';
    unfinished.style.opacity = '0.1';

    if (remaining > 0.05) {
        if (remaining <= 1.0 / 8.0) {
            unfinished.style.clipPath = 'polygon(50% 50%, 40% 0, ' + ((1 - remaining / (1.0 / 8.0)) * 50) + '% 0)';
        } else if (remaining <= 3.0 / 8.0) {
            unfinished.style.clipPath = 'polygon(50% 50%, 40% 0, 0 0, 0 ' + ((remaining - 1.0 / 8.0) / (2.0 / 8.0) * 100) + '%)';
        } else if (remaining <= 5.0 / 8.0) {
            unfinished.style.clipPath = 'polygon(50% 50%, 40% 0, 0 0, 0 100%, ' + ((remaining - 3.0 / 8.0) / (2.0 / 8.0) * 100) + '% 100%)';
        } else if (remaining <= 7.0 / 8.0) {
            unfinished.style.clipPath = 'polygon(50% 50%, 40% 0, 0 0, 0 100%, 100% 100%, 100% ' + ((1 - (remaining - 5.0 / 8.0) / (2.0 / 8.0)) * 100) + '%)';
        } else if (remaining <= 8.0 / 8.0) {
            unfinished.style.clipPath = 'polygon(50% 50%, 40% 0, 0 0, 0 100%, 100% 100%, 100% 0, ' + ((1 - (remaining - 7.0 / 8.0) / (1.0 / 8.0)) * 50) + '% 0)';
        }
    }

    // Unfinished cover
    var cover = document.createElement('div');
    cover.style.height = cover.style.width = '4.6rem';
    cover.style.position = 'absolute';
    cover.style.left = '50%';
    cover.style.marginLeft = '-2.3rem';
    cover.style.top = '50%';
    cover.style.marginTop = '-2.3rem';
    cover.style.borderRadius = '50%';
    cover.style.backgroundColor = 'white';

    // Finished segment
    var finished = document.createElement('div');
    finished.style.height = finished.style.width = '5rem';
    finished.style.position = 'absolute';

    if (percent <= 0.25) {
        finished.style.backgroundColor = '#E74C3C';
    } else if (percent <= 0.5) {
        finished.style.backgroundColor = '#E67E22';
    } else if (percent <= 0.75) {
        finished.style.backgroundColor = '#F1C40F';
    } else {
        finished.style.backgroundColor = '#2ECC71';
    }

    // Making it round and filling required percent
    finished.style.borderRadius = '50%';
    if (percent <= 1.0 / 8.0) {
        finished.style.clipPath = 'polygon(50% 50%, 50% 0, ' + (50 + percent / (1.0 / 8.0) * 50) + '% 0)';
    } else if (percent <= 3.0 / 8.0) {
        finished.style.clipPath = 'polygon(50% 50%, 50% 0, 100% 0, 100% ' + ((percent - 1.0 / 8.0) / (2.0 / 8.0) * 100) + '%)';
    } else if (percent <= 5.0 / 8.0) {
        finished.style.clipPath = 'polygon(50% 50%, 50% 0, 100% 0, 100% 100%, ' + ((1 - (percent - 3.0 / 8.0) / (2.0 / 8.0)) * 100) + '% 100%)';
    } else if (percent <= 7.0 / 8.0) {
        finished.style.clipPath = 'polygon(50% 50%, 50% 0, 100% 0, 100% 100%, 0 100%, 0 ' + ((1 - (percent - 5.0 / 8.0) / (2.0 / 8.0)) * 100) + '%)';
    } else if (percent <= 8.0 / 8.0) {
        finished.style.clipPath = 'polygon(50% 50%, 50% 0, 100% 0, 100% 100%, 0 100%, 0 0, ' + ((percent - 7.0 / 8.0) / (1.0 / 8.0) * 50) + '% 0)';
    }

    // Inner part of progress bar (used as text container)
    var inner = document.createElement('div');
    inner.style.height = inner.style.width = '4.5rem';
    inner.style.position = 'absolute';
    inner.style.left = '50%';
    inner.style.marginLeft = '-2.25rem';
    inner.style.top = '50%';
    inner.style.marginTop = '-2.25rem';
    inner.style.borderRadius = '50%';
    inner.style.backgroundColor = 'white';
    inner.style.lineHeight = '4.5rem';
    inner.style.textAlign = 'center';
    inner.style.fontSize = '1rem';
    inner.style.fontWeight = 'bold';
    inner.innerText = Math.round(percent * 100) + '%';

    // Container to hold and offset
    var container = document.createElement('div');
    container.className = 'tooltip--left';
    container.setAttribute('data-tooltip', done + ' от ' + total);
    container.style.height = container.style.width = '5rem';
    container.style.position = 'absolute';
    container.style.right = '0';
    container.style.top = '50%';
    container.style.marginTop = '-2rem';
    container.style.cursor = 'help';
    container.appendChild(unfinished);
    container.appendChild(cover);
    container.appendChild(finished);
    container.appendChild(inner);
    document.getElementById(parentId).appendChild(container);
}

/*
 * Subscribe for SSE events
 * https://www.html5rocks.com/en/tutorials/eventsource/basics/
 */
function subscribeForUpdates(url, targetElement, level=0) {
    if (level >= 10) {
        console.log('Reached maximum update time. Use page refresh instead.');
        showNotification('WARNING', 'Reached maximum auto-update time. Please refresh.', 1000000);
        return;
    }

    if (!!window.EventSource) {
        console.log('Initiating a new SSE connection.');
        var eventSource = new EventSource(url);
        eventSource.addEventListener('message', function(e) {
            console.log('Received new data at ' + ((new Date()).getTime()) + '.');
            var data = JSON.parse(e.data);
            if (data.hasOwnProperty('content')) {
                document.getElementById(targetElement).innerHTML = data['content'];
            }
            if (data.hasOwnProperty('eos')) {
                console.log('Closing the SSE connection.');
                eventSource.close();
            }
            if (data.hasOwnProperty('res')) {
                eventSource.close();
                subscribeForUpdates(url, targetElement, level + 1);
            }
        }, false);
        eventSource.addEventListener('error', function() {
            console.log('Encountered an SSE error. Closing the connection...');
            showNotification('WARNING', 'Auto-updating failed. Please refresh.', 1000000);
            eventSource.close();
        }, false);
        window.addEventListener('beforeunload', function() {
            console.log('Closing the SSE connection.');
            eventSource.close();
        });
    } else {
        console.log('Cannot subscribe to automatic updates. Use page refresh instead.');
    }
}

/*
 * Add <pre> tags for input/output on problem statements if browser is Firefox.
 * It appears there is a 17-year old bug about white-space: pre-wrap; not being copied properly.
 * https://bugzilla.mozilla.org/show_bug.cgi?id=116083
 */
function addPreTags() {
    if (navigator.userAgent.indexOf('Firefox') != -1) {
        var tables = document.getElementsByClassName('problem-sample');
        for (var i = 0; i < tables.length; i++) {
            for (var r = 1; r < tables[i].rows.length; r++) {
                for (var c = 0; c < tables[i].rows[r].cells.length; c++) {
                    tables[i].rows[r].cells[c].innerHTML = '<pre>' + tables[i].rows[r].cells[c].innerHTML + '</pre>';
                }
            }
        }
    }
}

/*
 * Update button tooltip for remaining time until next submit.
 */
function setSubmitTimeoutTimer(elementId, waitSeconds) {
    let message = 'Ще може да предадете отново след ' +
        waitSeconds + (waitSeconds === 1 ? ' секунда.' : ' секунди.');
    if (waitSeconds <= 0) {
        message = 'Рефрешнете страницата за да предадете решение.';
    }
    document.getElementById(elementId).setAttribute('data-tooltip', message);
    if (waitSeconds > 0) {
        setTimeout(setSubmitTimeoutTimer, 1000, elementId, waitSeconds - 1);
    }
}