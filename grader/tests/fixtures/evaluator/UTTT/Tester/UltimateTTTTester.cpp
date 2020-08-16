/*
ID: espr1t
TASK: Ultimate Tic-Tac-Toe
KEYWORDS: Tester
*/

#include <cstdio>
#include <cstring>
#include <string>

using namespace std;
FILE* in = stdin; FILE* out = stdout; FILE* log;

const int MAX_BUFF_SIZE = 20000000;
char dir[MAX_BUFF_SIZE], buff[MAX_BUFF_SIZE];

const double SCORE_WIN = 3.0;
const double SCORE_DRAW = 1.0;
const double SCORE_LOSS = 0.0;

const int MAX_ROWS = 12;
const int MAX_COLS = 12;

char board[MAX_ROWS][MAX_COLS] = {
    "...|...|...",
    "...|...|...",
    "...|...|...",
    "---+---+---",
    "...|...|...",
    "...|...|...",
    "...|...|...",
    "---+---+---",
    "...|...|...",
    "...|...|...",
    "...|...|..."
};

int lines[8][3][2] = {
    { {0, 0}, {0, 1}, {0, 2} },
    { {1, 0}, {1, 1}, {1, 2} },
    { {2, 0}, {2, 1}, {2, 2} },
    { {0, 0}, {1, 0}, {2, 0} },
    { {0, 1}, {1, 1}, {2, 1} },
    { {0, 2}, {1, 2}, {2, 2} },
    { {0, 0}, {1, 1}, {2, 2} },
    { {2, 0}, {1, 1}, {0, 2} }
};

int curPlayer = 0;
int nextRow = -1, nextCol = -1;

void printInput() {
    fprintf(out, "%d %d\n", nextRow, nextCol);
    for (int row = 0; row < 11; row++)
        fprintf(out, "%s\n", board[row]);
    fflush(out);
}

char getWinner(int largeRow, int largeCol) {
    for (int i = 0; i < 8; i++) {
        int cntX = 0, cntO = 0;
        for (int c = 0; c < 3; c++) {
            if (board[largeRow * 4 + lines[i][c][0]][largeCol * 4 + lines[i][c][1]] == 'X') cntX++;
            if (board[largeRow * 4 + lines[i][c][0]][largeCol * 4 + lines[i][c][1]] == 'O') cntO++;
        }
        if (cntX == 3) return 'X';
        if (cntO == 3) return 'O';
    }
    // If there is at least one empty place on the board return '.', otherwise '#'
    for (int row = 0; row < 3; row++)
        for (int col = 0; col < 3; col++)
            if (board[largeRow * 4 + row][largeCol * 4 + col] == '.')
                return '.';
    return '#';
}

string updateBoard(int largeRow, int largeCol, int smallRow, int smallCol) {
    board[largeRow * 4 + smallRow][largeCol * 4 + smallCol] = 'X';
    nextRow = smallRow, nextCol = smallCol;
    if (getWinner(nextRow, nextCol) != '.')
        nextRow = nextCol = -1;
    
    // Just won the board, block the remaining empty cells
    if (getWinner(largeRow, largeCol) != '.') {
        for (int row = 0; row < 3; row++)
            for (int col = 0; col < 3; col++)
                if (board[largeRow * 4 + row][largeCol * 4 + col] == '.')
                    board[largeRow * 4 + row][largeCol * 4 + col] = '#';
    }
    
    // Just won the game
    for (int i = 0; i < 8; i++) {
        int cntX = 0;
        for (int c = 0; c < 3; c++)
            if (getWinner(lines[i][c][0], lines[i][c][1]) == 'X') cntX++;
        if (cntX == 3) return "win";
    }

    // If we think that some of the players can win, continue the game.
    // This is a simple check, it may give false positives (but never false negatives).
    int canWin[3][3] = {
        {0, 0, 0},
        {0, 0, 0},
        {0, 0, 0}
    };
    for (int row = 0; row < 3; row++) {
        for (int col = 0; col < 3; col++) {
            if (getWinner(row, col) == 'X') {
                canWin[row][col] = 1;
            } else if (getWinner(row, col) == 'O') {
                canWin[row][col] = 2;
            } else {
                for (int i = 0; i < 8; i++) {
                    int cntX = 0, cntO = 0;
                    for (int c = 0; c < 3; c++) {
                        if (board[row * 4 + lines[i][c][0]][col * 4 + lines[i][c][1]] == 'X') cntX++;
                        if (board[row * 4 + lines[i][c][0]][col * 4 + lines[i][c][1]] == 'O') cntO++;
                    }
                    if (cntO == 0) canWin[row][col] |= 1;
                    if (cntX == 0) canWin[row][col] |= 2;
                }
            }
        }
    }
    for (int i = 0; i < 8; i++) {
        int cntX = 0, cntO = 0;
        for (int c = 0; c < 3; c++) {
            if (canWin[lines[i][c][0]][lines[i][c][1]] & 1) cntX++;
            if (canWin[lines[i][c][0]][lines[i][c][1]] & 2) cntO++;
        }
        if (cntX == 3 || cntO == 3) return "";
    }

    // If we know that the game cannot be won, stop it now.
    return "draw";
}

void finalVerdict(const char* verdict, double score1, double score2, const char* message) {
    fprintf(stderr, "%s\n", verdict);
    fprintf(stderr, "%.2lf\n", score1);
    fprintf(stderr, "%.2lf\n", score2);
    fprintf(stderr, "%s\n", message);
    exit(0);
}

void gameCycle() {
    buff[0] = 0;
    fgets(buff, MAX_BUFF_SIZE, in);
    // Invalid output
    int largeRow, largeCol, smallRow, smallCol;
    if (sscanf(buff, "%d %d %d %d", &largeRow, &largeCol, &smallRow, &smallCol) != 4) {
        char message[1024];
        sprintf(message, "%s player printed invalid move!", !curPlayer ? "First" : "Second");
        finalVerdict("OK", !curPlayer ? SCORE_LOSS : SCORE_WIN, curPlayer ? SCORE_LOSS : SCORE_WIN, message);
    }
    
    // Not in required board
    if (nextRow != -1 && (nextRow != largeRow || nextCol != largeCol)) {
        char message[1024];
        sprintf(message, "%s player didn't play in the required small board!", !curPlayer ? "First" : "Second");
        finalVerdict("OK", !curPlayer ? SCORE_LOSS : SCORE_WIN, curPlayer ? SCORE_LOSS : SCORE_WIN, message);
    }

    // Invalid numbers for row/col of large board
    if (largeRow < 0 || largeRow > 2 || largeCol < 0 || largeCol > 2) {
        char message[1024];
        sprintf(message, "%s player wanted to play in invalid cell of the large board: (%d, %d)!",
            !curPlayer ? "First" : "Second", largeRow, largeCol);
        finalVerdict("OK", !curPlayer ? SCORE_LOSS : SCORE_WIN, curPlayer ? SCORE_LOSS : SCORE_WIN, message);
    }

    // Invalid numbers for row/col of small board
    if (smallRow < 0 || smallRow > 2 || smallCol < 0 || smallCol > 2) {
        char message[1024];
        sprintf(message, "%s player wanted to play in invalid cell of the small board: (%d, %d)!",
            !curPlayer ? "First" : "Second", smallRow, smallCol);
        finalVerdict("OK", !curPlayer ? SCORE_LOSS : SCORE_WIN, curPlayer ? SCORE_LOSS : SCORE_WIN, message);
    }

    // Already occupied    
    if (board[largeRow * 4 + smallRow][largeCol * 4 + smallCol] != '.') {
        char message[1024];
        sprintf(message, "%s player wanted to play in a non-empty cell!",
            !curPlayer ? "First" : "Second", smallRow, smallCol);
        finalVerdict("OK", !curPlayer ? SCORE_LOSS : SCORE_WIN, curPlayer ? SCORE_LOSS : SCORE_WIN, message);
    }
    
    // Print log
    fprintf(log, "%d%d%d%d\n", largeRow, largeCol, smallRow, smallCol);
    fflush(log);

    // Update board with player's move
    string status = updateBoard(largeRow, largeCol, smallRow, smallCol);

    // Drawn?
    if (status == "draw") {
        finalVerdict("OK", SCORE_DRAW, SCORE_DRAW, "The match ended in a draw.");
    }
    
    // Winning?
    if (status == "win") {
        char message[1024];
        sprintf(message, "%s player won.\n", !curPlayer ? "First" : "Second");
        finalVerdict("OK", curPlayer ? SCORE_LOSS : SCORE_WIN, !curPlayer ? SCORE_LOSS : SCORE_WIN, message);
    }

    // Swap the signs of the players (so current player is always with 'X')    
    for (int row = 0; row < 11; row++) {
        for (int col = 0; col < 11; col++) {
            if (board[row][col] == 'X' || board[row][col] == 'O')
                board[row][col] = board[row][col] == 'X' ? 'O' : 'X';
        }
    }
    curPlayer = !curPlayer;
    printInput();
}

int main(int argc, char** argv) {
    log = fopen(argv[1], "wt");

    // Read dummy message in tests
    fgets(buff, MAX_BUFF_SIZE, in);

    printInput();
    while (true) {
        gameCycle();
    }
    return 0;
}
