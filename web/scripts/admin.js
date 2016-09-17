/*
 * Edit Problem
 */
function showEditProblemForm(content, redirect) {
    showActionForm(content, redirect);
}

function readFile(row, key, file) {
    var fileReader = new FileReader();
    fileReader.addEventListener('load', function() {
        row[key] = fileReader.result;

        row['status']++;
        if (row['status'] == 2) {
            row.cells[3].innerHTML = '<i class="fa fa-upload"></i>';
        }
    });
    fileReader.readAsDataURL(file);
}

function uploadTest(row) {
    row.cells[3].innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
    // TODO: Implement
    setTimeout(function() {
        row.cells[3].innerHTML = '<i class="fa fa-check green"></i>';
    }, 1000);
    // TODO: Add the md5 of the file to the table once uploaded.
    var result = {
        'inputHash': '0bf75d3a2e7420260475364e547db250',
        'outputHash': 'e934f7ab1999febc67c63ef7d1be5f62'
    };

    row.cells[0].innerHTML =
            row['input'] + '<div class="edit-problem-test-hash">' + result['inputHash'] + '</div>';
    row.cells[1].innerHTML =
            row['output'] + '<div class="edit-problem-test-hash">' + result['outputHash'] + '</div>';

    // Remove the event listener. A bit ugly, since the function is created with .bind()
    row.replaceChild(row.cells[3].cloneNode(true), row.cells[3]);
}

function updateTests() {
    var testSelector = document.getElementById('testSelector');
    var newTestList = testSelector.files;

    var testList = document.getElementById('testList');
    for (var i = 0; i < newTestList.length; i += 2) {
        var row = testList.insertRow(-1);
        row['input'] = newTestList[i + 0].name;
        row['output'] = newTestList[i + 1].name;
        row['score'] = 10;
        row['status'] = 0;

        var input = row.insertCell(-1); input.innerHTML = row['input'];
        var output = row.insertCell(-1); output.innerHTML = row['output'];
        var score = row.insertCell(-1); score.innerHTML = row['score']; score.contentEditable = true;
        var status = row.insertCell(-1); status.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
        status.addEventListener('click', uploadTest.bind(this, row));

        readFile(row, 'inputContent', newTestList[i + 0]);
        readFile(row, 'outputContent', newTestList[i + 1]);
    }
}

function toggleStatementHTML() {
    var statementEl = document.getElementById('statement');
    var statementHTML = statementEl.innerHTML || statementEl.value;
    var statementParent = statementEl.parentNode;
    statementParent.removeChild(statementEl);

    if (statementEl.nodeName == 'DIV') {
        var numLines = Math.max(20, statementHTML.split(/\r\n|\r|\n/).length);
        var textArea = document.createElement('TEXTAREA');
        textArea.id = 'statement';
        textArea.className = 'edit-problem-statement';
        textArea.rows = numLines;
        textArea.value = statementHTML.trim();
        statementParent.appendChild(textArea);
    } else {
        var editableDiv = document.createElement('DIV');
        editableDiv.id = 'statement';
        editableDiv.contentEditable = true;
        editableDiv.innerHTML = statementHTML;
        statementParent.appendChild(editableDiv);
    }
}

function submitEditProblemForm() {
    var urlTokens = window.location.href.split('/');
    var id = urlTokens[urlTokens.length - 1];

    var name = document.getElementById('problemName').value;
    var folder = document.getElementById('problemFolder').value;
    var author = document.getElementById('problemAuthor').value;
    var origin = document.getElementById('problemOrigin').value;
    var timeLimit = document.getElementById('problemTL').value;
    var memoryLimit = document.getElementById('problemML').value;
    var type = document.getElementById('problemType').value;
    var difficulty = document.getElementById('problemDifficulty').value;
    var statement = (document.getElementById('statement').innerHTML || document.getElementById('statement').value).trim();

    var tags = '';
    var tagCheckboxes = document.getElementsByName('problemTags');
    for (var i = 0; i < tagCheckboxes.length; i++) {
        if (tagCheckboxes[i].checked) {
            tags = tags + (tags == '' ? '' : ',') + tagCheckboxes[i].value;
        }
    }

    var checker = ''; // TODO
    var tester = ''; // TODO

    var data = {
        'id': id,
        'name': name,
        'folder': folder,
        'author': author,
        'origin': origin,
        'timeLimit': timeLimit,
        'memoryLimit': memoryLimit,
        'type': type,
        'difficulty': difficulty,
        'checker': checker,
        'tester': tester,
        'statement': statement,
        'tags': tags
    };

    var callback = function(response) {
        // alert(response);
        response = submitActionForm(response, 'Задачата беше запазена успешно.', 'Възникна проблем при записването на задачата.', false);
        if (id == 'new' && 'id' in response) {
            window.location.href = '/admin/problems?action=success';
        }
    }
    ajaxCall('/actions/modify', data, callback);
}
