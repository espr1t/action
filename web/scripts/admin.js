/*
 * Publish/Edit News
 */
function showEditNewsForm(content, redirect) {
    showActionForm(content, redirect);
}

function submitEditNewsForm() {
    var id = getLastUrlToken();
    var date = document.getElementById('newsDate').value;
    var title = document.getElementById('newsTitle').value;
    var content = document.getElementById('newsContent').value;

    var data = {
        'id': id,
        'date': date,
        'title': title,
        'content': content
    };

    var callback = function(response) {
        response = submitActionForm(response, false);
        if (id == 'new' && 'id' in response) {
            redirect('/admin/news?action=success');
        }
    }
    ajaxCall('/actions/publishNews', data, callback);
}


/*
 * Create/Edit Problem
 */
function showEditProblemForm(content, redirect) {
    showActionForm(content, redirect);
}

function changeTab(clickedId) {
    var tabIds = ['statementTab', 'optionsTab', 'testsTab'];
    for (var i = 0; i < tabIds.length; i++) {
        var buttonEl = document.getElementById(tabIds[i]);
        var contentEl = document.getElementById(tabIds[i] + 'Content');

        buttonEl.className = 'edit-problem-tab-button';
        contentEl.style.display = 'none';
        if (tabIds[i] == clickedId) {
            buttonEl.className += ' underline';
            contentEl.style.display = 'block';
        }
    }
}

var tests = [];

function isSameTest(name1, name2) {
    return name1.substr(0, name1.lastIndexOf('.')) == name2.substr(0, name2.lastIndexOf('.'));
}

function findTest(fileName) {
    for (var i = 0; i < tests.length; i++) {
        if (isSameTest(tests[i]['inpFile'], fileName) || isSameTest(tests[i]['solFile'], fileName)) {
            return i;
        }
    }
    return -1;
}

function updateFileHash(fileName, hash) {
    var test = findTest(fileName);
    if (test != -1) {
        if (tests[test]['inpFile'] == fileName) {
            tests[test]['inpHash'] = hash;
        }
        if (tests[test]['solFile'] == fileName) {
            tests[test]['solHash'] = hash;
        }
        updateTestTable();
    }
}

function updateTestTable() {
    var testTable = document.getElementById('testList');

    // Order by position
    tests.sort(function(t1, t2) {
        return (t1['position'] < t2['position'] ? -1 : 1);
    });

    // Clear the current contents of the table
    var rowCount = testTable.rows.length - 1;
    for (var i = 0; i < rowCount; i++)
        testTable.deleteRow(1);

    // Add all tests on separate rows
    for (var i = 0; i < tests.length; i++) {
        var row = testTable.insertRow(-1);

        var inputCol = row.insertCell(-1);
        var inputHash = (tests[i]['inpHash'] == 'error' ? 'red' : (tests[i]['inpHash'] == 'waiting' ? 'gray' : 'blue'));
        inputCol.innerHTML = tests[i]['inpFile'] + '<div class="edit-problem-test-hash ' + inputHash + '">' + tests[i]['inpHash'] + '</div>';

        var outputCol = row.insertCell(-1);
        var outputHash = (tests[i]['solHash'] == 'error' ? 'red' : (tests[i]['solHash'] == 'waiting' ? 'gray' : 'blue'));
        outputCol.innerHTML = tests[i]['solFile'] + '<div class="edit-problem-test-hash ' + outputHash + '">' + tests[i]['solHash'] + '</div>';

        var scoreCol = row.insertCell(-1);
        scoreCol.innerHTML = tests[i]['score'];
        scoreCol.contentEditable = true;
        scoreCol.addEventListener('input', (function(position, element) {
            for (var i = 0; i < tests.length; i++) {
                if (tests[i]['position'] == position) {
                    tests[i]['score'] = parseInt(element.innerHTML);
                    break;
                }
            }
        }).bind(this, tests[i]['position'], scoreCol));

        var statusCol = row.insertCell(-1);
        statusCol.innerHTML =
            (tests[i]['inpHash'] == 'error' || tests[i]['solHash'] == 'error') ? '<i class="fa fa-exclamation-circle red"></i>' :
            (tests[i]['inpHash'] == 'waiting' || tests[i]['solHash'] == 'waiting') ? '<i class="fa fa-spinner fa-spin"></i>' :
            '<i class="fa fa-check green"></i>';

        var deleteCol = row.insertCell(-1);
        deleteCol.innerHTML = '<i class="fa fa-trash red" style="cursor: pointer;"></i>';
        deleteCol.addEventListener('click', deleteTest.bind(undefined, tests[i]['position']));
    }
}

function deleteTest(position) {
    var index = 0;
    while (index < tests.length && tests[index]['position'] != position)
        index++;

    if (index < tests.length) {
        var data = {
            'problemId': getLastUrlToken(),
            'inpFile': tests[index]['inpFile'],
            'solFile': tests[index]['solFile'],
            'position': tests[index]['position']
        };

        var callback = function(response) {
            try {
                response = JSON.parse(response);
                if (response['status'] == 'OK') {
                    tests.splice(index, 1);
                    updateTestTable();
                    showMessage('INFO', 'Тестът беше изтрит успешно.');
                }
            } catch(ex) {
                showMessage('ERROR', 'Тестът не беше изтрит успешно.');
            }
        }
        ajaxCall('/actions/deleteTest', data, callback);
    }
}

function uploadTest(problemId, position, testFile) {
    var fileReader = new FileReader();
    fileReader.addEventListener('load', function() {
        var data = {
            'problemId': problemId,
            'testName': testFile.name,
            'testContent': fileReader.result.match(/,(.*)$/)[1],
            'testPosition': position
        };

        var callback = function(response) {
            var exception = false;
            try {
                response = JSON.parse(response);
            } catch(ex) {
                exception = true;
            }
            if (exception || response['status'] !== 'OK') {
                showMessage('ERROR', 'Възникна проблем при качването на тест "' + testFile.name + '"!');
                updateFileHash(testFile.name, 'error');
            } else {
                updateFileHash(testFile.name, response['hash']);
            }
        };
        ajaxCall('/actions/uploadTest', data, callback);
    });
    fileReader.readAsDataURL(testFile);
}

function addTests() {
    var problemId = getLastUrlToken();
    var testSelector = document.getElementById('testSelector');
    for (var i = 0; i < testSelector.files.length; i++) {
        var name = testSelector.files[i].name;
        // New test, add it to tests[] first
        if (findTest(name) == -1) {
            if (!name.match(/^[A-Za-z]+(\.\d\d)?\.(in|inp|out|sol)$/)) {
                showMessage('ERROR', 'Невалидно име на тест "' + name + '"!');
                continue;
            }
            var tokens = name.split('.');
            var position = tokens.length == 2 ? 0 : parseInt(tokens[1]);
            var extension = tokens[tokens.length - 1];

            tests.push({
                'inpFile': ((extension == 'in' || extension == 'inp') ? name : '-'),
                'inpHash': 'waiting',
                'solFile': ((extension == 'sol' || extension == 'out') ? name : '-'),
                'solHash': 'waiting',
                'position': position,
                'score': 10
            });
        }
        updateFileHash(name, 'waiting');
        uploadTest(problemId, tests[findTest(name)]['position'], testSelector.files[i]);
    }
    updateTestTable();
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
    var id = getLastUrlToken();
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

    var solutions = []; // TODO
    var testgen = ''; // TODO
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

    for (var i = 0; i < tests.length; i++) {
        data['test_' + tests[i]['position']] = tests[i]['score'];
    }

    var callback = function(response) {
        response = submitActionForm(response, false);
        if (id == 'new' && 'id' in response) {
            redirect('/admin/problems?action=success');
        }
    }
    ajaxCall('/actions/editProblem', data, callback);
}
