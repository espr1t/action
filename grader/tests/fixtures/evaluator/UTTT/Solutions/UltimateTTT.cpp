/*
ID: espr1t
TASK: Ultimate Tic-Tac-Toe
KEYWORDS: NP, Dummy
*/

#include <cstdio>
#include <cstdlib>
#include <vector>

using namespace std;
FILE* in = stdin; FILE* out = stdout;

const int MAX = 12;
const int MM = 128;

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

char winner[3][3];

int targetRow, targetCol;
char small[3][3][3][3];

char getWinner(int row, int col) {
    for (int i = 0; i < 8; i++) {
        int cntX = 0, cntO = 0;
        for (int c = 0; c < 3; c++) {
            if (small[row][col][lines[i][c][0]][lines[i][c][1]] == 'X') cntX++;
            if (small[row][col][lines[i][c][0]][lines[i][c][1]] == 'O') cntO++;
        }
        if (cntX == 3) return 'X';
        if (cntO == 3) return 'O';
    }
    return '.';
}

bool canWin(int row, int col, char who) {
    for (int i = 0; i < 8; i++) {
        int cntGood = 0, cntDots = 0;
        for (int c = 0; c < 3; c++) {
            if (small[row][col][lines[i][c][0]][lines[i][c][1]] == who) cntGood++;
            if (small[row][col][lines[i][c][0]][lines[i][c][1]] == '.') cntDots++;
        }
        if (cntGood == 2 && cntDots == 1)
            return true;
    }
    return false;
}

void getWinningMove(int& targetRow, int& targetCol, char who) {
    for (int i = 0; i < 8; i++) {
        int row = -1, col = -1;
        for (int c = 0; c < 3; c++) {
            if (winner[lines[i][c][0]][lines[i][c][1]] != who) {
                row = col = -1;
                break;
            }
            if (winner[lines[i][c][0]][lines[i][c][1]] == '.') {
                if (row != -1 || !canWin(lines[i][c][0], lines[i][c][1], who)) {
                    row = col = -1;
                    break;
                }
                row = lines[i][c][0], col = lines[i][c][1];
            }
        }
        if (row != -1) {
            targetRow = row, targetCol = col;
            return;
        }
    }
}

int getScore(int row, int col) {
    if (canWin(row, col, 'X')) return 2;
    if (canWin(row, col, 'O')) return 1;
    return 0;
}

char playTTT(int targetRow, int targetCol, char who) {
    if (getWinner(targetRow, targetCol) != '.')
        return getWinner(targetRow, targetCol);

    char best = '?';
    for (int row = 0; row < 3; row++) {
        for (int col = 0; col < 3; col++) {
            if (small[targetRow][targetCol][row][col] == '.') {
                small[targetRow][targetCol][row][col] = who;
                char res = playTTT(targetRow, targetCol, who == 'X' ? 'O' : 'X');
                small[targetRow][targetCol][row][col] = '.';
                if (res == who)
                    return who;
                if (best != '.')
                    best = res;
            }
        }
    }
    return best == '?' ? '.' : best;
}

void getMove(int targetRow, int targetCol, int& boardRow, int& boardCol) {
    boardRow = boardCol = -1;
    // Check for instant win
    for (int row = 0; row < 3; row++) {
        for (int col = 0; col < 3; col++) {
            if (small[targetRow][targetCol][row][col] == '.') {
                small[targetRow][targetCol][row][col] = 'X';
                if (getWinner(targetRow, targetCol) == 'X')
                    boardRow = row, boardCol = col;
                small[targetRow][targetCol][row][col] = '.';
            }
        }
    }
    if (boardRow != -1 && boardCol != -1)
        return;
    
    for (int row = 0; row < 3; row++) {
        for (int col = 0; col < 3; col++) {
            if (small[targetRow][targetCol][row][col] == '.') {
                small[targetRow][targetCol][row][col] = 'X';
                char res = playTTT(targetRow, targetCol, 'O');
                small[targetRow][targetCol][row][col] = '.';
                
                if (res == 'X') {
                    boardRow = row, boardCol = col;
                    return;
                } else if (res == '.') {
                    boardRow = row, boardCol = col;
                } else {
                    if (boardRow == -1 && boardCol == -1)
                        boardRow = row, boardCol = col;
                }
            }
        }
    }
}

void solve() {
    // Get a list of winners in individual small boards
    for (int row = 0; row < 3; row++)
        for (int col = 0; col < 3; col++)
            winner[row][col] = getWinner(row, col);

    // Win the entire game
    if (targetRow == -1 && targetCol == -1)
        getWinningMove(targetRow, targetCol, 'X');
    
    // Prevents a loss of the entire game
    if (targetRow == -1 && targetCol == -1)
        getWinningMove(targetRow, targetCol, 'O');
    
    // Find a board with highest score
    if (targetRow == -1 && targetCol == -1) {
        int best = -1;
        for (int row = 0; row < 3; row++) {
            for (int col = 0; col < 3; col++) {
                if (winner[row][col] == '.') {
                    int cur = getScore(row, col);
                    if (best < cur)
                        best = cur, targetRow = row, targetCol = col;
                }
            }
        }
    }
    
    // By this point we should know on which of the small boards we should play
    if (targetRow == -1 || targetCol == -1) {
        fprintf(stderr, "Uhmmmmmmmm?\n");
        exit(-1);
    }
    
    int boardRow = -1, boardCol = -1;
    getMove(targetRow, targetCol, boardRow, boardCol);
    fprintf(out, "%d %d %d %d\n", targetRow, targetCol, boardRow, boardCol);
}

int main(void) {
    // in = fopen("UltimateTTT.in", "rt");

    char board[MAX][MAX];
    fscanf(in, "%d %d", &targetRow, &targetCol);
    for (int row = 0; row < 11; row++) {
        fscanf(in, "%s", board[row]);
    }

    for (int i = 0; i < 3; i++) {
        for (int c = 0; c < 3; c++) {
            for (int row = 0; row < 3; row++)
                for (int col = 0; col < 3; col++)
                    small[i][c][row][col] = board[i * 4 + row][c * 4 + col];
            
        }
    }
    solve();

    return 0;
}
