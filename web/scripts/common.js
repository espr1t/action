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
    xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhttp.send(args);
}

/*
 * Redirect
 */
function redirect(url) {
    window.location = url;
    exit(0);
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
        '    <div class="message-close" onclick="hideMessage(\'' + className + '\', \'' + id + '\');"><i class="fa fa-close fa-fw"></i></div>' +
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
function showActionForm(content, redirect, fixed) {
    // Create an overlay shadowing the rest of the page
    showOverlay();

    // Create the form box and add it to the DOM using a fade-in animation
    var form = document.createElement('div');
    form.innerHTML = '' +
        '<div class="action-form-close" onclick="hideActionForm(\'' + redirect + '\');"><i class="fa fa-close fa-fw"></i></div>' +
        content
    ;
    if (fixed) {
        form.style.position = 'fixed';
    }
    document.body.appendChild(form);
    form.className = 'action-form fade-in';

    // Position the box in the center of the screen (well, kind of)
    var screenHeight = window.innerHeight || document.documentElement.clientHeight;
    var offset = Math.min(form.clientHeight / 2 + 20, screenHeight / 2 - 20);
    form.style.marginTop = -offset + 'px';

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
    showActionForm(content, '', true);

    // Run language detection after every update
    var sourceEl = document.getElementById('source');
    sourceEl.onchange = sourceEl.onpaste = sourceEl.onkeydown = function() {
        setTimeout(function() {
            var langEl = document.getElementById('language');
            langEl.innerText = detectLanguage(sourceEl.value);
        }, 50);
    }
}

function submitSubmitForm() {
    var source = document.getElementById('source').value;
    var language = detectLanguage(source);
    var problemId = getLastUrlToken();
    var data = {
        'problemId': problemId,
        'source' : source,
        'language' : language
    };

    var callback = function(response) {
        response = submitActionForm(response);
        if ('id' in response) {
            redirect(window.location.href + '/submits/' + response.id);
        }
    }
    ajaxCall('/actions/submitSolution', data, callback);
}

/*
 * Report form handling
 */
function showReportForm(content) {
    showActionForm(content, '', true);
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
            case 'town':
                return townA != townB ? townA > townB : (tasksA != tasksB ? tasksA < tasksB : achievementsA < achievementsB);
            case 'achievements':
                return achievementsA != achievementsB ? achievementsA < achievementsB : (tasksA != tasksB ? tasksA < tasksB : townA > townB);
            default:
                return tasksA != tasksB ? tasksA < tasksB : (achievementsA != achievementsB ? achievementsA < achievementsB : townA > townB);
        }
    });
    for (var i = 0; i < rows.length; i++) {
        rows[i].cells[0].textContent = i + 1;
        tableBody.appendChild(rows[i]);
    }
}
