/*
 * AJAX calls
 */
function ajaxCall(url, data, onready) {
    var first = true;
    var args = '';
    for (var field in data) {
        if (data.hasOwnProperty(field)) {
            args += (first ? '' : '&') + field + '=' + data[field];
            first = false;
        }
    }

    var xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
        if (xhttp.readyState == 4) {
            onready(xhttp.status == 200 ? xhttp.responseText : 'ERROR');
        }
    }
    xhttp.open('POST', url, true);
    xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhttp.send(args);
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

    document.body.appendChild(messageEl);

    // Hide the message after several seconds
    setTimeout(function() {
        hideMessage(className, id);
    }, 5000);
}

function hideMessage(className, id) {
    var messageEl = document.getElementById(id);
    if (messageEl) {
        messageEl.className = className + ' fade-out';
        setTimeout(function() {
            document.body.removeChild(messageEl);
        }, 300);
    }
}

/*
 * Report form handling
 */
var lastOnKeyDownEvent = null;

function identifyEscKeyPressedEvent(event) {
    if(event.keyCode == 27) {
        hideReportForm();
        event.stopPropagation();
    }
}

function submitReportForm() {
    var pageLink = window.location.href;
    // TODO: Trace where new lines are lost
    var problem = document.getElementById('reportText').value;

    var sendData = {
        'link': pageLink,
        'problem': problem
    };

    var onready = function(response) {
        try {
            response = JSON.parse(response);
        } catch(ex) {
            response = "";
        }
        if (!response || response.status != 'OKAY') {
            showMessage('ERROR', 'Съобщението не може да бъде изпратено в момента!');
        } else {
            showMessage('INFO', 'Докладваният проблем беше изпратен успешно.');
        }
    }
    ajaxCall('code/tools/mail.php', sendData, onready);
    hideReportForm();
}

function showReportForm(hasAccess) {
    // Check if user is logged in.
    if (!hasAccess) {
        showMessage('ERROR', 'Трябва да се оторизирате за да съобщите за проблем.');
        return;
    }

    // Create an overlay shadowing the rest of the page
    var overlay = document.createElement('div');
    overlay.id = 'reportOverlay';
    overlay.className = 'report-overlay';
    document.body.appendChild(overlay);

    // Create the report form and show it to the user
    var reportForm = document.createElement('div');
    reportForm.id = 'reportForm';
    reportForm.className = 'report-form';
    reportForm.innerHTML = '' +
        '<div class="report-close" onclick="hideReportForm();"><i class="fa fa-close fa-fw"></i></div>' +
        '<h2>Report a problem</h2>' +
        '<div class="separator"></div>' +
        '<br>' +
        '<div class="italic right" style="font-size: 0.8em;">On page: ' + window.location.href + '</div>' +
        '<textarea name="problem" class="report-problem" id="reportText"></textarea>' +
        '<div class="input-wrapper">' +
        '    <input type="submit" class="button button-color-red" onclick="return submitReportForm();">' +
        '</div>' +
    '';
    lastOnKeyDownEvent = document.onkeydown;
    document.onkeydown = function(event) {identifyEscKeyPressedEvent(event);}

    document.body.appendChild(reportForm);
    reportForm.className = 'report-form fade-in';
    overlay.className = 'report-overlay fade-in-overlay';
}

function hideReportForm() {
    document.onkeydown = lastOnKeyDownEvent;
    var overlay = document.getElementById('reportOverlay');
    var reportForm = document.getElementById('reportForm');
    reportForm.className = 'report-form fade-out';
    overlay.className = 'report-overlay fade-out-overlay';
    setTimeout(function() {
        document.body.removeChild(reportForm);
    }, 300);
    setTimeout(function() {
        document.body.removeChild(overlay);
    }, 300);
}

