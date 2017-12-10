/*
 * Get last url token (can be problem id, submission id, news id, etc.)
 */
function getLastUrlToken() {
    var urlTokens = window.location.href.split('/');
    return urlTokens[urlTokens.length - 1];
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
function redirect(url) {
    window.location = url;
}

/*
 * Key bindings
 */
var keyDownEventStack = [];
function identifyEscKeyPressedEvent(event, action) {
    if (event.keyCode == 27) {
        event.preventDefault();
        event.stopPropagation();
        action();
    }
}

/*
 * Butter bars (pop-up messages)
 */
function showMessage(type, message) {
    var messageEl = document.createElement('div');

    var id = "i" + Date.now();
    var className, icon;
    if (type == 'INFO') {
        className = 'message message-info';
        icon = '<i class="fa fa-check fa-fw"></i>';
    } else {
        className = 'message message-error';
        icon = '<i class="fa fa-warning fa-fw"></i>';
    }

    messageEl.id = id;
    messageEl.className = className + ' fade-in';
    messageEl.innerHTML = '' +
        '<div class="message-content">' +
        '    <div class="message-icon">' + icon + '</div>' +
        '    <div class="message-text">' + message + '</div>' +
        '    <div class="message-close" onclick="hideMessage(\'' + className + '\', \'' + id + '\');">' +
        '        <i class="fa fa-close fa-fw" style="line-height: 2rem;"></i>'+
        '    </div>' +
        '</div>' +
    '';

    // Make it possible to hide the message with hitting escape
    keyDownEventStack.push(document.onkeydown);
    document.onkeydown = function(event) {
        identifyEscKeyPressedEvent(event, function() {hideMessage(className, id);});
    }

    // If the menu is visible, don't cover it with the message.
    // Here we calculate the top scroll in such a way, that we show it 20 pixels bellow either the menu or the top of the screen.
    var scrollTop = (document.documentElement.scrollTop || document.body.scrollTop);
    var topDistancePixels = Math.max(20, 110 - scrollTop);
    messageEl.style = 'top: ' + topDistancePixels + 'px;';

    document.body.appendChild(messageEl);

    // Center the message horizontally
    // Please note that this has to be done after it is appended to the DOM, since otherwise its width will be 0 (it is not visible)
    messageEl.style.marginLeft = -messageEl.offsetWidth / 2 + 'px';

    // Hide the message after several seconds
    setTimeout(function() {
        hideMessage(className, id);
    }, 3000);
}

function hideMessage(className, id) {
    var messageEl = document.getElementById(id);
    if (messageEl) {
        document.onkeydown = keyDownEventStack.pop();
        messageEl.className = className + ' fade-out';
        setTimeout(function() {
            document.body.removeChild(messageEl);
        }, 300);
    }
}

/*
 * Overlay (for pop-up boxes)
 */
function showOverlay() {
    var overlay = document.createElement('div');
    overlay.id = 'overlay';
    overlay.className = 'overlay';
    document.body.appendChild(overlay);
    overlay.className = 'overlay fade-in-overlay';
}

function hideOverlay() {
    var overlay = document.getElementById('overlay');
    overlay.className = 'overlay fade-out-overlay';
    setTimeout(function() {
        document.body.removeChild(overlay);
    }, 300);
}

/*
 * Form actions (show/hide/submit)
 */
function reposition() {
    // Position the box in the center of the screen (well, kind of)
    var el = document.getElementsByClassName('action-form')[0];
    var screenHeight = window.innerHeight || document.documentElement.clientHeight;
    var offset = Math.min(el.clientHeight / 2 + 20, screenHeight / 2 - 20);
    el.style.marginTop = -offset + 'px';
}

function showActionForm(content, redirect, classes = '') {
    // Create an overlay shadowing the rest of the page
    showOverlay();

    // Create the form box and add it to the DOM using a fade-in animation
    var form = document.createElement('div');
    form.innerHTML = '' +
        '<div class="action-form-close" onclick="hideActionForm(\'' + redirect + '\');"><i class="fa fa-close fa-fw"></i></div>' +
        '<div id="action-form-content">' + content + '</div>'
    ;
    document.body.appendChild(form);
    form.className = 'action-form fade-in' + (classes != '' ? ' ' + classes : '');

    // Set the vertical position of the box
    reposition();

    // Bind escape button for closing it
    keyDownEventStack.push(document.onkeydown);
    document.onkeydown = function(event) {
        identifyEscKeyPressedEvent(event, function() {hideActionForm(redirect);});
    }
}

function hideActionForm(redirectUrl) {
    // Redirect to another page if requested
    if (redirectUrl && redirectUrl != 'undefined') {
        redirect(redirectUrl);
    }
    // Otherwise just hide the form box using a fade-out animation
    document.onkeydown = keyDownEventStack.pop();
    var form = document.getElementsByClassName('action-form')[0];
    form.className = 'action-form fade-out';
    setTimeout(function() {
        document.body.removeChild(form);
    }, 300);
    hideOverlay();
}

function submitActionForm(response, hideOnSuccess = true) {
    try {
        response = JSON.parse(response);
    } catch(ex) {
        alert(response);
        response = null;
    }
    var type = (response && response.status == 'OK') ? 'INFO' : 'ERROR';
    var message = (response && response.message != '') ? response.message :
            (type == 'INFO') ? 'Действието беше изпълнено успешно.' : 'Действието не може да бъде изпълнено.';
    if (type == 'INFO' && hideOnSuccess) {
        hideActionForm();
    }
    showMessage(type, message);
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
        response = submitActionForm(response);
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
        submitActionForm(response);
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
            var statusEl = document.getElementById('graderStatus');
            if ('status' in response) {
                if (response['status'] == 'OK') {
                    statusEl.className = 'fa fa-check-circle green';
                    statusEl.title = 'Грейдърът е достъпен за ' + response['message'] + 's.';
                } else {
                    statusEl.className = 'fa fa-exclamation-circle red';
                    statusEl.title = 'Грейдърът се прави на недостъпен.';
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
        showMessage('INFO', 'Събмит ' + id + ' беше пратен за ретестване.');
    }
    ajaxCall('/admin/regrade/' + id, {}, callback);
}

/*
 * Order ranking by various parameters
 */
function orderRanking(orderBy) {
    var table = document.getElementById('rankingTable');
    var tableBody = table.tBodies[1];
    var rows = Array.prototype.slice.call(tableBody.rows, 0);
    rows = rows.sort(function(a, b) {
        var townA = a.cells[3].textContent, tasksA = parseInt(a.cells[4].textContent), achievementsA = parseInt(a.cells[5].textContent);
        var townB = b.cells[3].textContent, tasksB = parseInt(b.cells[4].textContent), achievementsB = parseInt(b.cells[5].textContent);
        switch (orderBy) {
            // You expected this to be a standard operator < ? Well - no. It is designed after the C-style qsort() function,
            // which requires an integer result: less than 0 for "less", 0 for "equal", and greater-than 0 for "greater".
            case 'town':
                return townA != townB ? (townA < townB ? -1 : +1) : (tasksA != tasksB ? tasksA - tasksB : achievementsA - achievementsB);
            case 'achievements':
                return achievementsA != achievementsB ? achievementsA - achievementsB : (tasksA != tasksB ? tasksA - tasksB : (townA < townB ? -1 : +1));
            default:
                return tasksA != tasksB ? tasksA - tasksB : (achievementsA != achievementsB ? achievementsA - achievementsB : (townA < townB ? -1 : +1));
        }
    });
    rows.reverse();
    for (var i = 0; i < rows.length; i++) {
        rows[i].cells[0].textContent = i + 1;
        tableBody.appendChild(rows[i]);
    }
}

/*
 * Source-code
 */
function displaySource() {
    document.getElementById('sourceLink').style.display = 'none';
    document.getElementById('sourceField').style.display = 'block';
    reposition();
}

/*
 * Copy to clipboard
 */
function copyToClipboard() {
    if (document.getSelection) {
        var range = document.createRange();
        range.selectNode(document.getElementById('source'));
        var selection = document.getSelection();
        selection.empty();
        selection.addRange(range);
        document.execCommand('copy');
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
    container.style.height = container.style.width = '5rem';
    container.style.position = 'absolute';
    container.style.right = '0';
    container.style.top = '50%';
    container.style.marginTop = '-2rem';
    container.title = done + ' out of ' + total;
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
function subscribeForUpdates(url) {
    if (!!window.EventSource) {
        var eventSource = new EventSource(url);
        eventSource.addEventListener('message', function(e) {
            console.log('Received new data at ' + ((new Date()).getTime()) + '.');
            var data = JSON.parse(e.data);
            if (data.hasOwnProperty('content')) {
                document.getElementById('action-form-content').innerHTML = data['content'];
            }
            if (data.hasOwnProperty('eos')) {
                eventSource.close();
                console.log('Closing server-sent events connection.');
            }
        }, false);
    } else {
        console.log('Cannot subscribe to automatic updates. Use page refresh instead.');
    }
}
