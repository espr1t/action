TETRIS_ENTER = 13;
TETRIS_SPACE = 32;
TETRIS_ARROW_LEFT = 37;
TETRIS_ARROW_UP = 38;
TETRIS_ARROW_RIGHT = 39;
TETRIS_ARROW_DOWN = 40;
TETRIS_MOVE_INTERVAL = 33; // Milliseconds

TETRIS_NOTHING = 0;
TETRIS_FALLING = 1;
TETRIS_REMOVED = 2;

tetrisWell = [];
tetrisPieces = [
    [['1', '.', '.', '.'], ['1', '.', '.', '.'], ['1', '.', '.', '.'], ['1', '.', '.', '.']],
    [['2', '2', '.', '.'], ['2', '.', '.', '.'], ['2', '.', '.', '.'], ['.', '.', '.', '.']],
    [['3', '3', '.', '.'], ['.', '3', '.', '.'], ['.', '3', '.', '.'], ['.', '.', '.', '.']],
    [['4', '4', '.', '.'], ['4', '4', '.', '.'], ['.', '.', '.', '.'], ['.', '.', '.', '.']],
    [['5', '.', '.', '.'], ['5', '5', '.', '.'], ['.', '5', '.', '.'], ['.', '.', '.', '.']],
    [['6', '.', '.', '.'], ['6', '6', '.', '.'], ['6', '.', '.', '.'], ['.', '.', '.', '.']],
    [['.', '7', '.', '.'], ['7', '7', '.', '.'], ['7', '.', '.', '.'], ['.', '.', '.', '.']]
];

tetrisCurPiece = -1;
tetrisCurPieceCol = -1;
tetrisCurPieceRot = -1;
tetrisNextPiece = -1;

tetrisPlayerName = "Player Name";
tetrisDropLoop = null;
tetrisReplayRunning = false;

function isNumeric(token) {
    return !isNaN(token);
}

function tetrisGetRandomPiece() {
    return Math.floor(Math.random() * 7);
}

function tetrisGetPieceDimensions(piece) {
    var minRow = 1e10, maxRow = -1e10;
    var minCol = 1e10, maxCol = -1e10;
    for (var row = 0; row < piece.length; row++) {
        for (var col = 0; col < piece[row].length; col++) {
            if (piece[row][col] != '.') {
                minRow = Math.min(minRow, row);
                maxRow = Math.max(maxRow, row);
                minCol = Math.min(minCol, col);
                maxCol = Math.max(maxCol, col);
            }
        }
    }
    return [minRow, maxRow, minCol, maxCol];
}

function tetrisRotatePiece(piece) {
    rotated = [['.', '.', '.', '.'], ['.', '.', '.', '.'], ['.', '.', '.', '.'], ['.', '.', '.', '.']];
    for (var row = 0; row < 4; row++)
        for (var col = 0; col < 4; col++)
            rotated[col][3 - row] = piece[row][col];
    return rotated;
}

function tetrisUpdateTop() {
    // Clear the top
    for (var row = 0; row < 4; row++)
        for (var col = 0; col < tetrisWell[row].length; col++)
            tetrisWell[row][col] = '.';

    // Get the rotated view of the piece
    piece = tetrisPieces[tetrisCurPiece];
    for (var i = 0; i < tetrisCurPieceRot; i++)
        piece = tetrisRotatePiece(piece);

    // Find the first non-empty column in the rotation
    var dimensions = tetrisGetPieceDimensions(piece);

    // Move the piece slightly to the left if necessary
    tetrisCurPieceCol = Math.min(tetrisCurPieceCol,
        tetrisWell[0].length - 2 - (dimensions[3] - dimensions[2] + 1));

    // Put the piece on top of the well
    var offRow = 4 - (dimensions[1] - dimensions[0] + 1);
    for (var row = dimensions[0]; row <= dimensions[1]; row++)
        for (var col = dimensions[2]; col <= dimensions[3]; col++)
            tetrisWell[row - dimensions[0] + offRow][tetrisCurPieceCol + col + 1 - dimensions[2]] = piece[row][col];
}

function tetrisMove(key) {
    if (key == TETRIS_ARROW_LEFT)
        tetrisCurPieceCol = Math.max(tetrisCurPieceCol - 1, 0);
    else if (key == TETRIS_ARROW_RIGHT)
        tetrisCurPieceCol = Math.min(tetrisCurPieceCol + 1, tetrisWell[0].length - 2);
    tetrisUpdateTop();
    tetrisUpdateDOM();
}

function tetrisRotate(key) {
    if (key == TETRIS_ARROW_UP)
        tetrisCurPieceRot = (tetrisCurPieceRot + 1) % 4;
    else if (key == TETRIS_ARROW_DOWN)
        tetrisCurPieceRot = (tetrisCurPieceRot + 3) % 4;
    tetrisUpdateTop();
    tetrisUpdateDOM();
}

tetrisComponents = null;
tetrisNeighbours = [ [-1, 0], [0, 1], [1, 0], [0, -1] ];

function tetrisGetComponent(row, col, visited, component) {
    visited[row][col] = true;
    component.push([row, col]);
    for (var i = 0; i < tetrisNeighbours.length; i++) {
        var nrow = row + tetrisNeighbours[i][0];
        var ncol = col + tetrisNeighbours[i][1];
        if (nrow < 0 || nrow >= tetrisWell.length) continue;
        if (ncol < 0 || ncol >= tetrisWell[0].length) continue;
        if (!visited[nrow][ncol] && isNumeric(tetrisWell[nrow][ncol]))
            tetrisGetComponent(nrow, ncol, visited, component);
    }
}

function tetrisUpdateBoard() {
    if (tetrisComponents == null) {
        var visited = new Array(tetrisWell.length);
        for (var row = 0; row < tetrisWell.length; row++) {
            visited[row] = new Array(tetrisWell[row].length);
            for (var col = 0; col < tetrisWell[row].length; col++)
                visited[row][col] = false;
        }

        tetrisComponents = [];
        for (var row = 0; row < tetrisWell.length; row++) {
            for (var col = 0; col < tetrisWell[row].length; col++) {
                if (!visited[row][col] && isNumeric(tetrisWell[row][col])) {
                    var component = [];
                    tetrisGetComponent(row, col, visited, component);
                    component.sort(function(l, r) {return r[0] - l[0];});
                    tetrisComponents.push(component);
                }
            }
        }
    }

    fallingComponents = [];
    for (var i = 0; i < tetrisComponents.length; i++) {
        var component = tetrisComponents[i];
        var isFalling = true;
        for (var c = 0; c < component.length; c++) {
            if (tetrisWell[component[c][0] + 1][component[c][1]] != '.') {
                // Hitting the bottom or another component, EXCEPT if hitting
                // another cell of the same component
                var sameComponent = false;
                for (var j = 0; j < component.length; j++) {
                    if (component[j][0] == component[c][0] + 1 && component[j][1] == component[c][1]) {
                        sameComponent = true;
                        break;
                    }
                }
                if (!sameComponent) {
                    isFalling = false;
                    break;
                }
            }
        }
        if (isFalling) {
            for (var c = 0; c < component.length; c++) {
                tetrisWell[component[c][0] + 1][component[c][1]] = tetrisWell[component[c][0]][component[c][1]];
                tetrisWell[component[c][0]][component[c][1]] = '.';
                component[c][0]++;
            }
            fallingComponents.push(component);
        }
    }

    if (fallingComponents.length > 0) {
        tetrisComponents = fallingComponents;
        return TETRIS_FALLING;
    }
    tetrisComponents = null;

    // Maybe a filled line?
    var removedRow = false;
    for (var row = tetrisWell.length - 1; row >= 0; row--) {
        var countFilled = 0;
        for (var col = 0; col < tetrisWell.length; col++)
            if (isNumeric(tetrisWell[row][col])) countFilled++;
        if (countFilled == 10) {
            for (var col = 1; col < tetrisWell[row].length - 1; col++)
                tetrisWell[row][col] = '.';
            removedRow = true;
        }
    }
    return removedRow ? TETRIS_REMOVED : TETRIS_NOTHING;
}

function tetrisDropBlocks() {
    var status = tetrisUpdateBoard();

    if (status != TETRIS_NOTHING) {
        // If an animation is ongoing, update the DOM and schedule
        // the next frame after a short delay
        tetrisDropLoop = setTimeout(function() {
            tetrisDropBlocks();
        }, TETRIS_MOVE_INTERVAL * (status == TETRIS_REMOVED ? 10 : 1));
    } else {
        // First, clear the drop loop
        clearTimeout(tetrisDropLoop);
        tetrisDropLoop = null;

        // If something is sticking out of the top, the game has been lost.
        var gameLost = false;
        for (var row = 0; row < 4; row++) {
            for (var col = 0; col < tetrisWell[row].length; col++) {
                if (tetrisWell[row][col] != '.') {
                    gameLost = true;
                }
            }
        }
        if (gameLost) {
            tetrisEndGame('Game over!');
        } else {
            // If there are no more actions and we haven't lost the game
            // we can continue with the next piece
            tetrisCurPiece = tetrisNextPiece;
            tetrisCurPieceCol = 4;
            tetrisCurPieceRot = 0;
            tetrisNextPiece = tetrisGetRandomPiece();
            tetrisUpdateTop();
        }
    }
    tetrisUpdateDOM();
}

function tetrisIdentifyActionEvent(event) {
    // Only make actions available after all blocks have cleared
    if (tetrisDropLoop != null)
        return;

    if (event.keyCode >= TETRIS_ARROW_LEFT && event.keyCode <= TETRIS_ARROW_DOWN) {
        event.preventDefault();
        event.stopPropagation();
        if (event.keyCode == TETRIS_ARROW_LEFT || event.keyCode == TETRIS_ARROW_RIGHT) {
            tetrisMove(event.keyCode);
        } else {
            tetrisRotate(event.keyCode);
        }
    } else if (event.keyCode == TETRIS_ENTER) {
        var score = parseInt(document.getElementById('playerScore').innerHTML);
        document.getElementById('playerScore').innerHTML = score + 1;
        tetrisDropBlocks();
    }
}

function tetrisIdentifyReplayEvent(event) {
    if (event.keyCode == TETRIS_SPACE) {
        event.preventDefault();
        event.stopPropagation();
        tetrisReplayRunning = !tetrisReplayRunning;
    }
}

function tetrisEndGame(message) {
    document.removeEventListener('keydown', tetrisIdentifyActionEvent, false);
    showMessage('ERROR', message);
}

function tetrisUserMove() {
    // If the returned string is non-empty, the game has ended (either with a win, or with a loss)
    var message = updateTetrisGame();
    if (message != '') {
        tetrisEndGame(message);
        return false;
    }
    return true;
}

/*
function tetrisRunReplay(log) {
    TETRIS_MOVE_INTERVAL = 80; // Milliseconds

    tetrisNumApples = 25;

    // Create the board
    var settings = log.split('|')[0].split(',');

    document.getElementById('p1score').innerHTML = 1;
    document.getElementById('p2score').innerHTML = 1;

    tetrisWell = [];
    for (var row = 0; row < tetrisNumRows; row++) {
        tetrisWell.push([]);
        for (var col = 0; col < tetrisNumCols; col++) {
            tetrisWell[tetrisWell.length - 1].push('.');
        }
    }

    var playerOneRow = parseInt(settings[0]);
    var playerOneCol = parseInt(settings[1]);
    tetrisWell[playerOneRow][playerOneCol] = 'A';

    var playerTwoRow = parseInt(settings[2]);
    var playerTwoCol = parseInt(settings[3]);
    tetrisWell[playerTwoRow][playerTwoCol] = 'a';

    for (var app = 0; app < tetrisNumApples; app++) {
        var appleRow = parseInt(settings[app * 2 + 4]);
        var appleCol = parseInt(settings[app * 2 + 5]);
        tetrisWell[appleRow][appleCol] = '@';
    }

    tetrisUpdateDOM();

    log = log.split('|')[1];
    // Start the update process
    tetrisReplayCycle(0, log);
}
*/

function tetrisCreateGame() {
    /*
    tetrisWell = [
        ['#', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '#'],
        ['#', '.', '.', '.', '6', '.', '.', '.', '.', '.', '.', '#'],
        ['#', '.', '.', '.', '6', '6', '.', '.', '.', '.', '.', '#'],
        ['#', '.', '.', '.', '6', '.', '.', '.', '.', '.', '.', '#'],
        ['#', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '#'],
        ['#', '4', '4', '1', '.', '.', '.', '.', '.', '.', '.', '#'],
        ['#', '4', '4', '1', '.', '.', '5', '.', '.', '.', '1', '#'],
        ['#', '6', '.', '1', '.', '.', '5', '5', '.', '.', '1', '#'],
        ['#', '6', '6', '1', '.', '.', '1', '5', '4', '4', '1', '#'],
        ['#', '6', '7', '2', '.', '7', '1', '6', '4', '4', '1', '#'],
        ['#', '7', '7', '2', '7', '7', '1', '6', '6', '.', '3', '#'],
        ['#', '7', '2', '2', '7', '.', '1', '6', '3', '3', '3', '#'],
        ['#', '#', '#', '#', '#', '#', '#', '#', '#', '#', '#', '#']
    ];
    */
    tetrisWell = [
        ['.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.'],
        ['.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.'],
        ['.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.'],
        ['.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.'],
        ['#', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '#'],
        ['#', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '#'],
        ['#', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '#'],
        ['#', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '#'],
        ['#', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '#'],
        ['#', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '#'],
        ['#', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '#'],
        ['#', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '#'],
        ['#', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '#'],
        ['#', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '#'],
        ['#', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '#'],
        ['#', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '#'],
        ['#', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '#'],
        ['#', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '#'],
        ['#', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '#'],
        ['#', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '#'],
        ['#', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '#'],
        ['#', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '#'],
        ['#', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '#'],
        ['#', '.', '.', '.', '.', '.', '.', '.', '.', '.', '.', '#'],
        ['#', '#', '#', '#', '#', '#', '#', '#', '#', '#', '#', '#']
    ];
    tetrisCurPiece = tetrisGetRandomPiece();
    tetrisCurPieceCol = 4;
    tetrisCurPieceRot = 0;
    tetrisNextPiece = tetrisGetRandomPiece();
    tetrisUpdateTop();
}

function tetrisResetGame() {
    tetrisDropLoop = null;
    tetrisReplayRunning = false;

    tetrisCreateGame();
    tetrisUpdateDOM();

    document.getElementById('playerScore').innerText = '0';
    // Add action event listeners
    document.addEventListener('keydown', tetrisIdentifyActionEvent, false);
}

function tetrisGetBoardDOM() {
    var piece = tetrisPieces[tetrisCurPiece];
    for (var i = 0; i < tetrisCurPieceRot; i++)
        piece = tetrisRotatePiece(piece);
    var dimensions = tetrisGetPieceDimensions(piece);
    var pieceWidth = dimensions[3] - dimensions[2] + 1;

    var boardDOM = document.createElement('table');
    boardDOM.className = 'tetris-board';
    var tbody = document.createElement('tbody');
    for (var row = 0; row < tetrisWell.length; row++) {
        var tr = document.createElement('tr');
        for (var col = 0; col < tetrisWell[row].length; col++) {
            var td = document.createElement('td');
            if (tetrisWell[row][col] == '#') {
                td.className = 'border';
            } else if (tetrisWell[row][col] >= '1' && tetrisWell[row][col] <= '7') {
                td.className = 'piece' + tetrisWell[row][col];
            } else {
                if (col > tetrisCurPieceCol && col <= tetrisCurPieceCol + pieceWidth) {
                    var numSegments = 0;
                    for (var r = row - 1; r >= 0; r--) {
                        if (tetrisWell[r][col] != '.' && tetrisWell[r + 1][col] == '.')
                            numSegments++;
                    }
                    if (numSegments == 1)
                        td.className = 'piece' + (tetrisCurPiece + 1) + '-shadow';
                }
            }
            if (row < 4 && !isNumeric(tetrisWell[row][col])) {
                td.style.opacity = 0.0;
            }

            tr.appendChild(td);
        }
        tbody.appendChild(tr);
    }
    boardDOM.appendChild(tbody);
    return boardDOM;
}

function tetrisGetNextPieceDOM() {
    var pieceDOM = document.createElement('table');
    pieceDOM.className = 'tetris-board';
    var tbody = document.createElement('tbody');

    var piece = tetrisPieces[tetrisNextPiece];
    var dimensions = tetrisGetPieceDimensions(piece);

    for (var row = dimensions[0]; row <= dimensions[1]; row++) {
        var tr = document.createElement('tr');
        for (var col = dimensions[2]; col <= dimensions[3]; col++) {
            var td = document.createElement('td');
            if (piece[row][col] != '.') {
                td.className = 'piece' + piece[row][col];
            } else {
                td.style.opacity = 0.0;
            }
            tr.appendChild(td);
        }
        tbody.appendChild(tr);
    }
    pieceDOM.appendChild(tbody);
    return pieceDOM;
}

function tetrisUpdateDOM() {
    // Update the DOM with the current board
    var boardWrapper = document.getElementById('tetrisBoard');
    boardWrapper.removeChild(boardWrapper.firstChild);
    boardWrapper.appendChild(tetrisGetBoardDOM());

    var nextPieceWrapper = document.getElementById('tetrisNextPiece');
    nextPieceWrapper.removeChild(nextPieceWrapper.lastChild);
    nextPieceWrapper.appendChild(tetrisGetNextPieceDOM());
}

function tetrisGetContent() {
    // Create the board and initial cells
    tetrisCreateGame();

    // Now create the DOM content
    var content = document.createElement('div');
    content.className = 'tetris-content';

    // Header with the task name
    var header = document.createElement('div');
    header.style.textAlign = 'left';
    header.innerHTML = '<h2><span class="blue">Tetris</span> :: Визуализатор</h2>';
    content.appendChild(header);

    // First player (nickname and score)
    var playerScore = document.createElement('div');
    playerScore.style = 'display: inline-block; width: 20%; vertical-align: middle; font-size: 1.5rem; font-weight: bold;';
    playerScore.innerHTML += '<div class="blue">' + tetrisPlayerName + '</div>';
    playerScore.innerHTML += '<div style="font-size: smaller;" id="playerScore">0</div>';
    content.appendChild(playerScore);

    // The actual playing board
    var board = document.createElement('div');
    board.style = 'display: inline-block; width: 60%; vertical-align: middle;';
    board.id = 'tetrisBoard';
    board.appendChild(tetrisGetBoardDOM());
    content.appendChild(board);

    // Next Piece (visualization)
    var nextPiece = document.createElement('div');
    nextPiece.id = 'tetrisNextPiece';
    nextPiece.style = 'display: inline-block; width: 20%; vertical-align: middle; font-size: 1.5rem; font-weight: bold;';
    nextPiece.innerHTML += '<div class="red">Next Piece</div>';
    nextPiece.appendChild(tetrisGetNextPieceDOM());
    content.appendChild(nextPiece);

    var instructions = document.createElement('div');
    instructions.id = 'instructions';
    instructions.style.textAlign = 'center';
    instructions.style.fontStyle = 'italic';
    instructions.style.marginTop = '0.5rem';
    instructions.innerText = 'Парчетата се местят и ротират със стрелките, и се пускат с enter.';
    content.appendChild(instructions);

    return content;
}

/**
 * Public functions
 */

function showTetrisReplay(playerName, log) {
    tetrisPlayerName = playerName;

    // Create and show the initial board
    var content = tetrisGetContent(false);

    var instructions = document.createElement('div');
    instructions.id = 'instructions';
    instructions.style.textAlign = 'center';
    instructions.style.fontStyle = 'italic';
    instructions.innerText = 'Натиснете шпация или кликнете на дъската за да пуснете или паузирате играта.';
    content.appendChild(instructions);

    // Make pressing escape return back to the game
    var gameUrl = window.location.href.substr(0, window.location.href.lastIndexOf('/replays'));
    showActionForm(content.outerHTML, gameUrl);

    // Add action event listeners
    document.addEventListener('keydown', tetrisIdentifyReplayEvent, false);
    document.getElementById('tetrisBoard').addEventListener('mousedown', function() {
        tetrisReplayRunning = !tetrisReplayRunning;
    }, true);

    // Run the actual replay
    tetrisRunReplay(log);
}

function showTetrisVisualizer(username) {
    tetrisPlayerName = username;

    // Create and show the initial board
    var content = tetrisGetContent();

    // Display the visualizer in an action form
    var gameUrl = window.location.href.substr(0, window.location.href.lastIndexOf('/'));
    showActionForm(content.outerHTML, gameUrl);

    // Init the board and pieces and display them
    tetrisResetGame();
}
