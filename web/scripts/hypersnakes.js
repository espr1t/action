HYPERSNAKES_SPACE = 32;
HYPERSNAKES_ARROW_LEFT = 37;
HYPERSNAKES_ARROW_UP = 38;
HYPERSNAKES_ARROW_RIGHT = 39;
HYPERSNAKES_ARROW_DOWN = 40;
HYPERSNAKES_MOVE_INTERVAL = 120; // Milliseconds

hypersnakesCells = [];
hypersnakesNumRows = 20;
hypersnakesNumCols = 20;
hypersnakesNumApples = 25;
hypersnakesSnakeDir = null;

hypersnakesDemo = false;
hypersnakesAiTurn = false;
hypersnakesGameLoop = null;
hypersnakesShowLetters = true;
hypersnakesReplayRunning = false;
hypersnakesPlayerOne = '';
hypersnakesPlayerTwo = '';

function identifyHypersnakesArrowEvent(event) {
    if (event.keyCode >= HYPERSNAKES_ARROW_LEFT && event.keyCode <= HYPERSNAKES_ARROW_DOWN) {
        event.preventDefault();
        event.stopPropagation();
        hypersnakesSnakeDir = event.keyCode;
        clearTimeout(hypersnakesGameLoop);
        runHypersnakesGame();
    }
}

function identifyHypersnakesReplayEvent(event) {
    if (event.keyCode == HYPERSNAKES_SPACE) {
        event.preventDefault();
        event.stopPropagation();
        hypersnakesReplayRunning = !hypersnakesReplayRunning;
    }
}

function hypersnakesAiLogic() {
    // BFS to find shortest paths, originated at the apple
    var INF = 1000000001;
    var dir = [ [-1, 0], [0, 1], [1, 0], [0, -1] ];

    dist = [];
    for (var row = 0; row < hypersnakesNumRows; row++) {
        var cur = [];
        for (var col = 0; col < hypersnakesNumCols; col++)
            cur.push(INF - 1);
        dist.push(cur);
    }

    q = [];
    for (var row = 0; row < hypersnakesNumRows; row++) {
        for (var col = 0; col < hypersnakesNumCols; col++) {
            if (hypersnakesCells[row][col] == '@') {
                q.push([row, col]);
                dist[row][col] = 0;
            }
        }
    }

    for (var i = 0; i < q.length; i++) {
        var curRow = q[i][0], curCol = q[i][1];
        for (var d = 0; d < 4; d++) {
            var nxtRow = curRow + dir[d][0]; if (nxtRow < 0 || nxtRow >= hypersnakesNumRows) continue;
            var nxtCol = curCol + dir[d][1]; if (nxtCol < 0 || nxtCol >= hypersnakesNumCols) continue;
            if (hypersnakesCells[nxtRow][nxtCol] == '.' && dist[curRow][curCol] + 1 < dist[nxtRow][nxtCol]) {
                dist[nxtRow][nxtCol] = dist[curRow][curCol] + 1;
                q.push([nxtRow, nxtCol]);
            }
        }
    }

    // Find the best possible move
    var headRow = -1, headCol = -1;
    for (var row = 0; row < hypersnakesNumRows; row++) {
        for (var col = 0; col < hypersnakesNumCols; col++) {
            if (hypersnakesCells[row][col] == 'a')
                headRow = row, headCol = col;
        }
    }
    
    var bestDir = -1, bestDist = INF;
    for (var d = 0; d < 4; d++) {
        var row = headRow + dir[d][0]; if (row < 0 || row >= hypersnakesNumRows) continue;
        var col = headCol + dir[d][1]; if (col < 0 || col >= hypersnakesNumCols) continue;
        if (hypersnakesCells[row][col] != '.' && hypersnakesCells[row][col] != '@')
            continue;
        if (bestDist > dist[row][col]) {
            bestDist = dist[row][col];
            bestDir = d;
        }
    }

    // Save the user's direction
    var userSnakeDir = hypersnakesSnakeDir;

    if (bestDir == 0) hypersnakesSnakeDir = HYPERSNAKES_ARROW_UP;
    else if (bestDir == 1) hypersnakesSnakeDir = HYPERSNAKES_ARROW_RIGHT;
    else if (bestDir == 2) hypersnakesSnakeDir = HYPERSNAKES_ARROW_DOWN;
    else hypersnakesSnakeDir = HYPERSNAKES_ARROW_LEFT;
    var message = updateHypersnakesGame();

    // Revert moving direction to the one the user has selected
    hypersnakesSnakeDir = userSnakeDir;

    return message;
}

function addHypersnakesApple() {
    var appleRow = Math.floor(Math.random() * hypersnakesNumRows);
    var appleCol = Math.floor(Math.random() * hypersnakesNumCols);

    while (hypersnakesCells[appleRow][appleCol] != '.') {
        appleRow = Math.floor(Math.random() * hypersnakesNumRows);
        appleCol = Math.floor(Math.random() * hypersnakesNumCols);
    }
    hypersnakesCells[appleRow][appleCol] = '@';
}

function endHypersnakesGame(message) {
    clearTimeout(hypersnakesGameLoop);
    document.removeEventListener('keydown', identifyHypersnakesArrowEvent, false);
    showMessage('INFO', message);
    if (hypersnakesDemo) {
        window.setTimeout(function() {location.reload();}, 4000);
    }
}

function changeHypersnakesChar(ch, add) {
    return String.fromCharCode(ch.charCodeAt(0) + add)
}

function updateHypersnakesGame() {
    var headChar = hypersnakesAiTurn ? 'a' : 'A';
    var tailChar = hypersnakesAiTurn ? 'z' : 'Z';

    var snake = {};
    var currentLength = 0;
    for (row = 0; row < hypersnakesNumRows; row++) {
        for (col = 0; col < hypersnakesNumCols; col++) {
            if (hypersnakesCells[row][col] >= headChar && hypersnakesCells[row][col] <= tailChar) {
                currentLength++;
                snake[hypersnakesCells[row][col]] = [row, col];
            }
        }
    }
    if (!snake.hasOwnProperty(headChar))
        return 'WTF?';

    var nextRow = snake[headChar][0];
    var nextCol = snake[headChar][1];
    if (hypersnakesSnakeDir == HYPERSNAKES_ARROW_LEFT) nextCol--;
    else if (hypersnakesSnakeDir == HYPERSNAKES_ARROW_UP) nextRow--;
    else if (hypersnakesSnakeDir == HYPERSNAKES_ARROW_RIGHT) nextCol++;
    else if (hypersnakesSnakeDir == HYPERSNAKES_ARROW_DOWN) nextRow++;

    var currentPlayer = !hypersnakesAiTurn ? hypersnakesPlayerOne : hypersnakesPlayerTwo;

    // Outside the board
    if (nextRow < 0 || nextRow >= hypersnakesNumRows || nextCol < 0 || nextCol >= hypersnakesNumCols) {
        return 'Game over! ' + currentPlayer + ' left limits of the board.';
    }

    // Part of own snake
    if (hypersnakesCells[nextRow][nextCol] >= 'A' && hypersnakesCells[nextRow][nextCol] <= 'Z') {
        return 'Game over! ' + currentPlayer + ' attempted eating part of a snake.';
    }

    // Part of opponent's snake
    if (hypersnakesCells[nextRow][nextCol] >= 'a' && hypersnakesCells[nextRow][nextCol] <= 'z') {
        return 'Game over! ' + currentPlayer + ' attempted eating part of a snake.';
    }

    // Eating an apple
    if (hypersnakesCells[nextRow][nextCol] == '@') {
        // Otherwise, update the rest of the body of the snake
        for (var key in snake) {
            hypersnakesCells[snake[key][0]][snake[key][1]] = changeHypersnakesChar(hypersnakesCells[snake[key][0]][snake[key][1]], +1);
        }
        hypersnakesCells[nextRow][nextCol] = headChar;

        currentLength++;
        hypersnakesNumApples--;
        document.getElementById(!hypersnakesAiTurn ? 'p1score' : 'p2score').innerHTML = currentLength;
    }

    // Just a regular empty cell
    if (hypersnakesCells[nextRow][nextCol] == '.') {
        for (var key in snake) {
            hypersnakesCells[snake[key][0]][snake[key][1]] = changeHypersnakesChar(hypersnakesCells[snake[key][0]][snake[key][1]], +1);
        }
        var lastPos = snake[changeHypersnakesChar(headChar, currentLength - 1)];
        hypersnakesCells[lastPos[0]][lastPos[1]] = '.';
        hypersnakesCells[nextRow][nextCol] = headChar;
    }

    // Update the DOM
    updateHypersnakesBoard();

    // If already at desired length
    if (hypersnakesNumApples == 0) {
        if (currentLength >= 14)
            return 'Player ' + (!hypersnakesAiTurn ? hypersnakesPlayerOne : hypersnakesPlayerTwo) + ' won!';
        else
            return 'Player ' + (!hypersnakesAiTurn ? hypersnakesPlayerTwo : hypersnakesPlayerOne) + ' won!';
    }

    return '';
}

function hypersnakesUserMove() {
    // If the returned string is non-empty, the game has ended (either with a win, or with a loss)
    var message = updateHypersnakesGame();
    if (message != '') {
        endHypersnakesGame(message);
        return false;
    }
    return true;
}

function hypersnakesAiMove() {
    message = hypersnakesAiLogic();
    if (message != '') {
        if (message.startsWith('Player'))
            endHypersnakesGame(message);
        else if (message == 'You win!')
            endHypersnakesGame('You lost. Opponent ate more apples!');
        else
            endHypersnakesGame('You win! Opponent made invalid move.');
        return false;
    }
    return true;
}

function runHypersnakesGame() {
    // User's move
    if (!hypersnakesAiTurn) {
        if (!hypersnakesUserMove()) {
            return false;
        }
    }
    // AI move
    else {
        if (!hypersnakesAiMove()) {
            return false;
        }
    }
    // Continue self-update loop
    hypersnakesAiTurn = !hypersnakesAiTurn;
    hypersnakesGameLoop = setTimeout(function() {
        runHypersnakesGame();
    }, HYPERSNAKES_MOVE_INTERVAL);
}

function hypersnakesReplayCycle(idx, log) {
    if (hypersnakesReplayRunning || idx == 0) {
        if (idx >= log.length) {
            endHypersnakesGame('Reached end of game log.');
            return;
        } else if (log[idx] == '(') {
            idx++;
            var appleCoords = '';
            while (log[idx] != ')')
                appleCoords += log[idx++];
            idx++;
            var appleRow = parseInt(appleCoords.split(',')[0]);
            var appleCol = parseInt(appleCoords.split(',')[1]);
            hypersnakesCells[appleRow][appleCol] = '@';
            updateHypersnakesBoard();
        } else {
            if (log[idx] == 'L') hypersnakesSnakeDir = HYPERSNAKES_ARROW_LEFT;
            else if (log[idx] == 'U') hypersnakesSnakeDir = HYPERSNAKES_ARROW_UP;
            else if (log[idx] == 'R') hypersnakesSnakeDir = HYPERSNAKES_ARROW_RIGHT;
            else if (log[idx] == 'D') hypersnakesSnakeDir = HYPERSNAKES_ARROW_DOWN;
            else {
                alert('Error in the game log: found invalid character \'' + log[idx] + '\'!');
                return;
            }
            idx++;

            var message = updateHypersnakesGame();
            if (message != '') {
                endHypersnakesGame(message);
                if (idx < log.length) {
                    alert('Game log claims there are more moves?');
                }
                return;
            }
            hypersnakesAiTurn = !hypersnakesAiTurn;
        }
    }
    window.setTimeout(function() {hypersnakesReplayCycle(idx, log);}, HYPERSNAKES_MOVE_INTERVAL);
}

function runHypersnakesReplay(log) {
    HYPERSNAKES_MOVE_INTERVAL = 80; // Milliseconds

    hypersnakesNumApples = 25;

    // Create the board
    var settings = log.split('|')[0].split(',');

    document.getElementById('p1score').innerHTML = 1;
    document.getElementById('p2score').innerHTML = 1;

    hypersnakesCells = [];
    for (var row = 0; row < hypersnakesNumRows; row++) {
        hypersnakesCells.push([]);
        for (var col = 0; col < hypersnakesNumCols; col++) {
            hypersnakesCells[hypersnakesCells.length - 1].push('.');
        }
    }

    var playerOneRow = parseInt(settings[0]);
    var playerOneCol = parseInt(settings[1]);
    hypersnakesCells[playerOneRow][playerOneCol] = 'A';

    var playerTwoRow = parseInt(settings[2]);
    var playerTwoCol = parseInt(settings[3]);
    hypersnakesCells[playerTwoRow][playerTwoCol] = 'a';

    for (var app = 0; app < hypersnakesNumApples; app++) {
        var appleRow = parseInt(settings[app * 2 + 4]);
        var appleCol = parseInt(settings[app * 2 + 5]);
        hypersnakesCells[appleRow][appleCol] = '@';
    }

    updateHypersnakesBoard();

    if (hypersnakesDemo) {
        setTimeout(function() {hypersnakesReplayRunning = true;}, 1000);
    }

    log = log.split('|')[1];
    // Start the update process
    hypersnakesReplayCycle(0, log);
}

function updateHypersnakesBoard() {
    // Update the DOM
    var boardWrapper = document.getElementById('snakesBoard');
    boardWrapper.removeChild(boardWrapper.firstChild);
    boardWrapper.appendChild(getHypersnakesBoard());
}

function resetHypersnakesGame() {
    hypersnakesCells = [];
    hypersnakesNumRows = 20;
    hypersnakesNumCols = 20;
    hypersnakesNumApples = 25;
    hypersnakesSnakeDir = null;

    hypersnakesAiTurn = false;
    hypersnakesGameLoop = null;
    hypersnakesShowLetters = true;
    hypersnakesReplayRunning = false;

    createHypersnakesGame();
    updateHypersnakesBoard();

    document.getElementById('p1score').innerText = '1';
    document.getElementById('p2score').innerText = '1';
    // Add action event listeners
    document.addEventListener('keydown', identifyHypersnakesArrowEvent, false);
}

function createHypersnakesGame() {
    hypersnakesCells = []
    for (var row = 0; row < hypersnakesNumRows; row++) {
        hypersnakesCells.push([]);
        for (var col = 0; col < hypersnakesNumCols; col++) {
            hypersnakesCells[hypersnakesCells.length - 1].push('.');
        }
    }

    /*
    hypersnakesNumRows = hypersnakesNumCols = 15;
    hypersnakesCells = [
        ['@', '@', '@', '.', '.', '.', '.', '.', '.', '.', '.', '.', '@', '@', '@'],
        ['@', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '@'],
        ['@', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '@'],
        ['@', '.', 'A', '.', '.', 'P', 'Q', '.', '.', '.', '.', '.', '.', '.', '@'],
        ['@', '.', 'B', '.', '.', 'O', 'R', '.', '.', '.', '.', '.', '.', '.', '@'],
        ['@', '.', 'C', '.', '.', 'N', 'S', '.', 'e', 'd', 'c', 'b', 'a', '.', '@'],
        ['@', '.', 'D', 'K', 'L', 'M', 'T', '.', 'f', '.', '.', '.', '.', '.', '@'],
        ['@', '.', 'E', 'J', '.', '.', 'U', '.', 'g', '.', '.', '.', '.', '.', '@'],
        ['@', '.', 'F', 'I', '.', '.', 'V', '.', 'h', 'i', 'j', 'k', 'l', '.', '@'],
        ['@', '.', 'G', 'H', '.', '.', 'W', '.', '.', '.', '.', '.', 'm', '.', '@'],
        ['@', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', 'n', '.', '@'],
        ['@', '.', '.', '.', '.', '.', '.', '.', 's', 'r', 'q', 'p', 'o', '.', '@'],
        ['@', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '@'],
        ['@', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '@'],
        ['@', '@', '@', '.', '.', '.', '.', '.', '.', '.', '.', '.', '@', '@', '@']
    ];
    */

    var playerOneRow = Math.floor(Math.random() * hypersnakesNumRows);
    var playerOneCol = Math.floor(Math.random() * hypersnakesNumCols);
    hypersnakesCells[playerOneRow][playerOneCol] = 'A';

    var playerTwoRow = Math.floor(Math.random() * hypersnakesNumRows);
    var playerTwoCol = Math.floor(Math.random() * hypersnakesNumCols);
    while (playerOneRow == playerTwoRow && playerOneCol == playerTwoCol) {
        playerTwoRow = Math.floor(Math.random() * hypersnakesNumRows);
        playerTwoCol = Math.floor(Math.random() * hypersnakesNumCols);
    }
    hypersnakesCells[playerTwoRow][playerTwoCol] = 'a';

    hypersnakesNumApples = 25;
    for (var app = 0; app < hypersnakesNumApples; app++)
        addHypersnakesApple();
}

function getHypersnakesBoard() {
    var board = document.createElement('table');
    board.className = 'snakes-board';
    var tbody = document.createElement('tbody');
    for (var row = 0; row < hypersnakesCells.length; row++) {
        var tr = document.createElement('tr');
        for (var col = 0; col < hypersnakesCells[row].length; col++) {
            var td = document.createElement('td');
            if (hypersnakesCells[row][col] == '@') {
                td.className = 'apple';
            } else if (hypersnakesCells[row][col] >= 'A' && hypersnakesCells[row][col] <= 'Z') {
                var segmentId = hypersnakesCells[row][col].charCodeAt(0) - 'A'.charCodeAt(0);
                td.className = 'player-one';
                td.style = "opacity: " + (100 - segmentId * 3) / 100.0 + ";";
                td.innerText = hypersnakesShowLetters ? hypersnakesCells[row][col] : '';
            } else if (hypersnakesCells[row][col] >= 'a' && hypersnakesCells[row][col] <= 'z') {
                var segmentId = hypersnakesCells[row][col].charCodeAt(0) - 'a'.charCodeAt(0);
                td.className = 'player-two';
                td.style = "opacity: " + (100 - segmentId * 3) / 100.0 + ";";
                td.innerText = hypersnakesShowLetters ? hypersnakesCells[row][col] : '';
            }
            tr.appendChild(td);
        }
        tbody.appendChild(tr);
    }
    board.appendChild(tbody);
    return board;
}

function getHypersnakesContent(showReset) {
    // Create the board and initial cells
    createHypersnakesGame();

    // Now create the DOM content
    var content = document.createElement('div');
    content.className = 'snakes-content';

    // Header with the task name
    var header = document.createElement('div');
    header.style.textAlign = 'left';
    header.innerHTML = '<h2><span class="blue">HyperSnakes</span> :: Визуализатор</h2>';
    content.appendChild(header);

    // First player (nickname and score)
    var player1 = document.createElement('div');
    player1.style = 'display: inline-block; width: 20%; vertical-align: middle; font-size: 1.5rem; font-weight: bold;';
    player1.innerHTML += '<div class="blue">' + hypersnakesPlayerOne + '</div>';
    player1.innerHTML += '<div style="font-size: smaller;" id="p1score">1</div>';
    content.appendChild(player1);

    // The actual playing board
    var board = document.createElement('div');
    board.style = 'display: inline-block; width: 60%; vertical-align: middle;';
    board.id = 'snakesBoard';
    board.appendChild(getHypersnakesBoard());
    content.appendChild(board);

    // Second player (nickname and score)
    var player2 = document.createElement('div');
    player2.style = 'display: inline-block; width: 20%; vertical-align: middle; font-size: 1.5rem; font-weight: bold;';
    player2.innerHTML += '<div class="red">' + hypersnakesPlayerTwo + '</div>';
    player2.innerHTML += '<div style="font-size: smaller;" id="p2score">1</div>';
    content.appendChild(player2);

    if (showReset) {
        // Footer with instructions
        var footer = document.createElement('div');
        footer.style.textAlign = 'center';
        footer.innerHTML = '<i style="font-size: smaller">Използвайте стрелките на клавиатурата за да управлявате змията.</i>';
        content.appendChild(footer);

        // Reset button
        var reset = document.createElement('button');
        reset.className = 'button button-color-blue';
        reset.type = 'button';
        reset.innerText = 'Нова игра';
        reset.setAttribute('onclick', 'resetHypersnakesGame();');
        content.appendChild(reset);
    }

    return content;
}

function showHypersnakesReplay(playerOne, playerTwo, log, demo=false) {
    hypersnakesDemo = demo;
    hypersnakesPlayerOne = playerOne;
    hypersnakesPlayerTwo = playerTwo;

    // Create and show the initial board
    var content = getHypersnakesContent(false);

    var instructions = document.createElement('div');
    instructions.id = 'instructions';
    instructions.style.textAlign = 'center';
    instructions.style.fontStyle = 'italic';
    if (!hypersnakesDemo) {
        instructions.innerText = 'Натиснете шпация или кликнете на дъската за да пуснете или паузирате играта.';
    }
    content.appendChild(instructions);

    // Make pressing escape return back to the game
    var gameUrl = window.location.href.substr(0, window.location.href.lastIndexOf('/replays'));
    showActionForm(content.outerHTML, gameUrl);

    // Add action event listeners
    document.addEventListener('keydown', identifyHypersnakesReplayEvent, false);
    document.getElementById('snakesBoard').addEventListener('mousedown', function() {
        hypersnakesReplayRunning = !hypersnakesReplayRunning;
    }, true);

    // Run the actual replay
    runHypersnakesReplay(log);
}

function showHypersnakesVisualizer(username) {
    hypersnakesPlayerOne = username;
    hypersnakesPlayerTwo = 'AI';

    // Create and show the initial board
    var content = getHypersnakesContent(true);

    // Add action event listeners
    document.addEventListener('keydown', identifyHypersnakesArrowEvent, false);

    // Make pressing escape return back to the game
    var gameUrl = window.location.href.substr(0, window.location.href.lastIndexOf('/'));
    showActionForm(content.outerHTML, gameUrl);
}
