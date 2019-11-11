/*
 * Data for searchbox
 */
var searchboxData = {};
var callbackFunction = null;

/*
 * Download search data
 */
function getSearchboxData(dataType) {
    // Valid types are: users, tags, pages, problems
    ajaxCall('/actions/data/' + dataType, {}, function(response) {
        try {
            response = JSON.parse(response);
        } catch(ex) {
            alert(response);
            response = null;
        }
        if (response != null) {
            if (response['status'] == 'OK') {
                searchboxData[dataType] = response[dataType];
                // Add information what each object is.
                for (var i = 0; i < searchboxData[dataType].length; i++) {
                    searchboxData[dataType][i]['type'] = dataType.substring(0, dataType.length - 1);
                }
                console.log('Got ' + response[dataType].length + ' results of type "' + dataType + '".');
            }
        }
    });
}

/*
Result of the following few functions is an array of objects (results).
The objects have the same attributes as they are returned from the backend (thus can be arbitrary).
The only additional attribute added is "type", being one of {"user", "tag", "page", "problem"}.
*/

/*
 * Populate suggestions for tags
 */
function getTagSuggestions(tags, searchText) {
    var results = [];
    return results;
}

/*
 * Populate suggestions for pages
 */
function getPageSuggestions(pages, searchText) {
    var results = [];
    return results;
}

/*
 * Populate suggestions for users
 */
function transliterate(str) {
    var transliterations = {
        'a': 'а', 'b': 'б', 'c': 'ц', 'd': 'д',
        'e': 'е', 'f': 'ф', 'g': 'г', 'h': 'х',
        'i': 'и', 'j': 'ж', 'k': 'к', 'l': 'л',
        'm': 'м', 'n': 'н', 'o': 'о', 'p': 'п',
        'q': 'я', 'r': 'р', 's': 'с', 't': 'т',
        'u': 'у', 'v': 'в', 'w': 'в', 'x': 'кс',
        'y': 'ъ', 'z': 'з'
    };
    var result = '';
    for (var i = 0; i < str.length; i++) {
        result += (str[i] in transliterations) ? transliterations[str[i]] : str[i];
    }
    return result;
}

function getUserSuggestions(users, searchText) {
    // The users array contains objects of the following form:
    // {
    //     'type': 'user',
    //     'id': '42',
    //     'username': 'espr1t',
    //     'name': 'Alexander Georgiev'
    // }
    var suggestions = [];
    var needle = searchText.toLowerCase();
    for (const user of users) {
        var name = user['name'].toLowerCase();
        var username = user['username'].toLowerCase();
        var shouldAdd = name.includes(needle) ||
                        username.includes(needle) ||
                        transliterate(name).includes(needle) ||
                        transliterate(username).includes(needle) ||
                        name.includes(transliterate(needle)) ||
                        username.includes(transliterate(needle));
        if (shouldAdd) {
            suggestions.push(user);
        }
    }
    return suggestions;
}

/*
 * Populate suggestions for problems
 */
function getProblemSuggestions(problems, searchText) {
    var results = [];
    return results;
}

function getSuggestion(suggestion) {
    var suggestionEl = document.createElement('div');
    suggestionEl.className = 'searchbox-suggestion';

    var icon = '';
    switch (suggestion['type']) {
        case 'user':
            icon = '<i class="fa fa-user"></i>';
            break;
        default:
            console.error('Invalid suggestion type: ' + suggestion['type']);
    }
    var text = suggestion['username'];

    suggestionEl.innerHTML = `${icon} ${text}`;
    suggestionEl.data = suggestion;
    suggestionEl.onclick = function() {
        clickSuggestion(suggestionEl);
    }
    return suggestionEl;
}

var suggestionUpdater = null;
function updateSuggestions(searchText) {
    suggestionUpdater = null;

    var suggestions = [];
    // The suggestions are in order: tags, pages, users, problems
    if ('tags' in searchboxData) {
        suggestions = suggestions.concat(getTagSuggestions(searchboxData['tags'], searchText));
    }
    if ('pages' in searchboxData) {
        suggestions = suggestions.concat(getPageSuggestions(searchboxData['pages'], searchText));
    }
    if ('users' in searchboxData) {
        suggestions = suggestions.concat(getUserSuggestions(searchboxData['users'], searchText));
    }
    if ('problems' in searchboxData) {
        suggestions = suggestions.concat(getProblemSuggestions(searchboxData['problems'], searchText));
    }

    var suggestionsEl = document.getElementById('searchboxSuggestions');
    if (suggestions.length == 0) {
        suggestionsEl.innerHTML = '<div>No current suggestions.</div>';
    } else {
        // Clear all current suggestions
        while (suggestionsEl.firstChild) {
            suggestionsEl.removeChild(suggestionsEl.firstChild);
        }
        // Add the new ones
        for (var i = 0; i < Math.min(5, suggestions.length); i++) {
            suggestionsEl.appendChild(getSuggestion(suggestions[i]));
        }
    }
}

function onSearchBoxInput() {
    var searchboxInput = document.getElementById('searchboxInput');
    if (searchboxInput.value.trim() == '') {
        searchboxInput.value = '';
    }
    if (searchboxInput.value == '') {
        document.getElementById('searchboxDivider').style.display = 'none';
        document.getElementById('searchboxSuggestions').style.display = 'none';
    } else {
        document.getElementById('searchboxDivider').style.display = 'block';
        document.getElementById('searchboxSuggestions').style.display = 'block';
        // Cancel update if scheduled and schedule a new one a bit later on.
        if (suggestionUpdater != null) {
            window.clearTimeout(suggestionUpdater);
        }
        // Update 100 millisecond after the user finished typing
        suggestionUpdater = window.setTimeout(updateSuggestions.bind(null, searchboxInput.value), 100);
    }
}


/*
 * Navigating and selecting suggestions (through arrows and enter)
 */
const KEY_ENTER = 13;
const KEY_ARROW_UP = 38;
const KEY_ARROW_DOWN = 40;

function getSelectedSuggestion() {
    var suggestionsEl = document.getElementById('searchboxSuggestions');
    for (var child of suggestionsEl.children) {
        if (child.className.includes('searchbox-suggestion-selected')) {
            return child;
        }
    }
    return null;
}

function navigateSuggestions(key) {
    var selectedEl = getSelectedSuggestion();
    if (key == KEY_ARROW_UP) {
        if (selectedEl != null) {
            if (selectedEl.previousSibling) {
                selectedEl.className = 'searchbox-suggestion';
                selectedEl = selectedEl.previousSibling;
            } else {
                selectedEl.className = 'searchbox-suggestion';
                selectedEl = null;
            }
        }
    }
    if (key == KEY_ARROW_DOWN) {
        if (selectedEl == null) {
            selectedEl = document.getElementById('searchboxSuggestions').firstChild;
        } else {
            if (selectedEl.nextSibling) {
                selectedEl.className = 'searchbox-suggestion';
                selectedEl = selectedEl.nextSibling;
            }
        }
    }
    if (selectedEl && selectedEl.textContent != 'No current suggestions.') {
        selectedEl.className = 'searchbox-suggestion-selected';
    }
}

function clickSuggestion(selectedEl) {
    hideSearchBox();
    callbackFunction(selectedEl.data);
}

function selectSuggestion() {
    var selectedEl = getSelectedSuggestion();
    if (selectedEl != null) {
        clickSuggestion(selectedEl);
    }
}

function showSearchBox(dataTypes, callback) {
    // dataTypes should be an array of some of the strings: "users", "tags", "pages", "problems"
    // callback is being called whenever the user selects a suggestion (valid result)
    callbackFunction = callback;

    // Populate data if not already populated
    for (const dataType of dataTypes) {
        if (!(dataType in searchboxData)) {
            getSearchboxData(dataType);
        }
    }

    showOverlay('searchboxOverlay');
    var overlayEl = document.getElementById('searchboxOverlay');
    // Default overlay is below box, move it up on top of it.
    overlayEl.style.zIndex = 100;

    var wrapper = document.createElement('div');
    wrapper.id = 'searchboxWrapper';
    wrapper.innerHTML = `
        <div class="searchbox" id="searchbox">
            <input class="searchbox-input" id="searchboxInput" maxlength="50" oninput="onSearchBoxInput()">
            <div class="searchbox-divider" id="searchboxDivider"></div>
            <div class="searchbox-suggestions" id="searchboxSuggestions">
                <div>No current suggestions.</div>
            </div>
        </div>
    `;
    document.body.appendChild(wrapper);
    wrapper.className = 'searchbox-wrapper fade-in-searchbox';
    reposition('searchboxWrapper');

    // Bind escape button for closing it
    addEscHandler(function() {hideSearchBox();});

    // Bind up/down arrows to navigate suggestions
    wrapper.onkeydown = function(event) {
        var keyCode = event.keyCode || event.which || 0;
        if (keyCode == KEY_ARROW_UP || keyCode == KEY_ARROW_DOWN) {
            event.preventDefault();
            event.stopPropagation();
            navigateSuggestions(keyCode);
        }
        if (keyCode == KEY_ENTER) {
            selectSuggestion();
        }
    }

    // Deselect selection on mouse enter
    document.getElementById('searchboxSuggestions').onmouseenter = function(event) {
        var selectedEl = getSelectedSuggestion();
        if (selectedEl != null) {
            selectedEl.className = 'searchbox-suggestion';
        }
    }

    // Focus on the input
    document.getElementById('searchboxInput').focus();
}

function hideSearchBox() {
    var searchboxWrapper = document.getElementById('searchboxWrapper');
    searchboxWrapper.parentNode.removeChild(searchboxWrapper);

    var searchboxOverlay = document.getElementById('searchboxOverlay');
    searchboxOverlay.parentNode.removeChild(searchboxOverlay);
}