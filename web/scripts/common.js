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
 * Butter bars
 */
function showButterBar(type, message) {
    alert(type + ': ' + message);
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
        response = JSON.parse(response);
        if (response.status == 'OKAY') {
            showButterBar('INFO', 'Докладваният проблем беше изпратен успешно.');
        } else {
            showButterBar('ERROR', 'Съобщението не може да бъде изпратено в момента!');
        }
    }
    ajaxCall('code/tools/mail.php', sendData, onready);
    hideReportForm();
}

function showReportForm(hasAccess) {
    // Check if user is logged in.
    if (!hasAccess) {
        showButterBar('You must be signed in for this functionality to be available.', 'error');
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
    setTimeout(function() {document.body.removeChild(reportForm)}, 300);
    setTimeout(function() {document.body.removeChild(overlay)}, 300);
}

