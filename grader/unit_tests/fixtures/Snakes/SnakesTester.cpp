/*
ID: espr1t
TASK: Snakes
KEYWORDS: Tester
*/

#include <cstdio>
#include <cstring>
#include <random>
#include <vector>
#include <algorithm>

using namespace std;
FILE* in = stdin; FILE* out = stdout;

mt19937 mt;
int rand() {
    int ret = mt();
    return ret < 0 ? -ret : ret;
}

const int INF = 1000000001;
const int MAX_BUFF_SIZE = 20000000;
char buff[MAX_BUFF_SIZE];

const double SCORE_WIN = 1.0;
const double SCORE_LOSS = 0.0;

const int MAX_ROWS = 52;
const int MAX_COLS = 52;

int numApples;
int numRows, numCols;
char board[MAX_ROWS][MAX_COLS];
int curMove = 0, curPlayer = 0;
int lastPlayerWhoAteAnApple = -1;
int lastMoveWhenAnAppleWasEaten = INF;
int lengthPlayerOne = 1, lengthPlayerTwo = 1;

void printInput() {
    fprintf(out, "%d %d %d\n", numRows, numCols, numApples);
    for (int row = 0; row < numRows; row++)
        fprintf(out, "%s\n", board[row]);
    fflush(out);
}

void placeApple() {
    int row = rand() % numRows;
    int col = rand() % numCols;
    while (board[row][col] != '.') {
        row = rand() % numRows;
        col = rand() % numCols;
    }
    board[row][col] = '@';
}

void gameCycle() {
    fscanf(in, "%s", buff);

    int deltaRow = 0, deltaCol = 0;
    if (!strcmp(buff, "Up")) {
        deltaRow = -1, deltaCol = 0;
    } else if (!strcmp(buff, "Right")) {
        deltaRow = 0, deltaCol = +1;
    } else if (!strcmp(buff, "Down")) {
        deltaRow = +1, deltaCol = 0;
    } else if (!strcmp(buff, "Left")) {
        deltaRow = 0, deltaCol = -1;
    } else {
        fprintf(out, "%.2lf\n", curPlayer == 0 ? SCORE_LOSS : SCORE_WIN);
        fprintf(out, "%.2lf\n", curPlayer == 1 ? SCORE_LOSS : SCORE_WIN);
        fprintf(out, "%s player printed invalid move!\n", !curPlayer ? "First" : "Second");
        fprintf(out, "'%s'\n", buff);
        exit(0);
    }
    
    vector < pair <char, pair <int, int> > > snake1;
    vector < pair <char, pair <int, int> > > snake2;
    
    for (int row = 0; row < numRows; row++) {
        for (int col = 0; col < numCols; col++) {
            if (board[row][col] >= 'A' && board[row][col] <= 'Z')
                snake1.push_back(make_pair(board[row][col], make_pair(row, col)));
            if (board[row][col] >= 'a' && board[row][col] <= 'z')
                snake2.push_back(make_pair(board[row][col], make_pair(row, col)));
        }
    }
    sort(snake1.begin(), snake1.end());
    sort(snake2.begin(), snake2.end());
    
    int row = snake1[0].second.first + deltaRow;
    int col = snake1[0].second.second + deltaCol;
    if (row < 0 || row >= numRows || col < 0 || col >= numCols) {
        fprintf(out, "%.2lf\n", curPlayer == 0 ? SCORE_LOSS : SCORE_WIN);
        fprintf(out, "%.2lf\n", curPlayer == 1 ? SCORE_LOSS : SCORE_WIN);
        fprintf(out, "%s player tried to go outside the board!\n", !curPlayer ? "First" : "Second");
        exit(0);
    }
    
    // Eating part of a snake?
    if ((board[row][col] >= 'A' && board[row][col] <= 'Z') ||
        (board[row][col] >= 'a' && board[row][col] <= 'z')) {
        fprintf(out, "%.2lf\n", curPlayer == 0 ? SCORE_LOSS : SCORE_WIN);
        fprintf(out, "%.2lf\n", curPlayer == 1 ? SCORE_LOSS : SCORE_WIN);
        fprintf(out, "%s player tried to eat a part of a snake!\n", !curPlayer ? "First" : "Second");
        exit(0);
    }
    
    // Insert the head and change the rest of to body
    snake1.insert(snake1.begin(), make_pair('A', make_pair(row, col)));
    for (int i = 1; i < (int)snake1.size(); i++)
        snake1[i].first++;

    // Going to a regular cell, move the tail of the snake
    if (board[row][col] != '@') {
        board[snake1.back().second.first][snake1.back().second.second] = '.';
        snake1.pop_back();
    }
    // Except if eating an apple, in which case don't move it
    else {
        lastPlayerWhoAteAnApple = curPlayer;
        lastMoveWhenAnAppleWasEaten = curMove;
        curPlayer == 0 ? lengthPlayerOne++ : lengthPlayerTwo++;

        // Winning?
        if ((int)snake1.size() == numApples + 1) {
            fprintf(out, "%.2lf\n", curPlayer == 0 ? SCORE_WIN : SCORE_LOSS);
            fprintf(out, "%.2lf\n", curPlayer == 1 ? SCORE_WIN : SCORE_LOSS);
            fprintf(out, "%s player ate %d apples.\n", !curPlayer ? "First" : "Second", numApples);
            exit(0);
        }
        placeApple();
    }
    
    // None of the snakes has eaten an apple in a long time
    if (curMove - lastMoveWhenAnAppleWasEaten > 2 * (numRows + numCols)) {
        if (lengthPlayerOne != lengthPlayerTwo) {
            fprintf(out, "%.2lf\n", lengthPlayerOne > lengthPlayerTwo ? SCORE_WIN : SCORE_LOSS);
            fprintf(out, "%.2lf\n", lengthPlayerTwo > lengthPlayerOne ? SCORE_WIN : SCORE_LOSS);
            fprintf(out, "Game was drawn; %s player's snake was longer.\n",
                lengthPlayerOne > lengthPlayerTwo ? "first" : "second");
            exit(0);
        } else if (lastPlayerWhoAteAnApple != -1) {
            fprintf(out, "%.2lf\n", lastPlayerWhoAteAnApple == 0 ? SCORE_WIN : SCORE_LOSS);
            fprintf(out, "%.2lf\n", lastPlayerWhoAteAnApple == 1 ? SCORE_WIN : SCORE_LOSS);
            fprintf(out, "Game was drawn; snakes have the same length, but %s player ate the last apple.\n",
                lastPlayerWhoAteAnApple == 0 ? "first" : "second");
            exit(0);
        } else {
            fprintf(out, "%.2lf\n", SCORE_WIN);
            fprintf(out, "%.2lf\n", SCORE_LOSS);
            fprintf(out, "Game was drawn; none of the snakes has eaten an apple, so first player wins.\n");
            exit(0);
        }
    }
    
    // Swap the snakes (so it's the turn of the other player)
    for (int i = 0; i < (int)snake1.size(); i++)
        board[snake1[i].second.first][snake1[i].second.second] = snake1[i].first + 32;
    for (int i = 0; i < (int)snake2.size(); i++)
        board[snake2[i].second.first][snake2[i].second.second] = snake2[i].first - 32;
    
    curPlayer = !curPlayer;
    printInput();
}

void setupBoard(int startRow1, int startCol1, int startRow2, int startCol2) {
    curPlayer = 0;
    for (int row = 0; row < numRows; row++)
        for (int col = 0; col < numCols; col++)
            board[row][col] = '.';
    
    board[startRow1][startCol1] = 'A';
    board[startRow2][startCol2] = 'a';

    placeApple();
}

int main(void) {
    int seed;
    fscanf(in, "%d", &seed);
    mt.seed(seed);
    
    fscanf(in, "%d %d %d", &numRows, &numCols, &numApples);
    int startRowPlayer1, startColPlayer1;
    fscanf(in, "%d %d", &startRowPlayer1, &startColPlayer1);
    int startRowPlayer2, startColPlayer2;
    fscanf(in, "%d %d", &startRowPlayer2, &startColPlayer2);

    setupBoard(startRowPlayer1, startColPlayer1, startRowPlayer2, startColPlayer2);

    printInput();
    while (true) {
        gameCycle();
    }

    return 0;
}
