SNAKES_SPACE = 32;
SNAKES_ARROW_LEFT = 37;
SNAKES_ARROW_UP = 38;
SNAKES_ARROW_RIGHT = 39;
SNAKES_ARROW_DOWN = 40;
SNAKES_MOVE_INTERVAL = 120; // Milliseconds

snakesCells = [];
snakesNumRows = 15;
snakesNumCols = 15;
snakesDesiredLength = 10;
snakesSnakeDir = null;

aiTurn = false;
snakesGameLoop = null;
showLetters = true;
snakesReplayRunning = false;
snakesPlayerOne = '';
snakesPlayerTwo = '';

function identifySnakeArrowEvent(event) {
    if (event.keyCode >= SNAKES_ARROW_LEFT && event.keyCode <= SNAKES_ARROW_DOWN) {
        event.preventDefault();
        event.stopPropagation();
        snakesSnakeDir = event.keyCode;
        clearTimeout(snakesGameLoop);
        runSnakesGame();
    }
}

function identifySnakeReplayEvent(event) {
    if (event.keyCode == SNAKES_SPACE) {
        event.preventDefault();
        event.stopPropagation();
        snakesReplayRunning = !snakesReplayRunning;
    }
}

function aiLogic() {
    // BFS to find shortest paths, originated at the apple
    var INF = 1000000001;
    var dir = [ [-1, 0], [0, 1], [1, 0], [0, -1] ];

    var startRow = -1, startCol = -1;
    for (var row = 0; row < snakesNumRows; row++) {
        for (var col = 0; col < snakesNumCols; col++)
            if (snakesCells[row][col] == '@')
                startRow = row, startCol = col;
    }
    
    dist = [];
    for (var row = 0; row < snakesNumRows; row++) {
        var cur = [];
        for (var col = 0; col < snakesNumCols; col++)
            cur.push(INF - 1);
        dist.push(cur);
    }

    q = [];
    q.push([startRow, startCol]);
    dist[startRow][startCol] = 0;
    
    for (var i = 0; i < q.length; i++) {
        var curRow = q[i][0], curCol = q[i][1];
        for (var d = 0; d < 4; d++) {
            var nxtRow = curRow + dir[d][0]; if (nxtRow < 0 || nxtRow >= snakesNumRows) continue;
            var nxtCol = curCol + dir[d][1]; if (nxtCol < 0 || nxtCol >= snakesNumCols) continue;
            if (snakesCells[nxtRow][nxtCol] == '.' && dist[curRow][curCol] + 1 < dist[nxtRow][nxtCol]) {
                dist[nxtRow][nxtCol] = dist[curRow][curCol] + 1;
                q.push([nxtRow, nxtCol]);
            }
        }
    }

    // Find the best possible move
    var headRow = -1, headCol = -1;
    for (var row = 0; row < snakesNumRows; row++) {
        for (var col = 0; col < snakesNumCols; col++) {
            if (snakesCells[row][col] == 'a')
                headRow = row, headCol = col;
        }
    }
    
    var bestDir = -1, bestDist = INF;
    for (var d = 0; d < 4; d++) {
        var row = headRow + dir[d][0]; if (row < 0 || row >= snakesNumRows) continue;
        var col = headCol + dir[d][1]; if (col < 0 || col >= snakesNumCols) continue;
        if (snakesCells[row][col] != '.' && snakesCells[row][col] != '@')
            continue;
        if (bestDist > dist[row][col]) {
            bestDist = dist[row][col];
            bestDir = d;
        }
    }

    // Save the user's direction
    var userSnakeDir = snakesSnakeDir;

    if (bestDir == 0) snakesSnakeDir = SNAKES_ARROW_UP;
    else if (bestDir == 1) snakesSnakeDir = SNAKES_ARROW_RIGHT;
    else if (bestDir == 2) snakesSnakeDir = SNAKES_ARROW_DOWN;
    else snakesSnakeDir = SNAKES_ARROW_LEFT;
    var message = updateSnakesGame();

    // Revert moving direction to the one the user has selected
    snakesSnakeDir = userSnakeDir;

    return message;
}

function addSnakesApple() {
    // Don't add an apple if a replay is running, we'll get the position from the log
    if (snakesReplayRunning)
        return;

    var appleRow = Math.floor(Math.random() * snakesNumRows);
    var appleCol = Math.floor(Math.random() * snakesNumCols);

    while (snakesCells[appleRow][appleCol] != '.') {
        appleRow = Math.floor(Math.random() * snakesNumRows);
        appleCol = Math.floor(Math.random() * snakesNumCols);
    }
    snakesCells[appleRow][appleCol] = '@';
}

function endSnakesGame(message) {
    // Wait a little so the board is updated
    window.setTimeout(function() {alert(message);}, 20);
    clearTimeout(snakesGameLoop);
    document.removeEventListener('keydown', identifySnakeArrowEvent, false);
}

function changeSnakesChar(ch, add) {
    return String.fromCharCode(ch.charCodeAt(0) + add)
}

function updateSnakesGame() {
    var headChar = aiTurn ? 'a' : 'A';
    var tailChar = aiTurn ? 'z' : 'Z';

    var snake = {};
    var currentLength = 0;
    for (row = 0; row < snakesNumRows; row++) {
        for (col = 0; col < snakesNumCols; col++) {
            if (snakesCells[row][col] >= headChar && snakesCells[row][col] <= tailChar) {
                currentLength++;
                snake[snakesCells[row][col]] = [row, col];
            }
        }
    }
    if (!snake.hasOwnProperty(headChar))
        return 'WTF?';

    var nextRow = snake[headChar][0];
    var nextCol = snake[headChar][1];
    if (snakesSnakeDir == SNAKES_ARROW_LEFT) nextCol--;
    else if (snakesSnakeDir == SNAKES_ARROW_UP) nextRow--;
    else if (snakesSnakeDir == SNAKES_ARROW_RIGHT) nextCol++;
    else if (snakesSnakeDir == SNAKES_ARROW_DOWN) nextRow++;

    var currentPlayer = !aiTurn ? snakesPlayerOne : snakesPlayerTwo;

    // Outside the board
    if (nextRow < 0 || nextRow >= snakesNumRows || nextCol < 0 || nextCol >= snakesNumCols) {
        return 'Game over! ' + currentPlayer + ' left limits of the board.';
    }

    // Part of own snake
    if (snakesCells[nextRow][nextCol] >= 'A' && snakesCells[nextRow][nextCol] <= 'Z') {
        return 'Game over! ' + currentPlayer + ' attempted eating part of his/her own snake.';
    }

    // Part of opponent's snake
    if (snakesCells[nextRow][nextCol] >= 'a' && snakesCells[nextRow][nextCol] <= 'z') {
        return 'Game over! ' + currentPlayer + ' attempted eating part of opponent\'s snake.';
    }

    // Eating an apple
    if (snakesCells[nextRow][nextCol] == '@') {
        // Otherwise, update the rest of the body of the snake
        for (var key in snake) {
            snakesCells[snake[key][0]][snake[key][1]] = changeSnakesChar(snakesCells[snake[key][0]][snake[key][1]], +1);
        }
        snakesCells[nextRow][nextCol] = headChar;

        currentLength++;
        document.getElementById(!aiTurn ? 'p1score' : 'p2score').innerHTML = (currentLength - 1) + '/' + snakesDesiredLength;

        // Add an apple if the game doesn't end now
        if (currentLength < snakesDesiredLength + 1)
            addSnakesApple();
    }

    // Just a regular empty cell
    if (snakesCells[nextRow][nextCol] == '.') {
        for (var key in snake) {
            snakesCells[snake[key][0]][snake[key][1]] = changeSnakesChar(snakesCells[snake[key][0]][snake[key][1]], +1);
        }
        var lastPos = snake[changeSnakesChar(headChar, currentLength - 1)];
        snakesCells[lastPos[0]][lastPos[1]] = '.';
        snakesCells[nextRow][nextCol] = headChar;
    }

    // Update the DOM
    updateSnakesBoard();

    // If already at desired length
    if (currentLength == snakesDesiredLength + 1) {
        return 'Player ' + currentPlayer + ' won!';
    }

    return '';
}

function userMove() {
    // If the returned string is non-empty, the game has ended (either with a win, or with a loss)
    var message = updateSnakesGame();
    if (message != '') {
        endSnakesGame(message);
        return false;
    }
    return true;
}

function aiMove() {
    message = aiLogic();
    if (message != '') {
        if (message == 'You win!')
            endSnakesGame('You lost. Opponent ate ' + snakesDesiredLength + ' apples first!');
        else
            endSnakesGame('You win! Opponent made invalid move.');
        return false;
    }
    return true;
}

function runSnakesGame() {
    // User's move
    if (!aiTurn) {
        if (!userMove()) {
            return false;
        }
    }
    // AI move
    else {
        if (!aiMove()) {
            return false;
        }
    }
    // Continue self-update loop
    aiTurn = !aiTurn;
    snakesGameLoop = setTimeout(function() {
        runSnakesGame();
    }, SNAKES_MOVE_INTERVAL);
}

function snakesReplayCycle(idx, log) {
    if (snakesReplayRunning || idx == 0) {
        if (log[idx] == '(') {
            idx++;
            var appleCoords = '';
            while (log[idx] != ')')
                appleCoords += log[idx++];
            idx++;
            var appleRow = parseInt(appleCoords.split(',')[0]);
            var appleCol = parseInt(appleCoords.split(',')[1]);
            snakesCells[appleRow][appleCol] = '@';
            updateSnakesBoard();
        } else {
            if (log[idx] == 'L') snakesSnakeDir = SNAKES_ARROW_LEFT;
            else if (log[idx] == 'U') snakesSnakeDir = SNAKES_ARROW_UP;
            else if (log[idx] == 'R') snakesSnakeDir = SNAKES_ARROW_RIGHT;
            else if (log[idx] == 'D') snakesSnakeDir = SNAKES_ARROW_DOWN;
            else {
                alert('Error in the game log: found invalid character \'' + log[idx] + '\'!');
                return;
            }
            idx++;

            var message = updateSnakesGame();
            if (message != '') {
                endSnakesGame(message);
                if (idx < log.length) {
                    alert('Game log claims there are more moves?');
                }
                return;
            }
            aiTurn = !aiTurn;
        }
    }
    window.setTimeout(function() {snakesReplayCycle(idx, log);}, SNAKES_MOVE_INTERVAL);
}

function runSnakesReplay(log) {
    SNAKES_MOVE_INTERVAL = 80; // Milliseconds

    // Create the board
    var settings = log.split('|')[0].split(',');

    snakesNumRows = parseInt(settings[0]);
    snakesNumCols = parseInt(settings[1]);
    snakesDesiredLength = parseInt(settings[2]);
    document.getElementById('p1score').innerHTML = 0 + '/' + snakesDesiredLength;
    document.getElementById('p2score').innerHTML = 0 + '/' + snakesDesiredLength;

    snakesCells = []
    for (var row = 0; row < snakesNumRows; row++) {
        snakesCells.push([]);
        for (var col = 0; col < snakesNumCols; col++) {
            snakesCells[snakesCells.length - 1].push('.');
        }
    }

    var playerOneRow = parseInt(settings[3]);
    var playerOneCol = parseInt(settings[4]);
    snakesCells[playerOneRow][playerOneCol] = 'A';

    var playerTwoRow = parseInt(settings[5]);
    var playerTwoCol = parseInt(settings[6]);
    snakesCells[playerTwoRow][playerTwoCol] = 'a';

    updateSnakesBoard();

    log = log.split('|')[1];
    // Start the update process
    snakesReplayCycle(0, log);
}

function createSnakesGame() {
    snakesCells = []
    for (var row = 0; row < snakesNumRows; row++) {
        snakesCells.push([]);
        for (var col = 0; col < snakesNumCols; col++) {
            snakesCells[snakesCells.length - 1].push('.');
        }
    }
    var playerOneRow = Math.floor(Math.random() * snakesNumRows);
    var playerOneCol = Math.floor(Math.random() * snakesNumCols);
    snakesCells[playerOneRow][playerOneCol] = 'A';

    var playerTwoRow = Math.floor(Math.random() * snakesNumRows);
    var playerTwoCol = Math.floor(Math.random() * snakesNumCols);
    while (playerOneRow == playerTwoRow && playerOneCol == playerTwoCol) {
        playerTwoRow = Math.floor(Math.random() * snakesNumRows);
        playerTwoCol = Math.floor(Math.random() * snakesNumCols);
    }
    snakesCells[playerTwoRow][playerTwoCol] = 'a';

    addSnakesApple();
}

function updateSnakesBoard() {
    // Update the DOM
    var boardWrapper = document.getElementById('snakesBoard');
    boardWrapper.removeChild(boardWrapper.firstChild);
    boardWrapper.appendChild(getSnakesBoard());
}

function getSnakesBoard() {
    var board = document.createElement('table');
    board.className = 'snakes-board';
    var tbody = document.createElement('tbody');
    for (var row = 0; row < snakesCells.length; row++) {
        var tr = document.createElement('tr');
        for (var col = 0; col < snakesCells[row].length; col++) {
            var td = document.createElement('td');
            if (snakesCells[row][col] == '@') {
                td.className = 'apple';
            } else if (snakesCells[row][col] >= 'A' && snakesCells[row][col] <= 'Z') {
                var segmentId = snakesCells[row][col].charCodeAt(0) - 'A'.charCodeAt(0);
                td.className = 'player-one';
                td.style = "opacity: " + (100 - segmentId * 3) / 100.0 + ";";
                td.innerText = showLetters ? snakesCells[row][col] : '';
            } else if (snakesCells[row][col] >= 'a' && snakesCells[row][col] <= 'z') {
                var segmentId = snakesCells[row][col].charCodeAt(0) - 'a'.charCodeAt(0);
                td.className = 'player-two';
                td.style = "opacity: " + (100 - segmentId * 3) / 100.0 + ";";
                td.innerText = showLetters ? snakesCells[row][col] : '';
            }
            tr.appendChild(td);
        }
        tbody.appendChild(tr);
    }
    board.appendChild(tbody);
    return board;
}

function getSnakesContent() {
    // Create the board and initial cells
    createSnakesGame();

    // Now create the DOM content
    var content = document.createElement('div');
    content.className = 'snakes-content';

    // Header with the task name
    var header = document.createElement('div');
    header.style.textAlign = 'left';
    header.innerHTML = '<h2><span class="blue">Snakes</span> :: Визуализатор</h2>';
    content.appendChild(header);

    // First player (nickname and score)
    var player1 = document.createElement('div');
    player1.style = 'display: inline-block; width: 20%; vertical-align: middle; font-size: 1.5rem; font-weight: bold;';
    player1.innerHTML += '<div class="blue">' + snakesPlayerOne + '</div>';
    player1.innerHTML += '<div style="font-size: smaller;" id="p1score">0/' + snakesDesiredLength + '</div>';
    content.appendChild(player1);

    // The actual playing board
    var board = document.createElement('div');
    board.style = 'display: inline-block; width: 60%; vertical-align: middle;';
    board.id = 'snakesBoard';
    board.appendChild(getSnakesBoard());
    content.appendChild(board);

    // Second player (nickname and score)
    var player2 = document.createElement('div');
    player2.style = 'display: inline-block; width: 20%; vertical-align: middle; font-size: 1.5rem; font-weight: bold;';
    player2.innerHTML += '<div class="red">' + snakesPlayerTwo + '</div>';
    player2.innerHTML += '<div style="font-size: smaller;" id="p2score">0/' + snakesDesiredLength + '</div>';
    content.appendChild(player2);

    var instructions = document.createElement('div');
    instructions.style.textAlign = 'center';
    instructions.style.fontStyle = 'italic';
    instructions.innerHTML = 'Натиснете шпация или кликнете на дъската за да пуснете или паузирате играта.';
    content.appendChild(instructions);

    return content.outerHTML;
}

function showSnakesReplay(playerOne, playerTwo, log) {
    snakesPlayerOne = playerOne;
    snakesPlayerTwo = playerTwo;

    // Create and show the initial board
    var content = getSnakesContent();

    // Make pressing escape return back to the game
    var gameUrl = window.location.href.substr(0, window.location.href.lastIndexOf('/replays'));
    showActionForm(content, gameUrl);

    // Add action event listeners
    document.addEventListener('keydown', identifySnakeReplayEvent, false);
    document.getElementById('snakesBoard').addEventListener('mousedown', function() {
        snakesReplayRunning = !snakesReplayRunning;
    }, true);

    // Run the actual replay
    runSnakesReplay(log);
}

function showSnakesVisualizer(username) {
    snakesPlayerOne = username;
    snakesPlayerTwo = 'AI';

    // Create and show the initial board
    var content = getSnakesContent();

    // Add action event listeners
    document.addEventListener('keydown', identifySnakeArrowEvent, false);

    // Make pressing escape return back to the game
    var gameUrl = window.location.href.substr(0, window.location.href.lastIndexOf('/'));
    showActionForm(content, gameUrl);
}