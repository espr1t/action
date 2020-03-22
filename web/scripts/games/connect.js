CONNECT_SPACE = 32;
CONNECT_MOVE_INTERVAL = 500; // Milliseconds

CONNECT_PLAYER_ONE = 1;
CONNECT_PLAYER_TWO = 2;

connectBoard = [];
connectImportantCells = [];
connectCurrentPlayer = CONNECT_PLAYER_ONE;

connectPlayerName = "Player Name";
connectOpponentName = "Opponent Name";
connectAddListeners = true;
connectReplayRunning = false;


function randInt(mod) {
    return Math.floor(Math.random() * mod);
}

function connectIdentifyReplayEvent(event) {
    if (event.keyCode == CONNECT_SPACE) {
        event.preventDefault();
        event.stopPropagation();
        connectReplayRunning = !connectReplayRunning;
    }
}

function connectBFS(srow, scol, color) {
    var connectNeighbors = [ [-1, 0], [0, 1], [1, 1], [1, 0], [0, -1], [-1, -1] ];

    var idx = 0, needed = 0;
    var q1 = connectBoard[srow][scol] == color ? [[srow, scol]] : [];
    var q2 = connectBoard[srow][scol] == color ? [] : [[srow, scol]];
    var previous = {};
    previous[[srow, scol]] = [-1, -1];

    while (idx < q1.length || q2.length > 0) {
        if (idx >= q1.length) {
            q1 = q2;
            q2 = [];
            idx = 0;
            needed++;
            continue;
        }

        var cell = q1[idx++];

        var hasWon = false;
        if (color == CONNECT_PLAYER_ONE && cell[1] == connectBoard.length - 1) hasWon = true;
        if (color == CONNECT_PLAYER_TWO && cell[0] == connectBoard.length - 1) hasWon = true;
        if (hasWon) {
            var path = [];
            while (cell[0] != -1 && cell[1] != -1) {
                path.push(cell);
                cell = previous[cell];
            }
            return [path.reverse(), needed];
        }

        for (var i = 0; i < connectNeighbors.length; i++) {
            var nrow = cell[0] + connectNeighbors[i][0]; if (nrow < 0 || nrow >= connectBoard.length) continue;
            var ncol = cell[1] + connectNeighbors[i][1]; if (ncol < 0 || ncol >= connectBoard.length) continue;
            if (connectBoard[nrow][ncol] != color && connectBoard[nrow][ncol] != 0) continue;

            var ncell = [nrow, ncol];
            if (!(ncell in previous)) {
                previous[ncell] = cell;
                if (connectBoard[nrow][ncol] == color) {
                    q1.push(ncell);
                } else {
                    q2.push(ncell);
                }
            }
        }
    }
    return [[], -1];
}

function connectShortestPath(playerColor) {
    var bestResult = [[], -1];
    for (var dim = 0; dim < connectBoard.length; dim++) {
        var row = playerColor == CONNECT_PLAYER_ONE ? dim : 0;
        var col = playerColor == CONNECT_PLAYER_TWO ? dim : 0;
        if (connectBoard[row][col] == playerColor || connectBoard[row][col] == 0) {
            var bfsResult = connectBFS(row, col, playerColor);
            if (bfsResult[1] != -1 && (bestResult[1] == -1 || bestResult[1] > bfsResult[1]))
                bestResult = bfsResult;
        }
    }
    return bestResult;
}

function connectAiMove() {
    var aiColor = connectPlayerName == 'AI' ? CONNECT_PLAYER_ONE : CONNECT_PLAYER_TWO;
    var opColor = connectPlayerName != 'AI' ? CONNECT_PLAYER_ONE : CONNECT_PLAYER_TWO;

    // If there is a winning move
    var aiShortestResult = connectShortestPath(aiColor);
    var aiShortest = aiShortestResult[0], aiNeeded = aiShortestResult[1];
    if (aiNeeded == 1) {
        for (var i = 0; i < aiShortest.length; i++) {
            if (connectBoard[aiShortest[i][0]][aiShortest[i][1]] == 0) {
                connectMakeMove(aiShortest[i][0], aiShortest[i][1]);
                return;
            }
        }
    }

    // Next-turn win block
    var opShortestResult = connectShortestPath(opColor);
    var opShortest = opShortestResult[0], opNeeded = opShortestResult[1];
    if (opNeeded == 1) {
        for (var i = 0; i < opShortest.length; i++) {
            if (connectBoard[opShortest[i][0]][opShortest[i][1]] == 0) {
                connectMakeMove(opShortest[i][0], opShortest[i][1]);
                return;
            }
        }
    }

    // This is the first move in the game, place it anywhere
    if (aiNeeded == connectBoard.length && opNeeded == connectBoard.length) {
        connectMakeMove(randInt(connectBoard.length), randInt(connectBoard.length));
        return;
    }


    // Try placing in a cell of our shortest path, that is also
    // kinda blocking the opponent's path
    for (var i = 0; i < aiShortest.length; i++) {
        if (connectBoard[aiShortest[i][0]][aiShortest[i][1]] == 0) {
            if (aiColor == CONNECT_PLAYER_ONE) {
                // Try to the top
                var row = aiShortest[i][0];
                while (row >= 0 && connectBoard[row][aiShortest[i][1]] == 0)
                    row--;
                if (row >= 0 && connectBoard[row][aiShortest[i][1]] != aiColor) {
                    connectMakeMove(aiShortest[i][0], aiShortest[i][1]);
                    return;
                } else {
                    // Then try to the bottom
                    row = aiShortest[i][0];
                    while (row < connectBoard.length && connectBoard[row][aiShortest[i][1]] == 0)
                        row++;
                    if (row < connectBoard.length && connectBoard[row][aiShortest[i][1]] != aiColor) {
                        connectMakeMove(aiShortest[i][0], aiShortest[i][1]);
                        return;
                    }
                }
            } else {
                // Try to the left
                var col = aiShortest[i][1];
                while (col >= 0 && connectBoard[aiShortest[i][0]][col] == 0)
                    col--;
                if (col >= 0 && connectBoard[aiShortest[i][0]][col] != aiColor) {
                    connectMakeMove(aiShortest[i][0], aiShortest[i][1]);
                    return;
                } else {
                    // Then try to the right (a little bit of Monica in my life...)
                    col = aiShortest[i][1];
                    while (col < connectBoard.length && connectBoard[aiShortest[i][0]][col] == 0)
                        col++;
                    if (col < connectBoard.length && connectBoard[aiShortest[i][0]][col] != aiColor) {
                        connectMakeMove(aiShortest[i][0], aiShortest[i][1]);
                        return;
                    }
                }
            }
        }
    }

    // Just choose a random empty cell on our path
    var cell = [-1, -1], cnt = 0;
    for (var i = 0; i < aiShortest.length; i++) {
        if (connectBoard[aiShortest[i][0]][aiShortest[i][1]] == 0) {
            cell = randInt(++cnt) == 0 ? aiShortest[i] : cell;
        }
    }
    connectMakeMove(cell[0], cell[1]);
    return;
}

function connectGetWinner() {
    // Check if player one has won
    var shortestPathResult1 = connectShortestPath(CONNECT_PLAYER_ONE);
    var shortestPath1 = shortestPathResult1[0], shortestPathNeeded1 = shortestPathResult1[1];
    if (shortestPathNeeded1 == 0) {
        for (var row = 0; row < connectImportantCells.length; row++)
            connectImportantCells[row] = Array(connectBoard.length).fill(false);
        for (var i = 0; i < shortestPath1.length; i++)
            connectImportantCells[shortestPath1[i][0]][shortestPath1[i][1]] = true;
        return CONNECT_PLAYER_ONE;
    }

    // Check if player two has won
    var shortestPathResult2 = connectShortestPath(CONNECT_PLAYER_TWO);
    var shortestPath2 = shortestPathResult2[0], shortestPathNeeded2 = shortestPathResult2[1];
    if (shortestPathNeeded2 == 0) {
        for (var row = 0; row < connectImportantCells.length; row++)
            connectImportantCells[row] = Array(connectBoard.length).fill(false);
        for (var i = 0; i < shortestPath2.length; i++)
            connectImportantCells[shortestPath2[i][0]][shortestPath2[i][1]] = true;
        return CONNECT_PLAYER_TWO;
    }

    // Check if none of the players can win anymore (a draw)
    if (shortestPathNeeded1 == -1 && shortestPathNeeded2 == -1)
        return -1;

    // Otherwise continue playing
    return 0;
}

function connectMakeMove(row, col) {
    var invalidMove = false;
    if (!invalidMove && (row < 0 || row >= connectBoard.length)) invalidMove = true;
    if (!invalidMove && (col < 0 || col >= connectBoard[0].length)) invalidMove = true;
    if (!invalidMove && (connectBoard[row][col] != 0)) invalidMove = true;
    if (invalidMove) {
        var message = '';
        if (connectCurrentPlayer == CONNECT_PLAYER_ONE)
            message = 'Player ' + connectPlayerName + ' attempted to do an invalid move!';
        else
            message = 'Player ' + connectOpponentName + ' attempted to do an invalid move!';
        showNotification('ERROR', message);
        return;
    }

    connectBoard[row][col] = connectCurrentPlayer;
    connectCurrentPlayer = ((connectCurrentPlayer - 1) ^ 1) + 1;
    connectUpdateBoard();

    var winner = connectGetWinner();
    if (winner != 0) {
        var message = '';
        connectAddListeners = false;
        if (winner == -1) message = 'The game has ended in a draw.';
        else if (winner == CONNECT_PLAYER_ONE) message = 'Player ' + connectPlayerName + ' has won!';
        else if (winner == CONNECT_PLAYER_TWO) message = 'Player ' + connectOpponentName + ' has won!';
        showNotification('INFO', message);
        connectUpdateBoard();
        return;
    } else {
        if (connectCurrentPlayer == CONNECT_PLAYER_ONE && connectPlayerName == 'AI') connectAiMove();
        if (connectCurrentPlayer == CONNECT_PLAYER_TWO && connectOpponentName == 'AI') connectAiMove();
    }
}

function connectGetColor(row, col) {
    if ((row < 0 || row >= connectBoard.length) && (col < 0 || col >= connectBoard.length))
        return 0;
    if (col < 0 || col >= connectBoard.length) return CONNECT_PLAYER_ONE;
    if (row < 0 || row >= connectBoard.length) return CONNECT_PLAYER_TWO;
    return connectBoard[row][col];
}

function connectIsImportant(row, col) {
    if (row < 0 || row >= connectBoard.length || col < 0 || col >= connectBoard.length)
        return false;
    return connectImportantCells[row][col];
}

function connectGetBoardDOM() {
    var nonImportantOpacity = 0.66;
    for (var row = 0; row < connectImportantCells.length; row++)
        for (var col = 0; col < connectImportantCells[row].length; col++)
            if (!connectImportantCells[row][col]) nonImportantOpacity = 0.33;

    var boardDOM = document.createElement('table');
    boardDOM.className = 'connect-board';
    var tbody = document.createElement('tbody');

    var size = connectBoard.length * 2 - 1 + 4;
    for (var row = 0; row < size; row++) {
        var tr = document.createElement('tr');
        for (var col = 0; col < size; col++) {
            var td = document.createElement('td');
            if ((row <= 1 || row >= size - 2) && (col <= 1 || col >= size - 2)) {
                td.style.opacity = 0;
            } else {
                if (row <= 1 || row >= size - 2 || col <= 1 || col >= size - 2) {
                    td.style.opacity = nonImportantOpacity;
                }

                if (row % 2 == 1) {
                    if (col % 2 == 0) {
                        var edge = document.createElement('div');
                        edge.className = 'connect-vertical-edge';
                        var idxRow = Math.floor((row - 3) / 2), idxCol = Math.floor((col - 2) / 2);
                        var upperColor = connectGetColor(idxRow, idxCol);
                        var lowerColor = connectGetColor(idxRow + 1, idxCol);
                        if (!connectIsImportant(idxRow, idxCol) || !connectIsImportant(idxRow + 1, idxCol))
                            edge.style.opacity = nonImportantOpacity;
                        if (upperColor == CONNECT_PLAYER_ONE && lowerColor == CONNECT_PLAYER_ONE)
                            edge.className += ' connect-edge-color-player1';
                        if (upperColor == CONNECT_PLAYER_TWO && lowerColor == CONNECT_PLAYER_TWO)
                            edge.className += ' connect-edge-color-player2';
                        td.appendChild(edge);
                    } else {
                        var edge = document.createElement('div');
                        edge.className = 'connect-diagonal-edge';
                        var idxRow = Math.floor((row - 3) / 2), idxCol = Math.floor((col - 3) / 2);
                        var upperLeftColor = connectGetColor(idxRow, idxCol);
                        var lowerRghtColor = connectGetColor(idxRow + 1, idxCol + 1);
                        if (!connectIsImportant(idxRow, idxCol) || !connectIsImportant(idxRow + 1, idxCol + 1))
                            edge.style.opacity = nonImportantOpacity;
                        if (upperLeftColor == CONNECT_PLAYER_ONE && lowerRghtColor == CONNECT_PLAYER_ONE)
                            edge.className += ' connect-edge-color-player1';
                        if (upperLeftColor == CONNECT_PLAYER_TWO && lowerRghtColor == CONNECT_PLAYER_TWO)
                            edge.className += ' connect-edge-color-player2';
                        td.appendChild(edge);
                    }
                } else {
                    if (col % 2 == 1) {
                        var edge = document.createElement('div');
                        edge.className = 'connect-horizontal-edge';
                        var idxRow = Math.floor((row - 2) / 2), idxCol = Math.floor((col - 3) / 2);
                        var leftColor = connectGetColor(idxRow, idxCol);
                        var rghtColor = connectGetColor(idxRow, idxCol + 1);
                        if (!connectIsImportant(idxRow, idxCol) || !connectIsImportant(idxRow, idxCol + 1))
                            edge.style.opacity = nonImportantOpacity;
                        if (leftColor == CONNECT_PLAYER_ONE && rghtColor == CONNECT_PLAYER_ONE)
                            edge.className += ' connect-edge-color-player1';
                        if (leftColor == CONNECT_PLAYER_TWO && rghtColor == CONNECT_PLAYER_TWO)
                            edge.className += ' connect-edge-color-player2';

                        td.appendChild(edge);
                    } else {
                        td.className = 'connect-cell';
                        if (row == 0 || row == size - 1) td.className += ' player2';
                        if (col == 0 || col == size - 1) td.className += ' player1';

                        if (row > 0 && row < size - 1 && col > 0 && col < size - 1) {
                            // Normal board cell
                            var idxRow = Math.floor((row - 2) / 2), idxCol = Math.floor((col - 2) / 2);
                            td.id = 'cell_' + idxRow + '_' + idxCol;
                            if (!connectIsImportant(idxRow, idxCol))
                                td.style.opacity = nonImportantOpacity;

                            if (connectBoard[idxRow][idxCol] != 0) {
                                if (connectBoard[idxRow][idxCol] == CONNECT_PLAYER_ONE) td.className += ' player1';
                                if (connectBoard[idxRow][idxCol] == CONNECT_PLAYER_TWO) td.className += ' player2';
                            } else {
                                var humanPlayer = connectPlayerName == 'AI' ? CONNECT_PLAYER_TWO : CONNECT_PLAYER_ONE;
                                if (connectAddListeners && connectCurrentPlayer == humanPlayer) {
                                    td.onclick = function() {
                                        var r = parseInt(this.id.split('_')[1]);
                                        var c = parseInt(this.id.split('_')[2]);
                                        connectMakeMove(r, c);
                                    };
                                }
                            }
                        }
                    }
                }

            }
            tr.appendChild(td);
        }
        tbody.appendChild(tr);
    }

    boardDOM.appendChild(tbody);
    return boardDOM;
}


function connectUpdateBoard() {
    // Update the DOM with the current board
    var boardWrapper = document.getElementById('connectBoard');
    boardWrapper.removeChild(boardWrapper.firstChild);
    boardWrapper.appendChild(connectGetBoardDOM());
}

function connectReplayCycle(idx, moves) {
    if (connectReplayRunning) {
        if (idx + 1 >= moves.length) {
            showNotification('INFO', 'Reached end of game log.');
            var winner = connectGetWinner();
            connectUpdateBoard();
            return;
        } else {
            connectBoard[moves[idx++]][moves[idx++]] = connectCurrentPlayer;
            connectCurrentPlayer = ((connectCurrentPlayer - 1) ^ 1) + 1;
            connectUpdateBoard();
        }
    }
    window.setTimeout(function() {connectReplayCycle(idx, moves);}, CONNECT_MOVE_INTERVAL);
}

function connectRunReplay(log) {
    // Parse the log
    var size = parseInt(log.split('|')[0]);
    var moves = log.split('|')[1].split(',').map(Number);

    // Create the board
    connectCreateGame(size);
    connectUpdateBoard();

    // Start the update process
    connectReplayCycle(0, moves);
}

function connectCreateGame(size) {
    connectBoard = new Array(size);
    for (var row = 0; row < connectBoard.length; row++)
        connectBoard[row] = Array(size).fill(0);

    connectImportantCells = new Array(size);
    for (var row = 0; row < connectImportantCells.length; row++)
        connectImportantCells[row] = Array(size).fill(true);

    connectCurrentPlayer = CONNECT_PLAYER_ONE;
}

function connectResetGame() {
    connectAddListeners = true;
    connectReplayRunning = false;
    connectCurrentPlayer = CONNECT_PLAYER_ONE;

    if (randInt(2) == 0) {
        var saveName = connectPlayerName;
        connectPlayerName = connectOpponentName;
        connectOpponentName = saveName;
    }

    document.getElementById('player1Name').innerText = connectPlayerName;
    document.getElementById('player2Name').innerText = connectOpponentName;

    var randValue = randInt(3);
    var size = randValue == 0 ? 3 : (randValue == 1 ? 7 : 10);

    connectCreateGame(size);
    connectUpdateBoard();

    if (connectPlayerName == 'AI') {
        connectAiMove();
    }
}

function connectGetContent(showInstructions) {
    // Create the board and initial cells
    connectCreateGame(7);

    // Now create the DOM content
    var content = document.createElement('div');
    content.className = 'connect-content';

    // Header with the task name
    var header = document.createElement('div');
    header.style.textAlign = 'left';
    header.innerHTML = '<h2><span class="blue">Connect</span> :: Визуализатор</h2>';
    content.appendChild(header);

    // First player (nickname)
    var firstPlayerEl = document.createElement('div');
    firstPlayerEl.style = 'display: inline-block; width: 20%; vertical-align: middle; font-size: 1.5rem; font-weight: bold;';
    firstPlayerEl.innerHTML += '<div class="blue" id="player1Name">' + connectPlayerName + '</div>';
    content.appendChild(firstPlayerEl);

    // The actual playing board
    var board = document.createElement('div');
    board.style = 'display: inline-block; width: 60%; vertical-align: middle;';
    board.id = 'connectBoard';
    board.appendChild(connectGetBoardDOM());
    content.appendChild(board);

    // Second player (nickname)
    var secondPlayerEl = document.createElement('div');
    secondPlayerEl.style = 'display: inline-block; width: 20%; vertical-align: middle; font-size: 1.5rem; font-weight: bold;';
    secondPlayerEl.innerHTML += '<div class="red" id="player2Name">' + connectOpponentName + '</div>';
    content.appendChild(secondPlayerEl);

    if (showInstructions) {
        var instructions = document.createElement('div');
        instructions.id = 'instructions';
        instructions.style.textAlign = 'center';
        instructions.style.fontStyle = 'italic';
        instructions.style.marginTop = '0.5rem';
        instructions.innerText = 'Натиснете върху клетката, която искате да вземете.';
        content.appendChild(instructions);
    }

    return content;
}

/**
 * Public functions
 */

function showConnectReplay(playerOne, playerTwo, log) {
    connectAddListeners = false;
    connectPlayerName = playerOne;
    connectOpponentName = playerTwo;

    // Create and show the initial board
    var content = connectGetContent(false);

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
    document.addEventListener('keydown', connectIdentifyReplayEvent, false);
    document.getElementById('connectBoard').addEventListener('mousedown', function() {
        connectReplayRunning = !connectReplayRunning;
    }, true);

    // Run the actual replay
    connectRunReplay(log);
}

function showConnectVisualizer(username) {
    connectPlayerName = username;
    connectOpponentName = 'AI';

    // Create and show the initial board
    var content = connectGetContent();

    // Display the visualizer in an action form
    var gameUrl = window.location.href.substr(0, window.location.href.lastIndexOf('/'));
    showActionForm(content.outerHTML, gameUrl);

    // Init the board and pieces and display them
    connectResetGame();
}
