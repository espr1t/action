UTTT_REPLAY_INTERVAL = 1000; // Milliseconds

utttBoard = [];
utttNextPlayer = 'x';
utttNextRow = -1, utttNextCol = -1;

utttDemo = false;
utttAiTurn = false;
utttPlayerOne = '';
utttPlayerTwo = '';
utttReplayRunning = false;

function utttIdentifyReplayEvent(event) {
    if (event.keyCode == 32 /* space */) {
        event.preventDefault();
        event.stopPropagation();
        utttReplayRunning = !utttReplayRunning;
    }
}

function utttEndGame(message) {
    showMessage('INFO', message);
    if (utttDemo) {
        window.setTimeout(function() {location.reload();}, 4000);
    }
}

var utttLines = [
    [ [0, 0], [0, 1], [0, 2] ],
    [ [1, 0], [1, 1], [1, 2] ],
    [ [2, 0], [2, 1], [2, 2] ],
    [ [0, 0], [1, 0], [2, 0] ],
    [ [0, 1], [1, 1], [2, 1] ],
    [ [0, 2], [1, 2], [2, 2] ],
    [ [0, 0], [1, 1], [2, 2] ],
    [ [2, 0], [1, 1], [0, 2] ]
];

function utttBoardWinner(largeRow, largeCol) {
    for (var i = 0; i < 8; i++) {
        var cntX = 0, cntO = 0;
        for (var c = 0; c < 3; c++) {
            if (utttBoard[largeRow * 3 + utttLines[i][c][0]][largeCol * 3 + utttLines[i][c][1]] == 'x') cntX++;
            if (utttBoard[largeRow * 3 + utttLines[i][c][0]][largeCol * 3 + utttLines[i][c][1]] == 'o') cntO++;
        }
        if (cntX == 3) return 'x';
        if (cntO == 3) return 'o';
    }
    // If there is at least one empty place on the board return '.', otherwise '#'
    for (var row = 0; row < 3; row++)
        for (var col = 0; col < 3; col++)
            if (utttBoard[largeRow * 3 + row][largeCol * 3 + col] == '.')
                return '.';
    return '#';
}

function utttUpdateGame(largeRow, largeCol, smallRow, smallCol) {
    utttBoard[largeRow * 3 + smallRow][largeCol * 3 + smallCol] = utttNextPlayer;
    utttNextPlayer = (utttNextPlayer == 'x' ? 'o' : 'x');
    utttNextRow = smallRow, utttNextCol = smallCol;
    if (utttBoardWinner(utttNextRow, utttNextCol) != '.')
        utttNextRow = utttNextCol = -1;
    
    // Game is won by one of the players
    for (var i = 0; i < 8; i++) {
        var cntX = 0, cntO = 0;
        for (var c = 0; c < 3; c++) {
            if (utttBoardWinner(utttLines[i][c][0], utttLines[i][c][1]) == 'x') cntX++;
            if (utttBoardWinner(utttLines[i][c][0], utttLines[i][c][1]) == 'o') cntO++;
        }
        if (cntX == 3) return 'Player x won!';
        if (cntO == 3) return 'Player o won!';
    }

    // If there is still any (however small) chance that some of the players can win, continue the game.
    // This is a simple check, it may give false positives (but never false negatives).
    var canWin = [
        [0, 0, 0],
        [0, 0, 0],
        [0, 0, 0]
    ];
    for (var row = 0; row < 3; row++) {
        for (var col = 0; col < 3; col++) {
            if (utttBoardWinner(row, col) == 'x') {
                canWin[row][col] = 1;
            } else if (utttBoardWinner(row, col) == 'o') {
                canWin[row][col] = 2;
            } else {
                for (var i = 0; i < 8; i++) {
                    var cntX = 0, cntO = 0;
                    for (var c = 0; c < 3; c++) {
                        if (utttBoard[row * 3 + utttLines[i][c][0]][col * 3 + utttLines[i][c][1]] == 'x') cntX++;
                        if (utttBoard[row * 3 + utttLines[i][c][0]][col * 3 + utttLines[i][c][1]] == 'o') cntO++;
                    }
                    if (cntO == 0) canWin[row][col] |= 1;
                    if (cntX == 0) canWin[row][col] |= 2;
                }
            }
        }
    }
    for (var i = 0; i < 8; i++) {
        var cntX = 0, cntO = 0;
        for (var c = 0; c < 3; c++) {
            if (canWin[utttLines[i][c][0]][utttLines[i][c][1]] & 1) cntX++;
            if (canWin[utttLines[i][c][0]][utttLines[i][c][1]] & 2) cntO++;
        }
        if (cntX == 3 || cntO == 3) return '';
    }

    // If it is sure that the game cannot be won, stop it now.
    return 'Game is drawn.';
}


// Ultimate Tic-Tac-Toe simple AI follows

function utttCanWin(row, col, who) {
    for (var i = 0; i < 8; i++) {
        var cntGood = 0, cntDots = 0;
        for (var c = 0; c < 3; c++) {
            if (utttBoard[row * 3 + utttLines[i][c][0]][col * 3 + utttLines[i][c][1]] == who) cntGood++;
            if (utttBoard[row * 3 + utttLines[i][c][0]][col * 3 + utttLines[i][c][1]] == '.') cntDots++;
        }
        if (cntGood == 2 && cntDots == 1)
            return true;
    }
    return false;
}

function utttGetWinningMove(who) {
    for (var i = 0; i < 8; i++) {
        var row = -1, col = -1;
        for (var c = 0; c < 3; c++) {
            if (utttBoardWinner(utttLines[i][c][0], utttLines[i][c][1]) != who) {
                row = col = -1;
                break;
            }
            if (utttBoardWinner(utttLines[i][c][0], utttLines[i][c][1]) == '.') {
                if (row != -1 || !utttCanWin(utttLines[i][c][0], utttLines[i][c][1], who)) {
                    row = col = -1;
                    break;
                }
                row = utttLines[i][c][0], col = utttLines[i][c][1];
            }
        }
        if (row != -1)
            return [row, col];
    }
    return [];
}

function utttPlayTTT(targetRow, targetCol, who) {
    if (utttBoardWinner(targetRow, targetCol) != '.')
        return utttBoardWinner(targetRow, targetCol);

    var best = '?';
    for (var row = 0; row < 3; row++) {
        for (var col = 0; col < 3; col++) {
            if (utttBoard[targetRow * 3 + row][targetCol * 3 + col] == '.') {
                utttBoard[targetRow * 3 + row][targetCol * 3 + col] = who;
                var res = utttPlayTTT(targetRow, targetCol, who == 'x' ? 'o' : 'x');
                utttBoard[targetRow * 3 + row][targetCol * 3 + col] = '.';
                if (res == who)
                    return who;
                if (best != '#')
                    best = res;
            }
        }
    }
    return best == '?' ? '#' : best;
}

function utttGetMove(targetRow, targetCol) {
    var targetCell = [];

    // Check for instant win
    for (var row = 0; row < 3; row++) {
        for (var col = 0; col < 3; col++) {
            if (utttBoard[targetRow * 3 + row][targetCol * 3 + col] == '.') {
                utttBoard[targetRow * 3 + row][targetCol * 3 + col] = utttNextPlayer;
                if (utttBoardWinner(targetRow, targetCol) == utttNextPlayer)
                    targetCell = [row, col];
                utttBoard[targetRow * 3 + row][targetCol * 3 + col] = '.';
            }
        }
    }
    if (targetCell.length > 0)
        return targetCell;
    
    for (var row = 0; row < 3; row++) {
        for (var col = 0; col < 3; col++) {
            if (utttBoard[targetRow * 3 + row][targetCol * 3 + col] == '.') {
                utttBoard[targetRow * 3 + row][targetCol * 3 + col] = utttNextPlayer;
                var res = utttPlayTTT(targetRow, targetCol, utttNextPlayer == 'x' ? 'o' : 'x');
                utttBoard[targetRow * 3 + row][targetCol * 3 + col] = '.';
                
                if (res == utttNextPlayer) {
                    return [row, col];
                } else if (res == '#') {
                    targetCell = [row, col];
                } else {
                    if (targetCell.length == 0)
                        targetCell = [row, col];
                }
            }
        }
    }
    return targetCell;
}

function utttGetScore(row, col) {
    var winner = utttPlayTTT(row, col, utttNextPlayer);
    if (winner == utttNextPlayer) return 2;
    else if (winner == '#') return 1;
    else return 0;
}

function utttAiLogic() {
    var targetBoard = utttNextRow == -1 ? [] : [utttNextRow, utttNextCol];

    // Win the entire game
    if (targetBoard.length == 0)
        targetBoard = utttGetWinningMove(utttNextPlayer == 'x' ? 'x' : 'o');
    
    // Prevents a loss of the entire game
    if (targetBoard.length == 0)
        targetBoard = utttGetWinningMove(utttNextPlayer == 'x' ? 'o' : 'x');
    
    // Find a board with highest score
    if (targetBoard.length == 0) {
        var best = -1;
        for (var row = 0; row < 3; row++) {
            for (var col = 0; col < 3; col++) {
                if (utttBoardWinner(row, col) == '.') {
                    var cur = utttGetScore(row, col);
                    if (best < cur)
                        best = cur, targetBoard = [row, col];
                }
            }
        }
    }
    
    // By this point we should know on which of the small boards we should play
    if (targetBoard.length == 0) {
        alert("Error in UTTT AI!");
        return [];
    }
    var targetCell = utttGetMove(targetBoard[0], targetBoard[1]);
    return [targetBoard[0], targetBoard[1], targetCell[0], targetCell[1]];
}

function utttReplayCycle(idx, log) {
    if (utttReplayRunning) {
        if (idx >= log.length) {
            endSnakesGame('Reached end of game log.');
            return;
        }
        
        var largeRow = log[idx++] - '0';
        var largeCol = log[idx++] - '0';
        var smallRow = log[idx++] - '0';
        var smallCol = log[idx++] - '0';

        var message = utttUpdateGame(largeRow, largeCol, smallRow, smallCol);
        utttUpdateBoard();
        if (message != '') {
            utttEndGame(message);
            if (idx < log.length) {
                alert('Game log claims there are more moves?');
            }
            return;
        }
    }
    window.setTimeout(function() {utttReplayCycle(idx, log);}, UTTT_REPLAY_INTERVAL);
}

function utttRunReplay(log) {
    if (utttDemo) {
        setTimeout(function() {utttReplayRunning = true;}, 1000);
    }

    // Start the update process
    utttReplayCycle(0, log);
}

function utttCreateGame() {
    utttBoard = [
        ['.', '.', '.', '.', '.', '.', '.', '.', '.'],
        ['.', '.', '.', '.', '.', '.', '.', '.', '.'],
        ['.', '.', '.', '.', '.', '.', '.', '.', '.'],
        ['.', '.', '.', '.', '.', '.', '.', '.', '.'],
        ['.', '.', '.', '.', '.', '.', '.', '.', '.'],
        ['.', '.', '.', '.', '.', '.', '.', '.', '.'],
        ['.', '.', '.', '.', '.', '.', '.', '.', '.'],
        ['.', '.', '.', '.', '.', '.', '.', '.', '.'],
        ['.', '.', '.', '.', '.', '.', '.', '.', '.']
    ];
}

function utttGetSmallBoard(largeRow, largeCol) {
    // Board is already won
    if (utttBoardWinner(largeRow, largeCol) != '.') {
        var small = document.createElement('div');
        small.className = 'uttt-small-board-won';
        small.innerText = utttBoardWinner(largeRow, largeCol);
        small.style.color = small.innerText == 'x' ? '#0099FF' : '#D84A38';
        return small;
    }

    // Board is not yet won
    var small = document.createElement('table');
    small.className = 'uttt-small-board';
    small.id = 'large' + largeRow + largeCol + '_board';
    var tbody = document.createElement('tbody');
    for (var smallRow = 0; smallRow < 3; smallRow++) {
        var tr = document.createElement('tr');
        for (var smallCol = 0; smallCol < 3; smallCol++) {
            var td = document.createElement('td');
            td.id = 'large' + largeRow + largeCol + '_small' + smallRow + smallCol;
            td.className = 'cell-normal';
            if (utttBoard[largeRow * 3 + smallRow][largeCol * 3 + smallCol] == '.') {
                if (!utttAiTurn && (utttNextRow == -1 || (utttNextRow == largeRow && utttNextCol == largeCol))) {
                    td.className = 'cell-clickable';
                    var color = utttNextPlayer == 'x' ? '#0099FF' : '#D84A38';
                    td.innerHTML = '<div class="uttt-small-board-hidden-text" style="color: ' + color + ';">' + utttNextPlayer + '</div>';
                    td.addEventListener('mousedown', utttClickCell.bind(this, largeRow, largeCol, smallRow, smallCol), false);
                }
            } else if (utttBoard[largeRow * 3 + smallRow][largeCol * 3 + smallCol] == 'x') {
                td.innerText = 'x';
                td.style.color = '#0099FF';
            } else if (utttBoard[largeRow * 3 + smallRow][largeCol * 3 + smallCol] == 'o') {
                td.innerText = 'o';
                td.style.color = '#D84A38';
            } else {
                alert("WTF?");
            }
            tr.appendChild(td);
        }
        tbody.appendChild(tr);
    }
    small.appendChild(tbody);
    return small;
}

function utttGetBoard() {
    var board = document.createElement('table');
    board.className = 'uttt-board';
    var tbody = document.createElement('tbody');
    for (var largeRow = 0; largeRow < 3; largeRow++) {
        var tr = document.createElement('tr');
        for (var largeCol = 0; largeCol < 3; largeCol++) {
            var td = document.createElement('td');
            td.id = 'large' + largeRow + largeCol;
            td.className = 'cell-normal';
            var cannotPlay = utttBoardWinner(largeRow, largeCol) != '.' ||
                    (utttNextRow != -1 && (utttNextRow != largeRow || utttNextCol != largeCol));
            if (cannotPlay) {
                td.style.opacity = 0.33;
            }
            td.appendChild(utttGetSmallBoard(largeRow, largeCol));
            tr.appendChild(td);
        }
        tbody.appendChild(tr);
    }
    board.appendChild(tbody);
    return board;
}

function utttUpdateBoard() {
    // Update the DOM
    var boardWrapper = document.getElementById('utttBoard');
    boardWrapper.removeChild(boardWrapper.firstChild);
    boardWrapper.appendChild(utttGetBoard());
}

function utttClickCell(largeRow, largeCol, smallRow, smallCol) {
    var message = utttUpdateGame(largeRow, largeCol, smallRow, smallCol);
    utttAiTurn = true;
    utttUpdateBoard();
    if (message != '') {
        utttEndGame(message);
        return;
    }

    window.setTimeout(function() {
        var move = utttAiLogic();
        message = utttUpdateGame(move[0], move[1], move[2], move[3]);
        utttAiTurn = false;
        utttUpdateBoard();
        if (message != '') {
            // Make it impossible for user to click anymore
            aiTurn = true;
            utttUpdateBoard();
            utttEndGame(message);
            return;
        }
    }, 200);
}

function utttGetContent() {
    // Create the board and initial cells
    utttCreateGame();

    // Now create the DOM content
    var content = document.createElement('div');
    content.className = 'uttt-content';

    // Header with the task name
    var header = document.createElement('div');
    header.style.textAlign = 'left';
    header.innerHTML = '<h2><span class="blue">Ultimate Tic-Tac-Toe</span> :: Визуализатор</h2>';
    content.appendChild(header);

    // First player (nickname and score)
    var player1 = document.createElement('div');
    player1.style = 'display: inline-block; width: 20%; vertical-align: middle; font-size: 1.5rem; font-weight: bold;';
    player1.innerHTML += '<div class="blue">' + utttPlayerOne + '</div>';
    content.appendChild(player1);

    // The actual playing board
    var board = document.createElement('div');
    board.style = 'display: inline-block; width: 60%; vertical-align: middle;';
    board.id = 'utttBoard';
    board.appendChild(utttGetBoard());
    content.appendChild(board);

    // Second player (nickname and score)
    var player2 = document.createElement('div');
    player2.style = 'display: inline-block; width: 20%; vertical-align: middle; font-size: 1.5rem; font-weight: bold;';
    player2.innerHTML += '<div class="red">' + utttPlayerTwo + '</div>';
    content.appendChild(player2);

    return content;
}

function showUtttReplay(playerOne, playerTwo, log, demo=false) {
    utttDemo = demo;
    utttPlayerOne = playerOne;
    utttPlayerTwo = playerTwo;
    utttAiTurn = true;

    // Create and show the initial board
    var content = utttGetContent();

    var instructions = document.createElement('div');
    instructions.style.textAlign = 'center';
    instructions.style.fontStyle = 'italic';
    if (!utttDemo) {
        instructions.innerText = 'Натиснете шпация или кликнете на дъската за да пуснете или паузирате играта.';
    }
    content.appendChild(instructions);

    // Make pressing escape return back to the game
    var gameUrl = window.location.href.substr(0, window.location.href.lastIndexOf('/replays'));
    showActionForm(content.outerHTML, gameUrl);

    // Add action event listeners
    document.addEventListener('keydown', utttIdentifyReplayEvent, false);
    document.getElementById('utttBoard').addEventListener('mousedown', function() {
        utttReplayRunning = !utttReplayRunning;
    }, true);

    // Run the actual replay
    utttRunReplay(log);
}

function showUtttVisualizer(username) {
    utttPlayerOne = username;
    utttPlayerTwo = 'AI';

    // Create and show the initial board
    var content = utttGetContent();

    // Make pressing escape return back to the game
    var gameUrl = window.location.href.substr(0, window.location.href.lastIndexOf('/'));
    showActionForm(content.outerHTML, gameUrl);
    utttUpdateBoard();
}